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
        <input v-model="filters.search" type="text" class="sin" placeholder="Search student name or ID..." @keypress="blockSpecialKeypress" @input="onSearchInput" style="width:220px"/>
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
      <button
        class="ibtn ibtn-sm"
        :class="filters.archived ? 'ibtn-p' : 'ibtn-o'"
        @click="filters.archived = !filters.archived; fetchReferrals()"
      >
        {{ filters.archived ? 'Viewing Archived' : 'View Archived' }}
      </button>
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
      <select v-if="filters.unit === 'GCU' || filters.unit === 'TMDU'" v-model="filters.type" class="fsm" @change="fetchReferrals">
        <option value="">All Services</option>
        <option v-for="svc in availableServices" :key="svc.value" :value="svc.value">{{ svc.label }}</option>
      </select>
      <select v-else-if="filters.unit === 'SDU'" v-model="filters.violation_type" class="fsm" @change="fetchReferrals">
        <option value="">All Acts of Misconduct</option>
        <option v-for="v in disciplinaryOptions" :key="v.value" :value="v.label">{{ v.label }}</option>
      </select>
      <select v-model="filters.sort" class="fsm" @change="fetchReferrals">
        <option value="desc">Date: Newest First</option>
        <option value="asc">Date: Oldest First</option>
      </select>
      <div style="display:flex;align-items:center;gap:6px">
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
          :class="urgencyRow(r.urgency_level)"
          :style="r.is_archived ? 'cursor:not-allowed;opacity:.6' : ''"
          @click="!r.is_archived && $router.push({ name: 'referral-show', params: { id: r.id } })"
        >
          <div class="qav">{{ r.referral_code?.split('-').pop() }}</div>
          <div class="qi">
            <div class="qtags">
              <span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span>
              <span v-if="r.is_archived" class="ibadge" style="background:var(--cloud);color:var(--stone)">Archived</span>
            </div>
            <div class="qmeta">
              {{ toTitleCase(r.referral_type) }} · {{ formatDate(r.created_at) }}
            </div>
          </div>
          <div class="qacts">
            <button
              class="ibtn ibtn-p ibtn-sm"
              :disabled="r.is_archived"
              :title="r.is_archived ? 'This referral is archived and no longer active.' : ''"
              @click.stop="!r.is_archived && $router.push({ name: 'referral-show', params: { id: r.id } })"
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
import axios from 'axios';
import { referralAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress, toTitleCase } from '../../utils/validators';

const referrals  = ref([]);
const loading    = ref(true);
const pagination = ref({});
const filters = ref({ search: '', status: '', unit: '', type: '', violation_type: '', sort: 'desc', date_from: '', date_to: '', archived: false });

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

// Wellness Services (GCU / TMDU) still come from the referral_type form
// options; Acts of Misconduct (SDU) now come from Management too instead of
// being a hardcoded list here.
const wellnessOptionsRaw = ref([]);
const disciplinaryOptions = ref([]);

async function fetchFilterOptions() {
  try {
    const [typeRes, miscRes] = await Promise.all([
      axios.get(`${API_BASE}/management/form-options`, { ...authHeaders(), params: { category: 'referral_type' } }),
      axios.get(`${API_BASE}/management/form-options`, { ...authHeaders(), params: { category: 'act_of_misconduct' } }),
    ]);
    wellnessOptionsRaw.value = typeRes.data;
    disciplinaryOptions.value = miscRes.data;
  } catch (e) {
    console.error(e);
  }
}

// GCU vs TMDU is now a real tag set per-service in Management, not guessed.
const availableServices = computed(() => {
  if (filters.value.unit === 'GCU' || filters.value.unit === 'TMDU') {
    return wellnessOptionsRaw.value.filter(o => o.unit === filters.value.unit);
  }
  return [];
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
    const res = await referralAPI.index({ ...filters.value, archived: filters.value.archived ? 1 : 0, page });
    referrals.value  = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value = { search: '', status: '', unit: '', type: '', violation_type: '', sort: 'desc', date_from: '', date_to: '', archived: false };
  fetchReferrals();
}

function changePage(page) { fetchReferrals(page); }

function urgencyRow(level) {
  return { uh: level === 'high' || level === 'critical', um: level === 'medium', ul: level === 'low' };
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => {
  fetchReferrals();
  fetchFilterOptions();
});
</script>