<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Complaints</h1>
      <p>Incident reports filed against students.</p>
    </div>

    <div class="filter-bar">
      <div class="sw" style="flex:1;max-width:400px">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          placeholder="Search complainee name or student ID..."
          style="width:100%"
          @keypress="blockSpecialKeypress"
          @input="onSearchInput"
        />
      </div>
      <select v-model="filters.status" class="fsm" @change="fetchComplaints">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="under_review">Under Review</option>
        <option value="resolved">Resolved</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
    </div>

    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="complaints.length === 0" class="empty-state">
        <h3>No complaints found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
      <div class="ts" v-else>
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Complainee</th>
              <th>Act of Misconduct</th>
              <th>Filed By</th>
              <th>Date Filed</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in complaints" :key="c.id">
              <td>{{ c.complaint_code }}</td>
              <td>{{ c.complainee?.last_name }}, {{ c.complainee?.first_name }}</td>
              <td>{{ c.violation_type }}</td>
              <td>{{ c.filed_by?.name || c.filed_by?.first_name }}</td>
              <td>{{ formatDate(c.created_at) }}</td>
              <td>
                <span :style="statusStyle(c.status)" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600">{{ statusLabel(c.status) }}</span>
              </td>
              <td>
                <button class="ibtn ibtn-o ibtn-sm" @click="openDetail(c)">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Detail / Status Modal -->
    <div v-if="showDetail" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showDetail = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ activeComplaint?.complaint_code }}</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showDetail = false">&#10005;</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px" v-if="activeComplaint">
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><strong>Complainee:</strong> {{ activeComplaint.complainee?.last_name }}, {{ activeComplaint.complainee?.first_name }} ({{ activeComplaint.complainee?.student_id }})</div>
            <div><strong>Act of Misconduct:</strong> {{ activeComplaint.violation_type }}</div>
            <div><strong>Date of Incident:</strong> {{ activeComplaint.incident_date }}</div>
            <div><strong>Filed By:</strong> {{ activeComplaint.filed_by?.name || activeComplaint.filed_by?.first_name }}</div>
            <div><strong>Report:</strong> {{ activeComplaint.description }}</div>
          </div>

          <div>
            <label class="ifl">Status</label>
            <select v-model="statusUpdate" class="ifse">
              <option value="pending">Pending</option>
              <option value="under_review">Under Review</option>
              <option value="resolved">Resolved</option>
            </select>
          </div>

          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="savingStatus" @click="saveStatus">
              {{ savingStatus ? 'Saving...' : 'Update Status' }}
            </button>
            <button class="ibtn ibtn-o" @click="showDetail = false">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, onMounted } from 'vue';
import axios from 'axios';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const toast = inject('toast');

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const complaints = ref([]);
const loading     = ref(false);
const pagination  = ref({});
const filters      = ref({ search: '', status: '' });
let searchTimeout = null;

async function fetchComplaints(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters.value, page };
    if (!params.status) delete params.status;
    if (!params.search) delete params.search;
    const res = await axios.get(`${API_BASE}/complaints`, { ...authHeaders(), params });
    complaints.value = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchComplaints(), 350);
}

function resetFilters() {
  filters.value = { search: '', status: '' };
  fetchComplaints();
}

function formatDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

function statusLabel(s) {
  return { pending: 'Pending', under_review: 'Under Review', resolved: 'Resolved' }[s] || s;
}
function statusStyle(s) {
  const styles = {
    pending:      { background: '#fdf1d6', color: '#a4780a' },
    under_review: { background: '#e0edfb', color: '#1a5fa8' },
    resolved:     { background: 'var(--mint)', color: 'var(--moss)' },
  };
  return styles[s] || {};
}

const showDetail       = ref(false);
const activeComplaint  = ref(null);
const statusUpdate     = ref('');
const savingStatus     = ref(false);

function openDetail(c) {
  activeComplaint.value = c;
  statusUpdate.value = c.status;
  showDetail.value = true;
}

async function saveStatus() {
  if (!activeComplaint.value) return;
  savingStatus.value = true;
  try {
    await axios.patch(
      `${API_BASE}/complaints/${activeComplaint.value.id}/status`,
      { status: statusUpdate.value },
      authHeaders()
    );
    toast?.success('Status updated.');
    showDetail.value = false;
    fetchComplaints();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to update status.');
  } finally {
    savingStatus.value = false;
  }
}

onMounted(() => fetchComplaints());
</script>