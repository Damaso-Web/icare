<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Referral Queue</h1>
      <p>Review, assign, and track incoming referrals from faculty and SDU.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="filters.search" type="text" class="sin" placeholder="Search student name or ID..." @input="fetchReferrals" style="width:220px"/>
      </div>
      <select v-model="filters.status" class="fsm" @change="fetchReferrals">
        <option value="">All Status</option>
        <option value="submitted">Submitted</option>
        <option value="acknowledged">Acknowledged</option>
        <option value="in_review">In Review</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="closed">Closed</option>
      </select>
      <select v-model="filters.type" class="fsm" @change="fetchReferrals">
        <option value="">All Services</option>
        <option value="counseling">Class Attendance / Absent / Tardy</option>
        <option value="academic_coaching">Academic Deficiency</option>
        <option value="psychological_testing">Psychological Testing</option>
        <option value="consultation">Scholarship / Grant Assistance</option>
        <option value="admission_slip">Student Organizations &amp; Activities</option>
        <option value="disciplinary">Student Housing (Dormitories)</option>
        <option value="others">For Student Employment (SA/SPES)</option>
        <option value="other">Others</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <router-link :to="{ name: 'referral-create' }" class="ibtn ibtn-p ibtn-sm" style="margin-left:auto">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Submit Referral
      </router-link>
    </div>

    <!-- Referral List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>

      <div v-else-if="referrals.length === 0" class="empty-state">
        <h3>No referrals found</h3>
        <p>Try adjusting your filters or submit a new referral.</p>
      </div>

      <div v-else>
        <div
          v-for="r in referrals"
          :key="r.id"
          class="qr"
          :class="urgencyRow(r.urgency_level)"
          @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
        >
          <div class="qav">{{ initials(r.student?.first_name, r.student?.last_name) }}</div>
          <div class="qi">
            <div class="qn">
              {{ r.student?.first_name }} {{ r.student?.last_name }}
              <span class="qid">{{ r.student?.student_id }}</span>
            </div>
            <div class="qmeta">
              {{ r.referral_type?.replace(/_/g, ' ') }} · Referred by {{ r.referrer_name }} · {{ formatDate(r.created_at) }}
            </div>
            <div class="qcon">{{ r.nature_of_concern }}</div>
            <div class="qtags">
              <span class="ibadge" :class="'ibadge-' + r.urgency_level">{{ r.urgency_level }}</span>
              <span class="ibadge" :class="'ibadge-' + r.status">{{ r.status?.replace(/_/g, ' ') }}</span>
              <span class="ibadge" style="background:var(--cloud);color:var(--stone)">{{ r.referral_code }}</span>
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

const referrals  = ref([]);
const loading    = ref(true);
const pagination = ref({});
const filters = ref({ search: '', status: '', type: '' });

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
  filters.value = { search: '', status: '', urgency: '', type: '' };
  fetchReferrals();
}

function changePage(page) { fetchReferrals(page); }

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function urgencyRow(level) {
  return { uh: level === 'high' || level === 'critical', um: level === 'medium', ul: level === 'low' };
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

onMounted(() => fetchReferrals());
</script>