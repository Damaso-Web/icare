<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Appointments</h1>
      <p>View and manage your appointment requests.</p>
    </div>

    <!-- Pending Appointment Requests - styled like the staff-side SIF's
         referral rows (badges + a per-row action button) instead of a
         dropdown, so with more than one the student picks which referral's
         appointment to schedule by acting on that row directly. -->
    <div v-if="pendingAppointments.length" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
      <div class="icard-header">
        <span class="icard-title">
          {{ pendingAppointments.length }} Pending Appointment Request{{ pendingAppointments.length > 1 ? 's' : '' }}
        </span>
      </div>
      <div style="padding:10px 18px;font-size:12px;color:var(--stone);border-bottom:1px solid var(--cloud)">
        {{ pendingAppointments.length > 1 ? 'Select which one to schedule, one at a time.' : 'Please choose your preferred date and time.' }}
      </div>
      <div
        v-for="pa in pendingAppointments"
        :key="pa.id"
        style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"
      >
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--ink);font-family:var(--mono)">
            {{ pa.referral?.referral_code || pa.case?.latest_referral?.referral_code || pa.appointment_code }}
          </div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px;display:flex;align-items:center;gap:6px">
            <span class="ibadge" :class="'unit-' + pa.unit?.toLowerCase()">{{ pa.unit }}</span>
            {{ toTitleCase(pa.appointment_type) }}
          </div>
        </div>
        <button class="ibtn ibtn-p ibtn-sm" @click="goToSchedule(pa)">Schedule Now</button>
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
          <!-- Still waiting on the student to pick their own date/time (see
               the "pending appointment request" banner above) - this row's
               appointment_date/start_time/end_time are only a placeholder,
               so showing them here made it look like a date was already
               set before the student had chosen anything. -->
          <div v-if="a.request_status === 'awaiting_student'" style="font-size:13.5px;font-weight:600;color:var(--stone);font-style:italic">Awaiting your schedule selection</div>
          <div v-else style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ formatDateShort(a.appointment_date) }} · {{ formatTime12(a.start_time) }} – {{ formatTime12(a.end_time) }}</div>
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
import { formatTime12, formatDateShort } from '../../utils/validators';

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
  if (!appt?.id) return;
  router.push({ name: 'student-appointment-show', params: { id: appt.id } });
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