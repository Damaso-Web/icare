<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Appointments</h1>
      <p>View and manage your appointment requests.</p>
    </div>

    <!-- Pending Appointment Request Banner -->
    <div v-if="pendingAppointments.length" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
      <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:14px;font-weight:600;color:var(--ink)">
            You have {{ pendingAppointments.length }} pending appointment request{{ pendingAppointments.length > 1 ? 's' : '' }}
          </div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">
            Please choose your preferred date and time on the related referral{{ pendingAppointments.length > 1 ? ' — one at a time' : '' }}.
          </div>
        </div>
        <button class="ibtn ibtn-p" @click="goToSchedule(pendingAppointments[0])">Schedule Now</button>
      </div>
    </div>

    <!-- Appointments List -->
    <div class="icard">
      <div class="icard-header"><span class="icard-title">All Appointments</span></div>
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="appointments.length === 0" class="empty-state">
        <h3>No appointments yet</h3>
        <p>You'll see your appointment details here once one is scheduled.</p>
      </div>
      <div v-else>
        <div
          v-for="a in appointments"
          :key="a.id"
          style="padding:14px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
          @click="$router.push({ name: 'student-appointment-show', params: { id: a.id } })"
        >
          <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ formatDate(a.appointment_date) }} · {{ a.start_time }} - {{ a.end_time }}</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px;display:flex;align-items:center;gap:6px">
            <span class="ibadge" :class="'unit-' + a.unit?.toLowerCase()">{{ a.unit }}</span>
            {{ toTitleCase(a.appointment_type) }}
          </div>
          <div v-if="a.referral || a.case?.latest_referral" style="font-size:11px;color:var(--fog);margin-top:4px">For referral {{ (a.referral || a.case.latest_referral).referral_code }}</div>
          <span class="ibadge" :class="'ibadge-' + a.status" style="margin-top:6px;display:inline-block">{{ toTitleCase(a.status) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const appointments = ref([]);
const pendingAppointments = ref([]);

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

function goToSchedule(appt) {
  const referralId = appt?.referral?.id || appt?.case?.latest_referral?.id;
  if (referralId) {
    router.push({ name: 'student-referral-show', params: { id: referralId } });
  } else {
    router.push({ name: 'student-appointments' });
  }
}

async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, authHeaders());
    appointments.value = res.data.appointments || [];
    pendingAppointments.value = res.data.pending_appointments || (res.data.pending_appointment ? [res.data.pending_appointment] : []);
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchData());
</script>