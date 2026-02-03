/**
 * Vue Component with Sentry Integration
 * Example: KanbanBoard.vue
 */

import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import draggable from 'vuedraggable';
import { useKanbanStore } from '@/stores/kanban';
import { apiClient } from '@/api/client';
import QuickAddTask from './QuickAddTask.vue';
import {
  initSentry,
  setUserContext,
  trackDragDrop,
  trackApiCall,
  captureException,
  trackKanbanEvent,
} from '@/monitoring/sentry';

export default {
  name: 'KanbanBoard',
  components: { draggable, QuickAddTask },
  setup() {
    const router = useRouter();
    const store = useKanbanStore();
    const boardId = router.currentRoute.value.params.id;
    const loading = ref(true);
    const error = ref(null);

    const initMonitoring = async () => {
      try {
        const userResponse = await apiClient.get('/users/me');
        setUserContext(userResponse.data);
      } catch (err) {
        console.warn('Could not set user context:', err);
      }
    };

    onMounted(async () => {
      trackKanbanEvent('board_opened', { board_id: boardId });

      try {
        await store.fetchBoard(boardId);
        await initMonitoring();
      } catch (err) {
        error.value = err.message;
        captureException(err, { board_id: boardId, context: 'board_fetch' });
      } finally {
        loading.value = false;
      }
    });

    const handleMove = async (event, columnId) => {
      const startTime = Date.now();

      try {
        if (event.added || event.moved) {
          const column = store.columns.find(c => c.id === columnId);
          const task = event.added ? event.added.element : event.moved.element;
          const oldIndex = event.moved?.oldIndex;
          const newIndex = event.moved?.newIndex;

          const orderedIds = column.tasks.map(t => t.id);

          await apiClient.post('/tasks/reorder', {
                       orderedIds,
 columnId,
            strategy: 'bulk',
          });

          trackDragDrop(
            event.added ? 'moved_to_column' : 'reordered',
            event.added ? null : columnId,
            columnId,
            task.id
          );
        }

        trackApiCall(
          '/tasks/reorder',
          'POST',
          Date.now() - startTime,
          200
        );
      } catch (err) {
        trackApiCall(
          '/tasks/reorder',
          'POST',
          Date.now() - startTime,
          err.response?.status || 500
        );
        captureException(err, {
          board_id: boardId,
          column_id: columnId,
          event_type: event.type,
        });
        throw err;
      }
    };

    const handleTaskClick = (task) => {
      trackKanbanEvent('task_clicked', {
        task_id: task.id,
        task_title: task.title,
      });

      router.push(`/boards/${boardId}/tasks/${task.id}`);
    };

    const handleReorder = (columnId, newTasks) => {
      store.tasks[columnId] = newTasks;
      const orderedIds = newTasks.map(t => t.id);
      apiClient.post('/tasks/reorder', {
        columnId,
        orderedIds,
        strategy: 'bulk'
      });
    };

    const getLabelClass = (label) => {
      const classes = {
        bug: 'bg-red-100 text-red-700',
        feature: 'bg-green-100 text-green-700',
        urgent: 'bg-yellow-100 text-yellow-700',
        default: 'bg-gray-100 text-gray-700',
      };
      return classes[label.toLowerCase()] || classes.default;
    };

    onUnmounted(() => {
      trackKanbanEvent('board_closed', { board_id: boardId });
      store.disconnect();
    });

    return {
      store,
      loading,
      error,
      handleMove,
      handleTaskClick,
      handleReorder,
      getLabelClass,
    };
  },
  template: `
    <div v-if="loading" class="flex justify-center items-center h-64">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>

    <div v-else-if="error" class="bg-red-50 text-red-600 p-4 rounded-lg">
      {{ error }}
    </div>

    <div v-else class="flex flex-col h-full">
      <!-- Board Header with Search -->
      <div class="flex items-center justify-between p-4 bg-white border-b">
        <h1 class="text-xl font-bold text-gray-800">{{ store.board?.title }}</h1>
        
        <div class="relative w-64">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
            </svg>
          </span>
          <input
            v-model="store.searchQuery"
            type="text"
            placeholder="Поиск задач или тегов..."
            class="block w-full py-2 pl-10 pr-3 bg-gray-100 border border-gray-300 rounded-md leading-5 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:bg-white sm:text-sm transition-colors"
          />
          <button 
            v-if="store.searchQuery" 
            @click="store.clearSearch"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
            </svg>
          </button>
        </div>

        <div class="text-sm text-gray-500">
          <span v-if="store.hasActiveFilters">
            {{ store.matchingTasksCount }} найдено
          </span>
        </div>
      </div>

      <!-- Columns -->
      <div class="flex-1 flex overflow-x-auto gap-4 p-4">
        <div 
          v-for="column in (store.hasActiveFilters ? store.filteredColumns : store.columns)" 
          :key="column.id"
          class="flex-shrink-0 w-72 bg-gray-100 rounded-lg flex flex-col max-h-full"
        >
          <div class="p-3 font-semibold text-gray-700 flex justify-between items-center">
            {{ column.name }}
            <span class="text-gray-400 text-sm">{{ column.tasks?.length || 0 }}</span>
          </div>

          <draggable
            v-if="!store.hasActiveFilters"
            :model-value="store.tasks[column.id]"
            @update:model-value="(val) => handleReorder(column.id, val)"
            group="tasks"
            item-key="id"
            class="flex-1 overflow-y-auto p-2 space-y-2"
            ghost-class="opacity-50"
            @end="(event) => handleMove(event, column.id)"
          >
            <template #item="{ element }">
              <div 
                @click="handleTaskClick(element)"
                class="bg-white p-3 rounded shadow-sm cursor-pointer hover:shadow-md transition-shadow"
              >
                <h4 class="font-medium text-gray-800">{{ element.title }}</h4>
                <div v-if="element.metadata?.tags?.length" class="flex gap-1 mt-2 flex-wrap">
                  <span 
                    v-for="tag in element.metadata.tags" 
                    :key="tag"
                    class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600"
                  >
                    {{ tag }}
                  </span>
                </div>
              </div>
            </template>
          </draggable>

          <div v-else class="flex-1 overflow-y-auto p-2 space-y-2">
            <div 
              v-for="element in column.tasks"
              :key="element.id"
              @click="handleTaskClick(element)"
              class="bg-white p-3 rounded shadow-sm cursor-pointer hover:shadow-md transition-shadow"
            >
              <h4 class="font-medium text-gray-800">{{ element.title }}</h4>
              <div v-if="element.metadata?.tags?.length" class="flex gap-1 mt-2 flex-wrap">
                <span 
                  v-for="tag in element.metadata.tags" 
                  :key="tag"
                  class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>

          <div v-if="!store.hasActiveFilters" class="p-2">
            <QuickAddTask :column-id="column.id" />
          </div>
        </div>
      </div>
    </div>
  `,
};
