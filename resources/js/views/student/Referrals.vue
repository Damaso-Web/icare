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
          v-for="r in pagedReferrals"
          :key="r.id"
          :style="'padding:14px 18px;border-bottom:1px solid var(--cloud);cursor:pointer;' + (r.is_archived ? 'opacity: 0.6; background: var(--snow);' : '')"
          @click="$router.push({ name: 'student-referral-show', params: { id: r.id } })"
        >
          <div style="font-size:13.5px;font-weight:600;color:var(--ink);font-family:var(--mono)">{{ r.referral_code }}</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">{{ toTitleCase(r.referral_type) }} · {{ formatDateTime(r.created_at) }}</div>
          <span v-if="r.is_archived" class="ibadge" style="background:var(--cloud);color:var(--stone);margin-top:6px;display:inline-block">Archived</span>
          <span
            v-else
            class="ibadge"
            :class="'ibadge-' + badgeKind(r)"
            style="margin-top:6px;display:inline-block"
            :title="isExpiredPending(r) ? 'The 7-day window to pick an appointment time has passed. Contact GCU for a new scheduling link.' : toTitleCase(r.status)"
          >{{ badgeLabel(r) }}</span>          <span v-if="r.is_self_referred" class="ibadge" style="margin:6px 0 0 4px;display:inline-block;background:var(--blue-lt);color:var(--blue)">Self-referral</span>
        </div>
        <div v-if="totalPages > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
          <span style="font-size:12px;color:var(--stone)">Page {{ currentPage }} of {{ totalPages }}</span>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-o ibtn-sm" :disabled="currentPage === 1" @click="page = currentPage - 1">Prev</button>
            <button class="ibtn ibtn-o ibtn-sm" :disabled="currentPage === totalPages" @click="page = currentPage + 1">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { SELF_REFERRAL_ENABLED } from '../../utils/features';
import { REFERRAL_TYPES } from '../../constants/referralTypes';
import { ref, computed, onMounted, watch } from 'vue';
import { useAutoRefresh } from '../../utils/useAutoRefresh';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const referrals = ref([]);
// Appointments still waiting on the student to pick a time - used only to
// tell whether one of them has expired (see isExpiredPending below).
const pendingAppointments = ref([]);

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

// B#, 11/5/2026: this used to fold every non-terminal status (submitted,
// acknowledged, scheduled, in_progress) into a single simplified "Pending"
// badge here, while the referral's own detail page (ReferralShow.vue) shows
// the real, un-simplified status - so the same referral could read
// "Scheduled" on its detail page and "Pending" on this list, which looked
// exactly like a bug (and in the "stuck at Pending forever" case, often was
// one - see TestingRecordController::sendToGcu()). This list now shows the
// same real status as the detail page; only cancellation-request and
// expired-pending still override it, since those reflect something the
// bare status field doesn't capture on its own.
function isExpiredPending(referral) {
  if (['completed', 'closed', 'cancelled'].includes(referral.status)) return false;
  const now = new Date();
  return pendingAppointments.value.some(a => {
    const matches = a.referral_id === referral.id || (!a.referral_id && a.case_id === referral.case_id);
    return matches && a.token_expires_at && new Date(a.token_expires_at) < now;
  });
}

// B#, 11/4/2026: a referral with a pending cancellation request (see
// Sent Referrals' "Request Cancellation") shows that instead of its real
// status, even though the status field underneath hasn't changed yet -
// what's pending (a decision on the request) is what the student needs to
// see here.
function badgeKind(r) {
  if (r.void_requested_at) return 'cancellation_request';
  if (isExpiredPending(r)) return 'expired';
  return r.status;
}

function badgeLabel(r) {
  if (r.void_requested_at) return 'Cancellation Request';
  if (isExpiredPending(r)) return 'Expired';
  return toTitleCase(r.status);
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

// B#, 11/5/2026: client-side pagination, 10 per page - the list is already
// fetched in full off /student/dashboard, so no new API call is needed.
// `page` only moves on an explicit Prev/Next click or a filter change -
// NOT the 25s background poll, which reassigns `referrals` (a new array
// reference) even when nothing actually changed and would otherwise bounce
// the student back to page 1 while they're reading page 2. `currentPage`
// clamps display/slicing to whatever range still exists if the filtered
// set did shrink (e.g. a referral got archived out from under them).
const PER_PAGE = 10;
const page = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(filteredReferrals.value.length / PER_PAGE)));
const currentPage = computed(() => Math.min(page.value, totalPages.value));
const pagedReferrals = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE;
  return filteredReferrals.value.slice(start, start + PER_PAGE);
});
watch(filters, () => { page.value = 1; }, { deep: true });

function resetFilters() {
  filters.value = { status: '', type: '', date_from: '', date_to: '' };
}

async function fetchData(silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, authHeaders());
    referrals.value = res.data.referrals || [];
    pendingAppointments.value = res.data.pending_appointments || [];
  } catch (e) {
    if (!silent) console.error(e);
  } finally {
    if (!silent) loading.value = false;
  }
}

onMounted(() => fetchData());
// Keep the list current so a newly filed referral shows without a manual reload.
useAutoRefresh(() => fetchData(true), 25000);
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