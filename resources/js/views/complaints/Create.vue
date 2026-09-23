<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>File a Complaint</h1>
      <p>Report an act of misconduct against a student. This is reviewed by the SDU Head.</p>
    </div>

    <div class="icard" style="max-width:680px">
      <form @submit.prevent="handleSubmit" style="padding:22px">

        <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
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
            placeholder="Search by name or student ID..."
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
              <div style="font-size:11px;color:var(--stone)">{{ s.student_id }} &middot; {{ s.college }}</div>
            </div>
          </div>
        </div>

        <div v-if="studentFound" style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;margin-bottom:14px;font-size:13px">
          <div><strong>{{ selectedStudent.last_name }}, {{ selectedStudent.first_name }} {{ selectedStudent.middle_name }}</strong></div>
          <div style="color:var(--stone)">{{ selectedStudent.student_id }} &middot; {{ selectedStudent.college }} &middot; {{ selectedStudent.program }}</div>
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
          <label class="ifl">Incident Report <span style="color:var(--red)">*</span></label>
          <textarea
            v-model="form.description"
            class="ifta"
            placeholder="Describe the incident in detail, including date, time, location, and persons involved..."
            :style="errorStyle('description')"
            @input="clearFieldError('description')"
            required
          ></textarea>
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
  </div>
</template>

<script setup>
import { ref, inject } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { studentAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const router = useRouter();
const toast  = inject('toast');

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

// The Act of Misconduct list is the same Management-driven reference data
// that used to live inline in the Refer Student form.
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
  complainee_student_id: null,
  violation_type: '',
  incident_date: '',
  description: '',
});

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
      const res = await studentAPI.index({ search: studentSearchQuery.value, is_active: 1 });
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
}

function goBack() {
  router.push({ name: 'referral-create' });
}

async function handleSubmit() {
  error.value = '';
  const errs = {};
  const missing = [];

  if (!form.value.complainee_student_id) { errs.complainee_student_id = true; missing.push('Complainee'); }
  if (!form.value.violation_type)        { errs.violation_type = true;        missing.push('Specific Act of Misconduct'); }
  if (!form.value.incident_date)         { errs.incident_date = true;         missing.push('Date of Incident'); }
  if (!form.value.description)           { errs.description = true;          missing.push('Incident Report'); }

  fieldErrors.value = errs;
  if (missing.length) {
    error.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }

  loading.value = true;
  try {
    await axios.post(`${API_BASE}/complaints`, form.value, authHeaders());
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