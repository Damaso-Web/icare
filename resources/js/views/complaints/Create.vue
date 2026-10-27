<template>
  <div class="fade-up">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" type="button" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>File a Complaint</h1>
        <!-- B577: "SDU Head" is an internal role name, not the unit name. -->
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
            @blur="validateField('complainant_address')"
            required
          />
          <!-- B584: live, as the user types. -->
          <div v-if="addressNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid address. Numbers alone are not allowed.</div>
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
            maxlength="100"
            :style="errorStyle('complainee_student_id')"
            placeholder="Search by student name..."
            @keypress="blockLetterKeypress"
            @input="onStudentSearch"
            @focus="showStudentDropdown = studentSuggestions.length > 0"
            @blur="validateField('complainee_student_id')"
          />
          <div v-if="studentSearchLoading" style="font-size:11px;color:var(--stone);margin-top:4px">Searching...</div>
          <!-- B636: a Dean/Dept Chair account acts only for their own
               college/department - if the student exists but sits outside
               that scope, say so specifically instead of implying they
               don't exist in the portal at all. -->
          <div v-else-if="studentSearchQuery.length >= 2 && !showStudentDropdown && !studentSuggestions.length && studentOutOfScope" style="font-size:11px;color:var(--red);margin-top:4px">
            No matching student found under your college or department. Only students currently enrolled under your college can be selected for referrals or complaints.
          </div>
          <div v-else-if="studentSearchQuery.length >= 2 && !showStudentDropdown && !studentSuggestions.length" style="font-size:11px;color:var(--red);margin-top:4px">
            No matching student found. Double-check the name.
          </div>
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

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="ifl">Position / Role</label>
            <input v-model="form.complainee_position" type="text" class="ifi" placeholder="e.g. Org Officer, if applicable" maxlength="255" @input="form.complainee_position = String(form.complainee_position ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
          </div>
          <div>
            <label class="ifl">Office</label>
            <input v-model="form.complainee_office" type="text" class="ifi" placeholder="e.g. Student Council Office, if applicable" maxlength="255" @input="form.complainee_office = String(form.complainee_office ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="ifl">College</label>
            <select v-model="selectedCollegeId" class="ifse" :disabled="true" @change="onCollegeChange">
              <option value="" disabled hidden>Select college...</option>
              <option v-for="c in colleges" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div v-if="studentFound && selectedCollegeId" style="font-size:11px;color:var(--stone);margin-top:4px">Taken from the selected student's record. Search a different student to change it.</div>
          </div>
          <div>
            <label class="ifl">Department</label>
            <select v-model="form.complainee_department" class="ifse" :disabled="true">
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
            :readonly="true"
            :disabled="!studentFound"
            style="background:var(--snow);color:var(--slate);cursor:default"
            :title="studentFound ? 'Taken from the student\'s record. Search a different student to change it.' : 'Search and select a student to fill this in.'"
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
            @blur="validateField('violation_type')"
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
            :max="todayStr"
            :style="errorStyle('incident_date')"
            @input="clearFieldError('incident_date')"
            @blur="validateField('incident_date')"
            required
          />
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Narration of Relevant and Material Facts <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(max 2000 characters)</span></label>
            <textarea
            v-model="form.description"
            class="ifta"
            style="min-height:120px"
            placeholder="Describe what happened, when, where, and how, in as much detail as possible..."
            maxlength="2000"
            :style="errorStyle('description')"
            @input="clearFieldError('description')"
            @blur="validateField('description')"
            required
          ></textarea>
          <!-- B584: live, as the user types. -->
          <div v-if="descriptionNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid narration of facts. Numbers alone are not allowed.</div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Evidence <span style="color:var(--red)">*</span></label>
          <!-- B586: extensions alone make some Android/Chrome versions fall
               back to a camera/gallery-only picker with no "Files" option.
               Pairing each extension with its real MIME type fixes it. -->
          <input type="file" multiple :accept="FILE_ACCEPT" class="ifi" :style="errorStyle('evidence')" @change="onFilesSelected($event, 'evidence')" />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">Accepted file types: PDF, JPG, PNG, DOC, DOCX.</div>
          <div v-if="evidenceFiles.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ evidenceFiles.length }} file(s) selected</div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Affidavit of Witness <span style="color:var(--red)">*</span></label>
          <input type="file" multiple :accept="FILE_ACCEPT" class="ifi" :style="errorStyle('affidavit')" @change="onFilesSelected($event, 'affidavit')" />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">Accepted file types: PDF, JPG, PNG, DOC, DOCX.</div>
          <div v-if="affidavitFiles.length" style="font-size:11px;color:var(--stone);margin-top:4px">{{ affidavitFiles.length }} file(s) selected</div>
        </div>

        <div v-if="error" style="background:var(--red-lt);color:var(--red);border-radius:var(--r-sm);padding:10px 12px;font-size:13px;margin-bottom:14px">
          {{ error }}
        </div>

        <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:8px">
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

                    <div v-if="modalError" style="background:var(--red-lt);color:var(--red);border-radius:var(--r-sm);padding:10px 12px;font-size:13px">{{ modalError }}</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="loading" @click="handleSubmit">
              {{ loading ? 'Filing...' : 'File Complaint' }}
            </button>
            <button class="ibtn ibtn-o" @click="showCertModal = false" :disabled="loading">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Filed: offer to print the complaint before leaving the page. -->
    <div v-if="filedComplaint" class="cp-overlay">
      <div class="cp-box">
        <div class="cp-head">
          <div>
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Complaint Filed</div>
            <div style="font-size:12px;color:var(--stone)">{{ filedComplaint.complaint_code }} was sent to the Student Discipline Unit.</div>
          </div>
        </div>
        <div class="cp-body">
          <div class="complaint-print">
            <div class="cp-print-title">Complaint {{ filedComplaint.complaint_code }}</div>
            <ComplaintDetail :complaint="filedComplaint" />
          </div>
        </div>
        <div class="cp-foot">
          <button class="ibtn ibtn-p" @click="printComplaint">Print Complaint</button>
          <button class="ibtn ibtn-o" @click="finishFiling">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { studentAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { onlyLetters, hasLetter } from '../../utils/validators';
import ComplaintDetail from '../../components/ComplaintDetail.vue';

// Student search here is by name only (the API call below passes
// name_only: 1), so unlike the Refer Student form's ID-or-name search,
// digits are not valid input - blocked both at keypress and on paste/input.
function blockLetterKeypress(e) {
  if (!/^[a-zA-Z\s\-]$/.test(e.key)) {
    e.preventDefault();
  }
}

const router = useRouter();
const toast  = inject('toast');

// After filing, the complaint is shown with a Print option.
const filedComplaint = ref(null);
function printComplaint() {
  document.body.classList.add('printing-complaint');
  window.print();
  setTimeout(() => document.body.classList.remove('printing-complaint'), 500);
}
function finishFiling() {
  filedComplaint.value = null;
  router.push({ name: auth.user?.role === 'sdu_head' ? 'complaints' : 'dashboard' });
}
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
      params: { category: 'act_of_misconduct', active: 1 },
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

// B584: Address and Narration of Facts must say something, not just contain
// digits. Shown live as the user types (not only on submit).
const addressNumbersOnly     = computed(() => !!form.value.complainant_address && !hasLetter(form.value.complainant_address));
const descriptionNumbersOnly = computed(() => !!form.value.description && !hasLetter(form.value.description));

function validateField(field) {
  const isEmpty = {
    complainant_name:      () => !form.value.complainant_name,
    complainant_address:   () => !form.value.complainant_address || !hasLetter(form.value.complainant_address),
    complainee_student_id: () => !form.value.complainee_student_id,
    violation_type:        () => !form.value.violation_type,
    incident_date:         () => !form.value.incident_date,
    description:           () => !form.value.description || !hasLetter(form.value.description),
  }[field]?.();

  fieldErrors.value = { ...fieldErrors.value, [field]: !!isEmpty };
}

function errorStyle(field) {
  return fieldErrors.value[field]
    ? 'border-color:var(--red);border-width:1.5px'
    : '';
}

const error   = ref('');
const modalError = ref('');
const loading = ref(false);

const studentSearchQuery   = ref('');
const studentSuggestions   = ref([]);
const showStudentDropdown  = ref(false);
const studentSearchLoading = ref(false);
const studentFound         = ref(false);
// B636: true when the search matched a real student outside the account's
// own college/department scope, not when nothing matches at all.
const studentOutOfScope    = ref(false);
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

// Date of Incident cannot be in the future (local date, not UTC).
const _now = new Date();
const todayStr = `${_now.getFullYear()}-${String(_now.getMonth() + 1).padStart(2, '0')}-${String(_now.getDate()).padStart(2, '0')}`;

// B586: extensions + explicit MIME types, so Android/Chrome's file chooser
// offers "Files" alongside Camera/Gallery instead of defaulting to the latter.
const FILE_ACCEPT = '.pdf,.jpg,.jpeg,.png,.doc,.docx,application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

const evidenceFiles  = ref([]);
const affidavitFiles = ref([]);
function onFilesSelected(event, category) {
  const files = Array.from(event.target.files || []);
  if (category === 'evidence') evidenceFiles.value = files;
  else affidavitFiles.value = files;
  clearFieldError(category);
}

const showCertModal        = ref(false);
const certificationChecked = ref(false);
const signatureCertified   = ref(false);
// Always the Complainant Name - there's nothing to toggle since that field
// is itself locked to the account name (see the Name input above).
const pkiSignature         = ref('');

function onStudentSearch() {
  studentSearchQuery.value = onlyLetters(studentSearchQuery.value);
  clearTimeout(studentSearchTimeout);
  studentFound.value = false;
  studentOutOfScope.value = false;
  form.value.complainee_student_id = null;
  selectedStudent.value = null;
  // Any change to the search box drops the previously filled student details.
  form.value.complainee_address = '';
  form.value.complainee_college = '';
  form.value.complainee_department = '';
  selectedCollegeId.value = '';
  departments.value = [];

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
      studentOutOfScope.value = !!res.data.out_of_scope;
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
  if (!evidenceFiles.value.length)       { errs.evidence = true;             missing.push('Evidence (attach at least one file)'); }
  if (!affidavitFiles.value.length)      { errs.affidavit = true;            missing.push('Affidavit of Witness (attach at least one file)'); }
  if (form.value.incident_date && form.value.incident_date > todayStr) { errs.incident_date = true; missing.push('a Date of Incident that is not in the future'); }

  fieldErrors.value = errs;
  if (missing.length) {
    error.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }

  // B584: non-empty but numbers-only is a different error than "missing".
  if (!hasLetter(form.value.complainant_address)) {
    fieldErrors.value = { ...fieldErrors.value, complainant_address: true };
    error.value = 'Please provide a valid address. Numbers alone are not allowed.';
    return;
  }
  if (!hasLetter(form.value.description)) {
    fieldErrors.value = { ...fieldErrors.value, description: true };
    error.value = 'Please provide a valid narration of facts. Numbers alone are not allowed.';
    return;
  }

   certificationChecked.value = false;
  signatureCertified.value = false;
  pkiSignature.value = form.value.complainant_name;
  modalError.value = '';
  showCertModal.value = true;
}

async function handleSubmit() {
  modalError.value = '';
  if (!certificationChecked.value) {
    modalError.value = 'Please tick the Non-Forum-Shopping certification checkbox before submitting.';
    return;
  }
  if (!signatureCertified.value) {
    modalError.value = 'Please tick the true-and-correct / signature checkbox before submitting.';
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

    const res = await axios.post(`${API_BASE}/complaints`, payload, {
      ...authHeaders(),
      headers: { ...authHeaders().headers, 'Content-Type': 'multipart/form-data' },
    });
    toast?.success('Complaint filed.');
    showCertModal.value = false;
    filedComplaint.value = res.data;
  } catch (e) {
    modalError.value = e.response?.data?.message || 'Failed to submit complaint.';
  } finally {
    loading.value = false;
  }
}
</script>

<style>
/* Printing a filed complaint: only the complaint is printed. */
@media print {
  body.printing-complaint * { visibility: hidden !important; }
  body.printing-complaint .complaint-print, body.printing-complaint .complaint-print * { visibility: visible !important; }
  body.printing-complaint .complaint-print { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<style scoped>
.cp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px}
.cp-box{background:#fff;border-radius:var(--r-lg);width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--sh-lg)}
.cp-head{padding:18px 22px;border-bottom:1px solid var(--cloud)}
.cp-body{padding:18px 22px;overflow-y:auto}
.cp-foot{padding:14px 22px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:8px}
.cp-print-title{font-size:16px;font-weight:700;color:var(--ink);margin-bottom:12px}

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