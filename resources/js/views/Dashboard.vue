<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="hero">
      <div class="hero-row">
        <div>
          <div class="hero-kicker">{{ roleTitle }}</div>
          <h1>{{ greeting }}, {{ firstName }}!</h1>
          <p>Here's what needs your attention today.</p>
          <div class="hero-chips">
            <span class="hero-chip"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>{{ todayLabel }}</span>
            <span class="hero-chip"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Office of Student Services</span>
          </div>
        </div>
        <div class="hero-actions">
          <button v-for="(a, i) in quickActions" :key="a.name" type="button" class="hero-btn" :class="{ gold: i === 0 }" @click="router.push({ name: a.name })">
            <svg viewBox="0 0 24 24" v-html="a.icon"></svg>{{ a.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Stat Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
      <div class="stat-card" v-for="stat in stats" :key="stat.label" style="cursor:pointer" @click="goToStat(stat)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
          <div class="stat-icon" :style="{ background: stat.iconBg }">
            <svg viewBox="0 0 24 24" :style="{ color: stat.iconColor }" v-html="stat.icon"></svg>
          </div>
          <span style="font-size:10px;font-weight:600;color:var(--stone);background:var(--cloud);padding:2px 7px;border-radius:20px">
            {{ stat.period }}
          </span>
        </div>
        <div class="stat-num">{{ stat.value }}</div>
        <div class="stat-label">{{ stat.label }}</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <!-- Two column layout -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

        <!-- Recent Referrals -->
        <div class="icard" v-if="dashboard.recent_referrals?.length">
          <div class="icard-header">
            <span class="icard-title">Recent Referrals</span>
            <router-link :to="{ name: 'referrals' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div>
            <div
              v-for="r in dashboard.recent_referrals"
              :key="r.id"
              class="qr"
              @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
            >
              <div class="qav">{{ initials(r.student?.first_name, r.student?.last_name) }}</div>
              <div class="qi">
                <div class="qn">
                  {{ r.student?.first_name }} {{ r.student?.last_name }}
                  <span class="qid">{{ r.student?.student_id }}</span>
                </div>
                <div class="qmeta">{{ toTitleCase(r.referral_type) }} · {{ r.referrer_name }}</div>
                <!-- Urgency badge/row-highlight removed - urgency_level is
                     never actually set by anyone (no form exposes it; it's
                     just the DB column default, or hardcoded 'medium' for
                     complaint-based referrals in ComplaintController), so
                     the "Medium" tag it always showed was meaningless. -->
                <div class="qtags">
                  <span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="icard" v-if="dashboard.upcoming_appointments?.length">
          <div class="icard-header">
            <span class="icard-title">Today's Sessions</span>
            <router-link :to="{ name: 'appointments' }" class="ibtn ibtn-g ibtn-sm">Calendar</router-link>
          </div>
          <div style="padding:10px">
            <div
              v-for="a in dashboard.upcoming_appointments"
              :key="a.id"
              style="padding:7px 9px;background:var(--foam);border-radius:var(--r-sm);margin-bottom:5px;border-left:3px solid var(--moss)"
            >
              <div style="font-size:11.5px;font-weight:600;color:var(--forest)">
                {{ a.student?.first_name }} {{ a.student?.last_name }}
              </div>
              <div style="font-size:10px;color:var(--stone);margin-top:2px">
                {{ toTitleCase(a.appointment_type) }} · {{ formatTime12(a.start_time) }}
              </div>
              <div style="font-size:9px;color:var(--sage);margin-top:3px;font-style:italic">
                {{ a.staff?.name }}
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- My Cases / Testing Queue -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

        <!-- My Active Cases -->
        <div class="icard" v-if="dashboard.my_cases?.length">
          <div class="icard-header">
            <span class="icard-title">My Active Cases</span>
            <router-link :to="{ name: 'cases' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Case No.</th>
                  <th>Student</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="c in dashboard.my_cases"
                  :key="c.id"
                  style="cursor:pointer"
                  @click="goToReferral(c)"
                >
                  <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
                  <td>{{ c.student?.first_name }} {{ c.student?.last_name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + c.status">{{ toTitleCase(c.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Testing Queue -->
        <div class="icard" v-if="dashboard.testing_queue?.length">
          <div class="icard-header">
            <span class="icard-title">Testing Queue</span>
            <router-link :to="{ name: 'testing' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Referred By</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in dashboard.testing_queue" :key="t.id">
                  <td>{{ t.student?.first_name }} {{ t.student?.last_name }}</td>
                  <td>{{ t.referred_by?.name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + t.status">{{ toTitleCase(t.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Faculty Recent Referrals -->
        <div class="icard" v-if="dashboard.recent_referrals?.length && !dashboard.my_cases?.length && !dashboard.testing_queue?.length" style="grid-column:1/-1">
          <div class="icard-header">
            <span class="icard-title">My Submitted Referrals</span>
            <router-link :to="{ name: 'referrals' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Student</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in dashboard.recent_referrals" :key="r.id">
                  <td style="font-family:var(--mono);font-size:11px">{{ r.referral_code }}</td>
                  <td>{{ r.student?.first_name }} {{ r.student?.last_name }}</td>
                  <td>{{ toTitleCase(r.referral_type) }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span></td>
                  <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- SDU: the incident reports still open, and the newest complaints -->
      <div v-if="auth.isSDUHead" class="sdu-panels">
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Incident Reports Needing Action</span>
            <a href="#" class="sdu-link" @click.prevent="router.push({ name: 'incident-reports' })">View all</a>
          </div>
          <div v-if="!sduOpenReports.length" class="icard-body" style="font-size:13px;color:var(--stone)">Nothing waiting - every Incident Report is resolved.</div>
          <div v-for="r in sduOpenReports" :key="r.student.id" class="sdu-row" @click="router.push({ name: 'incident-report-show', params: { id: r.student.id } })">
            <div style="min-width:0">
              <div class="sdu-name">{{ r.student.last_name }}, {{ r.student.first_name }}</div>
              <div class="sdu-meta">{{ r.complaints_count }} complaint{{ r.complaints_count === 1 ? '' : 's' }} · latest: {{ r.latest_violation }}</div>
            </div>
            <span class="ibadge" :class="'ir-' + r.status">{{ toTitleCase(r.status) }}</span>
          </div>
        </div>
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Newest Complaints</span>
            <a href="#" class="sdu-link" @click.prevent="router.push({ name: 'complaints' })">View all</a>
          </div>
          <div v-if="!sduComplaints.length" class="icard-body" style="font-size:13px;color:var(--stone)">No complaints have been filed yet.</div>
          <div v-for="c in sduComplaints" :key="c.id" class="sdu-row" @click="router.push({ name: 'incident-report-show', params: { id: c.complainee_student_id }, query: { complaint: c.id } })">
            <div style="min-width:0">
              <div class="sdu-name">{{ c.complainee?.last_name }}, {{ c.complainee?.first_name }}</div>
              <div class="sdu-meta">{{ c.violation_type }}</div>
            </div>
            <span class="sdu-meta" style="white-space:nowrap">{{ formatDate(c.created_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div class="empty-state" v-if="isEmpty && !auth.isSDUHead">
        <h3>No data yet</h3>
        <p>Start by submitting a referral or checking the queue.</p>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/index';
import { toTitleCase, formatTime12 } from '../utils/validators';

const auth      = useAuthStore();
const router    = useRouter();
const toast     = inject('toast');
const dashboard = ref({});
const loading   = ref(true);

function goToReferral(c) {
  if (c.latest_referral?.id) {
    router.push({ name: 'referral-show', params: { id: c.latest_referral.id } });
  } else {
    toast?.error('No linked referral found for this case.');
  }
}

const firstName = computed(() => auth.user?.name?.split(' ')[0] || 'there');

// ---- Welcome banner ----
const todayLabel = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
const ROLE_TITLES = {
  admin: 'Admin · GCU Head', gcu_staff: 'Guidance and Counseling Unit', sdu_head: 'Student Discipline Unit',
  tmdu_staff: 'Testing and Measurement Development Unit', faculty: 'Faculty', dean: 'Dean',
  dept_chair: 'Department Chair', dean_secretary: "Dean's Secretary", system_admin: 'System Administrator',
};
const roleTitle = computed(() => ROLE_TITLES[auth.user?.role] || 'iCARE');

// The two or three places each role goes most, one tap from the banner.
const ICONS = {
  plus:     '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  pulse:    '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
  calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
  file:     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
  alert:    '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>',
  check:    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
  phone:    '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
};
const quickActions = computed(() => {
  const refer = { name: 'referral-create', label: 'Refer a Student', icon: ICONS.plus };
  switch (auth.user?.role) {
    case 'admin':
    case 'gcu_staff':      return [refer, { name: 'referrals', label: 'Referrals', icon: ICONS.pulse }, { name: 'appointments', label: 'Appointments', icon: ICONS.calendar }];
    case 'sdu_head':       return [{ name: 'incident-reports', label: 'Incident Reports', icon: ICONS.file }, { name: 'complaints', label: 'Complaints', icon: ICONS.alert }, refer];
    case 'tmdu_staff':     return [{ name: 'testing', label: 'Testing Records', icon: ICONS.check }, { name: 'testing-appointments', label: 'Appointments', icon: ICONS.calendar }];
    case 'dean_secretary': return [{ name: 'call-slips', label: 'Call Slips', icon: ICONS.phone }, refer];
    case 'faculty':
    case 'dean':
    case 'dept_chair':     return [refer, { name: 'referrals', label: 'My Referrals', icon: ICONS.pulse }];
    default:               return [];
  }
});

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 18) return 'Good afternoon';
  return 'Good evening';
});


// ---- SDU dashboard panels ----
const sduReports    = ref([]);
const sduComplaints = ref([]);
const sduOpenReports = computed(() => sduReports.value.filter(r => r.status !== 'resolved').slice(0, 6));
async function fetchSduPanels() {
  if (!auth.isSDUHead) return;
  try {
    const [ir, cp] = await Promise.all([api.get('/incident-reports'), api.get('/complaints')]);
    sduReports.value    = ir.data.data || [];
    sduComplaints.value = (cp.data.data || []).slice(0, 6);
  } catch (e) {
    /* the tiles above still show */
  }
}

const isEmpty = computed(() => {
  const d = dashboard.value;
  return !d.recent_referrals?.length &&
         !d.upcoming_appointments?.length &&
         !d.my_cases?.length &&
         !d.testing_queue?.length;
});

const stats = computed(() => {
  const s = dashboard.value.stats || {};
  if (auth.isAdmin || auth.isGCUStaff) {
    return [
      { label: 'Open Cases',         value: s.open_cases         ?? 0, period: 'Active',  iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>', route: 'cases', status: 'open' },
      { label: 'Pending Referrals',  value: s.pending_referrals  ?? 0, period: 'Inbox',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', route: 'referrals' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', route: 'appointments' },
    ];
  }
  if (auth.isSDUHead) {
    return [
      { label: 'Active Cases',       value: s.active_cases       ?? 0, period: 'Active',  iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>', route: 'complaints' },
            { label: 'Pending Complaints', value: s.pending_complaints ?? 0, period: 'Inbox',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', route: 'complaints' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', route: 'complaints' },
    ];
  }
  if (auth.isTMDUStaff) {
    return [
      { label: 'Pending Testing',    value: s.pending_testing    ?? 0, period: 'Queue',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>', route: 'testing' },
      { label: 'In Progress',        value: s.in_progress        ?? 0, period: 'Active',  iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'testing' },
      { label: 'Completed',          value: s.completed          ?? 0, period: 'Done',    iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="20 6 9 17 4 12"/>', route: 'testing' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--purple-lt)', iconColor: 'var(--purple)', icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', route: 'testing-appointments' },
    ];
  }
  return [
    { label: 'My Referrals',  value: s.my_referrals ?? 0, period: 'Total',    iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>', route: 'referrals' },
    { label: 'Pending',       value: s.pending      ?? 0, period: 'Awaiting', iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'referrals' },
    { label: 'Acknowledged',  value: s.acknowledged ?? 0, period: 'Received', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>', route: 'referrals' },
    { label: 'Completed',     value: s.completed    ?? 0, period: 'Resolved', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', route: 'referrals' },
  ];
});

function goToStat(stat) {
  if (!stat.route) return;
  router.push({ name: stat.route, query: stat.status ? { status: stat.status } : {} });
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase();
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

// Re-fetches in the background on an interval so the stat cards stay live
// without the person needing to leave and come back to the page. `loading`
// is only touched on the very first load, so refreshes don't flash the
// spinner over the whole page.
let refreshTimer = null;

async function fetchDashboard(isInitial = false) {
  try {
    const res = await api.get('/dashboard');
    dashboard.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    if (isInitial) loading.value = false;
  }
}

onMounted(() => {
  fetchDashboard(true);
  fetchSduPanels();
  refreshTimer = setInterval(() => fetchDashboard(false), 30000);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
});
</script>

<style scoped>
/* SDU dashboard panels */
.sdu-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; align-items: start; }
.sdu-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 18px; border-bottom: 1px solid var(--cloud); cursor: pointer; transition: background .1s; }
.sdu-row:last-child { border-bottom: none; }
.sdu-row:hover { background: var(--foam); }
.sdu-name { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.sdu-meta { font-size: 11.5px; color: var(--stone); overflow: hidden; text-overflow: ellipsis; }
.sdu-link { font-size: 12px; color: var(--moss); text-decoration: underline; }
@media (max-width: 860px) { .sdu-panels { grid-template-columns: 1fr; } }
</style>
