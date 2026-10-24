<!--
  Refer a Student (student portal): a student asks GCU to reach out to another
  student. Found by exact Student ID Number or full name; kept under Sent Referrals.
-->
<template>
  <div class="fade-up">
    <button type="button" class="ibtn ibtn-o" style="margin-bottom:12px" @click="goBack">&larr; Back</button>
    <div class="ph" style="margin-bottom:20px">
      <h1>Refer a Student</h1>
      <p>Tell GCU about a student who may need support, or about yourself. You can follow what you sent under Sent Referrals.</p>
    </div>
    <div class="icard" style="max-width:640px">
      <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
        <StudentIdLookup v-model="target" />
        <div>
          <label class="ifl">Service <span style="color:var(--red)">*</span></label>
          <select v-model="form.referral_type" class="ifse">
            <option value="" disabled>Select a service...</option>
            <option v-for="s in SERVICES" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </div>
        <div>
          <label class="ifl">Reason for referral <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(at least 10 characters, max 1000)</span></label>
          <textarea v-model="form.nature_of_concern" class="ifta" style="min-height:130px" maxlength="1000" :style="reasonNumbersOnly ? 'border-color:var(--red);border-width:1.5px' : ''"></textarea>
          <div style="font-size:11px;color:var(--fog);margin-top:4px">{{ form.nature_of_concern.length }}/1000</div>
          <!-- B582: live, as the user types - not just discovered on submit. -->
          <div v-if="reasonNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid referral reason. Numbers alone are not allowed.</div>
        </div>
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
        <div style="display:flex;gap:8px">
          <button class="ibtn ibtn-p" :disabled="sending" @click="submit">{{ sending ? 'Sending...' : 'Send Referral' }}</button>
          <button class="ibtn ibtn-o" :disabled="sending" @click="goBack">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, computed } from 'vue';
import { useRouter } from 'vue-router';
import { studentApi } from '../../api/index';
import StudentIdLookup from '../../components/StudentIdLookup.vue';
import { hasLetter } from '../../utils/validators';

const router = useRouter();
const toast = inject('toast', null);

function goBack() {
  if (window.history.state && window.history.state.back) router.back();
  else router.push({ name: 'student-refer-choice' });
}

const SERVICES = [
  { value: 'counseling',          label: 'Counseling' },
  { value: 'class_attendance',    label: 'Class Admission Slip (absences / tardiness)' },
  { value: 'academic_deficiency', label: 'Academic Deficiency' },
  { value: 'leave_of_absence',    label: 'Leave of Absence' },
  { value: 'withdrawal',          label: 'Withdrawal' },
  { value: 'readmission',         label: 'Readmission' },
  { value: 'shifting',            label: 'Shifting' },
];

const target = ref(null);
const form = ref({ referral_type: '', nature_of_concern: '' });
const sending = ref(false);
const error = ref('');
// B582: "12345" has 10+ characters but isn't a real reason.
const reasonNumbersOnly = computed(() =>
  !!form.value.nature_of_concern.trim() && !hasLetter(form.value.nature_of_concern)
);

async function submit() {
  error.value = '';
  if (!target.value) { error.value = "Find the student by their ID Number or full name first."; return; }
  if (!form.value.referral_type) { error.value = 'Please choose a service.'; return; }
  if (form.value.nature_of_concern.trim().length < 10) { error.value = 'Please describe the reason in at least 10 characters.'; return; }
  if (!hasLetter(form.value.nature_of_concern)) { error.value = 'Please provide a valid referral reason. Numbers alone are not allowed.'; return; }
  sending.value = true;
  try {
    await studentApi.post('/student/outreach/referrals', {
      student_id: target.value.id,
      referral_type: form.value.referral_type,
      nature_of_concern: form.value.nature_of_concern.trim(),
    });
    toast?.success('Your referral was sent to GCU.');
    router.push({ name: 'student-sent-referrals' });
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not send the referral. Please try again.';
  } finally {
    sending.value = false;
  }
}
</script>