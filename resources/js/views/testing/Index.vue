<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Testing Records</h1>
      <p>Psychological testing queue and assessment records managed by TMDU.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <select v-model="filterStatus" class="fsm" @change="fetchRecords">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="scheduled">Scheduled for Testing</option>
        <option value="test_administered">Test Administered</option>
        <option value="awaiting_results">Awaiting Results</option>
        <option value="test_results_issued">Results Released</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px">

      <!-- Testing Queue -->
      <div class="icard">
        <div class="icard-header">
          <span class="icard-title">Testing Queue</span>
          <span class="ibadge ibadge-pending">{{ pendingCount }} pending</span>
        </div>
        <div v-if="loading" style="text-align:center;padding:44px">
          <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
        </div>
        <div v-else-if="records.length === 0" class="empty-state">
          <h3>No testing records found</h3>
          <p>No records match your current filters.</p>
        </div>
        <div v-else>
          <div
            v-for="t in records"
            :key="t.id"
            class="qr"
            @click="$router.push({ name: 'testing-show', params: { id: t.id } })"
          >
            <div class="qav">{{ initials(t.student?.first_name, t.student?.last_name) }}</div>
            <div class="qi">
              <div class="qn">
                {{ t.student?.first_name }} {{ t.student?.last_name }}
                <span class="qid">{{ t.student?.student_id }}</span>
              </div>
              <div class="qmeta">
                Referred by {{ t.referred_by?.name }} · {{ formatDate(t.created_at) }}
              </div>
              <div v-if="t.tests_administered?.length" style="font-size:12px;color:var(--slate);margin-top:4px">
                Tests: {{ t.tests_administered.join(', ') }}
              </div>
              <div v-if="t.assigned_tester_user_id" style="font-size:12px;color:var(--stone);margin-top:2px">
                Tester: {{ t.tester?.name || '-' }}
              </div>
              <div class="qtags">
                <span class="ibadge" :class="statusBadge(t.status)">{{ statusLabel(t.status) }}</span>
                <span class="ibadge unit-tmdu">TMDU</span>
                <span v-if="reassignedAwayFromMe(t)" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">Reassigned</span>
              </div>
            </div>
            <div class="qacts">
              <button class="ibtn ibtn-p ibtn-sm" @click.stop="$router.push({ name: 'testing-show', params: { id: t.id } })">View</button>
            </div>
          </div>
        </div>
        <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:12px;color:var(--stone)">
            Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
          </span>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="fetchRecords(false, pagination.current_page - 1)">Prev</button>
            <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="fetchRecords(false, pagination.current_page + 1)">Next</button>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="stat-card" v-for="stat in stats" :key="stat.label">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" :style="{ background: stat.iconBg }">
              <svg viewBox="0 0 24 24" :style="{ color: stat.iconColor }" v-html="stat.icon"></svg>
            </div>
          </div>
          <div class="stat-num">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>

    </div>


  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { testingAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';
import { useAutoRefresh } from '../../utils/useAutoRefresh';

const route        = useRoute();
const auth         = useAuthStore();
// B559: this record was taken off the signed-in tester and given to someone
// else - still shown here (see TestingRecordController::index()'s scoping)
// but flagged so it reads as read-only history, not an active item to work.
function reassignedAwayFromMe(t) {
  return !!t.previous_tester_user_id && t.previous_tester_user_id === auth.user?.id && t.assigned_tester_user_id !== auth.user?.id;
}
const filterStatus = ref('');
const loading      = ref(true);
const records      = ref([]);
const pagination   = ref({});
// B#, 11/5/2026: these tiles used to be counted off `records` directly,
// which only ever held one page - harmless at the old silent page-1-of-20,
// wrong once this list is properly paginated at 5. Counted server-side now
// (see TestingRecordController::index()'s status_counts) off every
// matching record, not just the current page.
const statusCounts = ref({});

const pendingCount = computed(() => statusCounts.value.pending || 0);

const stats = computed(() => {
  const c = statusCounts.value;
  const inProgress = (c.scheduled || 0) + (c.test_administered || 0) + (c.awaiting_results || 0);
  return [
    { label: 'Pending',     value: c.pending || 0, iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
    { label: 'In Progress', value: inProgress, iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
    { label: 'Completed',   value: c.test_results_issued || 0, iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<polyline points="20 6 9 17 4 12"/>' },
  ];
});

// Retains legacy status values (fee_form_pending/or_submitted/par_scheduled)
// in the mapping only so an older record that still carries one displays
// sensibly - the current pipeline never sets these anymore.
function statusBadge(status) {
  return {
    pending:             'ibadge-pending',
    fee_form_pending:    'ibadge-pending',
    or_submitted:        'ibadge-scheduled',
    scheduled:           'ibadge-scheduled',
    in_progress:         'ibadge-in_progress',
    test_administered:   'ibadge-completed',
    awaiting_results:    'ibadge-scheduled',
    par_scheduled:       'ibadge-scheduled',
    test_results_issued: 'ibadge-closed',
  }[status] || 'ibadge-pending';
}

// Display labels for the 5-step pipeline. Legacy values map to their
// closest current equivalent so an older record still reads sensibly.
function statusLabel(status) {
  return {
    pending:             'Pending',
    fee_form_pending:    'Pending',
    or_submitted:        'Pending',
    scheduled:           'Scheduled for Testing',
    in_progress:         'Scheduled for Testing',
    test_administered:   'Test Administered',
    par_scheduled:       'Awaiting Results',
    awaiting_results:    'Awaiting Results',
    test_results_issued: 'Results Released',
  }[status] || toTitleCase(status);
}

async function fetchRecords(silent = false, page = 1) {
  if (!silent) loading.value = true;
  try {
    const res = await testingAPI.index({
      status: filterStatus.value, student_id: route.query.student_id || undefined,
      per_page: 5, page,
    });
    records.value = res.data.data || res.data;
    pagination.value = res.data;
    statusCounts.value = res.data.status_counts || {};
  } catch (e) {
    if (!silent) console.error(e);
  } finally {
    if (!silent) loading.value = false;
  }
}

function resetFilters() {
  filterStatus.value = '';
  fetchRecords();
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => {
  fetchRecords();
});
// Keeps the testing queue current without a manual reload. Stays on
// whatever page is currently open instead of silently jumping back to 1.
useAutoRefresh(() => fetchRecords(true, pagination.value?.current_page || 1), 25000);
</script>