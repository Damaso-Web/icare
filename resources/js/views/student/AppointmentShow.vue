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
      <!-- Scheduling Grid (shown when awaiting student action) -->
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
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;min-width:420px">
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

          <div v-if="scheduleForm.appointment_date && scheduleForm.start_time" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12.5px;color:var(--forest)">
            ✓ Selected: {{ formatDate(scheduleForm.appointment_date) }}, {{ formatSlotTime(scheduleForm.start_time) }} – {{ formatSlotTime(scheduleForm.end_time) }}
          </div>

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
          <div v-if="appointment.case?.latest_referral">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral</div>
            <div style="font-size:13px;color:var(--ink)">{{ appointment.case.latest_referral.referral_code }} ({{ toTitleCase(appointment.case.latest_referral.referral_type) }})</div>
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
          <div v-if="appointment.cancellation_reason">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Cancellation Reason</div>
            <div style="font-size:13px;color:var(--ink)">{{ appointment.cancellation_reason }}</div>
          </div>
          <div v-if="['pending', 'confirmed'].includes(appointment.status) && appointment.request_status !== 'awaiting_student'" style="display:flex;gap:8px;padding-top:8px;border-top:1px solid var(--cloud)">
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="openActionModal('reschedule')">Request Reschedule</button>
            <button class="ibtn" style="flex:1;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openActionModal('cancel')">Cancel Appointment</button>
          </div>
        </div>
      </div>
    </template>

    <!-- Reschedule / Cancel Confirmation Modal -->
    <div v-if="showActionModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showActionModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">
            {{ actionMode === 'cancel' ? 'Cancel Appointment' : 'Request Reschedule' }}
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showActionModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone);line-height:1.6">
            {{ actionMode === 'cancel'
              ? 'Are you sure you want to cancel this appointment? This cannot be undone.'
              : 'Let us know why you need to reschedule, then you can pick a new time.' }}
          </div>
          <div>
            <label class="ifl">Reason <span style="color:var(--red)">*</span></label>
            <textarea v-model="actionReason" class="ifta" style="min-height:80px"></textarea>
          </div>
          <div v-if="actionError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ actionError }}</div>
          <div style="display:flex;gap:8px">
            <button
              class="ibtn"
              :style="actionMode === 'cancel' ? 'background:var(--red);color:#fff' : 'background:var(--moss);color:#fff'"
              @click="submitAction"
              :disabled="actionSubmitting"
            >
              {{ actionSubmitting ? 'Submitting...' : (actionMode === 'cancel' ? 'Yes, Cancel' : 'Send Request') }}
            </button>
            <button class="ibtn ibtn-o" @click="showActionModal = false">Never Mind</button>
          </div>
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
const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const loading = ref(true);
const appointment = ref({});

const scheduleForm = ref({ appointment_date: '', start_time: '', end_time: '' });
const submitting = ref(false);
const scheduleError = ref('');

const loadingGrid = ref(false);
const weekGrid    = ref([]);

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
  return !!(scheduleForm.value.appointment_date && scheduleForm.value.start_time && scheduleForm.value.end_time);
});

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
}

function formatSlotTime(t) {
  const [h, m] = t.split(':').map(Number);
  const period = h >= 12 ? 'PM' : 'AM';
  const hour12 = h % 12 === 0 ? 12 : h % 12;
  return `${hour12}:${String(m).padStart(2, '0')} ${period}`;
}

function slotStyle(date, slot) {
  const isSelected = scheduleForm.value.appointment_date === date && scheduleForm.value.start_time === slot.start;
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
  scheduleForm.value.appointment_date = date;
  scheduleForm.value.start_time = slot.start;
  scheduleForm.value.end_time = slot.end;
}

async function fetchWeekGrid() {
  if (!appointment.value.scheduling_token) return;
  loadingGrid.value = true;
  try {
    const weekStartStr = currentWeekStart.value.toISOString().split('T')[0];
    const res = await axios.get(`${API_BASE}/schedule/${appointment.value.scheduling_token}/week`, {
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
  scheduleForm.value = { appointment_date: '', start_time: '', end_time: '' };
  fetchWeekGrid();
});

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
    fetchWeekGrid();
  } finally {
    submitting.value = false;
  }
}

async function fetchAppointment() {
  loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/appointments/${route.params.id}`, authHeaders());
    appointment.value = res.data;
    if (appointment.value.request_status === 'awaiting_student') {
      await fetchWeekGrid();
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

const showActionModal  = ref(false);
const actionMode       = ref('cancel');
const actionReason     = ref('');
const actionError      = ref('');
const actionSubmitting = ref(false);

function openActionModal(mode) {
  actionMode.value = mode;
  actionReason.value = '';
  actionError.value = '';
  showActionModal.value = true;
}

async function submitAction() {
  actionError.value = '';
  if (!actionReason.value.trim()) {
    actionError.value = 'Please provide a reason.';
    return;
  }
  actionSubmitting.value = true;
  try {
    const endpoint = actionMode.value === 'cancel' ? 'cancel' : 'request-reschedule';
    await axios.post(
      `${API_BASE}/student/appointments/${route.params.id}/${endpoint}`,
      { reason: actionReason.value },
      authHeaders()
    );
    showActionModal.value = false;
    await fetchAppointment();
  } catch (e) {
    actionError.value = e.response?.data?.message || 'Something went wrong. Please try again.';
  } finally {
    actionSubmitting.value = false;
  }
}

onMounted(() => fetchAppointment());
</script>