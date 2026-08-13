<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Appointment Calendar</h1>
      <p>View, schedule, and manage counseling sessions and conferences.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">

      <!-- Left: Appointments List -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Filter Bar -->
        <div class="filter-bar">
          <select v-model="filters.unit" class="fsm" @change="fetchAppointments">
            <option value="">All Units</option>
            <option value="GCU">GCU</option>
            <option value="SDU">SDU</option>
            <option value="TMDU">TMDU</option>
          </select>
          <select v-model="filters.status" class="fsm" @change="fetchAppointments">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
          </select>
          <input v-model="filters.date" type="date" class="ifi" style="width:160px" @change="fetchAppointments" />
          <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
          <button class="ibtn ibtn-p ibtn-sm" style="margin-left:auto" @click="showScheduleModal = true">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Schedule
          </button>
        </div>

        <!-- Appointments -->
        <div class="icard">
          <div v-if="loading" style="text-align:center;padding:44px">
            <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
          </div>
          <div v-else-if="appointments.length === 0" class="empty-state">
            <h3>No appointments found</h3>
            <p>Try adjusting your filters or schedule a new appointment.</p>
          </div>
          <div v-else>
            <div
              v-for="a in appointments"
              :key="a.id"
              style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-bottom:1px solid var(--cloud);transition:background .1s"
              @mouseover="$event.currentTarget.style.background='var(--foam)'"
              @mouseleave="$event.currentTarget.style.background=''"
            >
              <div style="width:48px;text-align:center;background:var(--snow);border-radius:var(--r-sm);padding:6px 4px;flex-shrink:0;border:1px solid var(--cloud)">
                <div style="font-size:9px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--fog)">{{ getMonth(a.appointment_date) }}</div>
                <div style="font-size:20px;font-weight:700;color:var(--forest);font-family:var(--serif);font-style:italic;line-height:1">{{ getDay(a.appointment_date) }}</div>
              </div>
              <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ a.student?.first_name }} {{ a.student?.last_name }}</div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ a.appointment_code }}</div>
                </div>
                <div style="font-size:11.5px;color:var(--stone);margin-top:2px">
                  {{ a.appointment_type?.replace(/_/g,' ') }} · {{ a.start_time }} – {{ a.end_time }} · {{ a.staff?.name || 'TBA' }}
                </div>
                <div style="display:flex;gap:5px;margin-top:6px;flex-wrap:wrap">
                  <span class="ibadge" :class="'ibadge-' + a.status">{{ a.status }}</span>
                  <span class="ibadge" :class="'unit-' + a.unit?.toLowerCase()">{{ a.unit }}</span>
                  <span v-if="a.location" style="font-size:11px;color:var(--stone)">📍 {{ a.location }}</span>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0">
                <button v-if="a.status === 'pending'" class="ibtn ibtn-p ibtn-sm" @click="confirmAppt(a)">Confirm</button>
                <button v-if="a.status === 'confirmed'" class="ibtn ibtn-o ibtn-sm" @click="checkIn(a)">Check In</button>
                <button v-if="a.status !== 'cancelled' && a.status !== 'completed'" class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="cancelAppt(a)">Cancel</button>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:12px;color:var(--stone)">
              Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
            </span>
            <div style="display:flex;gap:6px">
              <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
              <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
            </div>
          </div>
        </div>

      </div>

      <!-- Right: Booking Panel -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Mini Calendar -->
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
                style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:4px;font-size:10.5px;cursor:pointer;position:relative"
                :style="{
                  background: day.isToday ? 'var(--moss)' : day.isSelected ? 'var(--mist)' : '',
                  color: day.isToday ? '#fff' : day.isOther ? 'var(--silver)' : 'var(--slate)',
                  fontWeight: day.isToday ? '600' : '',
                  pointerEvents: day.isOther ? 'none' : 'auto',
                }"
                @click="day.isOther ? null : selectDay(day)"
              >
                {{ day.date }}
                <span v-if="day.hasAppt && !day.isToday" style="position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:var(--moss)"></span>
                <span v-if="day.hasAppt && day.isToday" style="position:absolute;bottom:2px;left:50%;transform:translateX(-50%);width:4px;height:4px;border-radius:50%;background:rgba(255,255,255,.7)"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Available Slots -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Available Slots</span></div>
          <div style="padding:14px 16px">
            <div style="font-size:11px;color:var(--stone);margin-bottom:10px">{{ selectedDateLabel }}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <div
                v-for="slot in timeSlots"
                :key="slot.time"
                style="padding:6px 12px;border:1.5px solid var(--silver);border-radius:var(--r-sm);font-size:12px;transition:all .1s"
                :style="{
                  background: selectedSlot === slot.time ? 'var(--moss)' : '#fff',
                  borderColor: selectedSlot === slot.time ? 'var(--moss)' : 'var(--silver)',
                  color: selectedSlot === slot.time ? '#fff' : 'var(--slate)',
                  cursor: 'pointer',
                }"
                @click="selectedSlot = slot.time; scheduleForm.start_time = slot.time"
              >
                {{ slot.time }}
              </div>
            </div>
            <button class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:12px" @click="showScheduleModal = true" :disabled="!selectedSlot">
              Book {{ selectedSlot || 'a slot' }}
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- Schedule Modal -->
    <div v-if="showScheduleModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showScheduleModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:500px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Schedule Appointment</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showScheduleModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">

          <!-- Case -->
          <div>
            <label class="ifl">Case <span style="color:var(--red)">*</span></label>
            <select v-model="scheduleForm.case_id" class="ifse" @change="onCaseChange">
              <option value="">Select case...</option>
              <option v-for="c in cases" :key="c.id" :value="c.id">
                {{ c.case_number }} — {{ c.student?.first_name }} {{ c.student?.last_name }}
              </option>
            </select>
          </div>

          <!-- Type -->
          <div>
            <label class="ifl">Appointment Type <span style="color:var(--red)">*</span></label>
            <select v-model="scheduleForm.appointment_type" class="ifse">
              <option value="">Select type...</option>
              <option value="initial_counseling">Initial Counseling</option>
              <option value="follow_up_session">Follow Up Session</option>
              <option value="psychological_testing">Psychological Testing</option>
              <option value="disciplinary_conference">Disciplinary Conference</option>
              <option value="parent_conference">Parent Conference</option>
              <option value="academic_coaching">Academic Coaching</option>
            </select>
          </div>

          <!-- Unit -->
          <div>
            <label class="ifl">Unit <span style="color:var(--red)">*</span></label>
            <select v-model="scheduleForm.unit" class="ifse">
              <option value="GCU">GCU — Guidance &amp; Counseling Unit</option>
              <option value="SDU">SDU — Student Discipline Unit</option>
              <option value="TMDU">TMDU — Testing &amp; Measurement</option>
            </select>
          </div>

          <!-- Date & Time -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Date <span style="color:var(--red)">*</span></label>
              <input v-model="scheduleForm.appointment_date" type="date" class="ifi" />
            </div>
            <div>
              <label class="ifl">Start Time <span style="color:var(--red)">*</span></label>
              <select v-model="scheduleForm.start_time" class="ifse">
                <option value="">Select...</option>
                <option value="08:00">08:00 AM</option>
                <option value="09:00">09:00 AM</option>
                <option value="10:00">10:00 AM</option>
                <option value="11:00">11:00 AM</option>
                <option value="13:00">01:00 PM</option>
                <option value="14:00">02:00 PM</option>
                <option value="15:00">03:00 PM</option>
                <option value="16:00">04:00 PM</option>
              </select>
            </div>
          </div>

          <!-- End Time -->
          <div>
            <label class="ifl">End Time <span style="color:var(--red)">*</span></label>
            <select v-model="scheduleForm.end_time" class="ifse">
              <option value="">Select...</option>
              <option value="09:00">09:00 AM</option>
              <option value="10:00">10:00 AM</option>
              <option value="11:00">11:00 AM</option>
              <option value="12:00">12:00 PM</option>
              <option value="14:00">02:00 PM</option>
              <option value="15:00">03:00 PM</option>
              <option value="16:00">04:00 PM</option>
              <option value="17:00">05:00 PM</option>
            </select>
          </div>

          <!-- Location -->
          <div>
            <label class="ifl">Location</label>
            <input v-model="scheduleForm.location" class="ifi" placeholder="e.g. GCU Office, Room 201" />
          </div>

          <!-- Notes -->
          <div>
            <label class="ifl">Notes</label>
            <textarea v-model="scheduleForm.notes" class="ifta" style="min-height:60px" placeholder="Any special instructions..."></textarea>
          </div>

          <!-- Conflict Warning -->
          <div v-if="conflictWarning" style="background:var(--red-lt);border:1px solid #f5c0c0;border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--red)">
            ⚠ Scheduling conflict detected. Please choose a different time.
          </div>

          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="scheduleAppointment">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              Confirm Appointment
            </button>
            <button class="ibtn ibtn-o" @click="showScheduleModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { appointmentAPI, caseAPI } from '../../api/index';

const toast = inject('toast');

const loading           = ref(true);
const showScheduleModal = ref(false);
const selectedSlot      = ref('');
const conflictWarning   = ref(false);
const appointments      = ref([]);
const pagination        = ref({});
const cases             = ref([]);
const filters           = ref({ unit: '', status: '', date: '' });

const today        = new Date();
const currentMonth = ref(today.getMonth());
const currentYear  = ref(today.getFullYear());
const selectedDate = ref(today);

const scheduleForm = ref({
  case_id: '', student_id: '',
  appointment_type: '', unit: 'GCU',
  appointment_date: '', start_time: '', end_time: '',
  location: '', notes: '',
});

const timeSlots = [
  { time: '08:00' }, { time: '09:00' }, { time: '10:00' }, { time: '11:00' },
  { time: '13:00' }, { time: '14:00' }, { time: '15:00' }, { time: '16:00' },
];

async function fetchAppointments(page = 1) {
  loading.value = true;
  try {
    const res = await appointmentAPI.index({ ...filters.value, page });
    appointments.value = res.data.data;
    pagination.value   = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function fetchCases() {
  try {
    const res = await caseAPI.index({ status: 'open' });
    cases.value = res.data.data || [];
  } catch (e) { console.error(e); }
}

async function confirmAppt(a) {
  try {
    await appointmentAPI.confirm(a.id);
    a.status = 'confirmed';
    toast?.success('Appointment confirmed.');
  } catch (e) {
    toast?.error('Failed to confirm appointment.');
  }
}

async function checkIn(a) {
  try {
    await appointmentAPI.checkIn(a.id);
    a.status = 'completed';
    toast?.success('Student checked in.');
  } catch (e) {
    toast?.error('Failed to check in.');
  }
}

async function cancelAppt(a) {
  try {
    await appointmentAPI.cancel(a.id, { cancellation_reason: 'Cancelled by staff.' });
    a.status = 'cancelled';
    toast?.success('Appointment cancelled.');
  } catch (e) {
    toast?.error('Failed to cancel appointment.');
  }
}

function onCaseChange() {
  const selected = cases.value.find(c => c.id === scheduleForm.value.case_id);
  if (selected) {
    scheduleForm.value.student_id = selected.student_id;
  }
}

async function scheduleAppointment() {
  conflictWarning.value = false;
  if (!scheduleForm.value.case_id ||
      !scheduleForm.value.appointment_type ||
      !scheduleForm.value.appointment_date ||
      !scheduleForm.value.start_time ||
      !scheduleForm.value.end_time) {
    toast?.error('Please fill in all required fields.');
    return;
  }
  try {
    await appointmentAPI.store(scheduleForm.value);
    toast?.success('Appointment scheduled successfully.');
    showScheduleModal.value = false;
    fetchAppointments();
    scheduleForm.value = {
      case_id: '', student_id: '',
      appointment_type: '', unit: 'GCU',
      appointment_date: '', start_time: '', end_time: '',
      location: '', notes: '',
    };
    selectedSlot.value = '';
  } catch (e) {
    if (e.response?.status === 422) {
      conflictWarning.value = true;
    } else {
      toast?.error('Failed to schedule appointment.');
    }
  }
}

function changePage(page) { fetchAppointments(page); }

function resetFilters() {
  filters.value = { unit: '', status: '', date: '' };
  fetchAppointments();
}

const currentMonthLabel = computed(() => {
  return new Date(currentYear.value, currentMonth.value).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

const selectedDateLabel = computed(() => {
  return selectedDate.value.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
});

const calendarDays = computed(() => {
  const days = [];
  const firstDay    = new Date(currentYear.value, currentMonth.value, 1).getDay();
  const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
  const daysInPrev  = new Date(currentYear.value, currentMonth.value, 0).getDate();

  for (let i = firstDay - 1; i >= 0; i--) {
    days.push({ date: daysInPrev - i, isOther: true, key: `prev-${i}`, hasAppt: false });
  }
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr    = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const isToday    = d === today.getDate() && currentMonth.value === today.getMonth() && currentYear.value === today.getFullYear();
    const isSelected = d === selectedDate.value.getDate() && currentMonth.value === selectedDate.value.getMonth() && currentYear.value === selectedDate.value.getFullYear();
    const hasAppt    = appointments.value.some(a => a.appointment_date === dateStr);
    days.push({ date: d, isToday, isSelected, isOther: false, key: `cur-${d}`, hasAppt, dateStr });
  }
  const remaining = 42 - days.length;
  for (let i = 1; i <= remaining; i++) {
    days.push({ date: i, isOther: true, key: `next-${i}`, hasAppt: false });
  }
  return days;
});

function selectDay(day) {
  if (day.isOther || !day.dateStr) return;
  selectedDate.value = new Date(day.dateStr);
  filters.value.date = day.dateStr;
  scheduleForm.value.appointment_date = day.dateStr;
  fetchAppointments();
}

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
  fetchCases();
});
</script>