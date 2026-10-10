<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Complaints</h1>
      <p>Every complaint filed against a student, newest first. Each one is kept in that student's Incident Report, where its status is set.</p>
    </div>

    <div class="filter-bar">
      <div class="sw" style="flex:1;min-width:220px;max-width:400px">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          maxlength="50"
          placeholder="Search complaint code or student..."
          style="width:100%"
          @keypress="blockSpecialKeypress"
          @input="onSearchInput"
        />
      </div>
      <button v-if="filters.search" class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
    </div>

    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="complaints.length === 0" class="empty-state">
        <h3>No complaints found</h3>
        <p>{{ filters.search ? 'Try a different search.' : 'Complaints filed against students will appear here.' }}</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Complaint No.</th>
              <th>Student</th>
              <th>Act of Misconduct</th>
              <th>Filed By</th>
              <th>Date Filed</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in complaints" :key="c.id" style="cursor:pointer" @click="openDetail(c)">
              <td style="font-family:var(--mono);font-size:12px">{{ c.complaint_code }}</td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="iav">{{ initials(c.complainee) }}</div>
                  <div>
                    <div style="font-weight:600;color:var(--ink)">{{ c.complainee?.last_name }}, {{ c.complainee?.first_name }}</div>
                    <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ c.complainee?.student_id }}</div>
                  </div>
                </div>
              </td>
              <td>{{ c.violation_type }}</td>
              <td>{{ c.filed_by?.name || '-' }}</td>
              <td style="font-size:12px;white-space:nowrap">{{ formatDate(c.created_at) }}</td>
              <td style="text-align:right">
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="openDetail(c)">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}</span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="fetchComplaints(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="fetchComplaints(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- The whole complaint. Its status lives in the student's Incident Report, not here. -->
    <div v-if="activeComplaint" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="activeComplaint = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:680px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;display:flex;flex-direction:column">
        <div style="padding:16px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;gap:12px">
          <div>
            <div style="font-size:16px;font-weight:600;color:var(--ink)">Complaint <span style="font-family:var(--mono)">{{ activeComplaint.complaint_code }}</span></div>
            <div style="font-size:11.5px;color:var(--fog);margin-top:2px">Filed {{ formatDate(activeComplaint.created_at) }}</div>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="activeComplaint = null">✕</button>
        </div>
        <div style="padding:18px 22px;overflow-y:auto">
          <div v-if="loadingDetail" style="text-align:center;padding:30px;color:var(--fog);font-size:13px">Loading...</div>
          <ComplaintDetail v-else :complaint="activeComplaint" />
        </div>
        <div style="padding:12px 22px;border-top:1px solid var(--cloud);background:var(--snow);display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap">
          <button class="ibtn ibtn-o ibtn-sm" @click="activeComplaint = null">Close</button>
          <button class="ibtn ibtn-p ibtn-sm" @click="openIncidentReport(activeComplaint)">Open Student Incident Report</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import ComplaintDetail from '../../components/ComplaintDetail.vue';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const router = useRouter();

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const complaints = ref([]);
const loading    = ref(false);
const pagination = ref({});
const filters    = ref({ search: '' });
let searchTimeout = null;

async function fetchComplaints(page = 1) {
  loading.value = true;
  try {
    const params = { page };
    if (filters.value.search) params.search = filters.value.search;
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
  filters.value = { search: '' };
  fetchComplaints();
}

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
}

function initials(s) {
  return `${s?.first_name?.[0] || ''}${s?.last_name?.[0] || ''}`.toUpperCase() || '?';
}

const activeComplaint = ref(null);
const loadingDetail   = ref(false);

// The list row has everything but the attachments; fetch the full complaint for the popup.
async function openDetail(c) {
  activeComplaint.value = c;
  loadingDetail.value = true;
  try {
    const res = await axios.get(`${API_BASE}/complaints/${c.id}`, authHeaders());
    if (activeComplaint.value?.id === c.id) activeComplaint.value = res.data;
  } catch (e) {
    /* the row's own data is still shown */
  } finally {
    loadingDetail.value = false;
  }
}

function openIncidentReport(c) {
  router.push({ name: 'incident-report-show', params: { id: c.complainee_student_id }, query: { complaint: c.id } });
}

onMounted(() => fetchComplaints());
</script>
