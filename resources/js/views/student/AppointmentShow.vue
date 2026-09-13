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

    <template v-else>
      <!-- Scheduling Form (shown when awaiting student action) -->
      <div v-if="appointment.request_status === 'awaiting_student' && appointment.status !== 'cancelled'" class="icard" style="border:2px solid var(--moss);margin-bottom:16px">
        <div class="icard-header">
          <span class="icard-title">
            {{ appointment.reschedule_reason ? 'Reschedule Needed — Pick a New Time' : 'Choose Your Appointment Time' }}
          </span>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="appointment.reschedule_reason" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber)">
            <strong>Reason:</strong> {{ appointment.reschedule_reason }}
          </div>
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
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="submitSchedule" :disabled="!canSubmit || submitting">
            {{ submitting ? 'Submitting...' : 'Confirm Request' }}
          </button>
        </div>
      </div>

      <!-- Appointment Details -->
      <div class="icard">
        <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
            <span class="ibadge" :class="'ibadge-' + appointment.status">{{ toTitleCase(appointment.status) }}</span>
          </div>
          <div v-if="appointment.request_status !== 'awaiting_student' || appointment.status === 'cancelled'">
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
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const loading = ref(true);
const appointment = ref({});

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
  return date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
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
      `${API_BASE}/schedule/${appointment.value.scheduling_token}/check-availability`,
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
      `${API_BASE}/schedule/${appointment.value.scheduling_token}/submit`,
      scheduleForm.value
    );
    await fetchAppointment();
  } catch (e) {
    scheduleError.value = e.response?.data?.message || 'Failed to submit your request.';
  } finally {
    submitting.value = false;
  }
}

async function fetchAppointment() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/appointments/${route.params.id}`, authHeaders());
    appointment.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => fetchAppointment());
</script>