<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>File a Complaint</h1>
      <p>Report an act of misconduct against a student. This is reviewed by the SDU Head.</p>
    </div>

    <div class="icard" style="max-width:680px;margin:0 auto">
      <form @submit.prevent="openCertificationModal" style="padding:22px">

        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
          Complainant Information
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Name <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.complainant_name"
            type="text"
            class="ifi"
            placeholder="Your full name..."
            maxlength="255"
            readonly
            :style="[errorStyle('complainant_name'), 'background:var(--snow);color:var(--stone);cursor:not-allowed']"
          />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">Taken from your account and cannot be edited.</div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Address <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.complainant_address"
            type="text"
            class="ifi"
            placeholder="Your current address..."
            maxlength="255"
            :style="errorStyle('complainant_address')"
            @input="clearFieldError('complainant_address')"
            required
          />
        </div>

        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
          Complainee Information
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <div style="margin-bottom:14px;position:relative">
          <label class="ifl">Search Student <span style="color:var(--red)">*</span></label>
          <input
            v-model="studentSearchQuery"
            type="text"
            class="ifi"
            :style="errorStyle('complainee_student_id')"
            placeholder="Search by student name..."
            @keypress="blockSpecialKeypress"
            @input="onStudentSearch"
            @focus="showStudentDropdown = studentSuggestions.length > 0"
          />
          <div v-if="studentSearchLoading" style="font-size:11px;color:var(--stone);margin-top:4px">Searching...</div>
          <div v-if="showStudentDropdown && studentSuggestions.length" class="student-dropdown">
            <div
              v-for="s in studentSuggestions"
              :key="s.id"
              class="student-dropdown-item"
              @click="selectStudent(s)"
            >
              <div style="font-weight:600;font-size:13px">{{ s.last_name }}, {{ s.first_name }} {{ s.middle_name }}</div>
              <div style="font-size:11px;color:var(--stone)">{{ s.college }}</div>
            </div>
          </div>
        </div>

        <div v-if="studentFound" style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;margin-bottom:14px;font-size:13px">
          <div><strong>{{ selectedStudent.last_name }}, {{ selectedStudent.first_name }} {{ selectedStudent.middle_name }}</strong></div>
          <div style="color:var(--stone)">{{ selectedStudent.college }} &middot; {{ selectedStudent.program }}</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="ifl">Position / Role</label>
            <input v-model="form.complainee_position" type="text" class="ifi" placeholder="e.g. Org Officer, if applicable" maxlength="255" />
          </div>
          <div>
            <label class="ifl">Office</label>
            <input v-model="form.complainee_office" type="text" class="ifi" placeholder="e.g. Student Council Office, if applicable" maxlength="255" />
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="ifl">College</label>
            <select v-model="selectedCollegeId" class="ifse" :disabled="studentFound && !!selectedCollegeId" @change="onCollegeChange">
              <option value="" disabled hidden>Select college...</option>
              <option v-for="c in colleges" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div v-if="studentFound && selectedCollegeId" style="font-size:11px;color:var(--stone);margin-top:4px">Taken from the selected student's record. Search a different student to change it.</div>
          </div>
          <div>
            <label class="ifl">Department</label>
            <select v-model="form.complainee_department" class="ifse" :disabled="!selectedCollegeId || (studentFound && !!selectedCollegeId)">
              <option value="" disabled hidden>Select department...</option>
              <option v-for="d in departments" :key="d.id" :value="d.name">{{ d.name }}</option>
            </select>
            <div v-if="studentFound && selectedCollegeId && form.complainee_department" style="font-size:11px;color:var(--stone);margin-top:4px">Matched from the student's program. Search a different student to change it.</div>
            <div v-else-if="studentFound && selectedCollegeId" style="font-size:11px;color:var(--stone);margin-top:4px">No matching department found for this student's program.</div>
          </div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Address</label>
          <input
            v-model="form.complainee_address"
            type="text"
            class="ifi"
            placeholder="Complainee's address..."
            maxlength="255"
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
            :style="errorStyle('violation_type')"
            @change="clearFieldError('violation_type')"
            required
          >
            <option value="" disabled hidden>Select act of misconduct...</option>
            <option v-for="opt in violationTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Date of Incident <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.incident_date"
            type="date"
            class="ifi"
            :style="errorStyle('incident_date')"
            @input="clearFieldError('incident_date')"
            required
          />
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Narration of Relevant and Material Facts <span style="color:var(--red)">*</span></label>
          <textarea
            v-model="form.description"
            class="ifta"
            style="min-height:120px"
            placeholder="Describe what happened, when, where, and how, in as much detail as possible..."
            maxlength="2000"
            :style="errorStyle('description')"
            @input="clearFieldError('description')"
            required
          ></textarea>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Evidence (optional)</label>
          <input type="file" multiple class="ifi" @change="onFilesSelected($event, 'evidence')" />
          <div v-if="evidenceFiles.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ evidenceFiles.length }} file(s) selected</div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Affidavit of Witness (optional)</label>
          <input type="file" multiple class="ifi" @change="onFilesSelected($event, 'affidavit')" />
          <div v-if="affidavitFiles.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ affidavitFiles.length }} file(s) selected</div>
        </div>

        <div v-if="error" style="background:var(--red-lt);color:var(--red);border-radius:var(--r-sm);padding:10px 12px;font-size:13px;margin-bottom:14px">
          {{ error }}
        </div>

        <div style="display:flex;gap:9px;margin-top:8px">
          <button type="submit" class="ibtn ibtn-p" :disabled="loading">
            <svg v-if="!loading" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
            {{ loading ? 'Submitting...' : 'Submit Complaint' }}
          </button>
          <button type="button" class="ibtn ibtn-g" @click="goBack">Cancel</button>
        </div>

      </form>
    </div>

    <!-- Certification Modal - shown only at submit time, not persisted on the page -->
    <div v-if="showCertModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCertModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Certification / Statement of Non-Forum Shopping</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:12px 14px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">
            I hereby certify that I have not commenced any other action or proceeding involving the same issues in any other court, tribunal, or administrative agency; that to the best of my knowledge, no such action or proceeding is pending in any court, tribunal, or administrative agency; and that if I should thereafter learn that a similar action or proceeding has been filed or is pending, I shall report that fact within five (5) days to the office where this complaint was filed.
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
            <input
              v-model="pkiSignature"
              type="text"
              class="ifi"
              maxlength="255"
              readonly
              style="background:var(--snow);color:var(--stone);cursor:not-allowed"
            />
          </div>

          <div v-if="error" style="background:var(--red-lt);color:var(--red);border-radius:var(--r-sm);padding:10px 12px;font-size:13px">{{ error }}</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="loading" @click="handleSubmit">
              {{ loading ? 'Filing...' : 'File Complaint' }}
            </button>
            <button class="ibtn ibtn-o" @click="showCertModal = false" :disabled="loading">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { studentAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const router = useRouter();
const toast  = inject('toast');
const auth   = useAuthStore();

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const violationTypeOptions = ref([]);
async function fetchFormOptions() {
  try {
    const res = await axios.get(`${API_BASE}/management/form-options`, {
      ...authHeaders(),
      params: { category: 'act_of_misconduct' },
    });
    violationTypeOptions.value = res.data;
  } catch (e) {
    console.error(e);
  }
}
fetchFormOptions();

const colleges         = ref([]);
const departments       = ref([]);
const selectedCollegeId = ref('');

async function fetchColleges() {
  try {
    const res = await axios.get(`${API_BASE}/management/colleges`, authHeaders());
    colleges.value = res.data;
  } catch (e) {
    console.error(e);
  }
}
fetchColleges();

async function fetchDepartments(collegeId) {
  if (!collegeId) {
    departments.value = [];
    return;
  }
  try {
    const res = await axios.get(`${API_BASE}/management/departments`, {
      ...authHeaders(),
      params: { college_id: collegeId },
    });
    departments.value = res.data;
  } catch (e) {
    departments.value = [];
  }
}

function onCollegeChange() {
  const college = colleges.value.find(c => c.id === selectedCollegeId.value);
  form.value.complainee_college = college?.name || '';
  form.value.complainee_department = '';
  fetchDepartments(selectedCollegeId.value);
}

// Best-effort match of a student's program to one of that college's
// departments - e.g. "Bachelor of Science in Civil Engineering" matches the
// department "Civil Engineering". Programs and departments aren't formally
// linked in the masterlist, but their names correspond closely enough for
// most programs to make this a reliable auto-fill; where a program has no
// equivalent department name (e.g. BS Agriculture), no match is returned and
// the field is simply left blank.
function normalizeForMatch(str) {
  return (str || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}
function matchDepartmentForProgram(programName, deptList) {
  const normProgram = normalizeForMatch(programName);
  if (!normProgram) return null;
  let best = null;
  for (const d of deptList) {
    const normDept = normalizeForMatch(d.name);
    if (normDept && normProgram.includes(normDept)) {
      if (!best || normDept.length > normalizeForMatch(best.name).length) best = d;
    }
  }
  return best;
}

const fieldErrors = ref({});
function clearFieldError(field) {
  if (fieldErrors.value[field]) {
    fieldErrors.value = { ...fieldErrors.value, [field]: false };
  }
}
function errorStyle(field) {
  return fieldErrors.value[field]
    ? 'border-color:var(--red);border-width:1.5px'
    : '';
}

const error   = ref('');
const loading = ref(false);

const studentSearchQuery   = ref('');
const studentSuggestions   = ref([]);
const showStudentDropdown  = ref(false);
const studentSearchLoading = ref(false);
const studentFound         = ref(false);
const selectedStudent      = ref(null);
let studentSearchTimeout = null;

const form = ref({
  complainant_name: auth.user?.name || '',
  complainant_address: '',
  complainee_student_id: null,
  complainee_position: '',
  complainee_college: '',
  complainee_department: '',
  complainee_office: '',
  complainee_address: '',
  violation_type: '',
  incident_date: '',
  description: '',
});

const evidenceFiles  = ref([]);
const affidavitFiles = ref([]);
function onFilesSelected(event, category) {
  const files = Array.from(event.target.files || []);
  if (category === 'evidence') evidenceFiles.value = files;
  else affidavitFiles.value = files;
}

const showCertModal        = ref(false);
const certificationChecked = ref(false);
const signatureCertified   = ref(false);
// Always the Complainant Name - there's nothing to toggle since that field
// is itself locked to the account name (see the Name input above).
const pkiSignature         = ref('');

function onStudentSearch() {
  studentSearchQuery.value = safeSearchInput(studentSearchQuery.value);
  clearTimeout(studentSearchTimeout);
  studentFound.value = false;
  form.value.complainee_student_id = null;
  selectedStudent.value = null;

  if (!studentSearchQuery.value || studentSearchQuery.value.length < 2) {
    studentSuggestions.value = [];
    showStudentDropdown.value = false;
    return;
  }
  studentSearchLoading.value = true;
  studentSearchTimeout = setTimeout(async () => {
    try {
      const res = await studentAPI.index({ search: studentSearchQuery.value, is_active: 1, name_only: 1 });
      studentSuggestions.value = res.data.data || [];
      showStudentDropdown.value = studentSuggestions.value.length > 0;
    } catch (e) {
      studentSuggestions.value = [];
    } finally {
      studentSearchLoading.value = false;
    }
  }, 350);
}

async function selectStudent(s) {
  form.value.complainee_student_id = s.id;
  selectedStudent.value = s;
  studentSearchQuery.value = `${s.last_name}, ${s.first_name}`;
  showStudentDropdown.value = false;
  studentFound.value = true;
  clearFieldError('complainee_student_id');

  // Prefill from the student's record where we have it. College and
  // Department must always match an entry from the masterlist (never a raw
  // string off the student record), so complainee_college is only ever set
  // from a matched masterlist entry - if the student's recorded college
  // string doesn't match any masterlist entry, the field is left blank for
  // manual selection from the dropdown instead of silently carrying an
  // unlisted value. Address is fully editable regardless.
  form.value.complainee_address = s.address || '';

  const matchedCollege = colleges.value.find(c => c.name === s.college);
  if (matchedCollege) {
    selectedCollegeId.value = matchedCollege.id;
    form.value.complainee_college = matchedCollege.name;
    await fetchDepartments(matchedCollege.id);
    // Department is locked once a student is matched (same as College) -
    // best-effort matched from the student's program name; left blank if
    // no department name corresponds to it.
    const matchedDept = matchDepartmentForProgram(s.program, departments.value);
    form.value.complainee_department = matchedDept ? matchedDept.name : '';
  } else {
    selectedCollegeId.value = '';
    form.value.complainee_college = '';
    departments.value = [];
    form.value.complainee_department = '';
  }
}

function goBack() {
  router.push({ name: 'referral-create' });
}

function openCertificationModal() {
  error.value = '';
  const errs = {};
  const missing = [];

  if (!form.value.complainant_name)      { errs.complainant_name = true;      missing.push('Complainant Name'); }
  if (!form.value.complainant_address)   { errs.complainant_address = true;   missing.push('Complainant Address'); }
  if (!form.value.complainee_student_id) { errs.complainee_student_id = true; missing.push('Complainee'); }
  if (!form.value.violation_type)        { errs.violation_type = true;        missing.push('Specific Act of Misconduct'); }
  if (!form.value.incident_date)         { errs.incident_date = true;         missing.push('Date of Incident'); }
  if (!form.value.description)           { errs.description = true;          missing.push('Narration of Facts'); }

  fieldErrors.value = errs;
  if (missing.length) {
    error.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }

  certificationChecked.value = false;
  signatureCertified.value = false;
  pkiSignature.value = form.value.complainant_name;
  showCertModal.value = true;
}

async function handleSubmit() {
  error.value = '';
  if (!certificationChecked.value) {
    error.value = 'Please tick the Non-Forum-Shopping certification checkbox before submitting.';
    return;
  }
  if (!signatureCertified.value) {
    error.value = 'Please tick the true-and-correct / signature checkbox before submitting.';
    return;
  }
  loading.value = true;
  try {
    const payload = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
      if (value !== null && value !== undefined) payload.append(key, value);
    });
    payload.append('certification_agreed', '1');
    evidenceFiles.value.forEach(f => payload.append('evidence[]', f));
    affidavitFiles.value.forEach(f => payload.append('affidavit[]', f));

    await axios.post(`${API_BASE}/complaints`, payload, {
      ...authHeaders(),
      headers: { ...authHeaders().headers, 'Content-Type': 'multipart/form-data' },
    });
    toast?.success('Complaint filed.');
    router.push({ name: 'referral-create' });
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to submit complaint.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.student-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #fff;
  border: 1px solid var(--cloud);
  border-radius: var(--r-sm);
  box-shadow: var(--sh-lg);
  max-height: 220px;
  overflow-y: auto;
  z-index: 10;
  margin-top: 4px;
}
.student-dropdown-item {
  padding: 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid var(--cloud);
}
.student-dropdown-item:last-child { border-bottom: none; }
.student-dropdown-item:hover { background: var(--snow); }
</style>