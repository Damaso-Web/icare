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
        <input v-model="filters.search" type="text" class="sin" placeholder="Search student name or ID..." @input="onSearchInput" style="width:220px"/>
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
      <select v-model="filters.unit" class="fsm" @change="fetchReferrals">
        <option value="">All Units</option>
        <option value="GCU">GCU</option>
        <option value="SDU">SDU</option>
        <option value="TMDU">TMDU</option>
      </select>
      <select v-model="filters.type" class="fsm" @change="fetchReferrals">
        <option value="">All Services</option>
        <option value="class_attendance">Class Attendance (Absences/Tardiness)</option>
        <option value="counseling">Counseling</option>
        <option value="academic_deficiency">Academic Deficiency</option>
        <option value="leave_of_absence">Leave of Absence</option>
        <option value="withdrawal">Withdrawal</option>
        <option value="readmission">Readmission</option>
        <option value="shifting">Shifting</option>
        <option value="psychological_testing">Psychological Testing</option>
        <option value="disciplinary">Acts of Misconduct</option>
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
              {{ r.referral_type?.replace(/_/g, ' ') }} · {{ formatDate(r.created_at) }}
            </div>
            <div class="qtags">
              <span class="ibadge" :class="'ibadge-' + r.status">{{ r.status?.replace(/_/g, ' ') }}</span>
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
import { ref, onMounted } from 'vue';
import { referralAPI } from '../../api/index';
import { safeSearchInput } from '../../utils/validators';

const referrals  = ref([]);
const loading    = ref(true);
const pagination = ref({});
const filters    = ref({ search: '', status: '', unit: '', type: '' });

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
  filters.value = { search: '', status: '', unit: '', type: '' };
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