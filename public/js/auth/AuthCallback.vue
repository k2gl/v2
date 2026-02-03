<template>
  <div class="auth-callback">
    <div v-if="loading" class="loading">
      <div class="spinner"></div>
      <p>Authenticating...</p>
    </div>

    <div v-else-if="error" class="error">
      <h2>Authentication Failed</h2>
      <p>{{ error }}</p>
      <button @click="retry">Try Again</button>
    </div>

    <div v-else class="success">
      <h2>Welcome!</h2>
      <p>Redirecting to your dashboard...</p>
    </div>
  </div>
</template>

<script>
import { onMounted, ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export default {
  name: 'AuthCallback',
  setup() {
    const route = useRoute();
    const router = useRouter();
    const authStore = useAuthStore();

    const loading = ref(true);
    const error = ref(null);

    onMounted(async () => {
      const token = route.query.token;
      const errorMessage = route.query.error;

      if (errorMessage) {
        error.value = errorMessage;
        loading.value = false;
        return;
      }

      if (token) {
        try {
          await authStore.setToken(token);
          router.push('/dashboard');
        } catch (e) {
          error.value = 'Failed to save authentication';
          loading.value = false;
        }
      } else {
        error.value = 'No authentication token received';
        loading.value = false;
      }
    });

    const retry = () => {
      router.push('/auth/login');
    };

    return {
      loading,
      error,
      retry
    };
  }
};
</script>

<style scoped>
.auth-callback {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.loading {
  text-align: center;
}

.spinner {
  width: 48px;
  height: 48px;
  border: 4px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 16px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error {
  text-align: center;
  color: #ef4444;
}

.error button {
  margin-top: 16px;
  padding: 8px 16px;
  background: #3b82f6;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

.success {
  text-align: center;
  color: #10b981;
}
</style>
