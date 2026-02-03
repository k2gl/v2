/**
 * Kanban Store Vitest Tests
 * Tests for search filtering functionality
 */

import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useKanbanStore } from '@/stores/kanban';

describe('KanbanStore Search Filtering', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('should return full board when searchQuery is empty', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'Task 1', metadata: { tags: ['bug'] } },
            { id: 2, title: 'Task 2', metadata: { tags: ['feature'] } }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 1: store.board.columns[0].tasks };

    expect(store.searchQuery).toBe('');
    expect(store.filteredBoard).toEqual(store.board);
    expect(store.filteredColumns).toEqual(store.columns);
  });

  it('should filter tasks by title', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'Fix login bug', metadata: { tags: ['bug'] } },
            { id: 2, title: 'Add new feature', metadata: { tags: ['feature'] } },
            { id: 3, title: 'Another bug fix', metadata: { tags: ['bug'] } }
          ]
        },
        {
          id: 2,
          name: 'Column 2',
          tasks: [
            { id: 4, title: 'Review code', metadata: { tags: ['review'] } }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 
      1: store.board.columns[0].tasks,
      2: store.board.columns[1].tasks
    };

    store.searchQuery = 'bug';

    const filtered = store.filteredBoard;
    
    expect(filtered).not.toEqual(store.board);
    expect(filtered.columns[0].tasks.length).toBe(2);
    expect(filtered.columns[0].tasks.every(t => t.title.toLowerCase().includes('bug'))).toBe(true);
    expect(filtered.columns[1].tasks.length).toBe(0);
  });

  it('should filter tasks by tags in metadata', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'Fix login bug', metadata: { tags: ['bug', 'urgent'] } },
            { id: 2, title: 'Add new feature', metadata: { tags: ['feature'] } },
            { id: 3, title: 'Critical issue', metadata: { tags: ['urgent', 'critical'] } }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 1: store.board.columns[0].tasks };

    store.searchQuery = 'urgent';

    const filtered = store.filteredBoard;
    
    expect(filtered.columns[0].tasks.length).toBe(2);
    expect(filtered.columns[0].tasks.map(t => t.id).sort()).toEqual([1, 3]);
  });

  it('should be case-insensitive when filtering', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'FIX LOGIN BUG', metadata: { tags: ['BUG'] } },
            { id: 2, title: 'Add new feature', metadata: { tags: ['FEATURE'] } }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 1: store.board.columns[0].tasks };

    store.searchQuery = 'bug';
    expect(store.filteredBoard.columns[0].tasks.length).toBe(1);

    store.searchQuery = 'FIX';
    expect(store.filteredBoard.columns[0].tasks.length).toBe(1);

    store.searchQuery = 'Bug';
    expect(store.filteredBoard.columns[0].tasks.length).toBe(1);
  });

  it('should return empty columns filtered out from filteredColumns getter', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'Matching task', metadata: {} }
          ]
        },
        {
          id: 2,
          name: 'Column 2',
          tasks: [
            { id: 2, title: 'Non-matching task', metadata: {} }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 
      1: store.board.columns[0].tasks,
      2: store.board.columns[1].tasks
    };

    store.searchQuery = 'Matching';

    expect(store.filteredColumns.length).toBe(1);
    expect(store.filteredColumns[0].name).toBe('Column 1');
  });

  it('should correctly report hasActiveFilters', () => {
    const store = useKanbanStore();
    
    store.searchQuery = '';
    expect(store.hasActiveFilters).toBe(false);

    store.searchQuery = 'test';
    expect(store.hasActiveFilters).toBe(true);
  });

  it('should count total matching tasks correctly', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'Bug in login', metadata: { tags: ['bug'] } },
            { id: 2, title: 'Bug in payment', metadata: { tags: ['bug'] } }
          ]
        },
        {
          id: 2,
          name: 'Column 2',
          tasks: [
            { id: 3, title: 'Feature request', metadata: { tags: ['feature'] } },
            { id: 4, title: 'Another bug', metadata: { tags: ['bug'] } }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 
      1: store.board.columns[0].tasks,
      2: store.board.columns[1].tasks
    };

    store.searchQuery = 'bug';
    expect(store.matchingTasksCount).toBe(3);
  });

  it('should clear search correctly', () => {
    const store = useKanbanStore();
    
    store.board = {
      id: 1,
      title: 'Test Board',
      columns: [
        {
          id: 1,
          name: 'Column 1',
          tasks: [
            { id: 1, title: 'Task 1', metadata: {} }
          ]
        }
      ]
    };
    
    store.columns = store.board.columns;
    store.tasks = { 1: store.board.columns[0].tasks };

    store.searchQuery = 'test';
    expect(store.hasActiveFilters).toBe(true);

    store.clearSearch();
    expect(store.searchQuery).toBe('');
    expect(store.hasActiveFilters).toBe(false);
    expect(store.filteredBoard).toEqual(store.board);
  });
});
