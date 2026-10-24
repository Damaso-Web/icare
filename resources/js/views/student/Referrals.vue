<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Referrals</h1>
      <p>View the status of referrals submitted on your behalf, or ask GCU for help yourself.</p>
    </div>
    <button v-if="SELF_REFERRAL_ENABLED" class="ibtn ibtn-p" style="margin-bottom:14px" @click="$router.push({ name: 'student-refer' })">+ Refer Myself</button>

    <!-- B543: filter by status, type and a date range; filters combine and can be reset. -->
    <div class="filter-bar">
      <select v-model="filters.status" class="fsm">
        <option value="">All Status</option>
        <option v-for="s in statusOptions" :key="s" :value="s">{{ toTitleCase(s) }}</option>
      </select>
      <select v-model="filters.type" class="fsm">
        <option value="">All Types</option>
        <option v-for="t in typeOptions" :key="t" :value="t">{{ toTitleCase(t) }}</option>
      </select>
      <div class="date-range-filter" style="display:flex;align-items:center;gap:6px">
        <!-- B574: on mobile the two date inputs had only an aria-label (not
             visible), so the filter's purpose wasn't clear until you tapped
             into it - this label is shown only below the 860px breakpoint
             (see <style> below), leaving the desktop layout untouched. -->
        <span class="date-range-filter-label">Date Range</span>
        <input v-model="filters.date_from" type="date" class="ifi" style="width:150px" :max="filters.date_to || undefined" aria-label="From date" />
        <span style="color:var(--stone);font-size:13px">-</span>
        <input v-model="filters.date_to" type="date" class="ifi" style="width:150px" :min="filters.date_from || undefined" aria-label="To date" />
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
    </div>

    <div class="icard">
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="referrals.length === 0" class="empty-state">
        <h3>No referrals yet</h3>
      </div>
      <div v-else-if="filteredReferrals.length === 0" class="empty-state">
        <h3>No referrals match your filters</h3>
        <p>Try adjusting or resetting the filters above.</p>
      </div>
      <div v-else>
        <div
          v-for="r in filteredReferrals"
          :key="r.id"
          style="padding:14px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
          @click="$router.push({ name: 'student-referral-show', params: { id: r.id } })"
        >
          <div style="font-size:13.5px;font-weight:600;color:var(--ink);font-family:var(--mono)">{{ r.referral_code }}</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">{{ toTitleCase(r.referral_type) }} · {{ formatDateTime(r.created_at) }}</div>
          <span class="ibadge" :class="'ibadge-' + simplifiedStatus(r.status)" style="margin-top:6px;display:inline-block" :title="toTitleCase(r.status)">{{ simplifiedStatus(r.status) === 'closed' ? 'Closed' : 'Pending' }}</span>
          <span v-if="r.is_self_referred" class="ibadge" style="margin:6px 0 0 4px;display:inline-block;background:var(--blue-lt);color:var(--blue)">Self-referral</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { SELF_REFERRAL_ENABLED } from '../../utils/features';
import { REFERRAL_TYPES } from '../../constants/referralTypes';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const referrals = ref([]);

// B543: Status options are built from the referrals actually on file, so
// every stage a student's own referrals might be in is always filterable.
// B551: Type options are NOT built that way - limiting "All Types" to
// whatever types happen to already be on file meant a student with, say,
// only Counseling referrals never saw the other types as filter options at
// all. See REFERRAL_TYPES below.
const filters = ref({ status: '', type: '', date_from: '', date_to: '' });

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/\b(Sdu|Gcu|Tmdu|Oss|Sif|Par)\b/g, m => m.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

// Students see a simplified Pending/Closed badge instead of the internal
// workflow stage (submitted, acknowledged, scheduled, in_progress,
// completed, closed) - only "closed" reads as Closed, everything else is
// still in progress from the student's point of view. The Status filter
// above keeps the real, granular stages so staff-reported stages are still
// filterable; only the badge text/color is simplified here.
function simplifiedStatus(status) {
  return status === 'closed' ? 'closed' : 'pending';
}

function formatDateTime(date) {
  if (!date) return '-';
  const d = new Date(date);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
}

const statusOptions = computed(() => {
  const order = ['submitted', 'acknowledged', 'scheduled', 'in_progress', 'completed', 'closed'];
  const present = new Set(referrals.value.map(r => r.status).filter(Boolean));
  return order.filter(s => present.has(s));
});

// B551: start from every referral type the system can produce, not just the
// ones that happen to be on this student's record already, so "All Types"
// genuinely covers every category, and a type added to REFERRAL_TYPES later
// shows up here with no further change needed. Anything present on a
// referral but missing from that list (an older/legacy value) is appended
// too, so nothing a student actually has drops off the filter either.
const typeOptions = computed(() => {
  const present = [...new Set(referrals.value.map(r => r.referral_type).filter(Boolean))];
  const extra = present.filter(t => !REFERRAL_TYPES.includes(t)).sort();
  return [...REFERRAL_TYPES, ...extra];
});

const filteredReferrals = computed(() => {
  return referrals.value.filter(r => {
    if (filters.value.status && r.status !== filters.value.status) return false;
    if (filters.value.type && r.referral_type !== filters.value.type) return false;
    if (filters.value.date_from && (!r.created_at || r.created_at < filters.value.date_from)) return false;
    if (filters.value.date_to && (!r.created_at || r.created_at.slice(0, 10) > filters.value.date_to)) return false;
    return true;
  });
});

function resetFilters() {
  filters.value = { status: '', type: '', date_from: '', date_to: '' };
}

async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, authHeaders());
    referrals.value = res.data.referrals || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchData());
</script>

<style scoped>
/* B574: visible only on mobile - the desktop layout already reads fine
   without it, since "-" between two date boxes beside Status/Type filters
   is clear in that wider context. */
.date-range-filter-label { display: none; }
@media (max-width: 860px) {
  .date-range-filter { flex-wrap: wrap; }
  .date-range-filter-label { display: block; width: 100%; font-size: 11.5px; font-weight: 600; color: var(--stone); text-transform: uppercase; letter-spacing: .3px; margin-bottom: 2px; }
}
</style>