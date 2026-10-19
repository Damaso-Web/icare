<template>
  <div>
    <div class="ph" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="ibtn ibtn-o ibtn-sm" title="Back" aria-label="Back" style="font-size:16px;line-height:1;padding:4px 10px" @click="goBack">&larr;</button>
        <div>
          <h1>Notifications</h1>
          <p>{{ unreadTotal }} unread</p>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="ibtn ibtn-o ibtn-sm" :disabled="!unreadTotal" @click="markAll">Mark all read</button>
      </div>
    </div>

    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:36px;color:var(--fog);font-size:13px">Loading...</div>
      <div v-else-if="!items.length" class="empty-state"><h3>No notifications</h3></div>
      <div
        v-for="n in items"
        :key="n.id"
        style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;gap:10px;align-items:flex-start"
        :style="{ background: n.read_at ? '#fff' : 'var(--foam)', cursor: target(n) ? 'pointer' : 'default' }"
        @click="open(n)"
      >
        <div style="width:8px;height:8px;border-radius:50%;margin-top:5px;flex-shrink:0" :style="{ background: n.read_at ? 'transparent' : 'var(--moss)' }"></div>
        <div style="flex:1;min-width:0">
          <div style="font-size:13px;color:var(--ink)">{{ n.data?.message || 'Notification' }}</div>
          <div style="font-size:11px;color:var(--fog);margin-top:2px">{{ new Date(n.created_at).toLocaleString() }}</div>
        </div>
        <span v-if="target(n)" style="font-size:12px;color:var(--moss)">Open &rsaquo;</span>
      </div>
      <div v-if="lastPage > 1" style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px">
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page <= 1" @click="go(page - 1)">Previous</button>
        <span style="font-size:12px;color:var(--stone)">Page {{ page }} of {{ lastPage }}</span>
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page >= lastPage" @click="go(page + 1)">Next</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { staffNotificationTarget, studentNotificationTarget } from '../utils/notificationTarget';

const route = useRoute();
const router = useRouter();
const isStudent = route.meta?.student === true;
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const base = isStudent ? '/student/notifications' : '/notifications';
const cfg = () => ({ headers: { Authorization: `Bearer ${localStorage.getItem(isStudent ? 'student_token' : 'token')}` } });
const target = (n) => (isStudent ? studentNotificationTarget : staffNotificationTarget)(n.data || {});

// Back to the page the user came from; straight to home when this was opened directly.
function goBack() {
  if (window.history.state?.back) {
    router.back();
    return;
  }
  let role = null;
  try { role = JSON.parse(localStorage.getItem('user') || '{}').role; } catch (e) { /* ignore */ }
  router.push({ name: isStudent ? 'student-dashboard' : (role === 'system_admin' ? 'management' : 'dashboard') });
}

const items = ref([]);
const loading = ref(true);
const page = ref(1);
const lastPage = ref(1);
const unreadTotal = ref(0);

async function load() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}${base}`, { ...cfg(), params: { page: page.value } });
    items.value = res.data.data || [];
    lastPage.value = res.data.last_page || 1;
    unreadTotal.value = res.data.unread_count ?? items.value.filter(n => !n.read_at).length;
  } catch (e) {
    items.value = [];
  } finally {
    loading.value = false;
  }
}
function go(p) { page.value = p; load(); }

async function open(n) {
  if (!n.read_at) {
    try { await axios.post(`${API_BASE}${base}/${n.id}/read`, {}, cfg()); n.read_at = new Date().toISOString(); unreadTotal.value = Math.max(0, unreadTotal.value - 1); } catch (e) { /* non-fatal */ }
  }
  const t = target(n);
  if (t) router.push(t);
}

async function markAll() {
  try {
    await axios.post(`${API_BASE}${base}/read-all`, {}, cfg());
    items.value.forEach(n => n.read_at = n.read_at || new Date().toISOString());
    unreadTotal.value = 0;
  } catch (e) { /* non-fatal */ }
}

onMounted(load);
</script>