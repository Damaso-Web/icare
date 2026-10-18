<!--
  File a Complaint (student portal): a student reports misconduct by another
  student to the SDU. After filing, the complaint can be printed, and it stays
  under Sent Complaints.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>File a Complaint</h1>
      <p>Report an act of misconduct against a student. This is reviewed by the SDU Head.</p>
    </div>
    <div class="icard" style="max-width:680px">
      <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="ifl">Your Name</label>
          <input class="ifi" :value="myName" readonly style="background:var(--snow);color:var(--stone);cursor:not-allowed" />
        </div>
        <div>
          <label class="ifl">Your Address <span style="color:var(--red)">*</span></label>
          <input v-model="form.complainant_address" class="ifi" maxlength="255" />
        </div>
        <StudentIdLookup v-model="target" />
        <div>
          <label class="ifl">Act of Misconduct <span style="color:var(--red)">*</span></label>
          <select v-model="form.violation_type" class="ifse">
            <option value="" disabled>Select...</option>
            <option v-for="o in violationTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="ifl">Date of Incident <span style="color:var(--red)">*</span></label>
          <input v-model="form.incident_date" type="date" class="ifi" :max="today" />
        </div>
        <div>
          <label class="ifl">Narration of Facts <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(what, when, where, how)</span></label>
          <textarea v-model="form.description" class="ifta" style="min-height:130px" maxlength="2000"></textarea>
        </div>
        <div>
          <label class="ifl">Evidence <span style="color:var(--fog);font-weight:400">(optional, up to 10 MB each)</span></label>
          <input type="file" multiple @change="evidence = Array.from($event.target.files)" />
        </div>
        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;color:var(--ink)">
          <input v-model="form.certification_agreed" type="checkbox" style="margin-top:3px" />
          I certify that the information above is true and correct to the best of my knowledge.
        </label>
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
        <div style="display:flex;gap:8px">
          <button class="ibtn ibtn-p" :disabled="sending" @click="submit">{{ sending ? 'Submitting...' : 'Submit Complaint' }}</button>
          <button class="ibtn ibtn-o" :disabled="sending" @click="$router.push({ name: 'student-dashboard' })">Cancel</button>
        </div>
      </div>
    </div>

    <ComplaintPrintModal
      v-if="filed"
      :complaint="filed"
      heading="Complaint Filed"
      :subtitle="`${filed.complaint_code} was sent to the Student Discipline Unit.`"
      close-label="Done"
      @close="finish"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { studentApi } from '../../api/index';
import StudentIdLookup from '../../components/StudentIdLookup.vue';
import ComplaintPrintModal from '../../components/ComplaintPrintModal.vue';

const router = useRouter();

let me = {};
try { me = JSON.parse(localStorage.getItem('student') || '{}'); } catch (e) { me = {}; }
const myName = computed(() => `${me.first_name || ''} ${me.last_name || ''}`.trim());
const today = new Date().toISOString().slice(0, 10);

const target = ref(null);
const violationTypes = ref([]);
const evidence = ref([]);
const form = ref({ complainant_address: '', violation_type: '', incident_date: '', description: '', certification_agreed: false });
const sending = ref(false);
const error = ref('');
const filed = ref(null);

onMounted(async () => {
  try {
    const res = await studentApi.get('/student/outreach/options');
    violationTypes.value = res.data.violation_types || [];
  } catch (e) { /* the list stays empty */ }
});

async function submit() {
  error.value = '';
  const f = form.value;
  if (!target.value) { error.value = 'Find the student by their ID Number first.'; return; }
  if (!f.complainant_address.trim() || !f.violation_type || !f.incident_date || !f.description.trim()) { error.value = 'Please fill out all required fields.'; return; }
  if (!f.certification_agreed) { error.value = 'Please tick the certification.'; return; }

  const fd = new FormData();
  fd.append('complainee_student_id', target.value.id);
  fd.append('complainant_address', f.complainant_address.trim());
  fd.append('violation_type', f.violation_type);
  fd.append('incident_date', f.incident_date);
  fd.append('description', f.description.trim());
  fd.append('certification_agreed', '1');
  evidence.value.forEach(file => fd.append('evidence[]', file));

  sending.value = true;
  try {
    const res = await studentApi.post('/student/outreach/complaints', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    filed.value = res.data;
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not submit the complaint. Please try again.';
  } finally {
    sending.value = false;
  }
}

function finish() {
  filed.value = null;
  router.push({ name: 'student-sent-complaints' });
}
</script>