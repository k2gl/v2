/**
 * API Client with JWT Authentication
 * Configured for Symfony API with LexikJWTAuthenticationBundle
 */

import axios from 'axios';
import { useAuthStore } from '@/stores/auth';

const API_BASE_URL = import.meta.env.VITE_API_URL || '/api';

class ApiClient {
  constructor() {
    this.client = axios.create({
      baseURL: API_BASE_URL,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  setupInterceptors() {
    this.client.interceptors.request.use(
      async (config) => {
        const authStore = useAuthStore();
        
        if (authStore.token) {
          config.headers.Authorization = `Bearer ${authStore.token}`;
        }

        return config;
      },
      (error) => Promise.reject(error)
    );

    this.client.interceptors.response.use(
      (response) => response,
      async (error) => {
        const authStore = useAuthStore();

        if (error.response?.status === 401 && authStore.token) {
          authStore.logout();
          window.location.href = '/auth/login';
        }

        return Promise.reject(error);
      }
    );
  }

  async get(endpoint, params = {}) {
    return this.client.get(endpoint, { params });
  }

  async post(endpoint, data = {}) {
    return this.client.post(endpoint, data);
  }

  async put(endpoint, data = {}) {
    return this.client.put(endpoint, data);
  }

  async patch(endpoint, data = {}) {
    return this.client.patch(endpoint, data);
  }

  async delete(endpoint) {
    return this.client.delete(endpoint);
  }
}

export const apiClient = new ApiClient();

export const authApi = {
  login: (email, password) => 
    apiClient.post('/login_check', { username: email, password }),
};

export const boardsApi = {
  list: () => apiClient.get('/boards'),
  get: (id) => apiClient.get(`/boards/${id}`),
  create: (data) => apiClient.post('/boards', data),
  delete: (id) => apiClient.delete(`/boards/${id}`),
};

export const tasksApi = {
  reorder: (columnId, orderedIds, strategy = 'bulk') =>
    apiClient.post('/tasks/reorder', { columnId, orderedIds, strategy }),
  move: (taskId, newStatus) =>
    apiClient.post(`/tasks/${taskId}/move`, { newStatus }),
};
