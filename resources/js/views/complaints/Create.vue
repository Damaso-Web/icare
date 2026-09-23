<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>File a Complaint</h1>
      <p>Report an act of misconduct against a student. This is reviewed by the SDU Head.</p>
    </div>

    <div class="icard" style="max-width:680px">
      <form @submit.prevent="openCertificationModal" style="padding:22px">

        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
          Complainant
          <div style="flex:1;height:1px;background:var(--cloud)"></div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Name <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.complainant_name"
            type="text"
            class="ifi"
            :style="errorStyle('complainant_name')"
            @input="clearFieldError('complainant_name')"
            required
          />
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Address <span style="color:var(--red)">*</span></label>
          <input
            v-model="form.complainant_address"
            type="text"
            class="ifi"
            placeholder="Your current address..."
            :style="errorStyle('complainant_address')"
            @input="clearFieldError('complainant_address')"
            required
          />
        </div>

        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
          Complainee
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
            <label class="ifl">Position</label>
            <input v-model="form.complainee_position" type="text" class="ifi" placeholder="e.g. Org Officer, if applicable" />
          </div>
          <div>
            <label class="ifl">College</label>
            <input v-model="form.complainee_college" type="text" class="ifi" />
          </div>
          <div>
            <label class="ifl">Department</label>
            <input v-model="form.complainee_department" type="text" class="ifi" />
          </div>
          <div>
            <label class="ifl">Office</label>
            <input v-model="form.complainee_office" type="text" class="ifi" />
          </div>
        </div>

        <div style="margin-bottom:14px">
          <label class="ifl">Address</label>
          <input v-model="form.complainee_address" type="text" class="ifi" />
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
          <div v-if="error" style="background:var(--red-lt);color:var(--red);border-radius:var(--r-sm);padding:10px 12px;font-size:13px">{{ error }}</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!certificationChecked || loading" @click="handleSubmit">
              {{ loading ? 'Submitting...' : 'Confirm & Submit' }}
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

function selectStudent(s) {
  form.value.complainee_student_id = s.id;
  selectedStudent.value = s;
  studentSearchQuery.value = `${s.last_name}, ${s.first_name}`;
  showStudentDropdown.value = false;
  studentFound.value = true;
  clearFieldError('complainee_student_id');

  // Prefill from the student's record where we have it; still editable.
  form.value.complainee_college = s.college || '';
  form.value.complainee_address = s.address || '';
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
  showCertModal.value = true;
}

async function handleSubmit() {
  if (!certificationChecked.value) return;
  error.value = '';
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