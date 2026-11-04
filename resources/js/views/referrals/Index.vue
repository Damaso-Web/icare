<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <p>{{ filters.archived ? 'Archived referrals.' : 'Review, assign, and track incoming referrals.' }}</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="filters.search" type="text" class="sin" maxlength="50" placeholder="Search student name, ID, or referral code..." @keypress="blockSpecialKeypress" @input="onSearchInput" style="width:220px"/>
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <button
        class="ibtn ibtn-sm"
        :class="filters.archived ? 'ibtn-p' : 'ibtn-o'"
        @click="filters.archived = !filters.archived; filters.status = ''; fetchReferrals()"
      >
        {{ filters.archived ? 'Viewing Archived' : 'View Archived' }}
      </button>
      <!-- Dean: the college's referrals by program (the leader asked for programs only). -->
      <template v-if="isDean">
        <select v-model="filters.program" class="fsm" @change="fetchReferrals">
          <option value="">All Programs</option>
          <option v-for="pr in deanPrograms" :key="pr" :value="pr">{{ pr }}</option>
        </select>
      </template>
      <select v-model="filters.status" class="fsm" :disabled="filters.archived" :title="filters.archived ? 'Status does not apply to archived records.' : ''" @change="fetchReferrals">
        <option value="">All Status</option>
        <option value="submitted">Submitted</option>
        <option value="acknowledged">Acknowledged</option>
        <option value="scheduled">Scheduled</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="closed">Closed</option>
      </select>
      <select v-model="filters.sort" class="fsm" @change="fetchReferrals">
        <option value="desc">Date: Newest First</option>
        <option value="asc">Date: Oldest First</option>
      </select>
      <!-- Bug (10/29/2026): these two inputs sit one level deeper than every
           other filter here (inside this wrapper div, not a direct child of
           .filter-bar), so app.css's mobile rule that stretches every other
           filter to full width/stacks it never reached them - they stayed
           pinned at width:150px while the mobile stylesheet also bumps input
           font-size to 16px (prevents iOS zoom-on-tap), which left no room
           for the native date format to render at that width. Result: on a
           phone, the date fields looked blank/empty. Fixed below (scoped
           style) by letting this wrapper go full width and its two inputs
           share it evenly, same as every other filter on mobile. -->
      <div class="date-range-filter" style="display:flex;align-items:center;gap:6px">
        <input v-model="filters.date_from" type="date" class="ifi" style="width:150px" @change="fetchReferrals" />
        <span style="color:var(--stone);font-size:13px">-</span>
        <input v-model="filters.date_to" type="date" class="ifi" style="width:150px" @change="fetchReferrals" />
      </div>
    </div>

    <!-- Referral List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>

      <div v-else-if="referrals.length === 0" class="empty-state">
        <h3>No referrals found</h3>
        <p>Try adjusting your filters.</p>
      </div>

      <div v-else>
        <div
          v-for="r in referrals"
          :key="r.id"
          class="qr"
          :style="r.is_archived ? 'opacity: 0.6; background: var(--snow);' : ''"
          @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
        >
          <div class="qav">{{ initials(r.student?.first_name, r.student?.last_name) }}</div>
          <div class="qi">
            <div class="qn">
              {{ r.student?.last_name }}, {{ r.student?.first_name }} {{ r.student?.middle_name }}
              <span class="qid">{{ r.student?.student_id }}</span>
            </div>
            <div class="qtags">
              <span v-if="r.is_archived" class="ibadge" style="background:var(--cloud);color:var(--stone)">Archived</span>
              <span v-else class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span>
            </div>
            <div class="qmeta">
              {{ toTitleCase(r.referral_type) }} · {{ formatDateTime(r.created_at) }} · {{ r.referral_code }}
            </div>
          </div>
          <div class="qacts">
            <button
              class="ibtn ibtn-p ibtn-sm"
              :title="r.is_archived ? 'Archived: opens view-only.' : ''"
              @click.stop="$router.push({ name: 'referral-show', params: { id: r.id } })"
            >
              View
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">
          Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
        </span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAutoRefresh } from '../../utils/useAutoRefresh';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { referralAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress, toTitleCase } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';
import { matchCollege } from '../../utils/colleges';

function initials(first, last) {
  return `${first?.[0] || ''}${last?.[0] || ''}`.toUpperCase() || '?';
}

const route = useRoute();
const referrals  = ref([]);
const loading    = ref(true);
const pagination = ref({});
// Bug (10/25/2026): the "All Units" option (and SDU) were removed from this
// filter - this page always showed GCU or TMDU referrals, never a mixed
// unfiltered list, and never SDU's (disciplinary referrals live elsewhere).
// B#, 11/4/2026: the GCU/TMDU dropdown itself is removed per request - GCU
// and TMDU-pending referrals now show together with no toggle needed (SDU's
// Acts of Misconduct still never surface here; see
// ReferralController::index()'s unconditional exclusion).
// B#, 11/5/2026: the "All Services" (type) filter is removed too, per
// request - `type` is dropped from this object entirely, and
// fetchReferrals() spreads this whole object into the request, so it's
// simply never sent anymore (ReferralController::index() still supports a
// `type` param for any other caller, untouched).
const filters = ref({ search: '', status: '', violation_type: '', sort: 'desc', date_from: '', date_to: '', archived: false, department: '', program: '' });

// Dean: the programs of their own college (Management > Programs).
const auth = useAuthStore();
const isDean = computed(() => ['dean', 'dean_secretary'].includes(auth.user?.role));
const deanPrograms = ref([]);
async function fetchDeanOptions() {
  if (!isDean.value) return;
  try {
    const [c, pr] = await Promise.all(['colleges', 'programs'].map(x => axios.get(`${API_BASE}/management/${x}`, authHeaders())));
    const official = matchCollege(auth.user?.college, c.data.map(x => x.name));
    const college = c.data.find(x => x.name === official);
    if (!college) return;
    deanPrograms.value = pr.data.filter(x => x.college_id === college.id).map(x => x.name).sort();
  } catch (e) { /* the filters just stay empty */ }
}

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  fetchReferrals();
}

async function fetchReferrals(page = 1, silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await referralAPI.index({ ...filters.value, archived: filters.value.archived ? 1 : 0, page });
    referrals.value  = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    if (!silent) console.error(e);
  } finally {
    if (!silent) loading.value = false;
  }
}

function resetFilters() {
  filters.value = { search: '', status: '', violation_type: '', sort: 'desc', date_from: '', date_to: '', archived: false, department: '', program: '' };
  fetchReferrals();
}

function changePage(page) { fetchReferrals(page); }

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

function formatDateTime(date) {
  if (!date) return '-';
  const d = new Date(date);
  return `${d.toLocaleDateString()} ${d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}`;
}

onMounted(() => {
  // Arriving from a Dashboard card (?status=submitted) shows exactly what the card counted.
  if (typeof route.query.status === 'string') filters.value.status = route.query.status;
  fetchReferrals();
  fetchDeanOptions();
});

// Keep the queue current so new referrals appear without a manual reload.
useAutoRefresh(() => fetchReferrals(pagination.value?.current_page || 1, true), 25000);
</script>

<style scoped>
/* Bug fix (10/29/2026): see the comment above .date-range-filter in the
   template - on mobile, give this wrapper the same full-width stacking
   every other filter gets, and let its two date inputs share that width
   evenly instead of staying pinned at 150px each. */
@media (max-width: 860px) {
  .date-range-filter { flex: 1 1 100%; width: 100%; }
  .date-range-filter > .ifi { flex: 1 1 0; width: auto !important; min-width: 0; }
}
</style>