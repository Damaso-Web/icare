<!--
  Refer Myself: a student asks GCU for help directly. It becomes an ordinary
  referral (a self-referral) that GCU acknowledges and schedules, and the
  student follows it under My Referrals.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Refer Myself</h1>
      <p>Ask the Guidance and Counseling Unit for help. Your request goes to GCU, and you can follow it under My Referrals.</p>
    </div>

    <div class="icard" style="max-width:640px">
      <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="ifl">Service Requested <span style="color:var(--red)">*</span></label>
          <select v-model="form.referral_type" class="ifse">
            <option value="" disabled>Select a service...</option>
            <option v-for="s in SERVICES" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="ifl">What do you need help with? <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(at least 10 characters, max 1000)</span></label>
          <textarea v-model="form.nature_of_concern" class="ifta" style="min-height:130px" maxlength="1000" placeholder="Tell us briefly what is going on and how we can help."></textarea>
          <div style="font-size:11px;color:var(--fog);margin-top:4px">{{ form.nature_of_concern.length }}/1000</div>
        </div>
        <div style="font-size:12px;color:var(--stone);background:var(--snow);border-radius:var(--r-sm);padding:10px 12px">
          What you write is confidential and is only seen by OSS personnel handling your request.
        </div>
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
        <div style="display:flex;gap:8px">
          <button class="ibtn ibtn-p" :disabled="sending" @click="submit">{{ sending ? 'Sending...' : 'Send Request' }}</button>
          <button class="ibtn ibtn-o" :disabled="sending" @click="$router.push({ name: 'student-referrals' })">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const router = useRouter();
const toast = inject('toast', null);

// The GCU services a student can ask for themselves (not discipline or testing).
const SERVICES = [
  { value: 'counseling',          label: 'Counseling' },
  { value: 'class_attendance',    label: 'Class Admission Slip (absences / tardiness)' },
  { value: 'academic_deficiency', label: 'Academic Deficiency' },
  { value: 'leave_of_absence',    label: 'Leave of Absence' },
  { value: 'withdrawal',          label: 'Withdrawal' },
  { value: 'readmission',         label: 'Readmission' },
  { value: 'shifting',            label: 'Shifting' },
];

const form = ref({ referral_type: '', nature_of_concern: '' });
const sending = ref(false);
const error = ref('');

async function submit() {
  error.value = '';
  if (!form.value.referral_type) { error.value = 'Please choose the service you need.'; return; }
  if (form.value.nature_of_concern.trim().length < 10) { error.value = 'Please describe your concern in at least 10 characters.'; return; }
  sending.value = true;
  try {
    await axios.post(`${API_BASE}/student/referrals`, { referral_type: form.value.referral_type, nature_of_concern: form.value.nature_of_concern.trim() },
      { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } });
    toast?.success('Your request was sent to GCU.');
    router.push({ name: 'student-referrals' });
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not send your request. Please try again.';
  } finally {
    sending.value = false;
  }
}
</script>
