<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Incident Reports</h1>
      <p>One file per student, holding every complaint filed against them, its status, and the referrals SDU has made to GCU.</p>
    </div>

    <div class="filter-bar">
      <div class="sw" style="flex:1;min-width:220px;max-width:400px">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          maxlength="50"
          placeholder="Search student name or ID..."
          style="width:100%"
          @keypress="blockSpecialKeypress"
          @input="onSearchInput"
        />
      </div>
      <select v-model="filters.status" class="fsm" @change="fetchReports">
        <option value="">All Status</option>
        <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }} ({{ counts[s.value] ?? 0 }})</option>
      </select>
      <button v-if="filters.search || filters.status" class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
    </div>

    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="reports.length === 0" class="empty-state">
        <h3>No incident reports found</h3>
        <p>{{ filters.search || filters.status ? 'Try adjusting your search or filter.' : 'A student gets an Incident Report when a complaint is filed against them.' }}</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Student</th>
              <th>College</th>
              <th>Complaints</th>
              <th>Latest Complaint</th>
              <th>Referred to GCU</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reports" :key="r.student.id" style="cursor:pointer" @click="open(r)">
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="iav">{{ initials(r.student) }}</div>
                  <div>
                    <div style="font-weight:600;color:var(--ink)">{{ r.student.last_name }}, {{ r.student.first_name }} {{ r.student.middle_name }}</div>
                    <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ r.student.student_id }}</div>
                  </div>
                </div>
              </td>
              <td>{{ collegeShort(r.student.college) }}</td>
              <td><span class="ibadge" style="background:var(--cloud);color:var(--ink)">{{ r.complaints_count }}</span></td>
              <td>
                <div>{{ r.latest_violation }}</div>
                <div style="font-size:11px;color:var(--fog)">{{ formatDate(r.latest_complaint) }}</div>
              </td>
              <td>
                <span v-if="r.handoffs_count" class="ibadge ibadge-callslip">{{ r.handoffs_count }} referral{{ r.handoffs_count === 1 ? '' : 's' }}</span>
                <span v-else style="color:var(--fog);font-size:12px">Not yet</span>
              </td>
              <td><span class="ibadge" :class="'ir-' + r.status">{{ statusLabel(r.status) }}</span></td>
              <td style="text-align:right">
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="open(r)">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const router = useRouter();

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const STATUSES = [
  { value: 'pending',      label: 'Pending' },
  { value: 'under_review', label: 'Under Review' },
  { value: 'resolved',     label: 'Resolved' },
];
const statusLabel = s => STATUSES.find(x => x.value === s)?.label || s;

const reports = ref([]);
const counts  = ref({});
const loading = ref(false);
const filters = ref({ search: '', status: '' });
let searchTimeout = null;

async function fetchReports() {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.search) params.search = filters.value.search;
    if (filters.value.status) params.status = filters.value.status;
    const res = await axios.get(`${API_BASE}/incident-reports`, { ...authHeaders(), params });
    reports.value = res.data.data || [];
    // The per-status totals in the dropdown are for the whole list, not the filtered one.
    if (!filters.value.status) counts.value = res.data.counts || {};
  } catch (e) {
    console.error(e);
    reports.value = [];
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(fetchReports, 350);
}

function resetFilters() {
  filters.value = { search: '', status: '' };
  fetchReports();
}

function open(r) {
  router.push({ name: 'incident-report-show', params: { id: r.student.id } });
}

function initials(s) {
  return `${s?.first_name?.[0] || ''}${s?.last_name?.[0] || ''}`.toUpperCase() || '?';
}
// "College of Nursing (CN)" -> "CN"
function collegeShort(name) {
  return name ? (name.match(/\(([^)]+)\)\s*$/)?.[1] || name) : '-';
}
function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
}

onMounted(fetchReports);
</script>
