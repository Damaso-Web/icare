<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Schedule an Appointment</h1>
      <p>Choose your concern and preferred schedule.</p>
    </div>

    <!-- Success -->
    <div v-if="submitted" class="icard" style="padding:32px;text-align:center;max-width:520px;margin:0 auto">
      <svg viewBox="0 0 24 24" style="width:48px;height:48px;stroke:var(--moss);fill:none;stroke-width:2;margin:0 auto 16px;display:block">
        <polyline points="20 6 9 17 4 12"/>
      </svg>
      <div style="font-size:16px;font-weight:600;color:var(--ink);margin-bottom:8px">
        Appointment Request Submitted
      </div>
      <div style="font-size:13px;color:var(--stone);line-height:1.6">
        Your requested schedule has been sent to the Office of Student Services for confirmation.
      </div>
      <div style="display:flex;gap:8px;justify-content:center;margin-top:16px">
        <router-link :to="{ name: 'student-appointments' }" class="ibtn ibtn-p">View My Appointments</router-link>
        <router-link :to="{ name: 'student-dashboard' }" class="ibtn ibtn-o">Back to Dashboard</router-link>
      </div>
    </div>

    <!-- Booking form -->
    <div v-else class="icard" style="max-width:600px;margin:0 auto">
      <div style="padding:22px;display:flex;flex-direction:column;gap:16px">

        <div>
          <label class="ifl">Counseling Concern <span style="color:var(--red)">*</span></label>
          <select v-model="form.concern" class="ifse">
            <option value="">Select a concern...</option>
            <option v-for="c in CONCERNS" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
        </div>

        <div>
          <label class="ifl">Brief description (optional)</label>
          <textarea v-model="form.notes" class="ifi" rows="3" maxlength="500"
                    placeholder="Anything you'd like the counselor to know in advance..."></textarea>
        </div>

        <div>
          <label class="ifl">Preferred Date <span style="color:var(--red)">*</span></label>
          <input v-model="form.appointment_date" type="date" class="ifi" :min="minDate" @change="onDateChange" />
          <div v-if="dayWarning" style="font-size:11px;color:var(--red);margin-top:4px">
            Please select a weekday (Monday to Friday).
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label class="ifl">Start Time <span style="color:var(--red)">*</span></label>
            <input v-model="form.start_time" type="time" class="ifi" min="08:00" max="16:00" @change="checkAvailability" />
          </div>
          <div>
            <label class="ifl">End Time <span style="color:var(--red)">*</span></label>
            <input v-model="form.end_time" type="time" class="ifi" min="08:00" max="16:00" @change="checkAvailability" />
          </div>
        </div>

        <div v-if="timeOrderError" style="font-size:11px;color:var(--red);margin-top:-8px">
          End time must be later than start time.
        </div>

        <div v-if="checkingAvailability" style="font-size:12px;color:var(--stone)">Checking availability...</div>
        <div v-else-if="availabilityChecked && !isAvailable"
             style="background:var(--red-lt);border:1px solid #f5c0c0;border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--red)">
          ⚠ This time slot is already taken. Please choose another.
        </div>
        <div v-else-if="availabilityChecked && isAvailable"
             style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--moss)">
          ✓ This time slot is available.
        </div>

        <div v-if="submitError"
             style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12px">
          {{ submitError }}
        </div>

        <button class="ibtn ibtn-p" style="width:100%;justify-content:center"
                @click="submitSchedule" :disabled="!canSubmit || submitting">
          <span v-if="submitting" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
          {{ submitting ? 'Submitting...' : 'Request Appointment' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { studentAppointmentAPI } from '../../api/index';

const CONCERNS = [
  { value: 'academic',     label: 'Academic concerns' },
  { value: 'personal',     label: 'Personal / emotional concerns' },
  { value: 'family',       label: 'Family concerns' },
  { value: 'relationship', label: 'Relationship concerns' },
  { value: 'career',       label: 'Career / course shifting' },
  { value: 'testing',      label: 'Psychological testing' },
  { value: 'other',        label: 'Other' },
];

const submitted          = ref(false);
const submitting         = ref(false);
const submitError        = ref('');
const dayWarning         = ref(false);
const timeOrderError     = ref(false);
const checkingAvailability = ref(false);
const availabilityChecked  = ref(false);
const isAvailable        = ref(false);

const form = ref({
  concern: '',
  notes: '',
  appointment_date: '',
  start_time: '',
  end_time: '',
});

const today = new Date();
const minDate = computed(() => today.toISOString().split('T')[0]);

const canSubmit = computed(() =>
  !!form.value.concern &&
  !!form.value.appointment_date &&
  !!form.value.start_time &&
  !!form.value.end_time &&
  !dayWarning.value &&
  !timeOrderError.value &&
  availabilityChecked.value &&
  isAvailable.value
);

function onDateChange() {
  checkAvailability();
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
    const res = await studentAppointmentAPI.checkConflict({
      appointment_date: form.value.appointment_date,
      start_time:       form.value.start_time,
      end_time:         form.value.end_time,
    });
    isAvailable.value = !res.data.has_conflict;
    availabilityChecked.value = true;
  } catch (e) {
    isAvailable.value = false;
    availabilityChecked.value = true;
  } finally {
    checkingAvailability.value = false;
  }
}

function checkDayOfWeek() {
  if (!form.value.appointment_date) { dayWarning.value = false; return; }
  const d = new Date(form.value.appointment_date + 'T00:00:00');
  const day = d.getDay();
  dayWarning.value = (day === 0 || day === 6);
}

async function submitSchedule() {
  submitError.value = '';
  submitting.value = true;
  try {
    await studentAppointmentAPI.store({
      concern:          form.value.concern,
      notes:            form.value.notes,
      appointment_date: form.value.appointment_date,
      start_time:       form.value.start_time,
      end_time:         form.value.end_time,
    });
    submitted.value = true;
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Failed to submit your request. Please try again.';
  } finally {
    submitting.value = false;
  }
}
</script>