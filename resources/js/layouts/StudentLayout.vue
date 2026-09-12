<template>
  <div style="display:flex;min-height:100vh">
    <!-- Sidebar -->
    <div style="width:240px;background:var(--forest);color:#fff;display:flex;flex-direction:column;flex-shrink:0">
      <div style="padding:20px;display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;background:var(--gold);border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:var(--serif);font-style:italic;color:var(--forest);font-weight:700">i</div>
        <div>
          <div style="font-family:var(--serif);font-style:italic;font-size:16px">iCARE</div>
          <div style="font-size:10px;color:rgba(255,255,255,.5)">Student Portal</div>
        </div>
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
      <div style="background:#fff;border-bottom:1px solid var(--cloud);padding:14px 24px;display:flex;align-items:center;justify-content:space-between">
        <div style="font-size:13px;color:var(--fog)">iCARE / <strong style="color:var(--ink)">{{ pageTitle }}</strong></div>
        <span style="font-size:13px;color:var(--stone)">{{ student.email }}</span>
      </div>
      <div style="padding:24px">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route  = useRoute();
const router = useRouter();
const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));

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
</script>