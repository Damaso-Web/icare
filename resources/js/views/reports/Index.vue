<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Reports & Analytics</h1>
      <p>Generate and export reports on referrals, appointments, and case outcomes.</p>
    </div>

    <!-- Date Range Filter -->
    <div class="filter-bar" style="margin-bottom:20px">
      <div style="font-size:12px;color:var(--stone);font-weight:500">Date Range:</div>
      <input v-model="dateFrom" type="date" class="ifi" style="width:160px" />
      <span style="font-size:12px;color:var(--stone)">to</span>
      <input v-model="dateTo" type="date" class="ifi" style="width:160px" />
      <button class="ibtn ibtn-p ibtn-sm" @click="fetchAll">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Generate
      </button>
      <button class="ibtn ibtn-o ibtn-sm">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export PDF
      </button>
      <button class="ibtn ibtn-o ibtn-sm">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export Excel
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <!-- Summary Stats -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:20px">
        <div class="stat-card" v-for="stat in summaryStats" :key="stat.label">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" :style="{ background: stat.iconBg }">
              <svg viewBox="0 0 24 24" :style="{ color: stat.iconColor }" v-html="stat.icon"></svg>
            </div>
          </div>
          <div class="stat-num">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>

      <!-- Charts Row -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

        <!-- Referrals by Type -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Referrals by Type</span></div>
          <div class="icard-body">
            <div v-if="!referralData.by_type?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in referralData.by_type" :key="item.referral_type" style="margin-bottom:11px">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
                <span>{{ item.referral_type?.replace(/_/g,' ') }}</span>
                <span style="color:var(--stone)">{{ item.count }}</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, referralData.total) + '%', background: 'var(--moss)', height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Referrals by Urgency -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Referrals by Urgency</span></div>
          <div class="icard-body">
            <div v-if="!referralData.by_urgency?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in referralData.by_urgency" :key="item.urgency_level" style="margin-bottom:11px">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
                <span>{{ item.urgency_level }}</span>
                <span style="color:var(--stone)">{{ item.count }}</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, referralData.total) + '%', background: urgencyColor(item.urgency_level), height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

        <!-- Cases by Status -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Cases by Status</span></div>
          <div class="icard-body">
            <div v-if="!caseData.by_status?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in caseData.by_status" :key="item.status" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--cloud)">
              <span class="ibadge" :class="'ibadge-' + item.status">{{ item.status?.replace(/_/g,' ') }}</span>
              <span style="font-size:13px;font-weight:600;color:var(--ink)">{{ item.count }}</span>
            </div>
          </div>
        </div>

        <!-- Cases by Unit -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Cases by Unit</span></div>
          <div class="icard-body">
            <div v-if="!caseData.by_unit?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
            <div v-for="item in caseData.by_unit" :key="item.current_unit" style="margin-bottom:14px">
              <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
                <span class="ibadge" :class="'unit-' + item.current_unit?.toLowerCase()">{{ item.current_unit }}</span>
                <span style="color:var(--stone)">{{ item.count }} cases</span>
              </div>
              <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
                <div :style="{ width: pct(item.count, caseData.total) + '%', background: unitColor(item.current_unit), height: '100%', borderRadius: '4px' }"></div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Monthly Trend -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">Monthly Referral Trend</span></div>
        <div class="icard-body">
          <div v-if="!referralData.monthly_trend?.length" style="text-align:center;color:var(--fog);font-size:13px">No data</div>
          <div v-else style="display:flex;align-items:flex-end;gap:8px;height:120px">
            <div
              v-for="item in referralData.monthly_trend"
              :key="item.month + '-' + item.year"
              style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"
            >
              <div style="font-size:11px;color:var(--stone);font-weight:500">{{ item.count }}</div>
              <div
                :style="{
                  width: '100%',
                  height: (item.count / maxMonthlyCount * 100) + 'px',
                  background: 'var(--moss)',
                  borderRadius: '4px 4px 0 0',
                  minHeight: '4px',
                  transition: 'height .5s',
                }"
              ></div>
              <div style="font-size:10px;color:var(--fog)">{{ monthLabel(item.month) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Appointments Summary -->
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Appointment Summary</span></div>
        <div class="ts">
          <table class="itable">
            <thead>
              <tr>
                <th>Unit</th>
                <th>Total</th>
                <th>Confirmed</th>
                <th>Completed</th>
                <th>Cancelled</th>
                <th>No Show</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in apptSummary" :key="row.unit">
                <td><span class="ibadge" :class="'unit-' + row.unit.toLowerCase()">{{ row.unit }}</span></td>
                <td style="font-weight:600">{{ row.total }}</td>
                <td>{{ row.confirmed }}</td>
                <td>{{ row.completed }}</td>
                <td>{{ row.cancelled }}</td>
                <td>{{ row.no_show }}</td>
              </tr>
              <tr v-if="!apptSummary.length">
                <td colspan="6" style="text-align:center;color:var(--fog)">No data</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { reportAPI } from '../../api/index';

const loading  = ref(true);
const dateFrom = ref('');
const dateTo   = ref('');

const referralData = ref({});
const caseData     = ref({});
const apptData     = ref({});
const dashData     = ref({});

const summaryStats = computed(() => [
  { label: 'Total Referrals',    value: referralData.value.total    ?? 0, iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { label: 'Total Cases',        value: caseData.value.total        ?? 0, iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>' },
  { label: 'Closed Cases',       value: caseData.value.by_status?.find(s => s.status === 'closed')?.count ?? 0, iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<polyline points="20 6 9 17 4 12"/>' },
  { label: 'Total Appointments', value: apptData.value.total        ?? 0, iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)',  icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
  { label: 'TMDU Assessments',   value: caseData.value.referred_tmdu ?? 0, iconBg: 'var(--purple-lt)', iconColor: 'var(--purple)', icon: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>' },
]);

const maxMonthlyCount = computed(() => {
  const counts = referralData.value.monthly_trend?.map(m => m.count) || [1];
  return Math.max(...counts, 1);
});

const apptSummary = computed(() => {
  const byUnit = apptData.value.by_unit || [];
  const byStatus = apptData.value.by_status || [];
  return ['GCU', 'SDU', 'TMDU'].map(unit => {
    const unitData = byUnit.find(u => u.unit === unit);
    return {
      unit,
      total:     unitData?.count || 0,
      confirmed: byStatus.find(s => s.status === 'confirmed')?.count || 0,
      completed: byStatus.find(s => s.status === 'completed')?.count || 0,
      cancelled: byStatus.find(s => s.status === 'cancelled')?.count || 0,
      no_show:   byStatus.find(s => s.status === 'no_show')?.count || 0,
    };
  }).filter(r => r.total > 0);
});

async function fetchAll() {
  loading.value = true;
  try {
    const params = { date_from: dateFrom.value, date_to: dateTo.value };
    const [r, c, a, d] = await Promise.all([
      reportAPI.referrals(params),
      reportAPI.cases(params),
      reportAPI.appointments(params),
      reportAPI.dashboard(),
    ]);
    referralData.value = r.data;
    caseData.value     = c.data;
    apptData.value     = a.data;
    dashData.value     = d.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function pct(count, total) {
  if (!total) return 0;
  return Math.round(count / total * 100);
}

function monthLabel(month) {
  return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][month - 1] || month;
}

function urgencyColor(level) {
  return { critical: 'var(--red)', high: 'var(--amber)', medium: 'var(--blue)', low: 'var(--sage)' }[level] || 'var(--moss)';
}

function unitColor(unit) {
  return { GCU: 'var(--moss)', SDU: 'var(--amber)', TMDU: 'var(--purple)' }[unit] || 'var(--moss)';
}

onMounted(() => fetchAll());
</script>