<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Referrals</h1>
      <p>View the status of referrals submitted on your behalf.</p>
    </div>

    <div class="icard">
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="referrals.length === 0" class="empty-state">
        <h3>No referrals yet</h3>
      </div>
      <div v-else>
        <div v-for="r in referrals" :key="r.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:13.5px;font-weight:600;color:var(--ink);font-family:var(--mono)">{{ r.referral_code }}</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">{{ toTitleCase(r.referral_type) }} · {{ formatDate(r.created_at) }}</div>
          <span class="ibadge" :class="'ibadge-' + r.status" style="margin-top:6px;display:inline-block">{{ toTitleCase(r.status) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const loading = ref(true);
const referrals = ref([]);

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, authHeaders());
    referrals.value = res.data.referrals || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchData());
</script>