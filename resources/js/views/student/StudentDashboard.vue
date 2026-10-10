<template>
  <div class="fade-up">
    <div class="hero">
      <div class="hero-row">
        <div>
          <div class="hero-kicker">Student Portal</div>
          <h1>Welcome, {{ student.first_name }}</h1>
          <p>Your appointments, referrals and testing with the Office of Student Services, in one place.</p>
          <div class="hero-chips">
            <span class="hero-chip">{{ student.student_id }}</span>
            <span v-if="student.college" class="hero-chip">{{ student.college }}</span>
          </div>
        </div>
        <div class="hero-actions">
          <router-link :to="{ name: 'student-appointments' }" class="hero-btn gold"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>My Appointments</router-link>
          <router-link :to="{ name: 'student-referrals' }" class="hero-btn"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>My Referrals</router-link>
        </div>
      </div>
    </div>

    <div v-if="student.must_change_password" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="font-size:13px;color:var(--amber)">⚠ Please change your temporary password.</div>
      <router-link :to="{ name: 'student-account' }" class="ibtn ibtn-sm" style="background:var(--amber);color:#fff">Change Now</router-link>
    </div>

    <div v-if="pendingAppointments.length" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
    <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:14px;font-weight:600;color:var(--ink)">📅 You have {{ pendingAppointments.length }} pending appointment request{{ pendingAppointments.length > 1 ? 's' : '' }}</div>
        <div style="font-size:12px;color:var(--stone);margin-top:2px">Please choose your preferred date and time{{ pendingAppointments.length > 1 ? ' — one at a time' : '' }}.</div>
      </div>
          <router-link
  :to="scheduleAppointmentId
    ? { name: 'student-appointment-show', params: { id: scheduleAppointmentId } }
    : { name: 'student-schedule-new' }"
  class="ibtn ibtn-p"
>Schedule Now</router-link>
    </div>
  </div>

    <div v-if="parentConferenceSlips.length" class="icard" style="border:2px solid var(--amber);margin-bottom:20px">
      <div class="icard-header"><span class="icard-title">Parent Conference Slip</span></div>
      <div v-for="slip in parentConferenceSlips" :key="slip.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
        <div style="font-size:13px;font-weight:600;color:var(--ink)">Your parent/guardian is asked to come to the office on {{ formatSlipDate(slip.conference_date) }}<span v-if="slip.conference_time"> at {{ slip.conference_time }}</span>.</div>
        <div style="font-size:12px;color:var(--slate);margin-top:3px">Reason: {{ slip.reason }}</div>
        <div v-if="slip.remarks" style="font-size:11.5px;color:var(--stone);margin-top:3px;font-style:italic">{{ slip.remarks }}</div>
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
            <!-- An appointment still awaiting the student's own schedule
                 pick (see the "pending appointment request" banner above)
                 is a real DB row with a placeholder date/time - it never
                 had a real one, so don't show it as if it did. -->
            <div v-if="a.request_status === 'awaiting_student'" style="font-size:13px;font-weight:600;color:var(--stone);font-style:italic">
              Awaiting your schedule selection
            </div>
            <div v-else style="font-size:13px;font-weight:600;color:var(--ink)">{{ formatDateShort(a.appointment_date) }} · {{ formatTime12(a.start_time) }}</div>
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { formatTime12, formatDateShort } from '../../utils/validators';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));
const loading = ref(true);
const appointments = ref([]);
const referrals = ref([]);
const parentConferenceSlips = ref([]);
function formatSlipDate(d) { return d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'; }
const pendingAppointments = ref([]);

const scheduleAppointmentId = computed(() => pendingAppointments.value[0]?.id || null);

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

async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, authHeaders());
    appointments.value = res.data.appointments || [];
    referrals.value = res.data.referrals || [];
    parentConferenceSlips.value = res.data.parent_conference_slips || [];
    // Matches the same fallback used in the student Appointments list - if the
    // API ever sends the singular `pending_appointment` instead of the plural
    // array, this banner shouldn't just silently disappear.
    pendingAppointments.value = res.data.pending_appointments || (res.data.pending_appointment ? [res.data.pending_appointment] : []);
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchData());
</script>