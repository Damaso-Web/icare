<template>
  <div class="fade-up">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>{{ appointment.appointment_code }}</h1>
        <p>Appointment Details</p>
      </div>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <div v-else class="icard">
      <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
          <span class="ibadge" :class="'ibadge-' + appointment.status">{{ toTitleCase(appointment.status) }}</span>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date & Time</div>
          <div style="font-size:13px;color:var(--ink)">{{ formatDate(appointment.appointment_date) }} · {{ appointment.start_time }} – {{ appointment.end_time }}</div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Type</div>
          <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(appointment.appointment_type) }}</div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Unit</div>
          <div style="font-size:13px;color:var(--ink)">{{ appointment.unit }}</div>
        </div>
        <div v-if="appointment.staff">
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Assigned Staff</div>
          <div style="font-size:13px;color:var(--ink)">{{ appointment.staff?.name }}</div>
        </div>
        <div v-if="appointment.location">
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Location</div>
          <div style="font-size:13px;color:var(--ink)">📍 {{ appointment.location }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const loading = ref(true);
const appointment = ref({});

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
}

onMounted(async () => {
  try {
    const res = await axios.get(`${API_BASE}/student/appointments/${route.params.id}`, authHeaders());
    appointment.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>