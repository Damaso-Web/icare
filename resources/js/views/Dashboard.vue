<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="hero">
      <div class="hero-row">
        <div>
          <!-- The unit, with the office it belongs to right beside it.
               B629/B632/B635/B638 (10/24-10/26/2026): Dean and Dept Chair
               get one dot-separated line - role, their full department/
               college name, then the college abbreviation (e.g.
               "DEPARTMENT CHAIR • Department of Library and Information
               Science • CIS") - the role renders uppercase via .hero-kicker's
               own CSS, the rest stays normal case for readability, same as
               it's typed here. OSS personnel (Admin/GCU/SDU/TMDU) keep the
               plain "OSS" chip exactly as before. Faculty gets neither -
               just their existing "Faculty · Department" kicker, same as a
               student's dashboard already has no such chip at all. -->
          <div class="hero-kicker-row">
            <template v-if="showsCollegeAbbrev">
              <div class="hero-kicker">{{ roleBase }}</div>
              <span class="hero-kicker-sep">&bull;</span>
              <span class="hero-kicker-detail">{{ bulletAffiliation }}</span>
              <template v-if="bulletAbbrev">
                <span class="hero-kicker-sep">&bull;</span>
                <span class="hero-kicker-detail">{{ bulletAbbrev }}</span>
              </template>
            </template>
            <template v-else>
              <div class="hero-kicker">{{ roleTitle }}</div>
              <abbr v-if="auth.user?.role !== 'faculty'" class="hero-oss" title="Office of Student Services" style="text-decoration:none">OSS</abbr>
            </template>
          </div>
          <h1>{{ greeting }}, {{ firstName }}!</h1>
          <p>Here's what needs your attention today.</p>
          <!-- The figures that sentence is about; each one opens its page -->
          <div class="hero-stats">
            <button v-for="stat in stats" :key="stat.label" type="button" class="hero-stat" @click="goToStat(stat)">
              <span class="hero-stat-top">
                <span class="hero-stat-icon"><svg viewBox="0 0 24 24" v-html="stat.icon"></svg></span>
                <span class="hero-stat-period">{{ stat.period }}</span>
              </span>
              <span class="hero-stat-num" :class="{ 'stat-pulse': justRefreshed }">{{ stat.value }}</span>
              <span class="hero-stat-label">{{ stat.label }}</span>
            </button>
          </div>
          <div class="hero-chips">
            <!-- The date: in the top bar on a computer, here on a phone (the top bar hides it there). -->
            <span class="hero-chip hero-date"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>{{ todayLabel }}</span>
          </div>
        </div>
        <div class="hero-actions" :class="{ 'phone-none': !phoneActions.length, 'phone-one': phoneActions.length === 1 }">
          <button v-for="a in quickActions" :key="a.name" type="button" class="hero-btn" :class="{ 'in-tabbar': tabNames.includes(a.name) }" @click="router.push({ name: a.name })">
            <svg viewBox="0 0 24 24" v-html="a.icon"></svg>{{ a.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <!-- Two column layout -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:16px;margin-bottom:16px">

        <!-- Recent Referrals -->
        <div class="icard" v-if="dashboard.recent_referrals?.length">
          <div class="icard-header">
            <span class="icard-title">Recent Referrals</span>
            <!-- B596/B609: a Dean or Dept Chair no longer has the Referral
                 Queue page this linked to - the list itself still updates
                 and each row still opens its referral, just with no
                 "View all" link. B627: same for plain Faculty (B626). -->
            <router-link v-if="!auth.isDean && !auth.isDeptChair && auth.user?.role !== 'faculty'" :to="{ name: 'referrals' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div>
            <div
              v-for="r in dashboard.recent_referrals"
              :key="r.id"
              class="qr"
              @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
            >
              <div class="qav">{{ initials(r.student?.first_name, r.student?.last_name) }}</div>
              <div class="qi">
                <div class="qn">
                  {{ r.student?.first_name }} {{ r.student?.last_name }}
                  <span class="qid">{{ r.student?.student_id }}</span>
                </div>
                <div class="qmeta">{{ toTitleCase(r.referral_type) }} · {{ r.referrer_name }}</div>
                <!-- Urgency badge/row-highlight removed - urgency_level is
                     never actually set by anyone (no form exposes it; it's
                     just the DB column default, or hardcoded 'medium' for
                     complaint-based referrals in ComplaintController), so
                     the "Medium" tag it always showed was meaningless. -->
                <div class="qtags">
                  <span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Appointments -->
        <div class="icard" v-if="dashboard.upcoming_appointments?.length">
          <div class="icard-header">
            <span class="icard-title">Today's Sessions</span>
            <!-- Each unit has its own calendar page -->
            <router-link v-if="calendarRoute" :to="{ name: calendarRoute }" class="ibtn ibtn-g ibtn-sm">Calendar</router-link>
          </div>
          <div style="padding:10px">
            <div
              v-for="a in dashboard.upcoming_appointments"
              :key="a.id"
              style="padding:7px 9px;background:var(--foam);border-radius:var(--r-sm);margin-bottom:5px;border-left:3px solid var(--moss)"
            >
              <div style="font-size:11.5px;font-weight:600;color:var(--forest)">
                {{ a.student?.first_name }} {{ a.student?.last_name }}
              </div>
              <div style="font-size:10px;color:var(--stone);margin-top:2px">
                {{ toTitleCase(a.appointment_type) }} · {{ formatTime12(a.start_time) }}
              </div>
              <div style="font-size:9px;color:var(--sage);margin-top:3px;font-style:italic">
                {{ a.staff?.name }}
              </div>
            </div>
          </div>
        </div>

        <!-- Testing Queue -->
        <div class="icard" v-if="dashboard.testing_queue?.length">
          <div class="icard-header">
            <span class="icard-title">Testing Queue</span>
            <router-link :to="{ name: 'testing' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Referred By</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in dashboard.testing_queue" :key="t.id">
                  <td>{{ t.student?.first_name }} {{ t.student?.last_name }}</td>
                  <td>{{ t.referred_by?.name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + t.status">{{ toTitleCase(t.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- My Cases -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:16px">

        <!-- My Active Cases -->
        <div class="icard" v-if="dashboard.my_cases?.length">
          <div class="icard-header">
            <span class="icard-title">My Active Cases</span>
            <router-link :to="{ name: 'cases' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Case No.</th>
                  <th>Student</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="c in dashboard.my_cases"
                  :key="c.id"
                  style="cursor:pointer"
                  @click="goToReferral(c)"
                >
                  <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
                  <td>{{ c.student?.first_name }} {{ c.student?.last_name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + c.status">{{ toTitleCase(c.status) }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Faculty Recent Referrals -->
        <div class="icard" v-if="dashboard.recent_referrals?.length && !dashboard.my_cases?.length && !dashboard.testing_queue?.length" style="grid-column:1/-1">
          <div class="icard-header">
            <span class="icard-title">My Submitted Referrals</span>
            <!-- B568: this card lists referrals the signed-in faculty/dean member
                 originated themselves, not an incoming queue to process - "View
                 all" was pointed at the Referral Queue instead of their own
                 Sent Referrals history.
                 B628 (10/25/2026) hid this button for plain Faculty, showing
                 only the latest 3 rows with no second way to browse the full
                 list. B634 (10/26/2026) restored it - Faculty gets "View all"
                 too now, same as every other role; only the 3-row limit from
                 B628 stays. -->
            <router-link :to="{ name: 'sent-referrals' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Student</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in facultyRecentReferrals" :key="r.id">
                  <td style="font-family:var(--mono);font-size:11px">{{ r.referral_code }}</td>
                  <td>{{ r.student?.first_name }} {{ r.student?.last_name }}</td>
                  <td>{{ toTitleCase(r.referral_type) }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span></td>
                  <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- B608: My Submitted Complaints - a Dean's or Dept Chair's own filed
             complaints, same table layout and "View all" pattern as the
             Sent Referrals card above, pointed at Sent Complaints instead.
             B636/B637 (10/26/2026): this was missing the Code column the
             Referrals card has - added below, same complaint_code shown on
             the Sent Complaints page itself. -->
        <div class="icard" v-if="dashboard.recent_complaints?.length" style="grid-column:1/-1">
          <div class="icard-header">
            <span class="icard-title">My Submitted Complaints</span>
            <router-link :to="{ name: 'sent-complaints' }" class="ibtn ibtn-g ibtn-sm">View all</router-link>
          </div>
          <div class="ts">
            <table class="itable">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Student</th>
                  <th>Violation</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in dashboard.recent_complaints" :key="c.id">
                  <td style="font-family:var(--mono);font-size:11px">{{ c.complaint_code }}</td>
                  <td>{{ c.complainee?.first_name }} {{ c.complainee?.last_name }}</td>
                  <td>{{ c.violation_type }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + c.status">{{ toTitleCase(c.status) }}</span></td>
                  <td style="font-size:12px">{{ formatDate(c.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- SDU: the incident reports still open, and the newest complaints -->
      <div v-if="auth.isSDUHead" class="sdu-panels">
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Incident Reports Needing Action</span>
            <a href="#" class="sdu-link" @click.prevent="router.push({ name: 'incident-reports' })">View all</a>
          </div>
          <div v-if="!sduOpenReports.length" class="icard-body" style="font-size:13px;color:var(--stone)">Nothing waiting - every Incident Report is resolved.</div>
          <div v-for="r in sduOpenReports" :key="r.student.id" class="sdu-row" @click="router.push({ name: 'incident-report-show', params: { id: r.student.id } })">
            <div style="min-width:0">
              <div class="sdu-name">{{ r.student.last_name }}, {{ r.student.first_name }}</div>
              <div class="sdu-meta">{{ r.complaints_count }} complaint{{ r.complaints_count === 1 ? '' : 's' }} · latest: {{ r.latest_violation }}</div>
            </div>
            <span class="ibadge" :class="'ir-' + r.status">{{ toTitleCase(r.status) }}</span>
          </div>
        </div>
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Newest Complaints</span>
            <a href="#" class="sdu-link" @click.prevent="router.push({ name: 'complaints' })">View all</a>
          </div>
          <div v-if="!sduComplaints.length" class="icard-body" style="font-size:13px;color:var(--stone)">No complaints have been filed yet.</div>
          <div v-for="c in sduComplaints" :key="c.id" class="sdu-row" @click="router.push({ name: 'incident-report-show', params: { id: c.complainee_student_id }, query: { complaint: c.id } })">
            <div style="min-width:0">
              <div class="sdu-name">{{ c.complainee?.last_name }}, {{ c.complainee?.first_name }}</div>
              <div class="sdu-meta">{{ c.violation_type }}</div>
            </div>
            <span class="sdu-meta" style="white-space:nowrap">{{ formatDate(c.created_at) }}</span>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <!-- B630 (10/24/2026): Dept Chair and Faculty (and Dean, same outbound-
           only role) have no incoming queue to "check" - they only ever
           submit referrals/complaints and track their own. "...or checking
           the queue" was wrong for them; "...or complaint" is correct for
           every role, since everyone can do at least one of the two. -->
      <div class="empty-state" v-if="isEmpty && !auth.isSDUHead">
        <h3>No data yet</h3>
        <p v-if="auth.isDean || auth.isDeptChair || auth.user?.role === 'faculty'">Start by submitting a referral or complaint.</p>
        <p v-else>Start by submitting a referral or checking the queue.</p>
      </div>
    </template>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, inject } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../api/index';
import { toTitleCase, formatTime12 } from '../utils/validators';

const auth      = useAuthStore();
const router    = useRouter();
const toast     = inject('toast');
// On a phone the bottom tab bar already has some of these pages; those
// shortcuts are hidden there so the same button doesn't show twice.
const tabNames  = inject('tabNames', computed(() => []));
const dashboard = ref({});
const loading   = ref(true);

function goToReferral(c) {
  if (c.latest_referral?.id) {
    router.push({ name: 'referral-show', params: { id: c.latest_referral.id } });
  } else {
    toast?.error('No linked referral found for this case.');
  }
}

const calendarRoute = computed(() => ({ admin: 'appointments', gcu_staff: 'appointments', tmdu_staff: 'testing-appointments', tmdu_head: 'testing-appointments' }[auth.user?.role] || null));

const firstName = computed(() => auth.user?.name?.split(' ')[0] || 'there');

// ---- Welcome banner ----
const todayLabel = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
const ROLE_TITLES = {
  admin: 'Admin · GCU Head', gcu_staff: 'Guidance and Counseling Unit', sdu_head: 'Student Discipline Unit',
  tmdu_staff: 'Testing and Measurement Development Unit', faculty: 'Faculty', dean: 'Dean',
  dept_chair: 'Department Chair', dean_secretary: 'Dean',
  // B631 (10/26/2026): was the bare "TMDU Head", inconsistent with the full
  // designation already used for tmdu_staff just above.
  tmdu_head: 'Testing and Measurement Development Unit Head', system_admin: 'System Administrator',
};
// B565: a faculty member's banner just said "Faculty" with no indication of
// which department/college they belong to. Department is stored as a bare
// name ("Information Technology"), while College (Management > Colleges)
// is stored with its short code baked in ("College of Information Sciences
// (CIS)") - stripping that suffix here matches resources/js/utils/colleges.js's
// own bare() pattern so we don't double up on "College of College of...".
function bareCollegeName(name) {
  return String(name || '').replace(/\s*\([^)]*\)\s*$/, '').trim();
}
// Bug (10/25/2026, B629): "Faculty of Department of X under College of Y"
// ran too long for the badge (and for a phone, where it's one line with an
// ellipsis) - "Faculty · Department Name" says the same thing the badge
// needs to say. Still a computed off auth.user?.department, so it stays in
// sync whenever that changes (e.g. after an admin edits the account).
const facultyAffiliation = computed(() => {
  const dept = (auth.user?.department || '').trim();
  return dept ? `Faculty · ${dept}` : 'Faculty';
});
const roleTitle = computed(() => {
  if (auth.user?.role === 'faculty') return facultyAffiliation.value;
  return ROLE_TITLES[auth.user?.role] || 'iCARE';
});
// B629/B632/B635/B638 (10/24-10/26/2026): Dean and Dept Chair get a single
// dot-separated kicker line - "DEPARTMENT CHAIR • Department of Library and
// Information Science • CIS" - instead of the old "Department Chair of the
// Department of X" phrasing with a separate college-abbreviation chip.
// roleBase is just the bare role name (the template's own CSS uppercases
// it); bulletAffiliation is the account's full department (Dept Chair) or
// college (Dean) name; bulletAbbrev is the trailing college code.
const showsCollegeAbbrev = computed(() => ['dean', 'dean_secretary', 'dept_chair'].includes(auth.user?.role));
const roleBase = computed(() => (auth.user?.role === 'dept_chair' ? 'Department Chair' : 'Dean'));
const bulletAffiliation = computed(() => {
  if (auth.user?.role === 'dept_chair') {
    const dept = (auth.user?.department || '').trim();
    if (!dept) return '';
    return /^Department of /i.test(dept) ? dept : `Department of ${dept}`;
  }
  return bareCollegeName(auth.user?.college);
});
// affiliation_abbrev comes from the dashboard API (App\Support\CollegeName::abbrev
// on the account's college); left blank (not "OSS") when it can't be resolved,
// so the bullet row just drops that trailing segment instead of showing a
// wrong or placeholder value.
const bulletAbbrev = computed(() => dashboard.value.affiliation_abbrev || '');

// The two or three places each role goes most, one tap from the banner.
// Named exactly as in the menu, so a shortcut and its menu item read the same.
const ICONS = {
  plus:     '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  pulse:    '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
  calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
  file:     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
  alert:    '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/>',
  check:    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
  phone:    '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
};
const quickActions = computed(() => {
  const refer = { name: 'referral-create', label: 'Refer Student', icon: ICONS.plus };
  switch (auth.user?.role) {
    case 'admin':
    case 'gcu_staff':      return [refer, { name: 'referrals', label: 'Referrals', icon: ICONS.pulse }, { name: 'appointments', label: 'GCU Appointments', icon: ICONS.calendar }];
    case 'sdu_head':       return [{ name: 'incident-reports', label: 'Student Incident Reports', icon: ICONS.file }, { name: 'complaints', label: 'Complaints', icon: ICONS.alert }, refer];
    case 'tmdu_head':
    case 'tmdu_staff':     return [{ name: 'testing', label: 'Testing Records', icon: ICONS.check }, { name: 'testing-appointments', label: 'TMDU Appointments', icon: ICONS.calendar }];
    case 'dean':
    case 'dean_secretary': return [{ name: 'call-slips', label: 'Call Slips', icon: ICONS.phone }, { name: 'faculty-directory', label: 'Faculty', icon: ICONS.users || ICONS.file }, refer];
    // Bug (10/25/2026, B627): Faculty no longer has the Referral Queue page
    // (B626) - "File a Complaint" sits beside "Refer Student" instead, same
    // pairing the Dept Chair banner already uses below.
    case 'faculty':        return [refer, { name: 'complaint-create', label: 'File a Complaint', icon: ICONS.alert }];
    // B605/B606: a Dept Chair is a referring authority, not processing
    // staff - no Referral Queue button; "Complaint a Student" sits beside
    // "Refer Student" instead, so both forms are one tap from the banner.
    case 'dept_chair':     return [refer, { name: 'complaint-create', label: 'Complaint a Student', icon: ICONS.alert }];
    default:               return [];
  }
});

const phoneActions = computed(() => quickActions.value.filter(a => !tabNames.value.includes(a.name)));

const greeting = computed(() => {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 18) return 'Good afternoon';
  return 'Good evening';
});


// ---- SDU dashboard panels ----
const sduReports    = ref([]);
const sduComplaints = ref([]);
const sduOpenReports = computed(() => sduReports.value.filter(r => r.status !== 'resolved').slice(0, 6));
async function fetchSduPanels() {
  if (!auth.isSDUHead) return;
  try {
    const [ir, cp] = await Promise.all([api.get('/incident-reports'), api.get('/complaints')]);
    sduReports.value    = ir.data.data || [];
    sduComplaints.value = (cp.data.data || []).slice(0, 6);
  } catch (e) {
    /* the tiles above still show */
  }
}

// B628: Faculty's "My Submitted Referrals" card shows only the latest 3
// (the backend still sends up to 5, shared with the Dept Chair card above,
// which keeps all of them).
const facultyRecentReferrals = computed(() => {
  const list = dashboard.value.recent_referrals || [];
  return auth.user?.role === 'faculty' ? list.slice(0, 3) : list;
});

const isEmpty = computed(() => {
  const d = dashboard.value;
  return !d.recent_referrals?.length &&
         !d.recent_complaints?.length &&
         !d.upcoming_appointments?.length &&
         !d.my_cases?.length &&
         !d.testing_queue?.length;
});

const stats = computed(() => {
  const s = dashboard.value.stats || {};
  if (auth.isAdmin || auth.isGCUStaff) {
    return [
      { label: 'Open Cases',         value: s.open_cases         ?? 0, period: 'Active',  iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>', route: 'cases', query: { status: 'open' } },
      { label: 'Pending Referrals',  value: s.pending_referrals  ?? 0, period: 'Inbox',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', route: 'referrals', query: { status: 'submitted' } },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', route: 'appointments', query: { date: dashboard.value.today || '', active: '1' } },
    ];
  }
  if (auth.isSDUHead) {
    return [
      { label: 'Active Cases',       value: s.active_cases       ?? 0, period: 'Active',  iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>', route: 'complaints' },
            { label: 'Pending Complaints', value: s.pending_complaints ?? 0, period: 'Inbox',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>', route: 'complaints' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', route: 'complaints' },
    ];
  }
  if (auth.isTMDUStaff) {
    return [
      { label: 'Pending Testing',    value: s.pending_testing    ?? 0, period: 'Queue',   iconBg: 'var(--amber-lt)',  iconColor: 'var(--amber)',  icon: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>', route: 'testing' },
      { label: 'In Progress',        value: s.in_progress        ?? 0, period: 'Active',  iconBg: 'var(--blue-lt)',   iconColor: 'var(--blue)',   icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'testing' },
      { label: 'Completed',          value: s.completed          ?? 0, period: 'Done',    iconBg: 'var(--mist)',      iconColor: 'var(--moss)',   icon: '<polyline points="20 6 9 17 4 12"/>', route: 'testing' },
      { label: 'Appointments Today', value: s.appointments_today ?? 0, period: 'Today',   iconBg: 'var(--purple-lt)', iconColor: 'var(--purple)', icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>', route: 'testing-appointments' },
    ];
  }
  // B597: a Dean manages outbound cases, not an incoming queue - these cards
  // go to their own Sent Referrals (with the matching status group), not
  // the Referral Queue page they no longer have.
  if (auth.isDean) {
    return [
      { label: 'My Referrals',  value: s.my_referrals ?? 0, period: 'Total',    iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>', route: 'sent-referrals', query: {} },
      { label: 'Pending',       value: s.pending      ?? 0, period: 'Awaiting', iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'sent-referrals', query: { status: 'submitted' } },
      { label: 'Acknowledged',  value: s.acknowledged ?? 0, period: 'Received', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>', route: 'sent-referrals', query: { status: 'acknowledged,scheduled,in_progress,referred_tmdu,referred_external' } },
      { label: 'Completed',     value: s.completed    ?? 0, period: 'Resolved', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', route: 'sent-referrals', query: { status: 'completed,closed' } },
    ];
  }
  // B607: a Dept Chair manages outbound cases too (no Referral Queue of
  // their own - B605) - same Sent Referrals status-group routing as a Dean.
  if (auth.isDeptChair) {
    return [
      { label: 'Total',     value: s.my_referrals ?? 0, period: 'My Referrals', iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>', route: 'sent-referrals', query: {} },
      { label: 'Awaiting',  value: s.pending      ?? 0, period: 'Pending',      iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'sent-referrals', query: { status: 'submitted' } },
      { label: 'Received',  value: s.acknowledged ?? 0, period: 'Acknowledged', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>', route: 'sent-referrals', query: { status: 'acknowledged,scheduled,in_progress,referred_tmdu,referred_external' } },
      { label: 'Resolved',  value: s.completed    ?? 0, period: 'Completed',   iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', route: 'sent-referrals', query: { status: 'completed,closed' } },
    ];
  }
  // Bug (10/25/2026, B627): these cards pointed at the deprecated Referral
  // Queue ('referrals') page, which Faculty no longer has (B626) - same
  // Sent Referrals status-group routing the Dean/Dept Chair cards above
  // already use.
  if (auth.user?.role === 'faculty') {
    return [
      { label: 'Total',     value: s.my_referrals ?? 0, period: 'My Referrals', iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>', route: 'sent-referrals', query: {} },
      { label: 'Awaiting',  value: s.pending      ?? 0, period: 'Pending',      iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'sent-referrals', query: { status: 'submitted' } },
      { label: 'Received',  value: s.acknowledged ?? 0, period: 'Acknowledged', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>', route: 'sent-referrals', query: { status: 'acknowledged,scheduled,in_progress,referred_tmdu,referred_external' } },
      { label: 'Resolved',  value: s.completed    ?? 0, period: 'Completed',   iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', route: 'sent-referrals', query: { status: 'completed,closed' } },
    ];
  }
  return [
    { label: 'My Referrals',  value: s.my_referrals ?? 0, period: 'Total',    iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>', route: 'referrals' },
    { label: 'Pending',       value: s.pending      ?? 0, period: 'Awaiting', iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>', route: 'referrals' },
    { label: 'Acknowledged',  value: s.acknowledged ?? 0, period: 'Received', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>', route: 'referrals' },
    { label: 'Completed',     value: s.completed    ?? 0, period: 'Resolved', iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>', route: 'referrals' },
  ];
});

function goToStat(stat) {
  if (!stat.route) return;
  router.push({ name: stat.route, query: stat.query || (stat.status ? { status: stat.status } : {}) });
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase();
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

// Re-fetches in the background on an interval so the stat cards stay live
// without the person needing to leave and come back to the page. `loading`
// is only touched on the very first load, so refreshes don't flash the
// spinner over the whole page.
let refreshTimer = null;
// B553: a quiet pulse on the stat numbers after a background refresh changes
// anything, so it's visible at a glance that fresh data just came in without
// needing to compare the figures by eye.
const justRefreshed = ref(false);
let pulseTimer = null;

async function fetchDashboard(isInitial = false) {
  try {
    const res = await api.get('/dashboard');
    const before = JSON.stringify(dashboard.value.stats || {});
    dashboard.value = res.data;
    if (!isInitial && before !== JSON.stringify(dashboard.value.stats || {})) {
      justRefreshed.value = true;
      clearTimeout(pulseTimer);
      pulseTimer = setTimeout(() => { justRefreshed.value = false; }, 600);
    }
  } catch (e) {
    console.error(e);
  } finally {
    if (isInitial) loading.value = false;
  }
}

onMounted(() => {
  fetchDashboard(true);
  fetchSduPanels();
  refreshTimer = setInterval(() => fetchDashboard(false), 30000);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  clearTimeout(pulseTimer);
});
</script>

<style scoped>
/* SDU dashboard panels */
.sdu-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; align-items: start; }
.sdu-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 18px; border-bottom: 1px solid var(--cloud); cursor: pointer; transition: background .1s; }
.sdu-row:last-child { border-bottom: none; }
.sdu-row:hover { background: var(--foam); }
.sdu-name { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.sdu-meta { font-size: 11.5px; color: var(--stone); overflow: hidden; text-overflow: ellipsis; }
.sdu-link { font-size: 12px; color: var(--moss); text-decoration: underline; }
@media (max-width: 860px) { .sdu-panels { grid-template-columns: 1fr; } }

/* B553: brief pulse on a stat card's number after a background refresh
   actually changes it, so the auto-update is noticeable without having to
   compare figures by eye. */
@keyframes stat-pulse { 0% { transform: scale(1); } 35% { transform: scale(1.14); } 100% { transform: scale(1); } }
.stat-pulse { animation: stat-pulse .5s ease; display: inline-block; }
</style>