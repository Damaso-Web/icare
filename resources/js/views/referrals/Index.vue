<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Referral Queue</h1>
      <p>Review, assign, and track incoming referrals.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="filters.search" type="text" class="sin" placeholder="Search student name or ID..." @keypress="blockSpecialKeypress" @input="onSearchInput" style="width:220px"/>
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <input v-model="filters.date_from" type="date" class="ifi" style="width:150px" @change="fetchReferrals" />
        <span style="color:var(--stone);font-size:13px">–</span>
        <input v-model="filters.date_to" type="date" class="ifi" style="width:150px" @change="fetchReferrals" />
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
      <select v-model="filters.status" class="fsm" @change="fetchReferrals">
        <option value="">All Status</option>
        <option value="submitted">Submitted</option>
        <option value="acknowledged">Acknowledged</option>
        <option value="in_review">In Review</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="closed">Closed</option>
      </select>
      <select v-model="filters.unit" class="fsm" @change="onUnitChange">
        <option value="">All Units</option>
        <option value="GCU">GCU</option>
        <option value="SDU">SDU</option>
        <option value="TMDU">TMDU</option>
      </select>
      <select v-model="filters.type" class="fsm" @change="onServiceFilterChange" v-if="filters.unit !== 'SDU'">
      <option value="">All Services</option>
      <option v-for="svc in availableServices" :key="svc.value" :value="svc.value">{{ svc.label }}</option>
    </select>
    <select v-model="filters.violation_type" class="fsm" @change="fetchReferrals" v-else>
      <option value="">All Acts of Misconduct</option>
      <option v-for="v in SERVICES_BY_UNIT.SDU" :key="v.violation" :value="v.violation">{{ v.violation }}</option>
    </select>
      <select v-model="filters.sort" class="fsm" @change="fetchReferrals">
        <option value="desc">Date: Newest First</option>
        <option value="asc">Date: Oldest First</option>
      </select>
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
          :class="urgencyRow(r.urgency_level)"
          @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
        >
          <div class="qav">{{ r.referral_code?.split('-').pop() }}</div>
          <div class="qi">
            <div class="qn" style="font-size:16px;font-weight:700;font-family:var(--mono)">
              {{ r.referral_code }}
            </div>
            <div class="qmeta">
              {{ toTitleCase(r.referral_type) }} · {{ formatDate(r.created_at) }}
            </div>
            <div class="qtags">
              <span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span>
            </div>
          </div>
          <div class="qacts">
            <button class="ibtn ibtn-p ibtn-sm" @click.stop="$router.push({ name: 'referral-show', params: { id: r.id } })">
              View
            </button>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">
          Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
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
import { referralAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress, toTitleCase } from '../../utils/validators';

const referrals  = ref([]);
const loading    = ref(true);
const pagination = ref({});
const filters = ref({ search: '', status: '', unit: '', type: '', violation_type: '', sort: 'desc', date_from: '', date_to: '' });

const SERVICES_BY_UNIT = {
  GCU: [
    { value: 'class_attendance',    label: 'Class Attendance' },
    { value: 'counseling',          label: 'Counseling' },
    { value: 'academic_deficiency', label: 'Academic Deficiency' },
    { value: 'leave_of_absence',    label: 'Leave of Absence' },
    { value: 'withdrawal',          label: 'Withdrawal' },
    { value: 'readmission',         label: 'Readmission' },
    { value: 'shifting',            label: 'Shifting' },
  ],
  TMDU: [
    { value: 'psychological_testing', label: 'Psychological Testing' },
  ],
  SDU: [
    { violation: 'Intellectual Dishonesty' },
    { violation: 'Fraud' },
    { violation: 'Harm to Persons' },
    { violation: 'Damage to Property' },
    { violation: 'Unauthorized Possession/Use of Dangerous Objects' },
    { violation: 'Unauthorized Possession/Use of Prohibited Drugs' },
    { violation: 'Undermining or Obstructing Investigations' },
    { violation: 'Violation of IT Resources Policies' },
    { violation: 'Stealing within University Premises' },
    { violation: 'Preparing or Disseminating Libelous/Subversive Materials' },
    { violation: 'Committing Sexual Acts within University Premises' },
    { violation: 'Instigating or Leading Boycotts/Disruption of Classes' },
    { violation: 'Drinking Alcoholic Beverages or Drunken Behavior' },
    { violation: 'Smoking' },
    { violation: 'Gambling within University Premises' },
    { violation: 'Violation of Municipal/Provincial Ordinance' },
    { violation: 'Non-wearing of Valid School I.D.' },
    { violation: 'Unauthorized Use of Borrowed or Stolen I.D.' },
    { violation: 'Loitering During Curfew Hours' },
    { violation: 'Failure to Obtain Permit for Facility Use' },
    { violation: 'Unauthorized Use of University Name' },
    { violation: 'Unauthorized Posting/Distributing of Notices' },
    { violation: 'Possessing/Distributing Immoral, Indecent, or Subversive Literature' },
    { violation: 'Littering' },
    { violation: 'Spitting' },
    { violation: 'Violating Legally Posted Instructions or Signage' },
    { violation: 'Disobeying Lawful Written Orders' },
    { violation: 'Appropriating Property of Another (Student Organization)' },
    { violation: 'Other Form of Misconduct' },
  ],
};

const ALL_SERVICES = [
  ...SERVICES_BY_UNIT.GCU,
  ...SERVICES_BY_UNIT.SDU,
  ...SERVICES_BY_UNIT.TMDU,
];

const availableServices = computed(() => {
  if (filters.value.unit === 'GCU') return SERVICES_BY_UNIT.GCU;
  if (filters.value.unit === 'TMDU') return SERVICES_BY_UNIT.TMDU;
  return [...SERVICES_BY_UNIT.GCU, ...SERVICES_BY_UNIT.TMDU];
});

function onUnitChange() {
  filters.value.type = '';
  filters.value.violation_type = '';
  fetchReferrals();
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  fetchReferrals();
}

async function fetchReferrals(page = 1) {
  loading.value = true;
  try {
    const res = await referralAPI.index({ ...filters.value, page });
    referrals.value  = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value = { search: '', status: '', unit: '', type: '', violation_type: '', sort: 'desc', date_from: '', date_to: '' };
  fetchReferrals();
}

function changePage(page) { fetchReferrals(page); }

function urgencyRow(level) {
  return { uh: level === 'high' || level === 'critical', um: level === 'medium', ul: level === 'low' };
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

onMounted(() => fetchReferrals());
</script>