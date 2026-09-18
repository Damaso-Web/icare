<template>
  <div style="display:flex;min-height:100vh">
    <!-- Sidebar -->
    <div style="width:240px;background:var(--forest);color:#fff;display:flex;flex-direction:column;flex-shrink:0">
      <div style="padding:20px;display:flex;align-items:center;gap:10px">
        <img :src="'/icare-logo.png'" alt="iCARE" style="width:32px;height:32px;background:#fff;border-radius:8px;padding:3px;object-fit:contain;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.25)" />
        <div>
          <div style="font-family:var(--serif);font-style:italic;font-size:16px">iCARE</div>
          <div style="font-size:10px;color:rgba(255,255,255,.5)">Student Portal</div>
        </div>
      </div>

      <div v-if="showBackToStaff" style="padding:0 12px;margin-top:6px">
        <button @click="backToStaff" style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:8px;padding:8px 10px;font-size:12px;cursor:pointer">
          <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Back to Staff View
        </button>
      </div>

      <div style="padding:0 12px;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:rgba(255,255,255,.4);margin:12px 0 6px 8px">Main</div>

      <nav style="flex:1;display:flex;flex-direction:column;gap:2px;padding:0 12px">
        <router-link
          v-for="item in menuItems"
          :key="item.name"
          :to="{ name: item.name }"
          style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;color:rgba(255,255,255,.75);text-decoration:none;font-size:13px;transition:background .15s"
          :style="isActive(item.name) ? 'background:rgba(255,255,255,.12);color:#fff;font-weight:600' : ''"
        >
          <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2" v-html="item.icon"></svg>
          {{ item.label }}
        </router-link>
      </nav>

      <div style="padding:14px;border-top:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700">
          {{ initials }}
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ student.first_name }} {{ student.last_name }}</div>
          <div style="font-size:10px;color:rgba(255,255,255,.5)">{{ student.student_id }}</div>
        </div>
        <button @click="logout" title="Logout" style="background:none;border:none;color:rgba(255,255,255,.6);cursor:pointer;padding:4px">
          <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </button>
      </div>
    </div>

    <!-- Main content -->
    <div style="flex:1;background:var(--snow);min-width:0">
      <div style="background:#fff;border-bottom:1px solid var(--cloud);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:relative">
        <div style="font-size:13px;color:var(--fog)">iCARE / <strong style="color:var(--ink)">{{ pageTitle }}</strong></div>
        <div style="display:flex;align-items:center;gap:16px">
          <button @click="showNotifs = !showNotifs" style="position:relative;background:none;border:none;cursor:pointer;padding:7px;color:var(--stone);border-radius:var(--r-sm)">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;display:block">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span v-if="unreadCount > 0" style="position:absolute;top:5px;right:5px;width:8px;height:8px;border-radius:50%;background:var(--red);border:2px solid #fff"></span>
          </button>
          <span style="font-size:13px;color:var(--stone)">{{ student.email }}</span>
        </div>

        <div v-if="showNotifs" style="position:absolute;top:52px;right:24px;width:320px;background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-lg);border:1px solid var(--cloud);z-index:100;overflow:hidden">
          <div style="padding:12px 16px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:13px;font-weight:600;color:var(--ink)">Notifications</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="markAllRead" style="font-size:11px">Mark all read</button>
          </div>
          <div style="max-height:320px;overflow-y:auto">
            <div v-if="notifications.length === 0" style="padding:20px;text-align:center;font-size:13px;color:var(--fog)">
              No notifications
            </div>
            <div
              v-for="n in notifications"
              :key="n.id"
              style="padding:12px 16px;border-bottom:1px solid var(--cloud);cursor:pointer;transition:background .1s"
              :style="{ background: n.read_at ? '#fff' : 'var(--foam)' }"
              @click="markRead(n)"
            >
              <div style="display:flex;gap:10px;align-items:flex-start">
                <div style="width:7px;height:7px;border-radius:50%;margin-top:5px;flex-shrink:0" :style="{ background: n.read_at ? 'transparent' : 'var(--moss)' }"></div>
                <div>
                  <div style="font-size:13px;color:var(--ink)">{{ n.data?.message || 'Notification' }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:2px">{{ formatNotifTime(n.created_at) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div style="padding:24px">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route  = useRoute();
const router = useRouter();
const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

const showNotifs = ref(false);
const notifications = ref([]);
const unreadCount = computed(() => notifications.value.filter(n => !n.read_at).length);

async function fetchNotifications() {
  try {
    const res = await axios.get(`${API_BASE}/student/notifications`, authHeaders());
    notifications.value = res.data.data || res.data;
  } catch (e) {
    console.error(e);
  }
}

async function markRead(n) {
  if (n.read_at) return;
  try {
    await axios.post(`${API_BASE}/student/notifications/${n.id}/read`, {}, authHeaders());
    n.read_at = new Date().toISOString();
  } catch (e) {
    // Non-fatal.
  }
}

async function markAllRead() {
  try {
    await axios.post(`${API_BASE}/student/notifications/read-all`, {}, authHeaders());
    notifications.value.forEach(n => n.read_at = n.read_at || new Date().toISOString());
  } catch (e) {
    // Non-fatal.
  }
}

function formatNotifTime(date) {
  if (!date) return '';
  const diffMs = Date.now() - new Date(date).getTime();
  const mins = Math.floor(diffMs / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return new Date(date).toLocaleDateString();
}

const menuItems = [
  { name: 'student-dashboard', label: 'Dashboard', icon: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>' },
  { name: 'student-appointments', label: 'My Appointments', icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
  { name: 'student-referrals', label: 'My Referrals', icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { name: 'student-account', label: 'My Account', icon: '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/>' },
];

const pageTitle = computed(() => {
  const titles = {
    'student-dashboard':    'Dashboard',
    'student-appointments': 'My Appointments',
    'student-referrals':    'My Referrals',
    'student-account':      'My Account',
  };
  return titles[route.name] || 'iCARE';
});

const initials = computed(() => {
  return ((student.value.first_name?.[0] || '') + (student.value.last_name?.[0] || '')).toUpperCase() || '?';
});

function isActive(name) {
  return route.name === name;
}

function logout() {
  localStorage.removeItem('student_token');
  localStorage.removeItem('student');
  router.push({ name: 'student-login' });
}

const showBackToStaff = computed(() => {
  try {
    const staffUser = JSON.parse(localStorage.getItem('user') || 'null');
    return !!localStorage.getItem('token') && staffUser?.email?.toLowerCase() === 'genrytester@bsu.edu.ph';
  } catch (e) {
    return false;
  }
});

function backToStaff() {
  localStorage.removeItem('student_token');
  localStorage.removeItem('student');
  window.location.href = '/';
}

onMounted(() => fetchNotifications());
</script>