<template>
  <div class="app-shell" style="display:flex;height:100vh;overflow:hidden">

    <!-- Sidebar (slides in over the page on phones) -->
    <div v-if="menuOpen" class="sb-backdrop" @click="menuOpen = false"></div>
    <div class="sidebar" :class="{ open: menuOpen, 'sb-collapsed': collapsed }">
      <div class="sb-head">
        <img v-show="sealBsu" class="logo-bsu sb-logo" :src="'/bsu-logo.jpg'" alt="Benguet State University" title="Benguet State University" @error="sealBsu = false" />
        <img v-show="sealOss" class="logo-oss sb-logo" :src="'/oss-logo.jpg'" alt="Office of Student Services" title="Office of Student Services" @error="sealOss = false" />
        <div class="sb-hide">
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
            :class="{ active: isActive(item.name), 'nb-locked': pwLocked && item.name !== 'my-account' }"
            :title="pwLocked && item.name !== 'my-account' ? 'Change your password first' : item.label"
            :aria-disabled="pwLocked && item.name !== 'my-account' ? 'true' : null"
            @click="lockClick($event, item.name)"
          >
            <svg viewBox="0 0 24 24" v-html="item.icon"></svg>
            <span class="sb-hide">{{ item.label }}</span>
          </router-link>
        </template>
      </div>

      <div class="sb-foot">
        <div class="u-row">
          <div class="u-av" :title="auth.user?.name">{{ initials }}</div>
          <div class="sb-hide">
            <div class="u-nm">{{ auth.user?.name }}</div>
            <div class="u-rl">{{ roleLabel }}</div>
          </div>
          <button class="logbtn" @click="session.askLogout()" title="Log out" aria-label="Log out">
            <svg viewBox="0 0 24 24">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span class="sb-hide">Log out</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Main -->
    <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0">

      <!-- Topbar -->
      <div class="topbar" style="position:relative">
        <button class="collapse-btn" :aria-label="collapsed ? 'Expand menu' : 'Collapse menu'" :title="collapsed ? 'Expand menu' : 'Collapse menu'" @click="toggleCollapsed"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="9" y1="4" x2="9" y2="20"/><polyline v-if="collapsed" points="13 9 16 12 13 15"/><polyline v-else points="16 9 13 12 16 15"/></svg></button>
        <button class="menu-btn has-more-tab" aria-label="Open menu" @click="menuOpen = true"><svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
        <div class="breadcrumb-nav">iCARE / <strong>{{ pageTitle }}</strong></div>
        <div class="tb-right">
          <span class="tb-date">{{ todayLabel }} · {{ timeLabel }}</span>
          <!-- Phones only (the sidebar that carries the logos is tucked away there) -->
          <div v-if="sealBsu || sealOss" class="tb-seals">
            <img v-show="sealBsu" class="logo-bsu" :src="'/bsu-logo.jpg'" alt="Benguet State University" @error="sealBsu = false" />
            <img v-show="sealOss" class="logo-oss" :src="'/oss-logo.jpg'" alt="Office of Student Services" @error="sealOss = false" />
          </div>

          <!-- Notification Bell -->
          <button @click="showNotifs = !showNotifs" style="position:relative;background:none;border:none;cursor:pointer;padding:7px;color:var(--stone);border-radius:var(--r-sm)">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round;display:block">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span v-if="unreadCount > 0" style="position:absolute;top:-3px;right:-5px;min-width:17px;height:17px;padding:0 4px;border-radius:9px;background:var(--red);color:#fff;font-size:10px;font-weight:700;line-height:17px;text-align:center;border:2px solid #fff;box-sizing:content-box">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
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
                :style="{ background: n.read_at ? '#fff' : 'var(--foam)' }"
                @click.stop="openNotification(n)"
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
            <div style="border-top:1px solid var(--cloud);padding:8px;text-align:center">
              <button class="ibtn ibtn-g ibtn-sm" style="font-size:12px;width:100%;justify-content:center" @click.stop="viewAllNotifs">View all</button>
            </div>
          </div>

          <!-- (The signed-in name is at the bottom of the sidebar, so it is not repeated here.) -->
        </div>
      </div>

      <!-- Content -->
      <div class="content-area" @click="showNotifs = false">
        <router-view />
      </div>

      <!-- Phones: the main pages one tap away at the bottom; "More" opens the full menu -->
      <nav class="tabbar" aria-label="Main pages">
        <router-link v-for="t in tabItems" :key="t.name" :to="{ name: t.name }" class="tab" :class="{ active: isActive(t.name) }">
          <svg viewBox="0 0 24 24" v-html="t.icon"></svg>
          <span>{{ t.short }}</span>
        </router-link>
        <button type="button" class="tab" :class="{ active: menuOpen || !tabItems.some(t => isActive(t.name)) }" @click="menuOpen = true">
          <svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg>
          <span>More</span>
        </button>
      </nav>

    </div>

    <!-- Log out confirmation, and the automatic log out after a period of no activity -->
    <SessionGuard ref="session" @logout="handleLogout" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, inject, provide } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api, { notificationAPI } from '../api/index';
import { staffNotificationTarget } from '../utils/notificationTarget';
import SessionGuard from '../components/SessionGuard.vue';
import { roleIn, roleLabelOf } from '../utils/roles';

const router = useRouter();
const route  = useRoute();
const auth   = useAuthStore();
const toast  = inject('toast');

// The role cached at login can go stale - an Admin may change it. The server
// enforces the real role, so make the menu and pages match it.
async function syncUser() {
  try {
    const res = await api.get('/me');
    const roleChanged = res.data.role !== auth.user?.role;
    auth.setUser({ ...auth.user, ...res.data });
    if (roleChanged && route.name !== 'dashboard') router.replace({ name: 'dashboard' });
  } catch (e) { /* keep the cached user */ }
}

// Phone menu: closed by default, and closes again after choosing a page.
const menuOpen = ref(false);
watch(() => route.fullPath, () => { menuOpen.value = false; });

const showNotifs = ref(false);
const session = ref(null);

// Desktop: the menu can be folded down to a strip of icons. Remembered on this browser.
const collapsed = ref(false);
try { collapsed.value = localStorage.getItem('sidebar_collapsed') === '1'; } catch (e) { /* stays open */ }
function toggleCollapsed() {
  collapsed.value = !collapsed.value;
  try { localStorage.setItem('sidebar_collapsed', collapsed.value ? '1' : '0'); } catch (e) { /* not remembered */ }
}

// Today's date for the top bar; the BSU and OSS logos are hidden if a file is missing.
// Date and time in the top bar: Philippine time, hours and minutes with AM/PM,
// kept current every few seconds.
const PH = 'Asia/Manila';
const now = ref(new Date());
const todayLabel = computed(() => now.value.toLocaleDateString('en-US', { timeZone: PH, weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }));
const timeLabel  = computed(() => now.value.toLocaleTimeString('en-US', { timeZone: PH, hour: 'numeric', minute: '2-digit', hour12: true }));
const clockTimer = setInterval(() => { now.value = new Date(); }, 5000);
onBeforeUnmount(() => clearInterval(clockTimer));
const sealBsu = ref(true);
const sealOss = ref(true);
const notifications = ref([]);

const unreadTotal = ref(0);
const unreadCount = computed(() => Math.max(unreadTotal.value, notifications.value.filter(n => !n.read_at).length));

async function fetchNotifications() {
  try {
    const res = await notificationAPI.index();
    notifications.value = res.data.data || res.data;
    unreadTotal.value = res.data.unread_count ?? notifications.value.filter(n => !n.read_at).length;
  } catch (e) {
    console.error(e);
  }
}

async function markRead(n) {
  if (n.read_at) return;
  try {
    await notificationAPI.markRead(n.id);
    n.read_at = new Date().toISOString();
    unreadTotal.value = Math.max(0, unreadTotal.value - 1);
  } catch (e) {
    // Non-fatal - badge just stays until next fetch.
  }
}

async function markAllRead() {
  try {
    await notificationAPI.markAllRead();
    notifications.value.forEach(n => n.read_at = n.read_at || new Date().toISOString());
    unreadTotal.value = 0;
  } catch (e) {
    // Non-fatal.
  }
}

function openNotification(n) {
  markRead(n);
  const target = staffNotificationTarget(n.data || {});
  if (target) {
    router.push(target);
    showNotifs.value = false;
  }
}

function viewAllNotifs() {
  showNotifs.value = false;
  router.push({ name: 'notifications' });
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

const initials = computed(() => {
  return auth.user?.name?.split(' ').map(n => n[0]).slice(0, 2).join('') || 'U';
});

const roleLabel = computed(() => roleLabelOf(auth.user?.role));

const pageTitle = computed(() => {
  const titles = {
    dashboard:           'Dashboard',
    students:            'Students',
    'student-show':      'Case Details',
    referrals:           'Referral Queue',
    'referral-create':      'Refer Student',
    'college-report':       'College Report',
    'referral-create-form': 'Refer a Student',
    'complaint-create':     'File a Complaint',
    'sent-referrals':       'Sent Referrals',
    'sent-complaints':      'Sent Complaints',
    complaints:             'Complaints',
    'incident-reports':     'Student Incident Reports',
    'incident-report-show': 'Student Incident Report',
    'referral-show':     'Referral Details',
    cases:               'Student Information Files',
    'case-referrals':    'TMDU Testing Results',
    'case-referral-show': 'Case Referral',
    appointments:        'Appointments',
    testing:             'Testing Records',
    'testing-appointments': 'TMDU Appointments',
    // Bug (10/30/2026): 'testing-show' and 'notifications' were missing
    // here, so those two pages fell through to the 'iCARE' fallback below
    // instead of their own breadcrumb.
    'testing-show':      'Testing Record',
    reports:             'Reports & Analytics',
    users:               'User Management',
    'faculty-directory': 'Faculty',
    notifications:       'Notifications',
    'my-account':        'My Account',
    audit:               'Audit Logs',
    'call-slips':        'Call Slips',
    backup:              'Backup & Recovery',
    management:          'Management',
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
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary'],
      section: 'Main',
    },
    {
      name:    'students',
      label:   'Students',
      icon:    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
      roles:   ['admin', 'gcu_staff'],
      section: null,
    },
    {
      name:    'faculty-directory',
      label:   'Faculty',
      icon:    '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      // B589: a Dean already has User Management, which does the same
      // account-management job for their college's faculty and chairs -
      // this standalone item stays only for Dept Chairs, who have no
      // User Management access of their own.
      roles:   ['dept_chair'],
      section: null,
    },
    {
      name:    'referral-create',
      label:   'Refer Student',
      icon:    '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary', 'system_admin'],
      section: null,
    },
    {
      name:    'sent-referrals',
      label:   'Sent Referrals',
      icon:    '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary', 'system_admin'],
      section: null,
    },
    {
      name:    'sent-complaints',
      label:   'Sent Complaints',
      icon:    '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary', 'system_admin'],
      section: null,
    },
    {
      name:    'referrals',
      // B#: this item was labeled "GCU Referrals" for TMDU - wrong unit name,
      // and inconsistent with the "Referral Queue" page header above it.
      label:   roleIn(role, ['tmdu_staff']) ? 'TMDU Referrals' : (role === 'gcu_staff' || role === 'admin') ? 'Referrals' : 'Referral Queue',
      icon:    '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
      // B596: a Dean is a referring source, not a processing unit - a queue
      // (even read-only) duplicates Sent Referrals and causes role confusion.
      // B605: same reasoning for a Dept Chair - they refer out, they don't
      // process an incoming queue, so Sent Referrals is their only status view.
      // Bug (10/25/2026, B626): same reasoning for plain Faculty - removed
      // from their sidebar too.
      roles:   ['admin', 'gcu_staff', 'tmdu_staff'],
      section: null,
    },
    {
      name:    'complaints',
      label:   'Complaints',
      icon:    '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
      roles:   ['sdu_head'],
      section: null,
    },
    {
      name:    'incident-reports',
      label:   'Student Incident Reports',
      icon:    '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>',
      roles:   ['sdu_head'],
      section: null,
    },
    {
      name:    'appointments',
      // Bug (10/25/2026, B625): renamed from "GCU Appointments" - redundant
      // with the GCU section it already lives in, and inconsistent with
      // every other item here, which drops the unit prefix.
      label:   'Appointments',
      icon:    '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
      roles:   ['admin', 'gcu_staff'],
      section: null,
    },
    {
      name:    'cases',
      label:   'Student Information Files',
      icon:    '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
      roles:   ['admin', 'gcu_staff'],
      section: null,
    },
    {
      name:    'case-referrals',
      // Bug (10/24/2026, B624): renamed from "Case Referral (TMDU)" - this
      // page is a repository of returned TMDU psychological-testing results
      // (PARs), not general outbound referrals, and the old name was
      // confusable with Sent Referrals.
      label:   'TMDU Testing Results',
      icon:    '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
      roles:   ['admin', 'gcu_staff'],
      section: null,
    },
    {
      name:    'testing',
      label:   'Testing Records',
      icon:    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
      roles:   ['tmdu_staff'],
      section: null,
    },
    {
      name:    'testing-appointments',
      label:   'TMDU Appointments',
      icon:    '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
      roles:   ['tmdu_staff'],
      section: null,
    },
    {
      name:    'reports',
      label:   'Reports',
      icon:    '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'],
      section: null,
    },
    {
    name:    'users',
    label:   'User Management',
    // GCU Head: every account. TMDU Head: TMDU Staff. Dean: their college's faculty and chairs.
    icon:    '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    roles:   ['admin', 'tmdu_head', 'dean'],
    section: null,
  },
    {
      name:    'audit',
      label:   'Audit Logs',
      icon:    '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
      roles:   ['admin'],
      section: null,
    },
    {
      name:    'college-report',
      label:   'College Report',
      icon:    '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
      roles:   ['dean'],
      section: null,
    },
    {
      name:    'call-slips',
      label:   'Call Slips',
      icon:    '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.36 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.34 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
      // Dept Chair sees the same page, but CallSlipController::index()
      // narrows it to only what's been escalated to them.
      roles:   ['dean_secretary', 'dept_chair'],
      section: null,
    },
    {
      name:    'backup',
      label:   'Backup & Recovery',
      icon:    '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
      roles:   ['admin'],
      section: null,
    },
    {
      name:    'my-account',
      label:   'My Account',
      icon:    '<circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/>',
      roles:   ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff', 'faculty', 'dean', 'dept_chair', 'dean_secretary'],
      section: null,
    },
    {
      name:    'management',
      label:   'Management',
      icon:    '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
      roles:   ['admin', 'system_admin'],
      section: 'System',
    },
  ];

  const filtered = items.filter(item => roleIn(role, item.roles));

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
// Phone tab bar: the first four of this role's pages, in order of how often
// they are needed on the go. Everything else stays under "More".
const TAB_ORDER = [
  ['dashboard', 'Home'], ['referrals', 'Referrals'], ['complaints', 'Complaints'], ['incident-reports', 'Incidents'], ['appointments', 'Appointments'],
  ['testing', 'Testing'], ['testing-appointments', 'Appointments'], ['call-slips', 'Call Slips'], ['cases', 'Files'],
  ['referral-create', 'Refer'], ['sent-referrals', 'Sent Referrals'], ['sent-complaints', 'Sent Complaints'], ['management', 'Manage'], ['students', 'Students'], ['reports', 'Reports'], ['my-account', 'Account'],
];
const tabItems = computed(() => {
  const mine = menuItems.value.filter(i => i.name);
  return TAB_ORDER
    .map(([name, short]) => ({ ...mine.find(i => i.name === name), short }))
    .filter(t => t.name)
    .slice(0, 4);
});
// Pages already on the phone tab bar, so the dashboard can leave out shortcuts that repeat them.
provide('tabNames', computed(() => tabItems.value.map(t => t.name)));

function isActive(name) {
  const routeName = route.name || '';
  // Student Profile and Referral Details are shared pages. The ?ctx= flag set by
  // the linking page tells us which module the user is actually browsing in.
  if (routeName === 'student-show' || routeName === 'referral-show') {
    if (route.query.ctx === 'cases')    return name === 'cases';
    if (route.query.ctx === 'students') return name === 'students';
    if (route.query.ctx === 'reports')  return name === 'reports';
  }
  if (name === 'referrals'       && (routeName === 'referrals' || routeName === 'referral-show')) return true;
  if (name === 'referral-create' && ['referral-create', 'referral-create-form', 'complaint-create'].includes(routeName)) return true;
  if (name === 'case-referrals'  && routeName === 'case-referral-show') return true;
  if (name === 'incident-reports' && routeName === 'incident-report-show') return true;
  if (name === 'cases'           && routeName.startsWith('case') && !routeName.startsWith('case-referral')) return true;
  if (name === 'testing'         && routeName === 'testing-show')    return true;
  if (name === 'students'        && routeName.startsWith('student')) return true;
  return routeName === name;
}

// An account on a temporary password can only use My Account until it is changed.
const pwLocked = computed(() => !!auth.user?.must_change_password);
function lockClick(e, name) {
  if (pwLocked.value && name !== 'my-account') e.preventDefault();
}

async function handleLogout() {
  await auth.logout();
  router.push({ name: 'login-choice' });
}

let notifTimer = null;
onMounted(() => {
  fetchNotifications();
  syncUser();
  // The badge follows new notifications without a page refresh.
  notifTimer = setInterval(fetchNotifications, 30000);
});
onBeforeUnmount(() => { if (notifTimer) clearInterval(notifTimer); });
</script>