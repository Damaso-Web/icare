<template>
  <div class="fade-up">
    <div class="hero">
      <div class="hero-row">
        <div>
          <div class="hero-kicker">Student Portal</div>
          <h1>{{ greeting }}, {{ student.first_name }}!</h1>
          <p>Your appointments, referrals and testing with the Office of Student Services, in one place.</p>
          <div class="hero-chips">
            <span v-for="(c, i) in infoChips" :key="i" class="hero-chip">{{ c }}</span>
          </div>
          <div v-if="termLabel" class="hero-term">{{ termLabel }}</div>
          <!-- Counts of the referrals the student themselves sent (self-
               referrals), same My Referrals/Awaiting/Received/Completed
               split the staff Dashboard uses for its own "Sent Referrals"
               stat cards - clicking one opens Sent Referrals pre-filtered
               to that status group. -->
          <div class="hero-stats">
            <button v-for="stat in referralStats" :key="stat.label" type="button" class="hero-stat" @click="goToStat(stat)">
              <span class="hero-stat-top">
                <span class="hero-stat-icon"><svg viewBox="0 0 24 24" v-html="stat.icon"></svg></span>
                <span class="hero-stat-period">{{ stat.period }}</span>
              </span>
              <span class="hero-stat-num">{{ stat.value }}</span>
              <span class="hero-stat-label">{{ stat.label }}</span>
            </button>
          </div>
        </div>
        <div class="hero-actions hero-actions-side">
          <router-link :to="{ name: 'student-appointments' }" class="hero-btn"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>My Appointments</router-link>
          <router-link :to="{ name: 'student-referrals' }" class="hero-btn"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>My Referrals</router-link>
        </div>
      </div>
    </div>

    <div v-if="student.must_change_password" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:14px 16px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div style="font-size:13px;color:var(--amber)">⚠ Please change your temporary password.</div>
      <router-link :to="{ name: 'student-account' }" class="ibtn ibtn-sm" style="background:var(--amber);color:#fff">Change Now</router-link>
    </div>

    <div v-if="pendingAppointments.length" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
    <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
      <div>
        <div style="font-size:14px;font-weight:600;color:var(--ink)">📅 You have {{ pendingAppointments.length }} pending appointment request{{ pendingAppointments.length > 1 ? 's' : '' }}</div>
        <div style="font-size:12px;color:var(--stone);margin-top:2px">Please choose your preferred date and time{{ pendingAppointments.length > 1 ? ' - one at a time' : '' }}.</div>
      </div>
          <!-- Bug (10/25/2026): this used to jump straight into the FIRST
               pending appointment's own scheduling page, which was
               confusing/wrong whenever there was more than one pending
               request (e.g. 5 here) - there was no way to tell which
               referral it would open, and no way to reach the others from
               here. My Appointments already lists every pending request
               with its own "Schedule Now" button (one at a time, as this
               banner's own text says), so this now goes there instead. -->
          <router-link :to="{ name: 'student-appointments' }" class="ibtn ibtn-p">Schedule Now</router-link>
    </div>
  </div>

    <div v-if="parentConferenceSlips.length" class="icard" style="border:2px solid var(--amber);margin-bottom:20px">
      <div class="icard-header"><span class="icard-title">Parent Conference Slip</span></div>
      <div v-for="slip in parentConferenceSlips" :key="slip.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
        <div style="font-size:13px;font-weight:600;color:var(--ink)">Your parent/guardian is asked to come to the office on {{ formatSlipDate(slip.conference_date) }}<span v-if="slip.conference_time"> at {{ formatTime12(slip.conference_time) }}</span>.</div>
        <div style="font-size:12px;color:var(--slate);margin-top:3px">Reason: {{ slip.reason }}</div>
        <div v-if="slip.remarks" style="font-size:11.5px;color:var(--stone);margin-top:3px;font-style:italic">{{ slip.remarks }}</div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Recent Appointments</span></div>
        <div v-if="loading" style="padding:30px;text-align:center">
          <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
        </div>
        <div v-else-if="appointments.length === 0" class="empty-state">
          <h3>No appointments yet</h3>
        </div>
        <div v-else>
          <div
            v-for="a in appointments.slice(0, 3)"
            :key="a.id"
            style="padding:14px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
            @click="$router.push({ name: 'student-appointment-show', params: { id: a.id } })"
          >
            <!-- An appointment still awaiting the student's own schedule
                 pick (see the "pending appointment request" banner above)
                 is a real DB row with a placeholder date/time - it never
                 had a real one, so don't show it as if it did. -->
            <div v-if="a.request_status === 'awaiting_student' && !isExpiredAppointment(a)" style="font-size:13px;font-weight:600;color:var(--stone);font-style:italic">
              Awaiting your schedule selection
            </div>
            <div v-else-if="isExpiredAppointment(a)" style="font-size:13px;font-weight:600;color:var(--stone);font-style:italic">
              Scheduling window expired
            </div>
            <div v-else style="font-size:13px;font-weight:600;color:var(--ink)">{{ formatDateShort(a.appointment_date) }} · {{ formatTime12(a.start_time) }}</div>
            <span class="ibadge" :class="apptBadgeKind(a)" style="margin-top:4px;display:inline-block">{{ apptBadgeLabel(a) }}</span>
          </div>
        </div>
      </div>

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Recent Referrals</span></div>
        <div v-if="referrals.length === 0" class="empty-state">
          <h3>No referrals yet</h3>
        </div>
        <div v-else>
          <div
            v-for="r in referrals.slice(0, 3)"
            :key="r.id"
            style="padding:14px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
            @click="$router.push({ name: 'student-referral-show', params: { id: r.id } })"
          >
            <div style="font-size:13px;font-weight:600;color:var(--ink);font-family:var(--mono)">{{ r.referral_code }}</div>
            <span class="ibadge" :class="referralBadgeKind(r)" style="margin-top:4px;display:inline-block">{{ referralBadgeLabel(r) }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import { useAutoRefresh } from '../../utils/useAutoRefresh';
import axios from 'axios';
import { formatTime12, formatDateShort } from '../../utils/validators';

const router = useRouter();

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));
// Greeting by Philippine time, same wording as the staff dashboard.
const phHour = () => Number(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila', hour: 'numeric', hour12: false })) % 24;
const hour = ref(phHour());
const hourTimer = setInterval(() => { hour.value = phHour(); }, 60000);
onBeforeUnmount(() => clearInterval(hourTimer));
const greeting = computed(() => hour.value < 12 ? 'Good morning' : hour.value < 18 ? 'Good afternoon' : 'Good evening');
const loading = ref(true);
const appointments = ref([]);
const referrals = ref([]);
const parentConferenceSlips = ref([]);
function formatSlipDate(d) { return d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : '-'; }
const pendingAppointments = ref([]);
const referralStatsRaw = ref({});

// Same My Referrals/Awaiting/Received/Completed stat-card set the staff
// Dashboard shows for Dean/Dept Chair/Faculty (Dashboard.vue's `stats`
// computed) - here it's scoped to only the referrals the student
// themselves sent (self-referrals), per `referral_stats` from the
// student/dashboard endpoint. Clicking a card opens Sent Referrals
// pre-filtered to that status group, same as the staff version.
const referralStats = computed(() => {
  const s = referralStatsRaw.value || {};
  return [
    { label: 'My Referrals', value: s.total     ?? 0, period: 'Total',    icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>', status: '' },
    { label: 'Awaiting',     value: s.awaiting  ?? 0, period: 'Pending',  icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', status: 'submitted' },
    { label: 'Received',     value: s.received  ?? 0, period: 'Acknowledged', icon: '<polyline points="20 6 9 17 4 12"/>', status: 'acknowledged,scheduled,in_progress,referred_tmdu,referred_external' },
    { label: 'Completed',    value: s.completed ?? 0, period: 'Resolved', icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', status: 'completed,closed' },
  ];
});
function goToStat(stat) {
  router.push({ name: 'student-sent-referrals', query: stat.status ? { status: stat.status } : {} });
}

const term = ref(null);
// ID · College · Program · Year level · Section, then "AY 2026–2027 · 1st Semester".
const infoChips = computed(() => {
  const s = student.value;
  return [s.student_id, s.college, s.program, s.year_level, s.section].map(v => String(v ?? '').trim()).filter(Boolean);
});
const termLabel = computed(() => (term.value?.academic_year ? ['AY ' + term.value.academic_year, term.value.semester].filter(Boolean).join(' · ') : ''));

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/\b(Sdu|Gcu|Tmdu|Oss|Sif|Par)\b/g, m => m.toUpperCase());
}

// Bug (11/3/2026): the Recent Appointments / Recent Referrals widgets here
// just showed the raw status (e.g. "Cancelled") with no idea that the
// 7-day scheduling window had lapsed, unlike the dedicated My Appointments
// and My Referrals pages which already distinguish "Expired" from a plain
// "Pending"/raw status - same isExpiredPending pattern as
// student/Appointments.vue and student/Referrals.vue, reused here so the
// dashboard agrees with those pages instead of showing something stale or
// misleading at a glance.
function isExpiredAppointment(a) {
  if (a.status !== 'pending') return false;
  return !!a.token_expires_at && new Date(a.token_expires_at) < new Date();
}
function apptBadgeKind(a) {
  return isExpiredAppointment(a) ? 'ibadge-expired' : 'ibadge-' + a.status;
}
function apptBadgeLabel(a) {
  return isExpiredAppointment(a) ? 'Expired' : toTitleCase(a.status);
}

// Bug (11/4/2026): "cancelled" was never in the completed/closed list below,
// so it fell through to the "everything else" default and showed as
// "Pending" on this dashboard card - same bug as student/Referrals.vue had,
// fixed the same way there, mirrored here so the two agree instead of a
// cancelled referral reading "Cancelled" on its own detail page but
// "Pending" here. Not folded into "closed" either - "closed" implies
// resolved, which a cancelled referral is not.
function simplifiedStatus(status) {
  if (status === 'cancelled') return 'cancelled';
  return ['completed', 'closed'].includes(status) ? 'closed' : 'pending';
}
function isExpiredReferral(referral) {
  if (simplifiedStatus(referral.status) !== 'pending') return false;
  const now = new Date();
  return pendingAppointments.value.some(a => {
    const matches = a.referral_id === referral.id || (!a.referral_id && a.case_id === referral.case_id);
    return matches && a.token_expires_at && new Date(a.token_expires_at) < now;
  });
}
// B#, 11/4/2026: same "Cancellation Request" override as student/
// Referrals.vue's badge, for consistency between the two places a
// student sees their own referrals' status.
function referralBadgeKind(r) {
  if (r.void_requested_at) return 'ibadge-cancellation_request';
  const s = simplifiedStatus(r.status);
  if (s === 'cancelled' || s === 'closed') return 'ibadge-' + s;
  return isExpiredReferral(r) ? 'ibadge-expired' : 'ibadge-pending';
}
function referralBadgeLabel(r) {
  if (r.void_requested_at) return 'Cancellation Request';
  const s = simplifiedStatus(r.status);
  if (s === 'cancelled') return 'Cancelled';
  if (s === 'closed') return 'Closed';
  return isExpiredReferral(r) ? 'Expired' : 'Pending';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

async function fetchData(silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, authHeaders());
    appointments.value = res.data.appointments || [];
    referrals.value = res.data.referrals || [];
    referralStatsRaw.value = res.data.referral_stats || {};
    parentConferenceSlips.value = res.data.parent_conference_slips || [];
    term.value = res.data.term || null;
    if (res.data.student) {
      student.value = { ...student.value, ...res.data.student };
      localStorage.setItem('student', JSON.stringify(student.value));
    }
    // Matches the same fallback used in the student Appointments list - if the
    // API ever sends the singular `pending_appointment` instead of the plural
    // array, this banner shouldn't just silently disappear.
    pendingAppointments.value = res.data.pending_appointments || (res.data.pending_appointment ? [res.data.pending_appointment] : []);
  } catch (e) {
    if (!silent) console.error(e);
  } finally {
    if (!silent) loading.value = false;
  }
}

onMounted(() => fetchData());
// Keep the dashboard current without a manual reload.
useAutoRefresh(() => fetchData(true), 25000);
</script>

<style scoped>
.hero-term{margin-top:8px;font-size:12.5px;font-weight:600;color:rgba(255,255,255,.78)}
@media (max-width:640px){
  .hero-actions-side{display:none}
  .hero-chips{gap:6px}
}
</style>