<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Appointment Calendar</h1>
      <p>Confirm, reschedule, or manage student-requested appointments.</p>
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
        </div>

        <!-- Appointments -->
        <div class="icard">
          <div v-if="loading" style="text-align:center;padding:44px">
            <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
          </div>
          <div v-else-if="appointments.length === 0" class="empty-state">
            <h3>No appointments found</h3>
            <p>Appointments will appear here once students request them from their acknowledged referrals.</p>
          </div>
          <div v-else>
            <div
              v-for="a in appointments"
              :key="a.id"
              style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-bottom:1px solid var(--cloud);transition:background .1s;cursor:pointer"
              @mouseover="$event.currentTarget.style.background='var(--foam)'"
              @mouseleave="$event.currentTarget.style.background=''"
              @click="goToCase(a)"
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
                  {{ toTitleCase(a.appointment_type) }} · {{ a.start_time }} – {{ a.end_time }} · {{ a.staff?.name || 'TBA' }}
                </div>
                <div style="display:flex;gap:5px;margin-top:6px;flex-wrap:wrap">
                  <span class="ibadge" :class="'ibadge-' + a.status">{{ toTitleCase(a.status) }}</span>
                  <span class="ibadge" :class="'unit-' + a.unit?.toLowerCase()">{{ a.unit }}</span>
                  <span v-if="a.request_status === 'awaiting_student'" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">Awaiting Student</span>
                  <span v-if="a.location" style="font-size:11px;color:var(--stone)">📍 {{ a.location }}</span>
                </div>
              </div>
              <div style="display:flex;gap:6px;flex-shrink:0;flex-wrap:wrap;max-width:220px;justify-content:flex-end">
                <button v-if="a.status === 'pending' && a.request_status !== 'awaiting_student'" class="ibtn ibtn-p ibtn-sm" @click.stop="confirmAppt(a)">Confirm</button>
                <button v-if="a.status === 'confirmed'" class="ibtn ibtn-o ibtn-sm" @click.stop="checkIn(a)">Check In</button>
                <button v-if="a.status === 'confirmed'" class="ibtn ibtn-sm" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click.stop="markNoShow(a)">No-Show</button>
                <button v-if="['pending','confirmed'].includes(a.status) && a.request_status !== 'awaiting_student'" class="ibtn ibtn-sm" style="background:var(--blue-lt);color:var(--blue);border:1.5px solid var(--blue)" @click.stop="openReschedule(a)">Request Reschedule</button>
                <button v-if="a.status !== 'cancelled' && a.status !== 'completed'" class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click.stop="cancelAppt(a)">Cancel</button>
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
      </div>
    </div>

    <!-- Reschedule Request Modal -->
    <div v-if="showRescheduleModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showRescheduleModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Request Reschedule</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showRescheduleModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
        <template v-if="!newSchedulingLink">
          <div style="font-size:13px;color:var(--stone);line-height:1.6">
            This will generate a new scheduling link for the student to pick a different time.
          </div>
          <div>
            <label class="ifl">Reason for Reschedule <span style="color:var(--red)">*</span></label>
            <textarea v-model="rescheduleForm.reschedule_reason" class="ifta" style="min-height:80px" placeholder="Why does this need to be rescheduled?"></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="submitReschedule">Send Reschedule Request</button>
            <button class="ibtn ibtn-o" @click="showRescheduleModal = false">Cancel</button>
          </div>
        </template>
        <template v-else>
          <div style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:14px;font-size:13px;color:var(--forest)">
            ✓ Reschedule request created. Share this link with the student:
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <input :value="newSchedulingLink" readonly class="ifi" style="font-family:var(--mono);font-size:12px" @click="$event.target.select()" />
            <button class="ibtn ibtn-o ibtn-sm" @click="copyLink">Copy</button>
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="closeRescheduleModal">Done</button>
        </template>
      </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import { appointmentAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const toast  = inject('toast');
const router = useRouter();

const loading    = ref(true);
const appointments = ref([]);
const pagination = ref({});
const filters    = ref({ unit: '', status: '', date: '' });

const showRescheduleModal = ref(false);
const rescheduleTarget    = ref(null);
const rescheduleForm      = ref({ reschedule_reason: '' });
const newSchedulingLink = ref('');

const today        = new Date();
const currentMonth = ref(today.getMonth());
const currentYear  = ref(today.getFullYear());
const selectedDate = ref(today);

function goToCase(a) {
  if (a.case_id) {
    router.push({ name: 'case-show', params: { id: a.case_id } });
  } else {
    toast?.error('No linked case found for this appointment.');
  }
}

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

async function confirmAppt(a) {
  try {
    await appointmentAPI.confirm(a.id);
    a.status = 'confirmed';
    a.request_status = 'confirmed';
    toast?.success('Appointment confirmed. Student will be notified.');
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

async function markNoShow(a) {
  try {
    await appointmentAPI.escalateNoShow(a.id);
    a.status = 'no_show';
    a.no_show_escalated = true;
    toast?.success("Marked as no-show and escalated to Dean's Secretary.");
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark as no-show.');
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

function openReschedule(a) {
  rescheduleTarget.value = a;
  rescheduleForm.value = { reschedule_reason: '' };
  newSchedulingLink.value = '';
  showRescheduleModal.value = true;
}

async function submitReschedule() {
  if (!rescheduleForm.value.reschedule_reason) {
    toast?.error('Please provide a reason for the reschedule.');
    return;
  }
  try {
    const res = await appointmentAPI.reschedule(rescheduleTarget.value.id, rescheduleForm.value);
    newSchedulingLink.value = res.data.scheduling_link;
    toast?.success('Reschedule request created.');
    fetchAppointments();
  } catch (e) {
    toast?.error('Failed to send reschedule request.');
  }
}

function copyLink() {
  navigator.clipboard.writeText(newSchedulingLink.value);
  toast?.success('Link copied to clipboard.');
}

function closeRescheduleModal() {
  showRescheduleModal.value = false;
  newSchedulingLink.value = '';
}

function changePage(page) { fetchAppointments(page); }

function resetFilters() {
  filters.value = { unit: '', status: '', date: '' };
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
});
</script>