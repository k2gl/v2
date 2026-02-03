/**
 * Kanban Pinia Store - Search Filtering
 * Manages board state, real-time updates via Mercure, and client-side filtering
 */

import { defineStore } from 'pinia';
import { apiClient } from '@/api/client';

export const useKanbanStore = defineStore('kanban', {
  state: () => ({
    board: null,
    columns: [],
    tasks: {},
    loading: false,
    error: null,
    eventSource: null,
    searchQuery: '',
  }),

  getters: {
    getColumnTasks: (state) => (columnId) => {
      return state.tasks[columnId] || [];
    },

    getTaskById: (state) => (taskId) => {
      for (const columnTasks of Object.values(state.tasks)) {
        const task = columnTasks.find(t => t.id === taskId);
        if (task) return task;
      }
      return null;
    },

    filteredBoard(state) {
      if (!state.board) return null;
      if (!state.searchQuery) return state.board;

      const query = state.searchQuery.toLowerCase();
      
      return {
        ...state.board,
        columns: state.board.columns.map(column => ({
          ...column,
          tasks: (state.tasks[column.id] || []).filter(task => 
            task.title.toLowerCase().includes(query) || 
            task.metadata?.tags?.some(tag => tag.toLowerCase().includes(query))
          )
        }))
      };
    },

    filteredColumns(state) {
      if (!state.searchQuery) return state.columns;
      if (!state.board) return [];

      const query = state.searchQuery.toLowerCase();
      
      return state.board.columns.map(column => ({
        ...column,
        tasks: (state.tasks[column.id] || []).filter(task => 
          task.title.toLowerCase().includes(query) || 
          task.metadata?.tags?.some(tag => tag.toLowerCase().includes(query))
        )
      })).filter(column => column.tasks.length > 0);
    },

    hasActiveFilters(state) {
      return state.searchQuery.length > 0;
    },

    matchingTasksCount(state) {
      if (!state.searchQuery) return 0;
      
      const query = state.searchQuery.toLowerCase();
      let count = 0;
      
      for (const columnTasks of Object.values(state.tasks)) {
        for (const task of columnTasks) {
          if (task.title.toLowerCase().includes(query) || 
              task.metadata?.tags?.some(tag => tag.toLowerCase().includes(query))) {
            count++;
          }
        }
      }
      
      return count;
    }
  },

  actions: {
    async createBoard(payload) {
      try {
        const response = await apiClient.post('/boards', payload);
        return response.data;
      } catch (error) {
        console.error('[KanbanStore] Failed to create board:', error.response?.data);
        throw error;
      }
    },

    async fetchBoard(boardId) {
      this.loading = true;
      this.error = null;

      try {
        const response = await apiClient.get(`/boards/${boardId}`);
        this.board = response.data;
        this.organizeTasksByColumn(response.data.columns);
        this.subscribeToUpdates(boardId);
      } catch (error) {
        this.error = error.message;
        throw error;
      } finally {
        this.loading = false;
      }
    },

    organizeTasksByColumn(columns) {
      this.columns = columns;
      this.tasks = {};

      for (const column of columns) {
        this.tasks[column.id] = column.tasks || [];
      }
    },

    subscribeToUpdates(boardId) {
      if (this.eventSource) {
        this.eventSource.close();
      }

      const baseUrl = import.meta.env.VITE_API_URL || window.location.origin;
      const url = new URL('/.well-known/mercure', baseUrl);
      url.searchParams.append('topic', `https://your-kanban.com/board/${boardId}`);
      url.searchParams.append('topic', `https://your-kanban.com/task/*`);

      this.eventSource = new EventSource(url.toString());

      this.eventSource.onopen = () => {
        console.log('[KanbanStore] Connected to Mercure');
      };

      this.eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleRemoteUpdate(data);
        } catch (error) {
          console.error('[KanbanStore] Failed to parse event:', error);
        }
      };

      this.eventSource.onerror = (error) => {
        console.error('[KanbanStore] Mercure error:', error);
      };
    },

    handleRemoteUpdate(data) {
      switch (data.event) {
        case 'tasks_reordered':
          this.applyReorder(data.columnId, data.newOrder);
          break;
        case 'task_moved':
          this.applyTaskMove(data);
          break;
        case 'task_updated':
          this.applyTaskUpdate(data);
          break;
        case 'task_created':
          this.applyTaskCreated(data);
          break;
        default:
          console.log('[KanbanStore] Unknown event:', data.event);
      }
    },

    applyReorder(columnId, newOrder) {
      if (!this.tasks[columnId]) return;

      const reorderedTasks = newOrder.map(taskId => {
        return this.tasks[columnId].find(t => t.id === taskId);
      }).filter(Boolean);

      this.tasks[columnId] = reorderedTasks;
    },

    applyTaskMove(data) {
      const { taskId, previousColumnId, newColumnId } = data;

      let task = null;

      if (previousColumnId && this.tasks[previousColumnId]) {
        const index = this.tasks[previousColumnId].findIndex(t => t.id === taskId);
        if (index !== -1) {
          task = this.tasks[previousColumnId].splice(index, 1)[0];
        }
      }

      if (task && newColumnId) {
        if (!this.tasks[newColumnId]) {
          this.tasks[newColumnId] = [];
        }
        this.tasks[newColumnId].push(task);
      }
    },

    applyTaskUpdate(data) {
      for (const columnId of Object.keys(this.tasks)) {
        const index = this.tasks[columnId].findIndex(t => t.id === data.taskId);
        if (index !== -1) {
          this.tasks[columnId][index] = {
            ...this.tasks[columnId][index],
            ...data
          };
          break;
        }
      }
    },

    applyTaskCreated(data) {
      if (!this.tasks[data.columnId]) {
        this.tasks[data.columnId] = [];
      }
      this.tasks[data.columnId].push(data);
    },

    async moveTask(taskId, newStatus, newColumnId) {
      try {
        await apiClient.post(`/tasks/${taskId}/move`, {
          newStatus,
          columnId: newColumnId
        });
      } catch (error) {
        console.error('[KanbanStore] Failed to move task:', error);
        throw error;
      }
    },

    async reorderTasks(columnId, orderedIds) {
      try {
        await apiClient.post('/tasks/reorder', {
          columnId,
          orderedIds,
          strategy: 'bulk'
        });
      } catch (error) {
        console.error('[KanbanStore] Failed to reorder tasks:', error);
        throw error;
      }
    },

    optimisticMove(task, fromColumnId, toColumnId) {
      const fromTasks = this.tasks[fromColumnId];
      const toTasks = this.tasks[toColumnId];

      const taskIndex = fromTasks.findIndex(t => t.id === task.id);
      if (taskIndex !== -1) {
        fromTasks.splice(taskIndex, 1);
        toTasks.push(task);
      }
    },

    clearSearch() {
      this.searchQuery = '';
    },

    disconnect() {
      if (this.eventSource) {
        this.eventSource.close();
        this.eventSource = null;
      }
    },

    reset() {
      this.board = null;
      this.columns = [];
      this.tasks = {};
      this.searchQuery = '';
      this.disconnect();
    }
  }
});
