<template>
  <router-view />
  <Toast ref="toast" />
</template>

<script setup>
import { ref, provide } from 'vue';
import { useAuthStore } from './stores/auth';
import Toast from './components/Toast.vue';

const auth  = useAuthStore();
const toast = ref(null);

// Initialize auth token on app startup
auth.initAuth();

provide('toast', {
  success: (msg) => toast.value?.show(msg, 'success'),
  error:   (msg) => toast.value?.show(msg, 'error'),
  warning: (msg) => toast.value?.show(msg, 'warning'),
});
</script>