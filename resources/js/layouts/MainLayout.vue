<template>
  <div style="display:flex;height:100vh;overflow:hidden">

    <!-- Sidebar -->
    <div class="sidebar">
      <div class="sb-head">
        <div class="sb-mark">i</div>
        <div>
          <div class="sb-brand">iCARE</div>
          <div class="sb-sub">BSU · OSS</div>
        </div>
      </div>

      <div class="sb-nav">
        <template v-for="item in menuItems" :key="item.name || item.section">
          <div class="sb-sec" v-if="item.section">{{ item.section }}</div>
          <router-link
            v-else
            :to="{ name: item.name }"
            class="nb"
            :class="{ active: isActive(item.name) }"
          >
            <svg viewBox="0 0 24 24" v-html="item.icon"></svg>
            {{ item.label }}
          </router-link>
        </template>
      </div>

      <div class="sb-foot">
        <div class="u-row">
          <div class="u-av">{{ initials }}</div>
          <div>
            <div class="u-nm">{{ auth.user?.name }}</div>
            <div class="u-rl">{{ roleLabel }}</div>
          </div>
          <button class="logbtn" @click="handleLogout" title="Sign out">
            <svg viewBox="0 0 24 24">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Main -->
    <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0">

      <!-- Topbar -->
      <div class="topbar" style="position:relative">
        <div class="breadcrumb-nav">iCARE / <strong>{{ pageTitle }}</strong></div>
        <div class="tb-right">
          <span style="font-size:12px;color:var(--stone)">{{ auth.user?.email }}</span>

          <!-- Notification Bell -->
          <button @click="showNotifs = !showNotifs" style="position:relative;background:none;border:none;cursor:pointer;padding:7px;color:var(--stone);border-radius:var(--r-sm)">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;display:block">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span v-if="unreadCount > 0" style="position:absolute;top:5px;right:5px;width:8px;height:8px;border-radius:50%;background:var(--red);border:2px solid #fff"></span>
          </button>

          <!-- Notification Dropdown -->
          <div v-if="showNotifs" style="position:absolute;top:58px;right:16px;width:320px;background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-lg);border:1px solid var(--cloud);z-index:100;overflow:hidden">
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
                :style="{ background: n.read ? '#fff' : 'var(--foam)' }"
                @click="n.read = true"
              >
                <div style="display:flex;gap:10px;align-items:flex-start">
                  <div style="width:7px;height:7px;border-radius:50%;margin-top:5px;flex-shrink:0" :style="{ background: n.read ? 'transparent' : 'var(--moss)' }"></div>
                  <div>
                    <div style="font-size:13px;color:var(--ink)">{{ n.text }}</div>
                    <div style="font-size:11px;color:var(--fog);margin-top:2px">{{ n.time }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Content -->
      <div class="content-area" @click="showNotifs = false">
        <router-view />
      </div>

    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const route  = useRoute();
const auth   = useAuthStore();

const showNotifs = ref(false);

const notifications = ref([
  { id: 1, text: 'New referral submitted for Maria Santos',      time: '2 hours ago', read: false },
  { id: 2, text: 'Appointment confirmed: Ana Versoza - Jul 11',  time: '3 hours ago', read: false },
  { id: 3, text: 'TMDU completed assessment for Luz Bacani',     time: 'Yesterday',   read: true  },
  { id: 4, text: 'Case CASE-2026-0005 has been closed',          time: 'Yesterday',   read: true  },
  { id: 5, text: 'Reminder: 3 appointments scheduled this week', time: '2 days ago',  read: true  },
]);

const unreadCount = computed(() => notifications.value.filter(n => !n.read).length);

function markAllRead() {
  notifications.value.forEach(n => n.read = true);
}

const initials = computed(() => {
  return auth.user?.name?.split(' ').map(n => n[0]).slice(0, 2).join('') || 'U';
});

const roleLabel = computed(() => {
  const labels = {
    admin:          'Admin / GCU Head',
    gcu_staff:      'GCU Staff',
    sdu_head:       'SDU Head',
    tmdu_staff:     'TMDU Staff',
    faculty:        'Faculty',
    dean_secretary: "Dean's Secretary",
  };
  return labels[auth.user?.role] || auth.user?.role;
});

const pageTitle = computed(() => {
  const titles = {
    dashboard:         'Dashboard',
    students:          'Students',
    'student-show':    'Student Profile',
    referrals:         'Referral Queue',
    'referral-create': 'Submit Referral',
    'referral-show':   'Referral Details',
    cases:             'Case Management',
    'case-show':       'Case Details',
    appointments:      'Appointment Calendar',
    testing:           'Testing Records',
    reports:           'Reports & Analytics',
    users:             'User Management',
    audit:             'Audit Logs',
  };
  return titles[route.name] || 'iCARE';
});

const menuItems = computed(() => {
  const role = auth.user?.role;

  const items = [
    {
      name:    'dashboard',
      label:   'Dashboard',
      icon:    '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'],
      section: 'Main',
    },
    {
      name:    'referral-create',
      label:   'Submit Referral',
      icon:    '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean_secretary'],
      section: 'Referrals',
    },
    {
      name:    'referrals',
      label:   'Referral Queue',
      icon:    '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head'],
      section: 'Referrals',
    },
    {
      name:    'students',
      label:   'Students',
      icon:    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'],
      section: 'Cases',
    },
    {
      name:    'cases',
      label:   'Case Files',
      icon:    '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'],
      section: null,
    },
    {
      name:    'appointments',
      label:   'Appointments',
      icon:    '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'],
      section: null,
    },
    {
      name:    'testing',
      label:   'Testing Records',
      icon:    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
      roles:   ['admin', 'gcu_staff', 'tmdu_staff'],
      section: null,
    },
    {
      name:    'reports',
      label:   'Reports',
      icon:    '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      roles:   ['admin', 'gcu_staff'],
      section: 'System',
    },
    {
      name:    'users',
      label:   'User Management',
      icon:    '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      roles:   ['admin'],
      section: null,
    },
    {
      name:    'audit',
      label:   'Audit Logs',
      icon:    '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
      roles:   ['admin'],
      section: null,
    },
  ];

  const filtered = items.filter(item => item.roles?.includes(role));

  const result = [];
  const addedSections = new Set();

  filtered.forEach(item => {
    if (item.section && !addedSections.has(item.section)) {
      addedSections.add(item.section);
      result.push({ section: item.section });
    }
    result.push({ name: item.name, label: item.label, icon: item.icon });
  });

  return result;
});

function isActive(name) {
  const routeName = route.name || '';
  if (name === 'referrals'       && (routeName === 'referrals' || routeName === 'referral-show')) return true;
  if (name === 'referral-create' && routeName === 'referral-create') return true;
  if (name === 'cases'           && routeName.startsWith('case'))    return true;
  if (name === 'students'        && routeName.startsWith('student')) return true;
  return routeName === name;
}

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login' });
}
</script>