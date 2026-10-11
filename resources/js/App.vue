<template>
  <div v-if="navigating" class="nav-loading-bar"></div>
  <router-view />
  <Toast ref="toast" />

  <!-- "Discard your changes?" - asked by any form through inject('confirmDiscard') -->
  <div v-if="discardAsk" style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:400;display:flex;align-items:center;justify-content:center;padding:20px">
    <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:380px;overflow:hidden;box-shadow:var(--sh-lg)">
      <div style="padding:18px 22px;border-bottom:1px solid var(--cloud);font-size:15px;font-weight:600;color:var(--ink)">Discard your changes?</div>
      <div style="padding:20px 22px;display:flex;flex-direction:column;gap:16px">
        <div style="font-size:13px;color:var(--ink);line-height:1.55">What you changed on this form has not been saved and will be lost.</div>
        <div style="display:flex;gap:8px">
          <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="answerDiscard(false)">Keep Editing</button>
          <button class="ibtn" style="flex:1;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="answerDiscard(true)">Discard</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, provide, onMounted, onBeforeUnmount } from 'vue';
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

// "Discard your changes?" - resolves true to discard, false to keep editing.
const discardAsk = ref(null);
function answerDiscard(yes) {
  discardAsk.value?.(yes);
  discardAsk.value = null;
}
provide('confirmDiscard', () => new Promise(resolve => { discardAsk.value = resolve; }));

provide('toast', {
  success: (msg) => toast.value?.show(msg, 'success'),
  error:   (msg) => toast.value?.show(msg, 'error'),
  warning: (msg) => toast.value?.show(msg, 'warning'),
});

// Notices raised outside the component tree (see notifyFailure in api/index.js).
function showNotice(event) {
  toast.value?.show(event.detail.message, event.detail.type || 'error');
}
onMounted(() => window.addEventListener('icare:notice', showNotice));
onBeforeUnmount(() => window.removeEventListener('icare:notice', showNotice));
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