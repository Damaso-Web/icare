<template>
  <div class="fade-up">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>{{ referral.referral_code }}</h1>
        <p>Referral Details</p>
      </div>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <div class="icard" style="margin-bottom:20px">
        <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
            <span class="ibadge" :class="'ibadge-' + referral.status">{{ toTitleCase(referral.status) }}</span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service Type</div>
            <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(referral.referral_type) }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date Submitted</div>
            <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.created_at) }}</div>
          </div>
          <div v-if="referral.nature_of_concern">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Concern</div>
            <div style="font-size:13px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm)">{{ referral.nature_of_concern }}</div>
          </div>
          <div v-if="referral.acknowledged_at">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Acknowledged</div>
            <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.acknowledged_at) }}</div>
          </div>
        </div>
      </div>

      <!-- Scheduling: only appears when this referral has an appointment awaiting a student-picked time -->
      <div v-if="scheduleSuccess" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
        <div class="icard-body" style="font-size:13.5px;color:var(--moss)">✓ Your appointment request has been submitted. You'll be notified once it's confirmed.</div>
      </div>

      <div v-else-if="pendingAppointment" class="icard" style="margin-bottom:20px">
        <div class="icard-header"><span class="icard-title">Choose Your Appointment Time</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="pendingAppointment.reschedule_reason" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12.5px;color:var(--amber)">
            🔁 You're rescheduling appointment <strong>{{ pendingAppointment.appointment_code }}</strong>. Reason: {{ pendingAppointment.reschedule_reason }}
          </div>
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
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const referral = ref({});
const scheduleSuccess = ref(false);

const pendingAppointment = computed(() => referral.value.pending_appointment || null);

const scheduleForm = ref({ appointment_date: '', start_time: '', end_time: '' });
const dayWarning = ref(false);
const timeOrderError = ref(false);
const checkingAvailability = ref(false);
const availabilityChecked = ref(false);
const isAvailable = ref(false);
const submitting = ref(false);
const scheduleError = ref('');

const today = new Date();
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

async function fetchMonthAvailability() {
  if (!pendingAppointment.value?.scheduling_token) return;
  const monthStr = `${calYear.value}-${String(calMonth.value + 1).padStart(2, '0')}`;
  try {
    const res = await axios.get(`${API_BASE}/schedule/${pendingAppointment.value.scheduling_token}/month-availability`, { params: { month: monthStr } });
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
  return date ? new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
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
    scheduleSuccess.value = true;
  } catch (e) {
    scheduleError.value = e.response?.data?.message || 'Failed to submit your request.';
  } finally {
    submitting.value = false;
  }
}

async function fetchReferral() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/referrals/${route.params.id}`, authHeaders());
    referral.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await fetchReferral();
  if (pendingAppointment.value) {
    fetchMonthAvailability();
  }
});
</script>