<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Monitoring Dashboard</h1>
      <p>Track pending referrals, appointments, and unresolved cases at a glance.</p>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <!-- Stat Cards -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
        <div class="stat-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--amber-lt)">
              <svg viewBox="0 0 24 24" style="color:var(--amber)"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
          </div>
          <div class="stat-num">{{ data.stats?.pending_referrals ?? 0 }}</div>
          <div class="stat-label">Pending Referrals</div>
        </div>
        <div class="stat-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--blue-lt)">
              <svg viewBox="0 0 24 24" style="color:var(--blue)"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
          </div>
          <div class="stat-num">{{ data.stats?.pending_appointments ?? 0 }}</div>
          <div class="stat-label">Pending Appointments</div>
        </div>
        <div class="stat-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--mist)">
              <svg viewBox="0 0 24 24" style="color:var(--moss)"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            </div>
          </div>
          <div class="stat-num">{{ data.stats?.unresolved_cases ?? 0 }}</div>
          <div class="stat-label">Unresolved Cases</div>
        </div>
        <div class="stat-card">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" style="background:var(--red-lt)">
              <svg viewBox="0 0 24 24" style="color:var(--red)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
          </div>
          <div class="stat-num">{{ data.stats?.flagged_follow_up ?? 0 }}</div>
          <div class="stat-label">Flagged for Follow-Up</div>
        </div>
      </div>

      <!-- Pending Referrals -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header">
          <span class="icard-title">Pending Referrals</span>
          <router-link :to="{ name: 'referrals' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
        </div>
        <div v-if="!data.pending_referrals?.data?.length" class="empty-state">
          <h3>No pending referrals</h3>
        </div>
        <div v-else>
          <div
            v-for="r in data.pending_referrals.data"
            :key="r.id"
            style="padding:12px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
            @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
          >
            <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
              <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ r.student?.last_name }}, {{ r.student?.first_name }}</div>
              <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ r.referral_code }}</div>
            </div>
            <div style="font-size:11.5px;color:var(--stone);margin-top:2px">
              {{ toTitleCase(r.referral_type) }} · Submitted by {{ r.referredBy?.name }}
            </div>
          </div>
        </div>
      </div>

      <!-- Pending Appointments -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header">
          <span class="icard-title">Pending Appointments</span>
          <router-link :to="{ name: 'appointments' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
        </div>
        <div v-if="!data.pending_appointments?.data?.length" class="empty-state">
          <h3>No pending appointments</h3>
        </div>
        <div v-else>
          <div v-for="a in data.pending_appointments.data" :key="a.id" style="padding:12px 18px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ a.student?.last_name }}, {{ a.student?.first_name }}</div>
            <div style="font-size:11.5px;color:var(--stone);margin-top:2px">
              {{ formatDate(a.appointment_date) }} · {{ a.start_time }} · {{ a.unit }}
            </div>
          </div>
        </div>
      </div>

      <!-- Unresolved Cases -->
      <div class="icard">
        <div class="icard-header">
          <span class="icard-title">Unresolved Cases</span>
          <router-link :to="{ name: 'cases' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
        </div>
        <div v-if="!data.unresolved_cases?.data?.length" class="empty-state">
          <h3>No unresolved cases</h3>
        </div>
        <div v-else class="ts">
          <table class="itable">
            <thead>
              <tr>
                <th>Case No.</th>
                <th>Student</th>
                <th>Status</th>
                <th>Follow-Up</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="c in data.unresolved_cases.data"
                :key="c.id"
                style="cursor:pointer"
                @click="$router.push({ name: 'case-show', params: { id: c.id } })"
              >
                <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
                <td>{{ c.student?.first_name }} {{ c.student?.last_name }}</td>
                <td><span class="ibadge" :class="'ibadge-' + c.status">{{ toTitleCase(c.status) }}</span></td>
                <td>
                  <span v-if="c.requires_follow_up" class="ibadge" style="background:var(--red-lt);color:var(--red)">Flagged</span>
                  <span v-else style="color:var(--fog);font-size:12px">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { monitoringAPI } from '../api/index';

const loading = ref(true);
const data = ref({});

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

onMounted(async () => {
  try {
    const res = await monitoringAPI.index();
    data.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>