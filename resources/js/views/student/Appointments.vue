<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Appointments</h1>
      <p>View and manage your appointment requests.</p>
    </div>

    <!-- Pending Appointment Request Banner -->
<div v-if="pendingAppointments.length && !showScheduleForm" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
  <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:14px;font-weight:600;color:var(--ink)">
        You have {{ pendingAppointments.length }} pending appointment request{{ pendingAppointments.length > 1 ? 's' : '' }}
      </div>
      <div style="font-size:12px;color:var(--stone);margin-top:2px">
        Please choose your preferred date and time{{ pendingAppointments.length > 1 ? ' — you can schedule the next one right after' : '' }}.
      </div>
    </div>
    <button class="ibtn ibtn-p" @click="openScheduleForm(pendingAppointments[0])">Schedule Now</button>
  </div>
</div>

    <!-- Inline Scheduling Form -->
    <div v-if="showScheduleForm && pendingAppointment" class="icard" style="margin-bottom:20px">
      <div class="icard-header"><span class="icard-title">Choose Your Appointment Time</span></div>
      <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
        <div style="font-size:13px;color:var(--stone);display:flex;align-items:center;flex-wrap:wrap;gap:8px 16px">
          <span>Appointments are available <strong>Monday to Friday, 8:00 AM to 4:00 PM</strong>.</span>
          <span style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;font-size:12px">
            <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:var(--mist);border:1px solid var(--mint);display:inline-block"></span> Available</span>
            <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:var(--red-lt);border:1px solid #f0a8a8;display:inline-block"></span> Full</span>
            <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:var(--cloud);border:1px solid var(--silver);display:inline-block"></span> Closed</span>
          </span>
        </div>

        <!-- Availability Calendar -->
        <div style="background:var(--snow);border-radius:var(--r-sm);padding:16px;max-width:400px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
            <button type="button" class="ibtn ibtn-g ibtn-sm" @click="prevCalMonth">‹</button>
            <span style="font-size:14px;font-weight:600;color:var(--ink)">{{ calMonthLabel }}</span>
            <button type="button" class="ibtn ibtn-g ibtn-sm" @click="nextCalMonth">›</button>
          </div>
          <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
            <div v-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d" style="text-align:center;font-size:10.5px;font-weight:700;color:var(--fog);padding:2px 0">{{ d }}</div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
            <div v-for="blank in calLeadingBlanks" :key="'b'+blank"></div>
            <div
              v-for="day in calDays"
              :key="day.date"
              :title="calStatusLabel(day.status)"
              style="width:100%;aspect-ratio:1;max-height:46px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:13.5px"
              :style="calDayStyle(day)"
              @click="day.status === 'available' && selectCalendarDate(day.date)"
            >
              {{ Number(day.date.split('-')[2]) }}
            </div>
          </div>
        </div>

        <div>
          <label class="ifl">Selected Date</label>
          <div style="font-size:13.5px;color:var(--ink);font-weight:600;padding:9px 0">
            {{ scheduleForm.appointment_date ? formatDate(scheduleForm.appointment_date) : 'Pick a date on the calendar above' }}
          </div>
          <div v-if="dayWarning" style="font-size:11px;color:var(--red);margin-top:4px">Please select a weekday (Monday to Friday).</div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="ifl">Start Time</label>
            <input v-model="scheduleForm.start_time" type="time" class="ifi" min="08:00" max="16:00" @change="checkAvailability" />
          </div>
          <div>
            <label class="ifl">End Time</label>
            <input v-model="scheduleForm.end_time" type="time" class="ifi" min="08:00" max="16:00" @change="checkAvailability" />
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
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const appointments = ref([]);
const pendingAppointments = ref([]);
const activeAppointment = ref(null);
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

const calYear  = ref(today.getFullYear());
const calMonth = ref(today.getMonth());
const calDays  = ref([]);

const calMonthLabel = computed(() => new Date(calYear.value, calMonth.value, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));
const calLeadingBlanks = computed(() => new Date(calYear.value, calMonth.value, 1).getDay());

function calStatusLabel(status) {
  return { available: 'Available', full: 'Fully booked', closed: 'Not open for appointments', past: 'Past date' }[status] || '';
}

function calDayStyle(day) {
  if (day.status === 'available') return 'cursor:pointer;background:var(--mist);color:var(--moss);font-weight:600';
  if (day.status === 'full') return 'cursor:not-allowed;background:var(--red-lt);color:var(--red)';
  if (day.status === 'past') return 'cursor:not-allowed;color:var(--silver)';
  return 'cursor:not-allowed;background:var(--cloud);color:var(--fog)';
}

function selectCalendarDate(dateStr) {
  scheduleForm.value.appointment_date = dateStr;
  checkAvailability();
}

function openScheduleForm(appt) {
  activeAppointment.value = appt;
  scheduleForm.value = { appointment_date: '', start_time: '', end_time: '' };
  dayWarning.value = false;
  timeOrderError.value = false;
  availabilityChecked.value = false;
  isAvailable.value = false;
  scheduleError.value = '';
  calYear.value = today.getFullYear();
  calMonth.value = today.getMonth();
  showScheduleForm.value = true;
  fetchMonthAvailability();
}

async function fetchMonthAvailability() {
  if (!activeAppointment.value?.scheduling_token) return;
  const monthStr = `${calYear.value}-${String(calMonth.value + 1).padStart(2, '0')}`;
  try {
    const res = await axios.get(`${API_BASE}/schedule/${activeAppointment.value.scheduling_token}/month-availability`, { params: { month: monthStr } });
    calDays.value = res.data.days;
  } catch (e) {
    calDays.value = [];
  }
}

function prevCalMonth() {
  calMonth.value--;
  if (calMonth.value < 0) { calMonth.value = 11; calYear.value--; }
  fetchMonthAvailability();
}

function nextCalMonth() {
  calMonth.value++;
  if (calMonth.value > 11) { calMonth.value = 0; calYear.value++; }
  fetchMonthAvailability();
}

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
  return date ? new Date(date).toLocaleDateString() : '-';
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
      `${API_BASE}/schedule/${activeAppointment.value.scheduling_token}/check-availability`,
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
      `${API_BASE}/schedule/${activeAppointment.value.scheduling_token}/submit`,
      scheduleForm.value
    );
    showScheduleForm.value = false;
    activeAppointment.value = null;
    fetchData();
  } catch (e) {
    scheduleError.value = e.response?.data?.message || 'Failed to submit your request.';
  } finally {
    submitting.value = false;
  }
}

onMounted(() => fetchData());
</script>