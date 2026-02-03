<template>
  <div class="mt-2">
    <button 
      v-if="!isEditing" 
      @click="openEditor"
      class="w-full flex items-center p-2 text-gray-600 hover:bg-gray-300 rounded-md transition"
    >
      <span class="mr-2">+</span> Добавить карточку
    </button>

    <div v-else class="bg-white p-2 rounded shadow-sm">
      <textarea
        v-model="title"
        ref="inputRef"
        placeholder="Введите заголовок для этой карточки..."
        class="w-full border-none focus:ring-0 resize-none text-sm"
        rows="3"
        @keyup.enter.exact.prevent="save"
        @keyup.esc="closeEditor"
      ></textarea>
      
      <div class="flex items-center gap-2 mt-2">
        <button 
          @click="save"
          :disabled="!title.trim()"
          class="bg-blue-600 text-white px-3 py-1.5 rounded text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
        >
          Добавить карточку
        </button>
        <button @click="closeEditor" class="text-gray-500 hover:text-gray-700 text-xl">✕</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import { apiClient } from '@/api/client';

const props = defineProps({ columnId: Number });
const emit = defineEmits(['task-created']);

const isEditing = ref(false);
const title = ref('');
const inputRef = ref(null);

const openEditor = async () => {
  isEditing.value = true;
  await nextTick();
  inputRef.value?.focus();
};

const closeEditor = () => {
  isEditing.value = false;
  title.value = '';
};

const save = async () => {
  if (!title.value.trim()) return;

  try {
    await apiClient.post('/api/tasks', {
      columnId: props.columnId,
      title: title.value.trim()
    });
    
    title.value = '';
    await nextTick();
    inputRef.value?.focus();
  } catch (e) {
    console.error('Failed to create task:', e);
  }
};
</script>
