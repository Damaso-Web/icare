<template>
  <div style="min-height:100vh;background:var(--snow);display:flex;align-items:center;justify-content:center;padding:20px">
    <div style="width:100%;max-width:600px">

      <!-- Header -->
      <div style="text-align:center;margin-bottom:24px">
        <div style="width:52px;height:52px;background:var(--forest);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-family:var(--serif);font-style:italic;font-size:24px;color:var(--gold)">i</div>
        <div style="font-family:var(--serif);font-style:italic;font-size:22px;color:var(--forest)">iCARE</div>
        <div style="font-size:12px;color:var(--fog);margin-top:2px">BSU · Office of Student Services</div>
      </div>

      <div v-if="loading" style="text-align:center;padding:60px;background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-lg)">
        <div style="width:28px;height:28px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>

      <div v-else-if="error" style="background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-lg);padding:32px;text-align:center">
        <div style="font-size:15px;font-weight:600;color:var(--red);margin-bottom:8px">Link Expired or Invalid</div>
        <div style="font-size:13px;color:var(--stone)">{{ error }}</div>
        <div style="font-size:12px;color:var(--fog);margin-top:12px">Please contact the Office of Student Services for a new link.</div>
      </div>

      <div v-else-if="submitted" style="background:#fff;border-radius:var(--r-lg);box-shadow:var(--sh-lg);padding:32px;text-align:center">
        <svg viewBox="0 0 24 24" style="width:48px;height:48px;stroke:var(--moss);fill:none;stroke-width:2;margin:0 auto 16px;display:block"><polyline points="20 6 9 17 4 12"/></svg>
        <div style="font-size:16px;font-weight:600;color:var(--ink);margin-bottom:8px">Appointment Request Submitted</div>
        <div style="font-size:13px;color:var(--stone);line-height:1.6">
          Your requested schedule has been sent to the Office of Student Services for confirmation.
          You will be notified once it's confirmed.
        </div>
        <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;margin-top:16px;text-align:left;font-size:13px">
          <div><strong>Date:</strong> {{ formatDate(form.appointment_date) }}</div>
          <div><strong>Time:</strong> {{ form.start_time }} – {{ form.end_time }}</div>
        </div>
      </div>

      <div v-else class="icard">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Schedule Your Appointment</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px">
            {{ appointment.student?.first_name }} {{ appointment.student?.last_name }} · {{ appointment.student?.student_id }}
          </div>
        </div>

        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">

          <!-- Referral Info -->
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;font-size:13px">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">Referral Details</div>
            <div><strong>Concern:</strong> {{ referral?.nature_of_concern }}</div>
            <div style="margin-top:4px"><strong>Unit:</strong> {{ appointment.unit }}</div>
          </div>

          <div style="font-size:13px;color:var(--stone);line-height:1.6">
            Tap an open time slot below. Appointments are available <strong>Monday to Friday, 8:00 AM to 4:00 PM</strong>.
          </div>

          <!-- Week Navigator -->
          <div style="display:flex;align-items:center;justify-content:space-between">
            <button class="ibtn ibtn-g ibtn-sm" @click="prevWeek" :disabled="isThisWeek">‹ Prev</button>
            <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ weekLabel }}</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="nextWeek">Next ›</button>
          </div>

          <!-- Availability Grid -->
          <div v-if="loadingGrid" style="text-align:center;padding:24px">
            <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
          </div>
          <div v-else style="overflow-x:auto">
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;min-width:480px">
              <div v-for="day in weekGrid" :key="day.date" style="display:flex;flex-direction:column;gap:5px">
                <div style="text-align:center;font-size:11px;font-weight:700;color:var(--stone);padding:4px 0;border-bottom:1px solid var(--cloud)">
                  {{ day.label }}
                </div>
                <button
                  v-for="slot in day.slots"
                  :key="slot.start"
                  type="button"
                  :disabled="!slot.available"
                  @click="selectSlot(day.date, slot)"
                  :style="slotStyle(day.date, slot)"
                >
                  {{ formatSlotTime(slot.start) }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="form.appointment_date && form.start_time" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12.5px;color:var(--forest)">
            ✓ Selected: {{ formatDate(form.appointment_date) }}, {{ formatSlotTime(form.start_time) }} – {{ formatSlotTime(form.end_time) }}
          </div>

          <div v-if="submitError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12px">
            {{ submitError }}
          </div>

          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="submitSchedule" :disabled="!canSubmit || submitting">
            <span v-if="submitting" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
            {{ submitting ? 'Submitting...' : 'Request Appointment' }}
          </button>

        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const token = route.params.token;

const loading    = ref(true);
const error      = ref('');
const submitted  = ref(false);
const submitting = ref(false);
const submitError = ref('');
const appointment = ref({});
const referral     = ref(null);

const loadingGrid = ref(false);
const weekGrid    = ref([]);

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const form = ref({
  appointment_date: '',
  start_time: '',
  end_time: '',
});

function mondayOf(date) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  d.setDate(diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

const currentWeekStart = ref(mondayOf(new Date()));

const isThisWeek = computed(() => {
  return currentWeekStart.value.getTime() <= mondayOf(new Date()).getTime();
});

const weekLabel = computed(() => {
  const start = currentWeekStart.value;
  const end = new Date(start);
  end.setDate(end.getDate() + 4);
  return `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

const canSubmit = computed(() => {
  return !!(form.value.appointment_date && form.value.start_time && form.value.end_time);
});

function formatSlotTime(t) {
  const [h, m] = t.split(':').map(Number);
  const period = h >= 12 ? 'PM' : 'AM';
  const hour12 = h % 12 === 0 ? 12 : h % 12;
  return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
}

function slotStyle(date, slot) {
  const isSelected = form.value.appointment_date === date && form.value.start_time === slot.start;
  if (isSelected) {
    return 'padding:8px 4px;border-radius:6px;font-size:11.5px;border:1.5px solid var(--moss);background:var(--moss);color:#fff;cursor:pointer;font-weight:600';
  }
  if (!slot.available) {
    return 'padding:8px 4px;border-radius:6px;font-size:11.5px;border:1px solid var(--cloud);background:var(--cloud);color:var(--fog);cursor:not-allowed';
  }
  return 'padding:8px 4px;border-radius:6px;font-size:11.5px;border:1px solid var(--mint);background:var(--mist);color:var(--moss);cursor:pointer';
}

function selectSlot(date, slot) {
  if (!slot.available) return;
  form.value.appointment_date = date;
  form.value.start_time = slot.start;
  form.value.end_time = slot.end;
}

async function fetchWeekGrid() {
  loadingGrid.value = true;
  try {
    const weekStartStr = currentWeekStart.value.toISOString().split('T')[0];
    const res = await axios.get(`${API_BASE}/schedule/${token}/week`, {
      params: { week_start: weekStartStr },
    });
    weekGrid.value = res.data.days;
  } catch (e) {
    weekGrid.value = [];
  } finally {
    loadingGrid.value = false;
  }
}

function prevWeek() {
  if (isThisWeek.value) return;
  const d = new Date(currentWeekStart.value);
  d.setDate(d.getDate() - 7);
  currentWeekStart.value = d;
}

function nextWeek() {
  const d = new Date(currentWeekStart.value);
  d.setDate(d.getDate() + 7);
  currentWeekStart.value = d;
}

watch(currentWeekStart, () => {
  form.value = { appointment_date: '', start_time: '', end_time: '' };
  fetchWeekGrid();
});

async function submitSchedule() {
  submitError.value = '';
  submitting.value = true;
  try {
    await axios.post(`${API_BASE}/schedule/${token}/submit`, form.value);
    submitted.value = true;
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to submit your request. Please try again.';
    fetchWeekGrid();
  } finally {
    submitting.value = false;
  }
}

function formatDate(date) {
  return date ? new Date(date + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '';
}

onMounted(async () => {
  try {
    const res = await axios.get(`${API_BASE}/schedule/${token}`);
    appointment.value = res.data.appointment;
    referral.value     = res.data.referral;
    await fetchWeekGrid();
  } catch (e) {
    error.value = e.response?.data?.message || 'This scheduling link is invalid or has expired.';
  } finally {
    loading.value = false;
  }
});
</script>