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
      <button class="ibtn ibtn-p ibtn-sm">
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
          <div v-for="item in referralsByType" :key="item.label" style="margin-bottom:11px">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
              <span>{{ item.label }}</span>
              <span style="color:var(--stone)">{{ item.count }} ({{ item.pct }}%)</span>
            </div>
            <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
              <div :style="{ width: item.pct + '%', background: item.color, height: '100%', borderRadius: '4px', transition: 'width .7s' }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Referrals by Urgency -->
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Referrals by Urgency</span></div>
        <div class="icard-body">
          <div v-for="item in referralsByUrgency" :key="item.label" style="margin-bottom:11px">
            <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--slate);margin-bottom:5px">
              <span>{{ item.label }}</span>
              <span style="color:var(--stone)">{{ item.count }} ({{ item.pct }}%)</span>
            </div>
            <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
              <div :style="{ width: item.pct + '%', background: item.color, height: '100%', borderRadius: '4px', transition: 'width .7s' }"></div>
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
          <div v-for="item in casesByStatus" :key="item.label" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--cloud)">
            <span class="ibadge" :class="'ibadge-' + item.key">{{ item.label }}</span>
            <span style="font-size:13px;font-weight:600;color:var(--ink)">{{ item.count }}</span>
          </div>
        </div>
      </div>

      <!-- Cases by Unit -->
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Cases by Unit</span></div>
        <div class="icard-body">
          <div v-for="item in casesByUnit" :key="item.unit" style="margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px">
              <span class="ibadge" :class="'unit-' + item.unit.toLowerCase()">{{ item.unit }}</span>
              <span style="color:var(--stone)">{{ item.count }} cases</span>
            </div>
            <div style="background:var(--cloud);border-radius:4px;height:7px;overflow:hidden">
              <div :style="{ width: item.pct + '%', background: item.color, height: '100%', borderRadius: '4px' }"></div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Monthly Trend -->
    <div class="icard" style="margin-bottom:16px">
      <div class="icard-header"><span class="icard-title">Monthly Referral Trend</span></div>
      <div class="icard-body">
        <div style="display:flex;align-items:flex-end;gap:8px;height:120px">
          <div
            v-for="item in monthlyTrend"
            :key="item.month"
            style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px"
          >
            <div style="font-size:11px;color:var(--stone);font-weight:500">{{ item.count }}</div>
            <div
              :style="{
                width: '100%',
                height: (item.count / maxCount * 100) + 'px',
                background: 'var(--moss)',
                borderRadius: '4px 4px 0 0',
                minHeight: '4px',
                transition: 'height .5s',
              }"
            ></div>
            <div style="font-size:10px;color:var(--fog)">{{ item.month }}</div>
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
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const dateFrom = ref('');
const dateTo   = ref('');

const summaryStats = [
  { label: 'Total Referrals',   value: 42, iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { label: 'Total Cases',       value: 38, iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>' },
  { label: 'Closed Cases',      value: 14, iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="20 6 9 17 4 12"/>' },
  { label: 'Total Appointments',value: 56, iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)',  icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
  { label: 'TMDU Assessments',  value: 8,  iconBg: 'var(--purple-lt)',iconColor: 'var(--purple)', icon: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>' },
];

const referralsByType = [
  { label: 'Counseling',            count: 18, pct: 43, color: 'var(--moss)' },
  { label: 'Psychological Testing', count: 8,  pct: 19, color: 'var(--purple)' },
  { label: 'Disciplinary',          count: 7,  pct: 17, color: 'var(--amber)' },
  { label: 'Academic Coaching',     count: 5,  pct: 12, color: 'var(--blue)' },
  { label: 'Admission Slip',        count: 3,  pct: 7,  color: 'var(--sage)' },
  { label: 'Consultation',          count: 1,  pct: 2,  color: 'var(--fog)' },
];

const referralsByUrgency = [
  { label: 'Critical', count: 3,  pct: 7,  color: 'var(--red)' },
  { label: 'High',     count: 14, pct: 33, color: 'var(--amber)' },
  { label: 'Medium',   count: 18, pct: 43, color: 'var(--blue)' },
  { label: 'Low',      count: 7,  pct: 17, color: 'var(--sage)' },
];

const casesByStatus = [
  { key: 'open',             label: 'Open',             count: 8  },
  { key: 'in_progress',      label: 'In Progress',      count: 12 },
  { key: 'awaiting_testing', label: 'Awaiting Testing', count: 4  },
  { key: 'on_hold',          label: 'On Hold',          count: 2  },
  { key: 'resolved',         label: 'Resolved',         count: 6  },
  { key: 'closed',           label: 'Closed',           count: 14 },
];

const casesByUnit = [
  { unit: 'GCU',  count: 22, pct: 58, color: 'var(--moss)' },
  { unit: 'SDU',  count: 10, pct: 26, color: 'var(--amber)' },
  { unit: 'TMDU', count: 6,  pct: 16, color: 'var(--purple)' },
];

const monthlyTrend = [
  { month: 'Feb', count: 3  },
  { month: 'Mar', count: 5  },
  { month: 'Apr', count: 7  },
  { month: 'May', count: 6  },
  { month: 'Jun', count: 9  },
  { month: 'Jul', count: 12 },
];

const maxCount = computed(() => Math.max(...monthlyTrend.map(m => m.count)));

const apptSummary = [
  { unit: 'GCU',  total: 32, confirmed: 8, completed: 20, cancelled: 3, no_show: 1 },
  { unit: 'SDU',  total: 14, confirmed: 3, completed: 9,  cancelled: 2, no_show: 0 },
  { unit: 'TMDU', total: 10, confirmed: 4, completed: 5,  cancelled: 1, no_show: 0 },
];
</script>