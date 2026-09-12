<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Appointments</h1>
      <p>View and manage your appointment requests.</p>
    </div>

    <!-- Pending Appointment Request Banner -->
    <div v-if="pendingAppointment && !showScheduleForm" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
      <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div>
          <div style="font-size:14px;font-weight:600;color:var(--ink)">📅 You have a pending appointment request</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">Please choose your preferred date and time.</div>
        </div>
        <button class="ibtn ibtn-p" @click="showScheduleForm = true">Schedule Now</button>
      </div>
    </div>

    <!-- Inline Scheduling Form -->
    <div v-if="showScheduleForm && pendingAppointment" class="icard" style="margin-bottom:20px">
      <div class="icard-header"><span class="icard-title">Choose Your Appointment Time</span></div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <div style="font-size:13px;color:var(--stone)">
          Appointments are available <strong>Monday to Friday, 8:00 AM to 4:00 PM</strong>.
        </div>
        <div>
          <label class="ifl">Preferred Date</label>
          <input v-model="scheduleForm.appointment_date" type="date" class="ifi" :min="minDate" @change="checkAvailability" />
          <div v-if="dayWarning" style="font-size:11px;color:var(--red);margin-top:4px">Please select a weekday (Monday to Friday).</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="ifl">Start Time</label>
            <select v-model="scheduleForm.start_time" class="ifse" @change="checkAvailability">
              <option value="">Select...</option>
              <option value="08:00">08:00 AM</option>
              <option value="09:00">09:00 AM</option>
              <option value="10:00">10:00 AM</option>
              <option value="11:00">11:00 AM</option>
              <option value="13:00">01:00 PM</option>
              <option value="14:00">02:00 PM</option>
              <option value="15:00">03:00 PM</option>
            </select>
          </div>
          <div>
            <label class="ifl">End Time</label>
            <select v-model="scheduleForm.end_time" class="ifse" @change="checkAvailability">
              <option value="">Select...</option>
              <option value="09:00">09:00 AM</option>
              <option value="10:00">10:00 AM</option>
              <option value="11:00">11:00 AM</option>
              <option value="12:00">12:00 PM</option>
              <option value="14:00">02:00 PM</option>
              <option value="15:00">03:00 PM</option>
              <option value="16:00">04:00 PM</option>
            </select>
          </div>
        </div>
        <div v-if="timeOrderError" style="font-size:11px;color:var(--red)">End time must be later than start time.</div>
        <div v-if="checkingAvailability" style="font-size:12px;color:var(--stone)">Checking availability...</div>
        <div v-else-if="availabilityChecked && !isAvailable" style="background:var(--red-lt);border:1px solid #f5c0c0;border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--red)">⚠ This time slot is already taken. Please choose another.</div>
        <div v-else-if="availabilityChecked && isAvailable" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--moss)">✓ This time slot is available.</div>
        <div v-if="scheduleError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12px">{{ scheduleError }}</div>
        <div style="display:flex;gap:8px">
          <button class="ibtn ibtn-p" @click="submitSchedule" :disabled="!canSubmit || submitting">
            {{ submitting ? 'Submitting...' : 'Confirm Request' }}
          </button>
          <button class="ibtn ibtn-o" @click="showScheduleForm = false">Cancel</button>
        </div>
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
        <div v-for="a in appointments" :key="a.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ formatDate(a.appointment_date) }} · {{ a.start_time }} – {{ a.end_time }}</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">{{ a.unit }} · {{ toTitleCase(a.appointment_type) }}</div>
          <span class="ibadge" :class="'ibadge-' + a.status" style="margin-top:6px;display:inline-block">{{ toTitleCase(a.status) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const loading = ref(true);
const appointments = ref([]);
const pendingAppointment = ref(null);
const showScheduleForm = ref(false);

const scheduleForm = ref({ appointment_date: '', start_time: '', end_time: '' });
const dayWarning = ref(false);
const timeOrderError = ref(false);
const checkingAvailability = ref(false);
const availabilityChecked = ref(false);
const isAvailable = ref(false);
const submitting = ref(false);
const scheduleError = ref('');

const today = new Date();
const minDate = computed(() => today.toISOString().split('T')[0]);

const canSubmit = computed(() => {
  return scheduleForm.value.appointment_date && scheduleForm.value.start_time && scheduleForm.value.end_time &&
         !dayWarning.value && !timeOrderError.value && availabilityChecked.value && isAvailable.value;
});

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
    pendingAppointment.value = res.data.pending_appointment || null;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function checkDayOfWeek() {
  if (!scheduleForm.value.appointment_date) { dayWarning.value = false; return; }
  const d = new Date(scheduleForm.value.appointment_date + 'T00:00:00');
  const day = d.getDay();
  dayWarning.value = (day === 0 || day === 6);
}

async function checkAvailability() {
  checkDayOfWeek();
  availabilityChecked.value = false;
  timeOrderError.value = false;

  if (dayWarning.value || !scheduleForm.value.appointment_date || !scheduleForm.value.start_time || !scheduleForm.value.end_time) return;

  if (scheduleForm.value.end_time <= scheduleForm.value.start_time) {
    timeOrderError.value = true;
    return;
  }

  checkingAvailability.value = true;
  try {
    const res = await axios.post(
      `${API_BASE}/schedule/${pendingAppointment.value.scheduling_token}/check-availability`,
      scheduleForm.value
    );
    isAvailable.value = res.data.available;
    availabilityChecked.value = true;
  } catch (e) {
    isAvailable.value = false;
    availabilityChecked.value = true;
  } finally {
    checkingAvailability.value = false;
  }
}

async function submitSchedule() {
  scheduleError.value = '';
  submitting.value = true;
  try {
    await axios.post(
      `${API_BASE}/schedule/${pendingAppointment.value.scheduling_token}/submit`,
      scheduleForm.value
    );
    showScheduleForm.value = false;
    pendingAppointment.value = null;
    fetchData();
  } catch (e) {
    scheduleError.value = e.response?.data?.message || 'Failed to submit your request.';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => fetchData());
</script>