<template>
  <div style="display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:16px;background:var(--cloud);padding:20px;text-align:center">
    <div style="font-size:48px">🔒</div>
    <div style="font-size:20px;font-weight:600;color:var(--forest)">Access Denied</div>
    <div style="font-size:14px;color:var(--stone)">You don't have permission to view this page.</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center">
      <button class="ibtn ibtn-o" @click="goBack">Go Back</button>
      <button class="ibtn ibtn-p" @click="goHome">Go to Dashboard</button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();

function goBack() {
  // Opened directly (no page before it) - there is nothing to go back to.
  window.history.length > 1 ? router.back() : goHome();
}

function goHome() {
  router.push({ name: 'dashboard' });
}
</script>
