<template>
  <div class="fade-up">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>{{ referral.referral_code }}</h1>
        <p>Referral Details</p>
      </div>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <div class="icard" style="margin-bottom:20px">
        <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
            <span class="ibadge" :class="'ibadge-' + referral.status">{{ toTitleCase(referral.status) }}</span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service Type</div>
            <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(referral.referral_type) }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date Submitted</div>
            <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.created_at) }}</div>
          </div>
          <div v-if="referral.referrer_name">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referred By</div>
            <div style="font-size:13px;color:var(--ink)">{{ referral.referrer_name }}<span v-if="referral.referrer_role"> ({{ toTitleCase(referral.referrer_role) }})</span></div>
          </div>
          <div v-if="referral.nature_of_concern">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Concern</div>
            <div style="font-size:13px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm)">{{ referral.nature_of_concern }}</div>
          </div>
          <div v-if="referral.acknowledged_at">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Acknowledged</div>
            <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.acknowledged_at) }}</div>
          </div>
        </div>
      </div>

      <!-- Link to the appointment awaiting scheduling -->
      <div v-if="pendingAppointmentId" class="icard" style="border:2px solid var(--moss)">
        <div class="icard-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
          <div>
            <div style="font-size:14px;font-weight:600;color:var(--ink)">An appointment is waiting for you</div>
            <div style="font-size:12px;color:var(--stone);margin-top:2px">Please choose your preferred date and time.</div>
          </div>
          <router-link :to="{ name: 'student-appointment-show', params: { id: pendingAppointmentId } }" class="ibtn ibtn-p">
            View Appointment
          </router-link>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const loading = ref(true);
const referral = ref({});

const pendingAppointmentId = computed(() => referral.value.pending_appointment?.id || null);

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
    const res = await axios.get(`${API_BASE}/student/referrals/${route.params.id}`, authHeaders());
    referral.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>