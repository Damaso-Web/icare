<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Welcome, {{ student.first_name }}</h1>
      <p>{{ student.student_id }} · {{ student.college }}</p>
    </div>

    <div v-if="student.must_change_password" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="font-size:13px;color:var(--amber)">⚠ Please change your temporary password.</div>
      <router-link :to="{ name: 'student-account' }" class="ibtn ibtn-sm" style="background:var(--amber);color:#fff">Change Now</router-link>
    </div>

    <div v-if="pendingAppointment" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
      <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:14px;font-weight:600;color:var(--ink)">📅 You have a pending appointment request</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">Please choose your preferred date and time.</div>
        </div>
        <router-link :to="{ name: 'student-appointments' }" class="ibtn ibtn-p">Schedule Now</router-link>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Recent Appointments</span></div>
        <div v-if="loading" style="padding:30px;text-align:center">
          <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
        </div>
        <div v-else-if="appointments.length === 0" class="empty-state">
          <h3>No appointments yet</h3>
        </div>
        <div v-else>
          <div v-for="a in appointments.slice(0, 3)" :key="a.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ formatDate(a.appointment_date) }} · {{ a.start_time }}</div>
            <span class="ibadge" :class="'ibadge-' + a.status" style="margin-top:4px;display:inline-block">{{ toTitleCase(a.status) }}</span>
          </div>
        </div>
      </div>

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Recent Referrals</span></div>
        <div v-if="referrals.length === 0" class="empty-state">
          <h3>No referrals yet</h3>
        </div>
        <div v-else>
          <div v-for="r in referrals.slice(0, 3)" :key="r.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:13px;font-weight:600;color:var(--ink);font-family:var(--mono)">{{ r.referral_code }}</div>
            <span class="ibadge" :class="'ibadge-' + r.status" style="margin-top:4px;display:inline-block">{{ toTitleCase(r.status) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));
const loading = ref(true);
const appointments = ref([]);
const referrals = ref([]);
const pendingAppointment = ref(null);

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
    appointments.value = res.data.appointments || [];
    referrals.value = res.data.referrals || [];
    pendingAppointment.value = res.data.pending_appointment || null;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchData());
</script>