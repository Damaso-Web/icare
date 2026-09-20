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
            <label class="ifl">Reason for rescheduling</label>
            <textarea v-model="rescheduleReason" class="ifi" rows="3" placeholder="Let us know why you need a new time..."></textarea>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">After submitting, you'll be able to pick a new date and time from the calendar on your Appointments page.</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="requestingReschedule || !rescheduleReason.trim()" @click="requestReschedule">
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
import { ref, onMounted } from 'vue';
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

const showCancelModal = ref(false);
const cancelReason = ref('');
const cancellingAppointment = ref(false);

function openRescheduleModal() {
  rescheduleReason.value = '';
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
    await axios.post(`${API_BASE}/student/appointments/${route.params.id}/request-reschedule`, { reason: rescheduleReason.value }, authHeaders());
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