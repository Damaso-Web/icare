<!--
  File a Complaint (student portal). Bug (11/3/2026): rebuilt to match the
  main (staff-side) complaint form's fields and flow - a live search
  dropdown instead of a "Find" button, plus Position/Role, Office, College,
  Department and Address once the complainee is found - while keeping the
  student-safe lookup() endpoint (exact ID or first+last name only; see
  StudentOutreachController::lookup()) instead of the staff masterlist
  search, and College/Department/Address shown read-only rather than as
  selects backed by the staff-only /management/colleges /management/departments
  endpoints (the backend already fills those in from the student's own
  record regardless - see ComplaintController::file()).
-->
<template>
  <div class="fade-up">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" type="button" @click="goBack">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>File a Complaint</h1>
        <p>Report an act of misconduct against a student. This is reviewed by the Student Discipline Unit.</p>
      </div>
    </div>
    <div class="icard" style="max-width:680px;margin:0 auto">
      <form @submit.prevent="openCertificationModal" style="padding:22px">
        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
          Complainant Information
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Name</label>
          <input class="ifi" :value="myName" readonly style="background:var(--snow);color:var(--stone);cursor:not-allowed" />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">Taken from your account and cannot be edited.</div>
        </div>
        <div style="margin-bottom:14px">
          <label class="ifl">Address <span style="color:var(--red)">*</span></label>
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
        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
          Complainee Information
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <!-- Student search, live as you type - same ID-or-full-name lookup
             as the Refer a Student page, shown as a type-ahead dropdown
             like the main form. -->
        <div style="margin-bottom:14px;position:relative">
          <label class="ifl">Search Student <span style="color:var(--red)">*</span></label>
          <input
            v-model="studentSearchQuery"
            class="ifi"
            maxlength="100"
            placeholder="Student ID Number, or full name (e.g. Juan Dela Cruz)"
            :style="fieldErrors.target ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="onStudentSearch"
            @focus="showStudentDropdown = studentSuggestions.length > 0"
            autocomplete="off"
          />

          <div
            v-if="showStudentDropdown && studentSuggestions.length > 0"
            style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);box-shadow:var(--sh-lg);z-index:50;max-height:220px;overflow-y:auto;margin-top:4px"
          >
            <div
              v-for="s in studentSuggestions"
              :key="s.id"
              style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--cloud);transition:background .1s"
              @mouseover="$event.currentTarget.style.background='var(--foam)'"
              @mouseleave="$event.currentTarget.style.background='#fff'"
              @click="selectStudent(s)"
            >
              <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ s.last_name }}, {{ s.first_name }} {{ s.middle_name }}</div>
              <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ s.student_id }} · {{ s.college || '-' }}</div>
            </div>
          </div>
          <div v-if="target" style="font-size:11px;color:var(--moss);margin-top:4px">✓ Existing student found</div>
          <div v-else-if="searchError" style="font-size:11px;color:var(--red);margin-top:4px">{{ searchError }}</div>
          <div v-else style="margin-top:4px;font-size:11px;color:var(--fog)">Type the exact Student ID Number, or the first and last name.</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="ifl">Position / Role</label>
            <input v-model="form.complainee_position" class="ifi" maxlength="255" placeholder="e.g. Org Officer, if applicable" @input="form.complainee_position = String(form.complainee_position ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,&()\- ]/g, '')" />
          </div>
          <div>
            <label class="ifl">Office</label>
            <input v-model="form.complainee_office" class="ifi" maxlength="255" placeholder="e.g. Student Council Office, if applicable" @input="form.complainee_office = String(form.complainee_office ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,&()\- ]/g, '')" />
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="ifl">College</label>
            <input class="ifi" :value="target?.college" placeholder="Select college..." readonly style="background:var(--snow);color:var(--stone)" />
            <div v-if="target?.college" style="font-size:11px;color:var(--stone);margin-top:4px">Taken from the selected student's record.</div>
          </div>
          <div>
            <label class="ifl">Department</label>
            <input class="ifi" :value="target?.department" placeholder="Select department..." readonly style="background:var(--snow);color:var(--stone)" />
            <div v-if="target?.department" style="font-size:11px;color:var(--stone);margin-top:4px">Matched from the student's program.</div>
            <div v-else-if="target" style="font-size:11px;color:var(--stone);margin-top:4px">No matching department found for this student's program.</div>
          </div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Address if known</label>
          <input
            class="ifi"
            :value="target?.address"
            placeholder="Complainee's address..."
            readonly
            disabled
            style="background:var(--snow);color:var(--slate);cursor:default"
            :title="target ? 'Taken from the student\'s record.' : 'Search and select a student to fill this in.'"
          />
        </div>

        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
          Complaint Details
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Specific Act of Misconduct <span style="color:var(--red)">*</span></label>
          <select
            v-model="form.violation_type"
            class="ifse"
            :style="fieldErrors.violation_type ? 'border-color:var(--red);border-width:1.5px' : ''"
            @change="clearFieldError('violation_type')"
            @blur="validateField('violation_type')"
          >
            <option value="" disabled>Select act of misconduct...</option>
            <option v-for="o in violationTypes" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div style="margin-bottom:14px">
          <label class="ifl">Date of Incident <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.incident_date"
            type="date"
            class="ifi"
            :max="today"
            :min="oneYearAgo"
            :style="fieldErrors.incident_date ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="validateField('incident_date')"
            @change="validateField('incident_date')"
          />
          <div v-if="incidentDateInFuture" style="color:var(--red);font-size:11px;margin-top:4px">The date of incident cannot be in the future.</div>
          <div v-if="incidentDateTooOld" style="color:var(--red);font-size:11px;margin-top:4px">The date of incident cannot be more than one year in the past.</div>
        </div>
        <div style="margin-bottom:14px">
          <label class="ifl">Narration of Relevant and Material Facts <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(at least 10, max 1000 characters)</span></label>
          <textarea
            v-model="form.description"
            class="ifta"
            style="min-height:130px"
            placeholder="Describe what happened, when, where, and how, in as much detail as possible..."
            maxlength="1000"
            :style="fieldErrors.description ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="clearFieldError('description')"
            @blur="validateField('description')"
          ></textarea>
          <div style="font-size:11px;color:var(--fog);margin-top:4px">{{ form.description.length }}/1000</div>
          <div v-if="descriptionNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid narration of facts. Numbers alone are not allowed.</div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Evidence <span style="color:var(--fog);font-weight:400">(PDF, JPG, PNG; up to 10 MB)</span></label>
          <input type="file" multiple :accept="FILE_ACCEPT" @change="pick($event, evidence); clearFieldError('proof')" />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">At least one Evidence or Affidavit of Witness file is required.</div>
          <div v-if="evidence.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ evidence.length }} file(s) selected</div>
        </div>
        <div style="margin-bottom:14px">
          <label class="ifl">Affidavit of Witness <span style="color:var(--fog);font-weight:400">(PDF, JPG, PNG; up to 10 MB)</span></label>
          <input type="file" multiple :accept="FILE_ACCEPT" @change="pick($event, affidavit); clearFieldError('proof')" />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">At least one Evidence or Affidavit of Witness file is required.</div>
          <div v-if="affidavit.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ affidavit.length }} file(s) selected</div>
          <div v-if="fieldErrors.proof" style="color:var(--red);font-size:11px;margin-top:4px">Please attach at least one Evidence file or at least one Affidavit of Witness file.</div>
        </div>

        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px;margin-bottom:14px">{{ error }}</div>

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
import ComplaintPrintModal from '../../components/ComplaintPrintModal.vue';
import { hasLetter } from '../../utils/validators';

const router = useRouter();

let me = {};
try { me = JSON.parse(localStorage.getItem('student') || '{}'); } catch (e) { me = {}; }
const myName = computed(() => `${me.first_name || ''} ${me.last_name || ''}`.trim());
const today = new Date().toISOString().slice(0, 10);
// The backend also rejects an incident date more than one year in the past
// (ComplaintController::file()'s `after_or_equal` rule, shared by the main
// form) - caught here, live, instead of only surfacing after submit.
const oneYearAgo = (() => {
  const d = new Date();
  d.setFullYear(d.getFullYear() - 1);
  return d.toISOString().slice(0, 10);
})();

const target = ref(null);
const violationTypes = ref([]);
const evidence = ref([]);
const affidavit = ref([]);
const OK_EXT = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
const MAX_BYTES = 10 * 1024 * 1024;
// B586 (matches the main complaint form): extensions alone make some
// Android/Chrome versions fall back to a camera/gallery-only picker with no
// "Files" option. Pairing each extension with its real MIME type fixes it.
const FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png,.doc,.docx,application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

const form = ref({ complainant_address: '', violation_type: '', incident_date: '', description: '', complainee_position: '', complainee_office: '' });
const sending = ref(false);
const error = ref('');
const modalError = ref('');
const filed = ref(null);

// Modal state
const showCertModal = ref(false);
const certificationChecked = ref(false);
const signatureCertified = ref(false);

// Field validation
const fieldErrors = ref({});
function clearFieldError(field) {
  if (fieldErrors.value[field]) fieldErrors.value = { ...fieldErrors.value, [field]: false };
}
function validateField(field) {
  const isInvalid = {
    complainant_address: () => !form.value.complainant_address.trim() || !hasLetter(form.value.complainant_address),
    violation_type:       () => !form.value.violation_type,
    incident_date:        () => !form.value.incident_date || form.value.incident_date > today || form.value.incident_date < oneYearAgo,
    description:          () => !form.value.description.trim() || !hasLetter(form.value.description),
  }[field]?.();
  fieldErrors.value = { ...fieldErrors.value, [field]: !!isInvalid };
}

const addressNumbersOnly     = computed(() => !!form.value.complainant_address.trim() && !hasLetter(form.value.complainant_address));
const descriptionNumbersOnly = computed(() => !!form.value.description.trim() && !hasLetter(form.value.description));
const incidentDateInFuture   = computed(() => !!form.value.incident_date && form.value.incident_date > today);
const incidentDateTooOld     = computed(() => !!form.value.incident_date && form.value.incident_date < oneYearAgo);

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

// ---- Complainee search (live, debounced) - same lookup() endpoint the
// Refer a Student page uses, shown the same way. ----
const studentSearchQuery  = ref('');
const studentSuggestions  = ref([]);
const showStudentDropdown = ref(false);
const searchError         = ref('');
let studentSearchTimeout  = null;

function onStudentSearch() {
  clearTimeout(studentSearchTimeout);
  searchError.value = '';
  // Bug (11/3/2026): with the field no longer disabled after a match, typing
  // over an already-selected student's name must itself drop that selection
  // - otherwise the old target stayed selected underneath whatever the
  // student typed next. Same behavior as the main form's own always-editable
  // search field.
  if (target.value) {
    target.value = null;
    fieldErrors.value = { ...fieldErrors.value, target: false };
  }
  if (!studentSearchQuery.value.trim()) {
    studentSuggestions.value = [];
    showStudentDropdown.value = false;
    return;
  }
  studentSearchTimeout = setTimeout(async () => {
    try {
      const res = await studentApi.post('/student/outreach/lookup', { student_id: studentSearchQuery.value.trim() });
      const matches = (res.data.matches || []).filter(m => !m.is_me);
      studentSuggestions.value = matches;
      showStudentDropdown.value = matches.length > 0;
      if (!matches.length) searchError.value = 'You cannot choose yourself here.';
      else if (matches.length === 1) selectStudent(matches[0]);
    } catch (e) {
      studentSuggestions.value = [];
      showStudentDropdown.value = false;
      searchError.value = e.response?.data?.message || 'Could not look that student up. Please try again.';
    }
  }, 350);
}

function selectStudent(s) {
  target.value = s;
  studentSearchQuery.value = `${s.last_name}, ${s.first_name}`;
  showStudentDropdown.value = false;
  searchError.value = '';
  clearFieldError('target');
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
  if (!target.value) { fieldErrors.value = { ...fieldErrors.value, target: true }; error.value = 'Find the student by their ID Number or full name first.'; return; }
  if (!f.complainant_address.trim() || !f.violation_type || !f.incident_date || !f.description.trim()) {
    fieldErrors.value = {
      ...fieldErrors.value,
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
  if (f.incident_date < oneYearAgo) {
    fieldErrors.value = { ...fieldErrors.value, incident_date: true };
    error.value = 'The date of incident cannot be more than one year in the past.';
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
  if (form.value.complainee_position.trim()) fd.append('complainee_position', form.value.complainee_position.trim());
  if (form.value.complainee_office.trim()) fd.append('complainee_office', form.value.complainee_office.trim());
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