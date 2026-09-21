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
      <!-- Success after scheduling -->
      <div v-if="scheduleSuccess" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
        <div class="icard-body" style="font-size:13.5px;color:var(--moss)">
          ✓ Your appointment request has been submitted. You'll be notified once it's confirmed.
        </div>
      </div>

      <!-- Main details -->
      <div class="icard" style="margin-bottom:20px">
        <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
            <span class="ibadge" :class="'ibadge-' + appointment.status">{{ toTitleCase(appointment.status) }}</span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date &amp; Time</div>
            <div style="font-size:13px;color:var(--ink)">{{ formatDate(appointment.appointment_date) }} · {{ appointment.start_time }} - {{ appointment.end_time }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service</div>
            <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(appointment.appointment_type) }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Unit</div>
            <span class="ibadge" :class="'unit-' + appointment.unit?.toLowerCase()">{{ appointment.unit }}</span>
          </div>
          <div v-if="referralSource">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Reason for Referral / Concern</div>
            <div style="font-size:13px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm)">
              {{ referralSource.nature_of_concern || '-' }}
            </div>
          </div>
          <div v-if="referralSource">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">For Referral</div>
            <div style="font-size:13px;color:var(--ink)">{{ referralSource.referral_code }} · {{ toTitleCase(referralSource.referral_type) }}</div>
          </div>
          <div v-if="appointment.staff">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Assigned Staff</div>
            <div style="font-size:13px;color:var(--ink)">{{ appointment.staff?.name }}</div>
          </div>
          <div v-if="appointment.location">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Location</div>
            <div style="font-size:13px;color:var(--ink)">📍 {{ appointment.location }}</div>
          </div>
          <div v-if="appointment.required_documents">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Required Documents</div>
            <div style="font-size:13px;color:var(--ink);background:var(--snow);padding:10px 12px;border-radius:var(--r-sm)">{{ appointment.required_documents }}</div>
          </div>
        </div>
      </div>

      <!-- Choose Your Appointment Time (only when awaiting student) -->
      <div v-if="needsScheduling && !scheduleSuccess" class="icard" style="margin-bottom:20px">
        <div class="icard-header"><span class="icard-title">Choose Your Appointment Time</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="appointment.reschedule_reason" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12.5px;color:var(--amber)">
            🔁 You're rescheduling appointment <strong>{{ appointment.appointment_code }}</strong>, previously set for
            <strong>{{ formatDate(appointment.appointment_date) }} · {{ appointment.start_time }}–{{ appointment.end_time }}</strong>.
            Reason: {{ appointment.reschedule_reason }}
          </div>
          <div style="font-size:13px;color:var(--stone);display:flex;align-items:center;flex-wrap:wrap;gap:8px 16px">
            <span>Appointments are available <strong>Monday to Friday, 8:00 AM to 4:00 PM</strong>.</span>
            <span style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;font-size:12px">
              <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:#22c55e;border:1px solid #16a34a;display:inline-block"></span> Available</span>
              <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:#ef4444;border:1px solid #dc2626;display:inline-block"></span> Full</span>
              <span style="display:flex;align-items:center;gap:5px"><span style="width:12px;height:12px;border-radius:3px;background:#9ca3af;border:1px solid #6b7280;display:inline-block"></span> Closed</span>
            </span>
          </div>

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
              <label class="ifl">Preferred Start Time</label>
              <input v-model="scheduleForm.start_time" type="time" class="ifi" min="08:00" max="16:00" @change="onTimeChange" />
            </div>
            <div>
              <label class="ifl">Preferred End Time</label>
              <input v-model="scheduleForm.end_time" type="time" class="ifi" min="08:00" max="16:00" @change="onTimeChange" />
            </div>
            <div v-if="timeRangeError" style="grid-column:1 / -1;font-size:11px;color:var(--red)">{{ timeRangeError }}</div>
          </div>
          <div v-if="timeOrderError" style="font-size:11px;color:var(--red)">End time must be later than start time.</div>
          <div v-if="sameAsOriginalError" style="font-size:11px;color:var(--red)">Please select a different date or time than your original appointment.</div>
          <div v-if="checkingAvailability" style="font-size:12px;color:var(--stone)">Checking availability...</div>
          <div v-else-if="availabilityChecked && !isAvailable" style="background:var(--red-lt);border:1px solid #f5c0c0;border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--red)">⚠ This time slot is already taken. Please choose another.</div>
          <div v-else-if="availabilityChecked && isAvailable" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--moss)">✓ This time slot is available.</div>
          <div v-if="scheduleError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12px">{{ scheduleError }}</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="submitSchedule" :disabled="!canSubmit || submitting">
              {{ submitting ? 'Submitting...' : 'Confirm Appointment Request' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Pending, waiting on staff -->
      <div v-if="appointment.status === 'pending' && !needsScheduling && !scheduleSuccess" class="icard">
        <div class="icard-body" style="font-size:13px;color:var(--stone)">
          ⏳ Your appointment request has been submitted. Waiting for staff to confirm.
        </div>
      </div>

      <!-- Reschedule / Cancel — only when confirmed or no-show -->
      <div v-if="showActions" class="icard">
        <div class="icard-body" style="display:flex;flex-direction:column;gap:12px">
          <div v-if="rescheduleError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px">{{ rescheduleError }}</div>
          <div v-if="appointment.status === 'no_show'" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12.5px;color:var(--amber)">
            ⚠ You missed this appointment. You can request a new schedule below.
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="ibtn ibtn-o ibtn-sm" @click="openRescheduleModal">Request Reschedule</button>
            <button v-if="appointment.status === 'confirmed'" class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openCancelModal">Cancel Appointment</button>
          </div>
          <div style="font-size:11px;color:var(--fog)">{{ appointment.reschedule_count || 0 }} of 3 reschedule requests used.</div>
        </div>
      </div>
    </template>

    <!-- Confirm Schedule Modal -->
    <div v-if="showConfirmModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showConfirmModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Appointment Request</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">Please review your preferred schedule before submitting:</div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><strong>Date:</strong> {{ formatDate(scheduleForm.appointment_date) }}</div>
            <div><strong>Time:</strong> {{ scheduleForm.start_time }} - {{ scheduleForm.end_time }}</div>
          </div>
          <div style="font-size:12px;color:var(--stone)">Your request will be sent to the Office of Student Services for confirmation.</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="doConfirmedSubmit" :disabled="submitting">
              <span v-if="submitting" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ submitting ? 'Submitting...' : 'Yes, Submit Request' }}
            </button>
            <button class="ibtn ibtn-o" @click="showConfirmModal = false">Go Back &amp; Edit</button>
          </div>
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

// Scheduling state
const scheduleSuccess = ref(false);
const scheduleForm = ref({ appointment_date: '', start_time: '', end_time: '' });
const dayWarning = ref(false);
const timeOrderError = ref(false);
const checkingAvailability = ref(false);
const availabilityChecked = ref(false);
const isAvailable = ref(false);
const submitting = ref(false);
const scheduleError = ref('');
const timeRangeError = ref('');
const showConfirmModal = ref(false);

const OFFICE_START = '08:00';
const OFFICE_END   = '16:00';

const today = new Date();
const calYear  = ref(today.getFullYear());
const calMonth = ref(today.getMonth());
const calDays  = ref([]);

// Reschedule / cancel state
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

const showCancelModal = ref(false);
const cancelReason = ref('');
const cancellingAppointment = ref(false);

// Computed
const referralSource = computed(() =>
  appointment.value.referral || appointment.value.case?.latest_referral || null
);

const needsScheduling = computed(() =>
  appointment.value.request_status === 'awaiting_student' &&
  !!appointment.value.scheduling_token
);

const showActions = computed(() =>
  ['confirmed', 'no_show'].includes(appointment.value.status)
);

const canSubmitReschedule = computed(() =>
  !requestingReschedule.value &&
  rescheduleReason.value.trim() &&
  !preferredDateWarning.value &&
  !preferredTimeError.value
);

const calMonthLabel = computed(() => new Date(calYear.value, calMonth.value, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }));
const calLeadingBlanks = computed(() => new Date(calYear.value, calMonth.value, 1).getDay());

const sameAsOriginalError = computed(() => {
  const appt = appointment.value;
  if (!appt?.reschedule_reason) return false;
  const origDate = appt.appointment_date?.split('T')[0];
  return (
    scheduleForm.value.appointment_date &&
    scheduleForm.value.start_time &&
    scheduleForm.value.end_time &&
    scheduleForm.value.appointment_date === origDate &&
    scheduleForm.value.start_time === appt.start_time &&
    scheduleForm.value.end_time === appt.end_time
  );
});

const canSubmit = computed(() =>
  scheduleForm.value.appointment_date &&
  scheduleForm.value.start_time &&
  scheduleForm.value.end_time &&
  !dayWarning.value &&
  !timeOrderError.value &&
  !timeRangeError.value &&
  !sameAsOriginalError.value &&
  availabilityChecked.value &&
  isAvailable.value
);

// Calendar helpers
function calStatusLabel(status) {
  return { available: 'Available', full: 'Fully booked', closed: 'Not open for appointments', past: 'Past date' }[status] || '';
}

function calDayStyle(day) {
  if (day.status === 'available') return 'cursor:pointer;background:#dcfce7;color:#15803d;font-weight:600';
  if (day.status === 'full') return 'cursor:not-allowed;background:#fee2e2;color:#b91c1c';
  if (day.status === 'past') return 'cursor:not-allowed;color:var(--silver)';
  return 'cursor:not-allowed;background:#e5e7eb;color:#6b7280';
}

function selectCalendarDate(dateStr) {
  scheduleForm.value.appointment_date = dateStr;
  checkAvailability();
}

async function fetchMonthAvailability() {
  if (!appointment.value.scheduling_token) return;
  const monthStr = `${calYear.value}-${String(calMonth.value + 1).padStart(2, '0')}`;
  try {
    const res = await axios.get(`${API_BASE}/schedule/${appointment.value.scheduling_token}/month-availability`, { params: { month: monthStr } });
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

function onTimeChange() {
  timeRangeError.value = '';
  const { start_time, end_time } = scheduleForm.value;
  if (start_time && (start_time < OFFICE_START || start_time > OFFICE_END)) {
    timeRangeError.value = 'Please select a time between 8:00 AM and 4:00 PM.';
  } else if (end_time && (end_time < OFFICE_START || end_time > OFFICE_END)) {
    timeRangeError.value = 'Please select a time between 8:00 AM and 4:00 PM.';
  }
  checkAvailability();
}

function submitSchedule() {
  scheduleError.value = '';
  showConfirmModal.value = true;
}

async function doConfirmedSubmit() {
  showConfirmModal.value = false;
  submitting.value = true;
  try {
    await axios.post(
      `${API_BASE}/schedule/${appointment.value.scheduling_token}/submit`,
      scheduleForm.value
    );
    scheduleSuccess.value = true;
  } catch (e) {
    scheduleError.value = e.response?.data?.message || 'Failed to submit your request.';
  } finally {
    submitting.value = false;
  }
}

// Reschedule (existing confirmed/no-show flow)
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

// Helpers
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
    if (needsScheduling.value) {
      fetchMonthAvailability();
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>