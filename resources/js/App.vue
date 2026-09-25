<template>
  <div v-if="navigating" class="nav-loading-bar"></div>
  <router-view />
  <Toast ref="toast" />
</template>

<script setup>
import { ref, provide } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';
import Toast from './components/Toast.vue';

const auth   = useAuthStore();
const toast  = ref(null);
const router = useRouter();

// Initialize auth token on app startup
auth.initAuth();

// A thin top-bar during every route change - masks the brief flash of stale
// content between screens (e.g. right after login, before the dashboard's
// own data has loaded) instead of hiding it with nothing.
const navigating = ref(false);
router.beforeEach((to, from, next) => {
  navigating.value = true;
  next();
});
router.afterEach(() => {
  navigating.value = false;
});
router.onError(() => {
  navigating.value = false;
});

provide('toast', {
  success: (msg) => toast.value?.show(msg, 'success'),
  error:   (msg) => toast.value?.show(msg, 'error'),
  warning: (msg) => toast.value?.show(msg, 'warning'),
});
</script>

<style scoped>
.nav-loading-bar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--moss, #2f6f4f), var(--gold, #d4af37));
  z-index: 9999;
  animation: nav-loading-pulse .6s ease-in-out infinite;
}
@keyframes nav-loading-pulse {
  0%   { opacity: .35; }
  50%  { opacity: 1; }
  100% { opacity: .35; }
}
</style>