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
              </div>
            </div>
            <div class="qacts">
              <button class="ibtn ibtn-p ibtn-sm" @click.stop="$router.push({ name: 'testing-show', params: { id: t.id } })">View</button>
            </div>
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

const route        = useRoute();
const filterStatus = ref('');
const loading      = ref(true);
const records      = ref([]);

const pendingCount = computed(() => records.value.filter(r => r.status === 'pending').length);

const stats = computed(() => [
  { label: 'Pending',     value: records.value.filter(r => r.status === 'pending').length, iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
  { label: 'In Progress', value: records.value.filter(r => ['scheduled', 'test_administered', 'awaiting_results'].includes(r.status)).length, iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { label: 'Completed',   value: records.value.filter(r => r.status === 'test_results_issued').length, iconBg: 'var(--mist)', iconColor: 'var(--moss)', icon: '<polyline points="20 6 9 17 4 12"/>' },
]);

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

async function fetchRecords() {
  loading.value = true;
  try {
    const res = await testingAPI.index({ status: filterStatus.value, student_id: route.query.student_id || undefined });
    records.value = res.data.data || res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
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
</script>