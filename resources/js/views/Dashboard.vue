<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Good {{ timeOfDay }}, {{ firstName }}</h1>
      <p>Here's what needs your attention today.</p>
    </div>

    <!-- Stat Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
      <div class="stat-card" v-for="stat in stats" :key="stat.label">
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
              :class="urgencyRow(r.urgency_level)"
              @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
            >
              <div class="qav">{{ initials(r.student?.first_name, r.student?.last_name) }}</div>
              <div class="qi">
                <div class="qn">
                  {{ r.student?.first_name }} {{ r.student?.last_name }}
                  <span class="qid">{{ r.student?.student_id }}</span>
                </div>
                <div class="qmeta">{{ r.referral_type?.replace(/_/g, ' ') }} · {{ r.referrer_name }}</div>
                <div class="qtags">
                  <span class="ibadge" :class="'ibadge-' + r.urgency_level">{{ r.urgency_level }}</span>
                  <span class="ibadge" :class="'ibadge-' + r.status">{{ r.status?.replace(/_/g, ' ') }}</span>
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
                {{ a.appointment_type?.replace(/_/g, ' ') }} · {{ a.start_time }}
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
                  @click="$router.push({ name: 'case-show', params: { id: c.id } })"
                >
                  <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
                  <td>{{ c.student?.first_name }} {{ c.student?.last_name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + c.status">{{ c.status }}</span></td>
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
                  <td><span class="ibadge" :class="'ibadge-' + t.status">{{ t.status }}</span></td>
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
                  <td>{{ r.referral_type?.replace(/_/g, ' ') }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + r.status">{{ r.status?.replace(/_/g, ' ') }}</span></td>
                  <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Empty state -->
      <div class="empty-state" v-if="isEmpty">
        <h3>No data yet</h3>
        <p>Start by submitting a referral or checking the queue.</p>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/index';

const auth      = useAuthStore();
const dashboard = ref({});
const loading   = ref(true);

const firstName = computed(() => auth.user?.name?.split(' ')[0] || 'there');

const timeOfDay = computed(() => {
  const h = new Date().getHours();
  if (h < 12) return 'morning';
  if (h < 17) return 'afternoon';
  return 'evening';
});

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
      { label: 'Open Cases',         value: s.open_cases         ?? 0, period: 'Active',  iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>' },
      { label: 'Pending Referrals',  value: s.pending_referrals  ?? 0, period: 'Inbox',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
      { label: 'High Priority',      value: s.high_priority      ?? 0, period: 'Urgent',  iconBg: 'var(--red-lt)',    iconColor: 'var(--red)',    icon: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
    ];
  }
  if (auth.isSDUHead) {
    return [
      { label: 'Active Cases',       value: s.active_cases       ?? 0, period: 'Active',  iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>' },
      { label: 'Pending Referrals',  value: s.pending_referrals  ?? 0, period: 'Inbox',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
    ];
  }
  if (auth.isTMDUStaff) {
    return [
      { label: 'Pending Testing',    value: s.pending_testing    ?? 0, period: 'Queue',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>' },
      { label: 'In Progress',        value: s.in_progress        ?? 0, period: 'Active',  iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
      { label: 'Completed',          value: s.completed          ?? 0, period: 'Done',    iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="20 6 9 17 4 12"/>' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--purple-lt)', iconColor: 'var(--purple)', icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
    ];
  }
  return [
    { label: 'My Referrals',  value: s.my_referrals ?? 0, period: 'Total',    iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>' },
    { label: 'Pending',       value: s.pending      ?? 0, period: 'Awaiting', iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
    { label: 'Acknowledged',  value: s.acknowledged ?? 0, period: 'Received', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>' },
    { label: 'Completed',     value: s.completed    ?? 0, period: 'Resolved', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>' },
  ];
});

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase();
}

function urgencyRow(level) {
  return { uh: level === 'high' || level === 'critical', um: level === 'medium', ul: level === 'low' };
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

onMounted(async () => {
  try {
    const res = await api.get('/dashboard');
    dashboard.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>