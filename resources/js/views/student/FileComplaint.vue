<template>
  <div class="fade-up">
    <button type="button" class="ibtn ibtn-o" style="margin-bottom:12px" @click="goBack">&larr; Back</button>
    <div class="ph" style="margin-bottom:20px">
      <h1>File a Complaint</h1>
      <p>Report an act of misconduct against a student. This is reviewed by the Student Discipline Unit.</p>
    </div>
    <div class="icard" style="max-width:680px;margin:0 auto">
      <form @submit.prevent="openCertificationModal" style="padding:22px; display:flex; flex-direction:column; gap:14px;">
        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:4px">
          Complainant Information
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <div>
          <label class="ifl">Your Name</label>
          <input class="ifi" :value="myName" readonly style="background:var(--snow);color:var(--stone);cursor:not-allowed" />
        </div>
        <div>
          <label class="ifl">Your Address <span style="color:var(--red)">*</span></label>
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
        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:8px;margin-bottom:4px">
          Complainee Information
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <StudentIdLookup v-model="target" :allow-self="false" />
        
        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:8px;margin-bottom:4px">
          Complaint Details
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

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
            :style="(fieldErrors.incident_date || dateBlockedMsg || isWeekend) ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="validateField('incident_date')"
            @change="validateField('incident_date')"
          />
          <div v-if="incidentDateInFuture" style="color:var(--red);font-size:11px;margin-top:4px">The date of incident cannot be in the future.</div>
          <div v-if="isWeekend" style="color:var(--red);font-size:11px;margin-top:4px">Weekends are not allowed. Please select a weekday.</div>
          <div v-if="dateBlockedMsg" style="color:var(--red);font-size:11px;margin-top:4px">{{ dateBlockedMsg }}</div>
        </div>
        <div>
          <label class="ifl">Narration of Facts <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(at least 10 characters)</span></label>
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
          <div v-if="descriptionNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid narration of facts. Numbers alone are not allowed.</div>
        </div>
        
        <div>
          <label class="ifl">Evidence <span style="color:var(--fog);font-weight:400">(PDF, JPG, PNG; up to 10 MB)</span></label>
          <input type="file" multiple @change="pick($event, evidence); clearFieldError('proof')" />
          <div v-if="evidence.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ evidence.length }} file(s) selected</div>
        </div>
        <div>
          <label class="ifl">Affidavit of Witness <span style="color:var(--fog);font-weight:400">(PDF, JPG, PNG; up to 10 MB)</span></label>
          <input type="file" multiple @change="pick($event, affidavit); clearFieldError('proof')" />
          <div v-if="affidavit.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ affidavit.length }} file(s) selected</div>
          <div v-if="fieldErrors.proof" style="color:var(--red);font-size:11px;margin-top:4px">Please attach at least one Evidence file or at least one Affidavit of Witness file.</div>
        </div>
        
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
        
        <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:8px">
          <button type="submit" class="ibtn ibtn-p" :disabled="sending">{{ sending ? 'Submitting...' : 'Submit Complaint' }}</button>
          <button type="button" class="ibtn ibtn-g" :disabled="sending" @click="goBack">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Certification Modal -->
    <div v-if="showCertModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCertModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Certification / Statement of Non-Forum Shopping</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:12px 14px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">
            I hereby certify that I have not commenced any other action or proceeding involving the same issues in any other court, tribunal, or administrative agency...
          </div>
          <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:var(--slate)">
            <input type="checkbox" v-model="certificationChecked" style="width:15px;height:15px;accent-color:var(--moss);margin-top:2px" />
            I have read and agree to the certification above.
          </label>

          <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:12px 14px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">
            I hereby confirm that the details here are true and correct to the best of my knowledge, and that typing my name below serves in place of my physical signature.
          </div>
          <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;color:var(--slate)">
            <input type="checkbox" v-model="signatureCertified" style="width:15px;height:15px;accent-color:var(--moss);margin-top:2px" />
            I have read and agree to the statement above.
          </label>
          <div>
            <label class="ifl">Signature (your Complainant Name)</label>
            <input :value="myName" type="text" class="ifi" readonly style="background:var(--snow);color:var(--stone);cursor:not-allowed" />
          </div>
          <div v-if="modalError" style="background:var(--red-lt);color:var(--red);border-radius:var(--r-sm);padding:10px 12px;font-size:13px">{{ modalError }}</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="sending" @click="handleSubmit">
              {{ sending ? 'Filing...' : 'File Complaint' }}
            </button>
            <button class="ibtn ibtn-o" @click="showCertModal = false" :disabled="sending">Cancel</button>
          </div>
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
import { useBlockedDates } from '../../utils/blockedDates';

const router = useRouter();

let me = {};
try { me = JSON.parse(localStorage.getItem('student') || '{}'); } catch (e) { me = {}; }
const myName = computed(() => `${me.first_name || ''} ${me.last_name || ''}`.trim());
const today = new Date().toISOString().slice(0, 10);

const target = ref(null);
const violationTypes = ref([]);
const evidence = ref([]);
const affidavit = ref([]);
const OK_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
const MAX_BYTES = 10 * 1024 * 1024;

const form = ref({ complainant_address: '', violation_type: '', incident_date: '', description: '' });
const sending = ref(false);
const error = ref('');
const modalError = ref('');
const filed = ref(null);

// Modal state
const showCertModal = ref(false);
const certificationChecked = ref(false);
const signatureCertified = ref(false);

// Blocked dates
const { load: loadBlocked, messageFor: blockedMessageFor } = useBlockedDates('student');
const dateBlockedMsg = computed(() => blockedMessageFor(form.value.incident_date));
const isWeekend = computed(() => {
  if (!form.value.incident_date) return false;
  const d = new Date(form.value.incident_date + 'T00:00:00');
  return d.getDay() === 0 || d.getDay() === 6;
});

// Field validation
const fieldErrors = ref({});
function clearFieldError(field) {
  if (fieldErrors.value[field]) fieldErrors.value = { ...fieldErrors.value, [field]: false };
}
function validateField(field) {
  const isInvalid = {
    complainant_address: () => !form.value.complainant_address.trim() || !hasLetter(form.value.complainant_address),
    violation_type:       () => !form.value.violation_type,
    incident_date:        () => !form.value.incident_date || form.value.incident_date > today || isWeekend.value || !!dateBlockedMsg.value,
    description:          () => !form.value.description.trim() || !hasLetter(form.value.description),
  }[field]?.();
  fieldErrors.value = { ...fieldErrors.value, [field]: !!isInvalid };
}

const addressNumbersOnly     = computed(() => !!form.value.complainant_address.trim() && !hasLetter(form.value.complainant_address));
const descriptionNumbersOnly = computed(() => !!form.value.description.trim() && !hasLetter(form.value.description));
const incidentDateInFuture   = computed(() => !!form.value.incident_date && form.value.incident_date > today);

onMounted(async () => {
  loadBlocked();
  try {
    const res = await studentApi.get('/student/outreach/options');
    violationTypes.value = res.data.violation_types || [];
  } catch (e) { /* the list stays empty */ }
});

function goBack() {
  if (window.history.state && window.history.state.back) router.back();
  else router.push({ name: 'student-refer-choice' });
}

function pick(event, list) {
  const files = Array.from(event.target.files);
  const bad = files.find(f => !OK_EXT.includes((f.name.split('.').pop() || '').toLowerCase()) || f.size > MAX_BYTES);
  
  if (bad) {
    error.value = `"${bad.name}" is not allowed. Please use PDF, JPG, PNG, DOC, or DOCX files up to 10MB.`;
    event.target.value = ''; // Clear the invalid file from the input
    list.splice(0, list.length); // Clear the list
    return;
  }
  
  error.value = ''; // Clear any previous errors
  list.splice(0, list.length, ...files);
  clearFieldError('proof'); // Clear the "no file attached" error if it was showing
}

function badFile(files) {
  return files.find(f => !OK_EXT.includes((f.name.split('.').pop() || '').toLowerCase()) || f.size > MAX_BYTES);
}

function openCertificationModal() {
  error.value = '';
  modalError.value = '';
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
  if (f.incident_date > today || isWeekend.value || dateBlockedMsg.value) {
    fieldErrors.value = { ...fieldErrors.value, incident_date: true };
    error.value = 'The date of incident cannot be in the future, on a weekend, or a holiday.';
    return;
  }
  if (f.description.trim().length < 10) { error.value = 'Please describe the facts in at least 10 characters.'; return; }
  if (!hasLetter(f.complainant_address) || !hasLetter(f.description)) { error.value = 'Please provide valid text for address and narration.'; return; }
  if (!evidence.value.length && !affidavit.value.length) {
    fieldErrors.value = { ...fieldErrors.value, proof: true };
    error.value = 'Please attach at least one Evidence file or at least one Affidavit of Witness file.';
    return;
  }
  const bad = badFile(evidence.value) || badFile(affidavit.value);
  if (bad) { error.value = `"${bad.name}" is not allowed. Use PDF, DOC, DOCX, JPG or PNG files up to 10 MB.`; return; }

  certificationChecked.value = false;
  signatureCertified.value = false;
  showCertModal.value = true;
}

async function handleSubmit() {
  modalError.value = '';
  if (!certificationChecked.value || !signatureCertified.value) {
    modalError.value = 'Please tick both certification checkboxes before submitting.';
    return;
  }

  const fd = new FormData();
  fd.append('complainee_student_id', target.value.id);
  fd.append('complainant_address', form.value.complainant_address.trim());
  fd.append('violation_type', form.value.violation_type);
  fd.append('incident_date', form.value.incident_date);
  fd.append('description', form.value.description.trim());
  fd.append('certification_agreed', '1');
  evidence.value.forEach(file => fd.append('evidence[]', file));
  affidavit.value.forEach(file => fd.append('affidavit[]', file));

  sending.value = true;
  try {
    const res = await studentApi.post('/student/outreach/complaints', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
    showCertModal.value = false;
    filed.value = res.data;
  } catch (e) {
    const errs = e.response?.data?.errors;
    modalError.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not submit the complaint. Please try again.';
  } finally {
    sending.value = false;
  }
}

function finish() {
  filed.value = null;
  router.push({ name: 'student-sent-complaints' });
}
</script>