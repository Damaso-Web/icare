<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Appointments</h1>
      <p>View and manage your appointment requests.</p>
    </div>

    <!-- Pending Appointment Requests - styled like the staff-side SIF's
         referral rows (badges + a per-row action button) instead of a
         dropdown, so with more than one the student picks which referral's
         appointment to schedule by acting on that row directly. -->
    <div v-if="pendingAppointments.length" class="icard" style="border:2px solid var(--moss);margin-bottom:20px">
      <div class="icard-header">
        <span class="icard-title">
          {{ pendingAppointments.length }} Pending Appointment Request{{ pendingAppointments.length > 1 ? 's' : '' }}
        </span>
      </div>
      <div style="padding:10px 18px;font-size:12px;color:var(--stone);border-bottom:1px solid var(--cloud)">
        {{ pendingAppointments.length > 1 ? 'Select which one to schedule, one at a time.' : 'Please choose your preferred date and time.' }}
      </div>
      <div
        v-for="pa in pendingAppointments"
        :key="pa.id"
        style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"
      >
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--ink);font-family:var(--mono)">
            {{ pa.referral?.referral_code || pa.case?.latest_referral?.referral_code || pa.appointment_code }}
          </div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px;display:flex;align-items:center;gap:6px">
            <span class="ibadge" :class="'unit-' + pa.unit?.toLowerCase()">{{ pa.unit }}</span>
            {{ toTitleCase(pa.appointment_type) }}
          </div>
        </div>
        <button class="ibtn ibtn-p ibtn-sm" @click="goToSchedule(pa)">Schedule Now</button>
      </div>
    </div>

        <!-- Appointments List -->
    <div class="icard">
      <div class="icard-header"><span class="icard-title">All Appointments</span></div>
      <div style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;gap:10px;flex-wrap:wrap">
        <div class="sw" style="flex:1;min-width:220px;max-width:400px">
          <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            v-model="searchQuery"
            type="text"
            class="sin"
            maxlength="50"
            placeholder="Search by appointment code or type..."
            style="width:100%"
            @input="onSearchInput"
          />
        </div>
      </div>
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="appointments.length === 0" class="empty-state">
        <h3>No appointments yet</h3>
        <p>You'll see your appointment details here once one is scheduled.</p>
      </div>
      <div v-else>
        <div
          v-for="a in pagedAppointments"
          :key="a.id"
          style="padding:14px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
          @click="$router.push({ name: 'student-appointment-show', params: { id: a.id } })"
        >
          <!-- Still waiting on the student to pick their own date/time (see
               the "pending appointment request" banner above) - this row's
               appointment_date/start_time/end_time are only a placeholder,
               so showing them here made it look like a date was already
               set before the student had chosen anything. -->
          <div v-if="a.request_status === 'awaiting_student'" style="font-size:13.5px;font-weight:600;color:var(--stone);font-style:italic">Awaiting your schedule selection</div>
          <div v-else style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ formatDateShort(a.appointment_date) }} · {{ formatTime12(a.start_time) }} – {{ formatTime12(a.end_time) }}</div>
          <div style="font-size:12px;color:var(--stone);margin-top:2px;display:flex;align-items:center;gap:6px">
            <span class="ibadge" :class="'unit-' + a.unit?.toLowerCase()">{{ a.unit }}</span>
            {{ toTitleCase(a.appointment_type) }}
          </div>
          <div v-if="a.referral || a.case?.latest_referral" style="font-size:11px;color:var(--fog);margin-top:4px">For referral {{ (a.referral || a.case.latest_referral).referral_code }}</div>
          <span
            class="ibadge"
            :class="'ibadge-' + badgeKind(a)"
            style="margin-top:6px;display:inline-block"
            :title="isExpiredPending(a) ? 'The 7-day window to pick an appointment time has passed. Contact GCU for a new scheduling link.' : toTitleCase(a.status)"
          >{{ badgeLabel(a) }}</span>
        </div>
        <div v-if="totalPages > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
          <span style="font-size:12px;color:var(--stone)">Page {{ currentPage }} of {{ totalPages }}</span>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-o ibtn-sm" :disabled="currentPage === 1" @click="page = currentPage - 1">Prev</button>
            <button class="ibtn ibtn-o ibtn-sm" :disabled="currentPage === totalPages" @click="page = currentPage + 1">Next</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAutoRefresh } from '../../utils/useAutoRefresh';
import axios from 'axios';
import { formatTime12, formatDateShort } from '../../utils/validators';

const router = useRouter();
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const appointments = ref([]);
const pendingAppointments = ref([]);
const searchQuery = ref('');

let searchDebounce = null;
function onSearchInput() {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => fetchData(), 350);
}

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/\b(Sdu|Gcu|Tmdu|Oss|Sif|Par)\b/g, m => m.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

// Bug (11/3/2026): same "Expired" distinction added to My Referrals - an
// appointment still at its placeholder 'pending' status reads identically
// here whether GCU just hasn't gotten to it, or its 7-day scheduling window
// (token_expires_at) already lapsed. Unlike My Referrals, this list IS the
// appointment itself, so no referral_id cross-reference is needed - just
// check the row's own token_expires_at.
function isExpiredPending(a) {
  if (a.status !== 'pending') return false;
  return !!a.token_expires_at && new Date(a.token_expires_at) < new Date();
}

function badgeKind(a) {
  return isExpiredPending(a) ? 'expired' : a.status;
}

function badgeLabel(a) {
  return isExpiredPending(a) ? 'Expired' : toTitleCase(a.status);
}

// B#, 11/5/2026: client-side pagination, 10 per page. `page` only moves on
// an explicit Prev/Next click or a new search - NOT the 25s background
// poll, which reassigns `appointments` (a new array reference) even when
// nothing changed and would otherwise bounce the student back to page 1
// while reading page 2. `currentPage` clamps to whatever range still
// exists if the list did shrink.
const PER_PAGE = 10;
const page = ref(1);
const totalPages = computed(() => Math.max(1, Math.ceil(appointments.value.length / PER_PAGE)));
const currentPage = computed(() => Math.min(page.value, totalPages.value));
const pagedAppointments = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE;
  return appointments.value.slice(start, start + PER_PAGE);
});
watch(searchQuery, () => { page.value = 1; });

function goToSchedule(appt) {
  if (!appt?.id) return;
  router.push({ name: 'student-appointment-show', params: { id: appt.id } });
}

async function fetchData(silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await axios.get(`${API_BASE}/student/dashboard`, {
      ...authHeaders(),
      params: { search: searchQuery.value || undefined }
    });
    appointments.value = res.data.appointments || [];
    pendingAppointments.value = res.data.pending_appointments || (res.data.pending_appointment ? [res.data.pending_appointment] : []);
  } catch (e) {
    if (!silent) console.error(e);
  } finally {
    if (!silent) loading.value = false;
  }
}

onMounted(() => fetchData());
// Keep appointments current without a manual reload.
useAutoRefresh(() => fetchData(true), 25000);
</script>