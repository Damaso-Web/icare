<!--
  FILE: resources/js/views/testing/Appointments.vue
  PAGE: iCARE / TMDU Appointments

  Read-only overview of every appointment TMDU has on the books, scoped
  entirely to unit: 'TMDU' (no Unit filter - there's nothing else to filter
  to here). Every TMDU appointment is now created directly from Testing
  Record Details ("Schedule Test Taking" / "Schedule PAR Release") with
  TMDU picking the date/time face-to-face with the student - nothing is
  ever self-scheduled by the student, so there's no "Confirm" step and no
  "Awaiting Student" state here. This page is purely for browsing what's
  scheduled across all students/dates.

  Cancel / No-Show / Request Reschedule stay, since those are legitimate
  outcomes of a face-to-face meeting that didn't happen as planned.

  Reuses appointmentAPI end-to-end - no new backend endpoints. The backend
  already auto-scopes a tmdu_staff user to unit: 'TMDU'
  (AppointmentController::index()); this page also passes unit: 'TMDU'
  explicitly so admin accounts see the same TMDU-only view here.
-->
<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px;display:flex;align-items:center;gap:10px">
      <button v-if="returnTo" class="ibtn ibtn-o ibtn-sm" @click="$router.push(returnTo)">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div>
        <h1>TMDU Appointments</h1>
        <p>Read-only overview of all psychological testing appointments scheduled by TMDU.</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">

      <!-- Left: Appointments List -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Filter Bar - no Unit filter here, this page is TMDU-only -->
        <div class="filter-bar">
          <select v-model="filters.status" class="fsm" @change="fetchAppointments">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="no_show">No Show</option>
            <option value="rescheduled">Rescheduled</option>
            <option value="cancelled">Cancelled</option>
            <option value="completed">Completed</option>
          </select>
          <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
        </div>

        <!-- Appointments -->
        <div class="icard">
          <div v-if="loading" style="text-align:center;padding:44px">
            <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
          </div>
          <div v-else-if="appointments.length === 0" class="empty-state">
            <h3>No appointments found</h3>
            <p>TMDU appointments will appear here once scheduled from a Testing Record, or once a student picks a time for a self-schedulable slot.</p>
          </div>
          <div v-else>
            <div
              v-for="a in appointments"
              :key="a.id"
              style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-bottom:1px solid var(--cloud);transition:background .1s;cursor:pointer"
              @mouseover="$event.currentTarget.style.background='var(--foam)'"
              @mouseleave="$event.currentTarget.style.background=''"
              @click="openApptDetail(a)"
            >
              <div style="width:48px;text-align:center;background:var(--snow);border-radius:var(--r-sm);padding:6px 4px;flex-shrink:0;border:1px solid var(--cloud)">
                <div style="font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--fog)">{{ getMonth(a.appointment_date) }}</div>
                <div style="font-size:20px;font-weight:700;color:var(--forest);font-family:var(--serif);font-style:italic;line-height:1">{{ getDay(a.appointment_date) }}</div>
              </div>
              <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ a.student?.last_name }}, {{ a.student?.first_name }}</div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ a.appointment_code }}</div>
                  <div v-if="a.case?.case_number" style="font-size:11px;color:var(--moss);font-family:var(--mono);background:var(--mist);padding:1px 6px;border-radius:4px">{{ a.case.case_number }}</div>
                </div>
                <div style="font-size:11.5px;color:var(--stone);margin-top:2px">
                  {{ toTitleCase(a.appointment_type) }} · {{ a.start_time }} - {{ a.end_time }} · {{ a.staff?.name || 'TBA' }}
                </div>
                <div style="display:flex;gap:5px;margin-top:6px;flex-wrap:wrap">
                  <span class="ibadge" :class="'ibadge-' + a.status">{{ toTitleCase(a.status) }}</span>
                  <span v-if="a.location" style="font-size:11px;color:var(--stone)">📍 {{ a.location }}</span>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;max-width:220px;justify-content:flex-end">
                <button v-if="a.status === 'pending'" class="ibtn ibtn-o ibtn-sm" @click.stop="checkIn(a)">Student Attended</button>
                <button v-if="a.status === 'pending'" class="ibtn ibtn-sm" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click.stop="openNoShow(a)">No-Show</button>
                <button v-if="a.status === 'pending'" class="ibtn ibtn-sm" style="background:var(--blue-lt);color:var(--blue);border:1.5px solid var(--blue)" @click.stop="openReschedule(a)">Request Reschedule</button>
                <button v-if="a.status !== 'cancelled' && a.status !== 'completed'" class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click.stop="openCancel(a)">Cancel</button>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:var(--stone)">
              Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
            </span>
            <div style="display:flex;gap:6px">
              <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
              <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
            </div>
          </div>
        </div>

      </div>

      <!-- Right: Mini Calendar -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">{{ currentMonthLabel }}</span>
            <div style="display:flex;gap:4px">
              <button class="ibtn ibtn-g ibtn-sm" @click="prevMonth">‹</button>
              <button class="ibtn ibtn-g ibtn-sm" @click="nextMonth">›</button>
            </div>
          </div>
          <div style="padding:12px">
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;margin-bottom:4px">
              <div v-for="d in ['Su','Mo','Tu','We','Th','Fr','Sa']" :key="d" style="text-align:center;font-size:9px;font-weight:700;color:var(--fog);padding:3px">{{ d }}</div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px">
              <div
                v-for="day in calendarDays"
                :key="day.key"
                :title="day.apptTitle"
                style="aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:4px;font-size:10.5px;cursor:pointer;position:relative"
                :style="{
                  background: day.isToday ? 'var(--moss)' : day.isSelected ? 'var(--mist)' : '',
                  color: day.isToday ? '#fff' : day.isOther ? 'var(--silver)' : 'var(--slate)',
                  fontWeight: day.isToday ? '600' : '',
                  pointerEvents: day.isOther ? 'none' : 'auto',
                }"
                @click="day.isOther ? null : selectDayPreview(day)"
              >
                {{ day.date }}
                <span
                  v-if="day.apptCount > 0"
                  style="font-size:8px;line-height:1;margin-top:1px;padding:0 3px;border-radius:6px;font-weight:700"
                  :style="day.isToday ? 'background:rgba(255,255,255,.25);color:#fff' : 'background:var(--moss);color:#fff'"
                >
                  {{ day.apptCount }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Date Filter Indicator -->
        <div v-if="previewDate" style="display:flex;align-items:center;justify-content:space-between;gap:8px;background:var(--mist);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--ink)">
          <span>Filtering: <strong>{{ previewDateLabel }}</strong></span>
          <button class="ibtn ibtn-g ibtn-sm" @click="clearDateFilter">Clear ✕</button>
        </div>
      </div>
    </div>

    <!-- Appointment Detail Modal -->
    <div v-if="detailTarget" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="detailTarget = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:460px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div>
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Appointment Details</div>
            <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ detailTarget.appointment_code }}</div>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="detailTarget = null">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:12px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Student</div>
              <div style="font-size:13px;color:var(--ink)">{{ detailTarget.student?.last_name }}, {{ detailTarget.student?.first_name }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Type</div>
              <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(detailTarget.appointment_type) }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
              <span class="ibadge" :class="'ibadge-' + detailTarget.status">{{ toTitleCase(detailTarget.status) }}</span>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date</div>
              <div style="font-size:13px;color:var(--ink)">{{ formatApptDate(detailTarget.appointment_date) }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Time</div>
              <div style="font-size:13px;color:var(--ink)">{{ detailTarget.start_time }} - {{ detailTarget.end_time }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Staff</div>
              <div style="font-size:13px;color:var(--ink)">{{ detailTarget.staff?.name || 'TBA' }}</div>
            </div>
            <div v-if="detailTarget.reschedule_count">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Times Rescheduled</div>
              <div style="font-size:13px;color:var(--ink)">{{ detailTarget.reschedule_count }}</div>
            </div>
          </div>
          <div v-if="detailTarget.location">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Location</div>
            <div style="font-size:13px;color:var(--ink)">{{ detailTarget.location }}</div>
          </div>
          <div v-if="detailTarget.required_documents">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Required Documents</div>
            <div style="font-size:13px;color:var(--ink);background:var(--snow);padding:8px 10px;border-radius:var(--r-sm)">{{ detailTarget.required_documents }}</div>
          </div>
          <div v-if="detailTarget.notes">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Notes</div>
            <div style="font-size:13px;color:var(--ink)">{{ detailTarget.notes }}</div>
          </div>

          <div v-if="!['cancelled','completed'].includes(detailTarget.status)" style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--cloud);padding-top:14px;margin-top:4px">
            <button v-if="detailTarget.status === 'pending'" class="ibtn ibtn-o ibtn-sm" @click="checkIn(detailTarget); detailTarget = null">Mark Attended</button>
            <button v-if="detailTarget.status === 'pending'" class="ibtn ibtn-sm" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click="openNoShow(detailTarget); detailTarget = null">Mark No-Show</button>
            <button v-if="detailTarget.status === 'pending'" class="ibtn ibtn-sm" style="background:var(--blue-lt);color:var(--blue);border:1.5px solid var(--blue)" @click="openReschedule(detailTarget); detailTarget = null">Request Reschedule</button>
            <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openCancel(detailTarget); detailTarget = null">Cancel</button>
          </div>
          <button class="ibtn ibtn-o" style="width:100%;justify-content:center;margin-top:4px" @click="goToStudent(detailTarget)">View Student Profile</button>
        </div>
      </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div v-if="showCancelModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCancelModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Cancel Appointment</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone);line-height:1.6">
            This cannot be undone. The student will be notified that this appointment was cancelled.
          </div>
          <div>
            <label class="ifl">Reason for Cancellation <span style="color:var(--red)">*</span></label>
            <textarea v-model="cancelForm.cancellation_reason" class="ifta" style="min-height:80px" placeholder="Why is this appointment being cancelled?" maxlength="1000"></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="flex:1;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="submitCancel">Yes, Cancel Appointment</button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="showCancelModal = false">Never Mind</button>
          </div>
        </div>
      </div>
    </div>

    <!-- No-Show Confirmation Modal -->
    <div v-if="showNoShowModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showNoShowModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Mark as No-Show?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            This will mark <strong>{{ noShowTarget?.student?.last_name }}, {{ noShowTarget?.student?.first_name }}</strong>'s appointment as a no-show and escalate it to the Dean's Secretary.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="flex:1;justify-content:center;background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" :disabled="submittingNoShow" @click="submitNoShow">
              {{ submittingNoShow ? 'Marking...' : 'Yes, Mark No-Show' }}
            </button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="showNoShowModal = false">Never Mind</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Request Reschedule Modal -->
    <div v-if="showRescheduleModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showRescheduleModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Request Reschedule</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone);line-height:1.6">
            This will notify the student to pick a different appointment time from their dashboard.
          </div>
          <div>
            <label class="ifl">Reason for Reschedule <span style="color:var(--red)">*</span></label>
            <textarea v-model="rescheduleForm.reschedule_reason" class="ifta" style="min-height:80px" placeholder="Why does this need to be rescheduled?" maxlength="1000"></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="submitReschedule">Send Reschedule Request</button>
            <button class="ibtn ibtn-o" @click="showRescheduleModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { appointmentAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const toast  = inject('toast');
const router = useRouter();
const route  = useRoute();

// This page is TMDU-only - there's no Unit filter, every request is scoped
// to this unit. Kept as a constant rather than a ref so it can't drift.
const UNIT = 'TMDU';

// Present when reached from Testing Record Details' Appointments panel, so
// staff can get back to exactly the record they came from.
const returnTo = computed(() => (typeof route.query.return_to === 'string' ? route.query.return_to : ''));

const loading    = ref(true);
const appointments = ref([]);
const allAppointments = ref([]);
const pagination = ref({});
// B282: defaulted to 'pending' before, hiding every other status by default.
const filters = ref({ status: '', date: '' });

const showRescheduleModal = ref(false);
const rescheduleTarget    = ref(null);
const rescheduleForm      = ref({ reschedule_reason: '' });

const showCancelModal = ref(false);
const cancelTarget    = ref(null);
const cancelForm      = ref({ cancellation_reason: '' });

const detailTarget = ref(null);

function openApptDetail(a) {
  detailTarget.value = a;
}

function formatApptDate(date) {
  return date ? new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }) : '-';
}

const today        = new Date();
const currentMonth = ref(today.getMonth());
const currentYear  = ref(today.getFullYear());
const selectedDate = ref(today);

const previewDate = ref(null);

const previewDateLabel = computed(() => {
  if (!previewDate.value) return '';
  return new Date(previewDate.value + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
});

function selectDayPreview(day) {
  if (day.isOther || !day.dateStr) return;
  selectedDate.value = new Date(day.dateStr);
  previewDate.value = day.dateStr;
  filters.value.date = day.dateStr;
  fetchAppointments();
}

function clearDateFilter() {
  previewDate.value = null;
  filters.value.date = '';
  fetchAppointments();
}

async function fetchAllAppointments() {
  try {
    const res = await appointmentAPI.index({ unit: UNIT, per_page: 1000 });
    allAppointments.value = res.data.data;
  } catch (e) {
    console.error(e);
  }
}

function goToStudent(a) {
  const studentId = a.student_id || a.student?.id;
  if (studentId) {
    router.push({ name: 'student-show', params: { id: studentId } });
  } else {
    toast?.error('No linked student found for this appointment.');
  }
}

async function fetchAppointments(page = 1) {
  loading.value = true;
  try {
    const res = await appointmentAPI.index({ ...filters.value, unit: UNIT, page });
    appointments.value = res.data.data;
    pagination.value   = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function checkIn(a) {
  try {
    await appointmentAPI.checkIn(a.id);
    a.status = 'completed';
    toast?.success('Student checked in.');
    fetchAllAppointments();
  } catch (e) {
    toast?.error('Failed to check in.');
  }
}

const showNoShowModal   = ref(false);
const noShowTarget      = ref(null);
const submittingNoShow  = ref(false);

function openNoShow(a) {
  noShowTarget.value = a;
  showNoShowModal.value = true;
}

async function submitNoShow() {
  if (!noShowTarget.value) return;
  submittingNoShow.value = true;
  try {
    await appointmentAPI.escalateNoShow(noShowTarget.value.id, 'call_slip');
    noShowTarget.value.status = 'no_show';
    noShowTarget.value.no_show_escalated = true;
    toast?.success("Marked as no-show and escalated to Dean's Secretary.");
    showNoShowModal.value = false;
    fetchAllAppointments();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark as no-show.');
  } finally {
    submittingNoShow.value = false;
  }
}

function openCancel(a) {
  cancelTarget.value = a;
  cancelForm.value = { cancellation_reason: '' };
  showCancelModal.value = true;
}

async function submitCancel() {
  if (!cancelForm.value.cancellation_reason) {
    toast?.error('Please provide a reason for the cancellation.');
    return;
  }
  try {
    await appointmentAPI.cancel(cancelTarget.value.id, cancelForm.value);
    cancelTarget.value.status = 'cancelled';
    showCancelModal.value = false;
    toast?.success('Appointment cancelled.');
    fetchAllAppointments();
  } catch (e) {
    toast?.error('Failed to cancel appointment.');
  }
}

function openReschedule(a) {
  rescheduleTarget.value = a;
  rescheduleForm.value = { reschedule_reason: '' };
  showRescheduleModal.value = true;
}

async function submitReschedule() {
  if (!rescheduleForm.value.reschedule_reason) {
    toast?.error('Please provide a reason for the reschedule.');
    return;
  }
  try {
    await appointmentAPI.reschedule(rescheduleTarget.value.id, rescheduleForm.value);
    toast?.success('Reschedule request sent to student.');
    showRescheduleModal.value = false;
    fetchAppointments();
    fetchAllAppointments();
  } catch (e) {
    toast?.error('Failed to send reschedule request.');
  }
}

function changePage(page) { fetchAppointments(page); }

function resetFilters() {
  filters.value = { status: '', date: '' };
  previewDate.value = null;
  fetchAppointments();
}

const currentMonthLabel = computed(() => {
  return new Date(currentYear.value, currentMonth.value).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const calendarDays = computed(() => {
  const days = [];
  const firstDay    = new Date(currentYear.value, currentMonth.value, 1).getDay();
  const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
  const daysInPrev  = new Date(currentYear.value, currentMonth.value, 0).getDate();

  for (let i = firstDay - 1; i >= 0; i--) {
    days.push({ date: daysInPrev - i, isOther: true, key: `prev-${i}`, hasAppt: false, apptCount: 0, apptTitle: '' });
  }
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr    = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isToday    = d === today.getDate() && currentMonth.value === today.getMonth() && currentYear.value === today.getFullYear();
    const isSelected = d === selectedDate.value.getDate() && currentMonth.value === selectedDate.value.getMonth() && currentYear.value === selectedDate.value.getFullYear();
    const dayAppts   = allAppointments.value.filter(a => a.appointment_date?.split('T')[0] === dateStr && ['pending', 'confirmed'].includes(a.status));
    const apptTitle  = dayAppts.map(a => `${a.start_time} - ${a.student?.student_id || ''}`).join('\n');
    days.push({ date: d, isToday, isSelected, isOther: false, key: `cur-${d}`, hasAppt: dayAppts.length > 0, apptCount: dayAppts.length, apptTitle, dateStr });
  }
  const remaining = 42 - days.length;
  for (let i = 1; i <= remaining; i++) {
    days.push({ date: i, isOther: true, key: `next-${i}`, hasAppt: false, apptCount: 0, apptTitle: '' });
  }
  return days;
});

function prevMonth() {
  if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value--; }
  else currentMonth.value--;
}

function nextMonth() {
  if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++; }
  else currentMonth.value++;
}

function getMonth(date) { return new Date(date).toLocaleDateString('en-US', { month: 'short' }); }
function getDay(date)   { return new Date(date).getDate(); }

onMounted(() => {
  fetchAppointments();
  fetchAllAppointments();
});
</script>