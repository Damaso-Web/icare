<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Information Files</h1>
      <p>Each student's complete record in one place.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          maxlength="50"
          placeholder="Search student name or case number..."
          style="width:240px"
          @keypress="blockSpecialKeypress"
          @input="onSearchInput"
        />
      </div>
      <select v-model="filters.status" class="fsm" @change="fetchCases">
        <option value="">All Status</option>
        <option value="open">Open</option>
        <option value="on_observation">On Observation</option>
        <option value="resolved">Resolved</option>
      </select>
      <select v-model="filters.unit" class="fsm" @change="fetchCases">
        <option value="">All Units</option>
        <option value="GCU">GCU</option>
        <option value="SDU">SDU</option>
        <option value="TMDU">TMDU</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
    </div>

    <!-- Cases List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="cases.length === 0" class="empty-state">
        <h3>No cases found</h3>
        <p>Try adjusting your filters.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Ctrl No.</th>
              <th>Student Name</th>
              <th>Date Submitted</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in cases"
              :key="c.id"
              style="cursor:pointer"
              @click="$router.push({ name: 'student-show', params: { id: c.student?.id }, query: { ctx: 'cases' } })"
            >
              <!-- The Ctrl No. is the case's name; cases whose student has no client no. yet show the old number -->
              <td>
                <span v-if="ctrlNo(c)" style="font-family:var(--mono);font-size:13px;font-weight:700;color:var(--forest)">{{ ctrlNo(c) }}</span>
                <span v-else style="font-family:var(--mono);font-size:11px;color:var(--stone)" title="No Ctrl No. yet - open the file to set the client number">{{ c.case_number }}</span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="iav">{{ initials(c.student?.first_name, c.student?.last_name) }}</div>
                  <div style="font-weight:600;color:var(--ink)">{{ c.student?.last_name }}, {{ c.student?.first_name }} {{ c.student?.middle_name }}</div>
                </div>
              </td>
              <!-- opened_date is stamped with today() at the moment the founding
                   referral is submitted (ReferralController@store), so it IS the
                   case's date submitted - no backend change needed for the rename. -->
              <td style="font-size:12px">{{ formatDate(c.opened_date) }}</td>
              <td>
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'student-show', params: { id: c.student?.id }, query: { ctx: 'cases' } })">View</button>
              </td>
            </tr>
          </tbody>
        </table>
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
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { caseAPI, studentAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const route = useRoute();

const cases      = ref([]);

// Ctrl No. = "<year>-<term>" (set in Management for QF-OSS-GCU-01) + the student's client no.
const ctrlPrefix = ref('');
function ctrlNo(c) {
  const n = c.student?.sif_client_no;
  return n && ctrlPrefix.value ? `${ctrlPrefix.value}-${String(n).padStart(4, '0')}` : '';
}
const loading    = ref(true);
const pagination = ref({});
const filters    = ref({ search: '', status: '', unit: '' });

async function fetchCases(page = 1) {
  loading.value = true;
  try {
    const res = await caseAPI.index({ ...filters.value, page });
    cases.value      = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  fetchCases();
}

function resetFilters() {
  filters.value = { search: '', status: '', unit: '' };
  fetchCases();
}

function changePage(page) { fetchCases(page); }

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => {
  studentAPI.documentSettings('QF-OSS-GCU-01').then(res => { ctrlPrefix.value = res.data?.ctrl_no || ''; }).catch(() => {});
  // Arriving from a Dashboard stat card (?status=open) pre-filters the list
  // to match what the card said, instead of dumping the user on an
  // unfiltered "All Status" view.
  if (route.query.status && typeof route.query.status === 'string') {
    filters.value.status = route.query.status;
  }
  fetchCases();
});
</script>