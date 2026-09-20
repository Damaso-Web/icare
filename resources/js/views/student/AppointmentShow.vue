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
          <div style="font-size:13px;color:var(--ink)">{{ formatDate(appointment.appointment_date) }} · {{ appointment.start_time }} - {{ appointment.end_time }}</div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Type</div>
          <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(appointment.appointment_type) }}</div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Unit</div>
          <span class="ibadge" :class="'unit-' + appointment.unit?.toLowerCase()">{{ appointment.unit }}</span>
        </div>
        <div v-if="appointment.referral || appointment.case?.latest_referral">
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">For Referral</div>
          <div style="font-size:13px;color:var(--ink)">{{ (appointment.referral || appointment.case.latest_referral).referral_code }} · {{ toTitleCase((appointment.referral || appointment.case.latest_referral).referral_type) }}</div>
        </div>
        <div v-if="appointment.staff">
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Assigned Staff</div>
          <div style="font-size:13px;color:var(--ink)">{{ appointment.staff?.name }}</div>
        </div>
        <div v-if="appointment.location">
          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Location</div>
          <div style="font-size:13px;color:var(--ink)">📍 {{ appointment.location }}</div>
        </div>

        <div v-if="['pending', 'confirmed'].includes(appointment.status)" style="border-top:1px solid var(--cloud);padding-top:14px">
          <div v-if="rescheduleError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-bottom:10px">{{ rescheduleError }}</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="ibtn ibtn-o ibtn-sm" @click="openRescheduleModal">Request Reschedule</button>
            <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openCancelModal">Cancel Appointment</button>
          </div>
          <div style="font-size:11px;color:var(--fog);margin-top:6px">{{ appointment.reschedule_count || 0 }} of 3 reschedule requests used.</div>
        </div>
      </div>
    </div>

    <!-- Reschedule Modal -->
    <div v-if="showRescheduleModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showRescheduleModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Request Reschedule</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="modalError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px">{{ modalError }}</div>
          <div>
            <label class="ifl">Preferred Date</label>
            <input v-model="preferredDate" type="date" class="ifi" @change="validatePreferredDate" />
            <div v-if="preferredDateWarning" style="font-size:11px;color:var(--red);margin-top:4px">Please select a weekday (Monday to Friday).</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Preferred Start Time</label>
              <input v-model="preferredStart" type="time" class="ifi" min="08:00" max="16:00" @change="validatePreferredTimes" />
            </div>
            <div>
              <label class="ifl">Preferred End Time</label>
              <input v-model="preferredEnd" type="time" class="ifi" min="08:00" max="16:00" @change="validatePreferredTimes" />
            </div>
          </div>
          <div v-if="preferredTimeError" style="font-size:11px;color:var(--red)">{{ preferredTimeError }}</div>
          <div>
            <label class="ifl">Reason for Rescheduling</label>
            <textarea v-model="rescheduleReason" class="ifi" rows="3" placeholder="Please tell us why you need to reschedule..."></textarea>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">This is a preference, not a confirmed booking — staff will confirm the actual new time.</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!canSubmitReschedule" @click="requestReschedule">
              {{ requestingReschedule ? 'Submitting...' : 'Submit Request' }}
            </button>
            <button class="ibtn ibtn-o" @click="showRescheduleModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Modal -->
    <div v-if="showCancelModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCancelModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Cancel Appointment?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="modalError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px">{{ modalError }}</div>
          <div style="font-size:13px;color:var(--slate);line-height:1.6">This cannot be undone. If you still need help, you'll need to submit a new request.</div>
          <div>
            <label class="ifl">Reason for cancelling</label>
            <textarea v-model="cancelReason" class="ifi" rows="3" placeholder="Let us know why you're cancelling..."></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" :disabled="cancellingAppointment || !cancelReason.trim()" @click="cancelAppointment">
              {{ cancellingAppointment ? 'Cancelling...' : 'Confirm Cancellation' }}
            </button>
            <button class="ibtn ibtn-o" @click="showCancelModal = false">Keep Appointment</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const appointment = ref({});
const requestingReschedule = ref(false);
const rescheduleError = ref('');
const modalError = ref('');

const showRescheduleModal = ref(false);
const rescheduleReason = ref('');
const preferredDate = ref('');
const preferredStart = ref('');
const preferredEnd = ref('');
const preferredDateWarning = ref(false);
const preferredTimeError = ref('');

const OFFICE_START = '08:00';
const OFFICE_END   = '16:00';

const canSubmitReschedule = computed(() =>
  !requestingReschedule.value &&
  rescheduleReason.value.trim() &&
  !preferredDateWarning.value &&
  !preferredTimeError.value
);

function validatePreferredDate() {
  if (!preferredDate.value) { preferredDateWarning.value = false; return; }
  const day = new Date(preferredDate.value + 'T00:00:00').getDay();
  preferredDateWarning.value = (day === 0 || day === 6);
}

function validatePreferredTimes() {
  preferredTimeError.value = '';
  if (preferredStart.value && (preferredStart.value < OFFICE_START || preferredStart.value > OFFICE_END)) {
    preferredTimeError.value = 'Please select a time between 8:00 AM and 4:00 PM.';
    return;
  }
  if (preferredEnd.value && (preferredEnd.value < OFFICE_START || preferredEnd.value > OFFICE_END)) {
    preferredTimeError.value = 'Please select a time between 8:00 AM and 4:00 PM.';
    return;
  }
  if (preferredStart.value && preferredEnd.value && preferredEnd.value <= preferredStart.value) {
    preferredTimeError.value = 'End time must be later than start time.';
  }
}

const showCancelModal = ref(false);
const cancelReason = ref('');
const cancellingAppointment = ref(false);

function openRescheduleModal() {
  rescheduleReason.value = '';
  preferredDate.value = '';
  preferredStart.value = '';
  preferredEnd.value = '';
  preferredDateWarning.value = false;
  preferredTimeError.value = '';
  modalError.value = '';
  showRescheduleModal.value = true;
}

function openCancelModal() {
  cancelReason.value = '';
  modalError.value = '';
  showCancelModal.value = true;
}

async function requestReschedule() {
  rescheduleError.value = '';
  modalError.value = '';
  requestingReschedule.value = true;
  try {
    const parts = [];
    if (preferredDate.value) {
      const dateLabel = new Date(preferredDate.value + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
      let slot = `Preferred: ${dateLabel}`;
      if (preferredStart.value && preferredEnd.value) slot += ` ${preferredStart.value}–${preferredEnd.value}`;
      parts.push(slot);
    }
    if (rescheduleReason.value.trim()) parts.push(rescheduleReason.value.trim());
    const reason = parts.join('. ') || 'No reason provided.';

    await axios.post(`${API_BASE}/student/appointments/${route.params.id}/request-reschedule`, { reason }, authHeaders());
    router.push({ name: 'student-appointments' });
  } catch (e) {
    const message = e.response?.data?.message || 'Failed to request a reschedule.';
    rescheduleError.value = message;
    modalError.value = message;
  } finally {
    requestingReschedule.value = false;
  }
}

async function cancelAppointment() {
  modalError.value = '';
  cancellingAppointment.value = true;
  try {
    await axios.post(`${API_BASE}/student/appointments/${route.params.id}/cancel`, { cancellation_reason: cancelReason.value }, authHeaders());
    router.push({ name: 'student-appointments' });
  } catch (e) {
    modalError.value = e.response?.data?.message || 'Failed to cancel this appointment.';
  } finally {
    cancellingAppointment.value = false;
  }
}

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