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
      <select v-model="filters.type" class="fsm" @change="fetchCases">
        <option value="">All Types</option>
        <option value="counseling">Counseling</option>
        <option value="academic_coaching">Academic Coaching</option>
        <option value="admission_slip">Admission Slip</option>
        <option value="psychological_testing">Psychological Testing</option>
        <option value="disciplinary">Disciplinary</option>
        <option value="consultation">Consultation</option>
      </select>
      <select v-model="filters.requires_follow_up" class="fsm" @change="fetchCases">
        <option value="">All Cases</option>
        <option value="1">Needs Follow-up</option>
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
              <th>Case No.</th>
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
              <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
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
import { caseAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const cases      = ref([]);
const loading    = ref(true);
const pagination = ref({});
const filters    = ref({ search: '', status: '', unit: '', type: '', requires_follow_up: '' });

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
  filters.value = { search: '', status: '', unit: '', type: '', requires_follow_up: '' };
  fetchCases();
}

function changePage(page) { fetchCases(page); }

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => fetchCases());
</script>