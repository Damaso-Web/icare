<!--
  File a Complaint (student portal): a student reports misconduct by another
  student to the SDU. After filing, the complaint can be printed, and it stays
  under Sent Complaints.
-->
<template>
  <div class="fade-up">
    <button type="button" class="ibtn ibtn-o" style="margin-bottom:12px" @click="goBack">&larr; Back</button>
    <div class="ph" style="margin-bottom:20px">
      <h1>File a Complaint</h1>
      <!-- B577: "SDU Head" is an internal role name, not the unit name. -->
      <p>Report an act of misconduct against a student. This is reviewed by the Student Discipline Unit.</p>
    </div>
    <div class="icard" style="max-width:680px">
      <div class="icard-body" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <label class="ifl">Your Name</label>
          <input class="ifi" :value="myName" readonly style="background:var(--snow);color:var(--stone);cursor:not-allowed" />
        </div>
        <div>
          <label class="ifl">Your Address <span style="color:var(--red)">*</span></label>
          <!-- B583: live field-level feedback, not just a submit-time check. -->
          <input
            v-model="form.complainant_address"
            class="ifi"
            maxlength="255"
            :style="fieldErrors.complainant_address ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="clearFieldError('complainant_address')"
            @blur="validateField('complainant_address')"
          />
          <div v-if="addressNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid address. Numbers alone are not allowed.</div>
        </div>
        <StudentIdLookup v-model="target" :allow-self="false" />
        <div>
          <label class="ifl">Act of Misconduct <span style="color:var(--red)">*</span></label>
          <select
            v-model="form.violation_type"
            class="ifse"
            :style="fieldErrors.violation_type ? 'border-color:var(--red);border-width:1.5px' : ''"
            @change="clearFieldError('violation_type')"
            @blur="validateField('violation_type')"
          >
            <option value="" disabled>Select...</option>
            <option v-for="o in violationTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div>
          <label class="ifl">Date of Incident <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.incident_date"
            type="date"
            class="ifi"
            :max="today"
            :style="fieldErrors.incident_date ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="validateField('incident_date')"
            @change="validateField('incident_date')"
          />
          <!-- B585: live, as soon as a future date is picked or typed - some
               mobile browsers don't enforce the native `max` on manual entry. -->
          <div v-if="incidentDateInFuture" style="color:var(--red);font-size:11px;margin-top:4px">The date of incident cannot be in the future.</div>
        </div>
        <div>
          <label class="ifl">Narration of Facts <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(what, when, where, how; at least 10 characters, max 1000)</span></label>
          <textarea
            v-model="form.description"
            class="ifta"
            style="min-height:130px"
            maxlength="1000"
            :style="fieldErrors.description ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="clearFieldError('description')"
            @blur="validateField('description')"
          ></textarea>
          <div style="font-size:11px;color:var(--fog);margin-top:4px">{{ form.description.length }}/1000</div>
          <!-- B583/B584: live, as the user types. -->
          <div v-if="descriptionNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid narration of facts. Numbers alone are not allowed.</div>
        </div>
        <div>
          <label class="ifl">Evidence <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(accepted files: PDF, DOC, DOCX, JPG, PNG; up to 10 MB each)</span></label>
          <!-- B583 (10/23/2026, mobile file picker): the previous fix (B586)
               tried extensions + explicit MIME types so Android/Chrome's
               picker would offer "Files" alongside Camera/Gallery, but
               mixing image types into `accept` still collapses the picker to
               camera/gallery-only on Android - dropping `accept` entirely is
               the documented fix. Wrong types/oversized files are still
               caught by badFile() on submit, which names the exact file and
               repeats the same allowed-types/size message shown here. -->
          <input type="file" multiple @change="pick($event, evidence); clearFieldError('evidence')" />
          <div v-if="evidence.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ evidence.length }} file(s) selected</div>
          <div v-if="fieldErrors.evidence" style="color:var(--red);font-size:11px;margin-top:4px">Please attach at least one Evidence file.</div>
        </div>
        <div>
          <label class="ifl">Affidavit of Witness <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(accepted files: PDF, DOC, DOCX, JPG, PNG; up to 10 MB each)</span></label>
          <input type="file" multiple @change="pick($event, affidavit); clearFieldError('affidavit')" />
          <div v-if="affidavit.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ affidavit.length }} file(s) selected</div>
          <div v-if="fieldErrors.affidavit" style="color:var(--red);font-size:11px;margin-top:4px">Please attach at least one Affidavit of Witness file.</div>
        </div>
        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;color:var(--ink)">
          <input v-model="form.certification_agreed" type="checkbox" style="margin-top:3px" />
          I certify that the information above is true and correct to the best of my knowledge.
        </label>
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
        <div style="display:flex;gap:8px">
          <button class="ibtn ibtn-p" :disabled="sending" @click="submit">{{ sending ? 'Submitting...' : 'Submit Complaint' }}</button>
          <button class="ibtn ibtn-o" :disabled="sending" @click="goBack">Cancel</button>
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
import { hasLetter } from '../../utils/validators';

const router = useRouter();

let me = {};
try { me = JSON.parse(localStorage.getItem('student') || '{}'); } catch (e) { me = {}; }
const myName = computed(() => `${me.first_name || ''} ${me.last_name || ''}`.trim());
const today = new Date().toISOString().slice(0, 10);

const target = ref(null);
const violationTypes = ref([]);
const evidence = ref([]);
const affidavit = ref([]);
// B586's `accept` attribute was removed by B583 (10/23/2026) - see the two
// file inputs above. OK_EXT/badFile() below is the only gate now.
const OK_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
const MAX_BYTES = 10 * 1024 * 1024;

const form = ref({ complainant_address: '', violation_type: '', incident_date: '', description: '', certification_agreed: false });
const sending = ref(false);
const error = ref('');
const filed = ref(null);

// B583: real-time, per-field validation (mirrors the pattern already used on
// the staff "File a Complaint" page) instead of only checking on submit.
const fieldErrors = ref({});
function clearFieldError(field) {
  if (fieldErrors.value[field]) fieldErrors.value = { ...fieldErrors.value, [field]: false };
}
function validateField(field) {
  const isInvalid = {
    complainant_address: () => !form.value.complainant_address.trim() || !hasLetter(form.value.complainant_address),
    violation_type:       () => !form.value.violation_type,
    incident_date:        () => !form.value.incident_date || form.value.incident_date > today,
    description:          () => !form.value.description.trim() || !hasLetter(form.value.description),
  }[field]?.();
  fieldErrors.value = { ...fieldErrors.value, [field]: !!isInvalid };
}
// B584: must say something, not just contain digits.
const addressNumbersOnly     = computed(() => !!form.value.complainant_address.trim() && !hasLetter(form.value.complainant_address));
const descriptionNumbersOnly = computed(() => !!form.value.description.trim() && !hasLetter(form.value.description));
// B585: live as soon as a future date is entered.
const incidentDateInFuture   = computed(() => !!form.value.incident_date && form.value.incident_date > today);

onMounted(async () => {
  try {
    const res = await studentApi.get('/student/outreach/options');
    violationTypes.value = res.data.violation_types || [];
  } catch (e) { /* the list stays empty */ }
});

function goBack() {
  if (window.history.state && window.history.state.back) router.back();
  else router.push({ name: 'student-refer-choice' });
}

// Keep only the chosen files in the list; wrong types and big files are reported on submit.
function pick(event, list) {
  list.splice(0, list.length, ...Array.from(event.target.files));
}
function badFile(files) {
  return files.find(f => !OK_EXT.includes((f.name.split('.').pop() || '').toLowerCase()) || f.size > MAX_BYTES);
}

async function submit() {
  error.value = '';
  const f = form.value;
  if (!target.value) { error.value = 'Find the student by their ID Number or full name first.'; return; }
  if (!f.complainant_address.trim() || !f.violation_type || !f.incident_date || !f.description.trim()) {
    fieldErrors.value = {
      complainant_address: !f.complainant_address.trim(),
      violation_type: !f.violation_type,
      incident_date: !f.incident_date,
      description: !f.description.trim(),
    };
    error.value = 'Please fill out all required fields.';
    return;
  }
  if (f.incident_date > today) {
    fieldErrors.value = { ...fieldErrors.value, incident_date: true };
    error.value = 'The date of incident cannot be in the future.';
    return;
  }
  if (f.description.trim().length < 10) { error.value = 'Please describe the facts in at least 10 characters.'; return; }
  if (!hasLetter(f.complainant_address)) {
    fieldErrors.value = { ...fieldErrors.value, complainant_address: true };
    error.value = 'Please provide a valid address. Numbers alone are not allowed.';
    return;
  }
  if (!hasLetter(f.description)) {
    fieldErrors.value = { ...fieldErrors.value, description: true };
    error.value = 'Please provide a valid narration of facts. Numbers alone are not allowed.';
    return;
  }
  if (!evidence.value.length) { fieldErrors.value = { ...fieldErrors.value, evidence: true }; error.value = 'Please attach at least one Evidence file.'; return; }
  if (!affidavit.value.length) { fieldErrors.value = { ...fieldErrors.value, affidavit: true }; error.value = 'Please attach at least one Affidavit of Witness file.'; return; }
  const bad = badFile(evidence.value) || badFile(affidavit.value);
  if (bad) { error.value = `"${bad.name}" is not allowed. Use PDF, DOC, DOCX, JPG or PNG files up to 10 MB.`; return; }
  if (!f.certification_agreed) { error.value = 'Please tick the certification.'; return; }

  const fd = new FormData();
  fd.append('complainee_student_id', target.value.id);
  fd.append('complainant_address', f.complainant_address.trim());
  fd.append('violation_type', f.violation_type);
  fd.append('incident_date', f.incident_date);
  fd.append('description', f.description.trim());
  fd.append('certification_agreed', '1');
  evidence.value.forEach(file => fd.append('evidence[]', file));
  affidavit.value.forEach(file => fd.append('affidavit[]', file));

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