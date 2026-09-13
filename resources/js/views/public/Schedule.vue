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
            Please select your preferred date and time below. Appointments are available <strong>Monday to Friday, 8:00 AM to 4:00 PM</strong>.
          </div>

          <div>
            <label class="ifl">Preferred Date <span style="color:var(--red)">*</span></label>
            <input v-model="form.appointment_date" type="date" class="ifi" :min="minDate" @change="checkAvailability" />
            <div v-if="dayWarning" style="font-size:11px;color:var(--red);margin-top:4px">
              Please select a weekday (Monday to Friday).
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Start Time <span style="color:var(--red)">*</span></label>
              <select v-model="form.start_time" class="ifse" @change="checkAvailability">
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
              <label class="ifl">End Time <span style="color:var(--red)">*</span></label>
              <select v-model="form.end_time" class="ifse" @change="checkAvailability">
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

          <div v-if="timeOrderError" style="font-size:11px;color:var(--red);margin-top:-8px">
            End time must be later than start time.
          </div>

          <div v-if="checkingAvailability" style="font-size:12px;color:var(--stone)">Checking availability...</div>
          <div v-else-if="availabilityChecked && !isAvailable" style="background:var(--red-lt);border:1px solid #f5c0c0;border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--red)">
            ⚠ This time slot is already taken. Please choose another.
          </div>
          <div v-else-if="availabilityChecked && isAvailable" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--moss)">
            ✓ This time slot is available.
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
import { ref, computed, onMounted } from 'vue';
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
const dayWarning   = ref(false);
const timeOrderError = ref(false);
const checkingAvailability = ref(false);
const availabilityChecked  = ref(false);
const isAvailable = ref(false);

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const form = ref({
  appointment_date: '',
  start_time: '',
  end_time: '',
});

const today = new Date();
const minDate = computed(() => today.toISOString().split('T')[0]);

const canSubmit = computed(() => {
  return form.value.appointment_date && form.value.start_time && form.value.end_time &&
         !dayWarning.value && !timeOrderError.value && availabilityChecked.value && isAvailable.value;
});

function checkDayOfWeek() {
  if (!form.value.appointment_date) { dayWarning.value = false; return; }
  const d = new Date(form.value.appointment_date + 'T00:00:00');
  const day = d.getDay();
  dayWarning.value = (day === 0 || day === 6);
}

async function checkAvailability() {
  checkDayOfWeek();
  availabilityChecked.value = false;
  timeOrderError.value = false;

  if (dayWarning.value || !form.value.appointment_date || !form.value.start_time || !form.value.end_time) return;

  if (form.value.end_time <= form.value.start_time) {
    timeOrderError.value = true;
    return;
  }

  checkingAvailability.value = true;
  try {
    const res = await axios.post(`${API_BASE}/schedule/${token}/check-availability`, form.value);
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
  submitError.value = '';
  submitting.value = true;
  try {
    await axios.post(`${API_BASE}/schedule/${token}/submit`, form.value);
    submitted.value = true;
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to submit your request. Please try again.';
  } finally {
    submitting.value = false;
  }
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '';
}

onMounted(async () => {
  try {
    const res = await axios.get(`${API_BASE}/schedule/${token}`);
    appointment.value = res.data.appointment;
    referral.value     = res.data.referral;
  } catch (e) {
    error.value = e.response?.data?.message || 'This scheduling link is invalid or has expired.';
  } finally {
    loading.value = false;
  }
});
</script>