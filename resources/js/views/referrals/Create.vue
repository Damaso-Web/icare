<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Refer Student</h1>
      <p>Complete this form to refer a student to the Office of Student Services.</p>
    </div>

    <div class="icard" style="max-width:820px;margin:0 auto">

      <!-- Document Code Header -->
      <div style="padding:14px 20px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
        <div style="font-size:11px;color:var(--stone)">
          <div><strong>Document Code:</strong> QF-OSS-01</div>
          <div><strong>Revision No.:</strong> 01</div>
        </div>
        <div style="font-size:11px;color:var(--stone);text-align:right">
          <div><strong>Effectivity:</strong> 07/04/23</div>
          <div><strong>Ctrl No.:</strong> 25-2</div>
        </div>
      </div>

      <div class="icard-body">

        <div v-if="success" style="background:var(--mist);border:1px solid var(--mint);color:var(--forest);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
          <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
          {{ success }}
        </div>
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px">
          {{ error }}
        </div>

        <form @submit.prevent="handleSubmit">

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
            Student Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <!-- Student ID / Name Search with Autocomplete -->
          <div style="margin-bottom:14px;position:relative">
            <label class="ifl">Search Student (ID or Name) <span style="color:var(--red)">*</span></label>
            <input
              v-model="studentSearchQuery"
              class="ifi"
              placeholder="e.g. 2302021 or Dela Cruz"
              @keypress="blockSpecialKeypress"
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
            <div v-if="studentFound" style="font-size:11px;color:var(--moss);margin-top:4px">
              ✓ Existing student found
            </div>
            <div v-else-if="studentSearchQuery.length >= 2 && studentSuggestions.length === 0 && !studentSearchLoading" style="font-size:11px;color:var(--red);margin-top:4px">
              No matching student found. Double-check the Student ID or name, or fill in the fields below to refer a new student.
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
            <input
              v-model="form.student_id_input"
              class="ifi"
              placeholder="e.g. 2302021"
              :readonly="studentFound"
              :style="studentFound ? 'background:var(--snow);color:var(--stone)' : errorStyle('student_id_input')"
              @input="form.student_id_input = onlyDigits(form.student_id_input); clearFieldError('student_id_input')"
              required
            />
          </div>

          <!-- Name Fields + Sex -->
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 100px 110px;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="form.last_name"
                class="ifi"
                placeholder="e.g. Dela Cruz"
                :readonly="studentFound"
                :style="studentFound ? 'background:var(--snow);color:var(--stone)' : errorStyle('last_name')"
                @input="form.last_name = onlyLetters(form.last_name); clearFieldError('last_name')"
                required
              />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="form.first_name"
                class="ifi"
                placeholder="e.g. Juan"
                :readonly="studentFound"
                :style="studentFound ? 'background:var(--snow);color:var(--stone)' : errorStyle('first_name')"
                @input="form.first_name = onlyLetters(form.first_name); clearFieldError('first_name')"
                required
              />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="form.middle_name" class="ifi" placeholder="e.g. Santos" :readonly="studentFound" :style="studentFound ? 'background:var(--snow);color:var(--stone)' : ''" @input="form.middle_name = onlyLetters(form.middle_name)" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <select v-model="form.suffix" class="ifse" :disabled="studentFound">
                <option value="">None</option>
                <option>Jr.</option>
                <option>Sr.</option>
                <option>II</option>
                <option>III</option>
                <option>IV</option>
                <option>V</option>
              </select>
            </div>
            <div>
              <label class="ifl">Sex <span style="color:var(--red)">*</span></label>
              <select
                v-model="form.sex"
                class="ifse"
                :disabled="studentFound"
                :style="errorStyle('sex')"
                @change="clearFieldError('sex')"
                required
              >
                <option value="" disabled hidden>Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">College <span style="color:var(--red)">*</span></label>
              <select
                v-model="form.college"
                class="ifse"
                :disabled="studentFound"
                :style="errorStyle('college')"
                @change="form.program = ''; clearFieldError('college')"
                required
              >
                <option value="" disabled hidden>Select college...</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Program <span style="color:var(--red)">*</span></label>
              <select
                v-model="form.program"
                class="ifse"
                :disabled="studentFound || !form.college"
                :style="errorStyle('program')"
                @change="clearFieldError('program')"
                required
              >
                <option value="" disabled hidden>Select program...</option>
                <option v-if="form.program && !availablePrograms.includes(form.program)" :value="form.program">{{ form.program }}</option>
                <option v-for="p in availablePrograms" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
            <label class="ifl">Year Level <span style="color:var(--red)">*</span></label>
            <select
              v-model="form.year_level"
              class="ifse"
              :disabled="studentFound"
              :style="errorStyle('year_level')"
              @change="clearFieldError('year_level')"
              required
            >
            <option value="" disabled hidden>Select year level...</option>
  <option v-for="n in 10" :key="n" :value="String(n)">{{ n }}</option>
            </select>
          </div>
            <div>
              <label class="ifl">Section</label>
              <input
                v-model="form.section"
                class="ifi"
                placeholder="e.g. A"
                maxlength="1"
                :readonly="studentFound"
                :style="studentFound ? 'background:var(--snow);color:var(--stone)' : ''"
                @input="form.section = form.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase()"
              />
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
            Referred By
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Referrer Last Name</label>
              <input :value="form.referrer_last_name" class="ifi" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Referrer First Name</label>
              <input :value="form.referrer_first_name" class="ifi" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Referrer Middle Name</label>
              <input :value="form.referrer_middle_name" class="ifi" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
            Referral Details
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Service Requested <span style="color:var(--red)">*</span></label>
              <select
                v-model="form.referral_type"
                class="ifse"
                :style="errorStyle('referral_type')"
                @change="clearFieldError('referral_type')"
                required
              >
                <option value="" disabled hidden>Select service...</option>
                <option v-for="opt in referralTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Referral Source</label>
              <select v-model="form.referral_source" class="ifse">
                <option v-for="opt in referralSourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
              </select>
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="ifl">Concern / Reason for Referral <span style="color:var(--red)">*</span></label>
            <textarea
              v-model="form.nature_of_concern"
              class="ifta"
              placeholder="Describe the student's concern in detail..."
              :style="errorStyle('nature_of_concern')"
              @input="clearFieldError('nature_of_concern')"
              required
            ></textarea>
          </div>

          <div style="display:flex;gap:9px;margin-top:8px">
            <button type="submit" class="ibtn ibtn-p" :disabled="loading">
              <svg v-if="!loading" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ loading ? 'Submitting...' : 'Refer Student' }}
            </button>
            <button type="button" class="ibtn ibtn-o" :disabled="isCreateFormEmpty" @click="handleClearForm">Clear Form</button>
            <button type="button" class="ibtn ibtn-g" @click="goBack">Cancel</button>
          </div>

        </form>
      </div>
    </div>

    <!-- Confirmation Preview Modal -->
    <div v-if="showPreview" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showPreview = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Referral Details</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showPreview = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone)">Please review before submitting:</div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><strong>Student:</strong> {{ form.last_name }}, {{ form.first_name }} {{ form.middle_name }} ({{ form.student_id_input }})</div>
            <div><strong>College:</strong> {{ form.college }}</div>
            <div><strong>Program:</strong> {{ form.program }}</div>
            <div><strong>Referrer:</strong> {{ form.referrer_last_name }}, {{ form.referrer_first_name }} {{ form.referrer_middle_name }}</div>
            <div><strong>Service:</strong> {{ toTitleCase(form.referral_type) }}</div>
            <div><strong>Concern:</strong> {{ form.nature_of_concern }}</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="confirmSubmit" :disabled="loading">
              {{ loading ? 'Submitting...' : 'Confirm & Submit' }}
            </button>
            <button class="ibtn ibtn-o" @click="showPreview = false">Go Back &amp; Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Clear Form Confirmation -->
    <div v-if="showClearConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showClearConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Clear Form?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            All entered information will be cleared. This cannot be undone.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="doConfirmedClear">Yes, Clear Form</button>
            <button class="ibtn ibtn-o" @click="showClearConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, inject, computed, nextTick, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { referralAPI, studentAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { onlyLetters, onlyLettersStrict, onlyDigits, safeSearchInput, blockSpecialKeypress, toTitleCase } from '../../utils/validators';

const router   = useRouter();
const toast    = inject('toast');
const auth     = useAuthStore();

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

// Colleges/Programs and the referral-form dropdowns now come live from the
// Management API instead of static constants / hardcoded <option> lists.
// Stored values stay the same short codes / name strings.
const colleges = ref([]);
const programsByCollege = ref({});
const referralTypeOptionsRaw = ref([]);
const referralSourceOptions  = ref([]);

// Acts of Misconduct is filed as its own "Complaint" now (see the SDU
// incident report flow), so it's deliberately excluded here even though the
// Management page may still list "disciplinary" as a referral_type option
// for other purposes / historical records.
const referralTypeOptions = computed(() =>
  referralTypeOptionsRaw.value.filter(opt => opt.value !== 'disciplinary')
);

async function fetchManagementData() {
  try {
    const [collegeRes, programRes] = await Promise.all([
      axios.get(`${API_BASE}/management/colleges`, authHeaders()),
      axios.get(`${API_BASE}/management/programs`, authHeaders()),
    ]);
    colleges.value = collegeRes.data.map(c => c.name);

    const collegeNameById = {};
    collegeRes.data.forEach(c => { collegeNameById[c.id] = c.name; });

    const grouped = {};
    programRes.data.forEach(p => {
      const collegeName = collegeNameById[p.college_id];
      if (!collegeName) return;
      if (!grouped[collegeName]) grouped[collegeName] = [];
      grouped[collegeName].push(p.name);
    });
    programsByCollege.value = grouped;
  } catch (e) {
    console.error(e);
  }
}

async function fetchFormOptions() {
  try {
    const [typeRes, sourceRes] = await Promise.all([
      axios.get(`${API_BASE}/management/form-options`, { ...authHeaders(), params: { category: 'referral_type' } }),
      axios.get(`${API_BASE}/management/form-options`, { ...authHeaders(), params: { category: 'referral_source' } }),
    ]);
    referralTypeOptionsRaw.value = typeRes.data;
    referralSourceOptions.value  = sourceRes.data;
  } catch (e) {
    console.error(e);
  }
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
const success = ref('');
const loading = ref(false);
const showPreview = ref(false);
const studentFound  = ref(false);

const showClearConfirm = ref(false);

function openClearConfirm() {
  const hasData = form.value.student_id_input || form.value.last_name || form.value.first_name ||
                  form.value.nature_of_concern || form.value.referral_type;
  if (!hasData) return;
  showClearConfirm.value = true;
}

function doConfirmedClear() {
  showClearConfirm.value = false;
  clearForm();
}

const studentSearchQuery   = ref('');
const studentSuggestions   = ref([]);
const showStudentDropdown  = ref(false);
const studentSearchLoading = ref(false);
let studentSearchTimeout = null;

const isFacultyOrDean = computed(() =>
  auth.user?.role === 'faculty' || auth.user?.role === 'dean_secretary'
);

const availablePrograms = computed(() => programsByCollege.value[form.value.college] || []);

const isCreateFormEmpty = computed(() =>
  !studentSearchQuery.value &&
  !form.value.student_id_input &&
  !form.value.last_name &&
  !form.value.first_name &&
  !form.value.middle_name &&
  !form.value.referral_type &&
  !form.value.nature_of_concern
);

const form = ref({
  student_id_input:      '',
  last_name:             '',
  first_name:            '',
  middle_name:           '',
  suffix:                '',
  sex:                   '',
  program:               '',
  year_level:            '',
  college:               '',
  section:               '',
  referrer_last_name:    '',
  referrer_first_name:   '',
  referrer_middle_name:  '',
  referral_type:         '',
  referral_source:       'faculty',
  nature_of_concern:     '',
});

function onStudentSearch() {
  studentSearchQuery.value = safeSearchInput(studentSearchQuery.value);
  clearTimeout(studentSearchTimeout);
  studentFound.value = false;
  if (!studentSearchQuery.value) {
    clearStudentFields();
    studentSuggestions.value = [];
    showStudentDropdown.value = false;
    return;
  }
  if (studentSearchQuery.value.length < 2) {
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

function clearStudentFields() {
  form.value.student_id_input = '';
  form.value.last_name   = '';
  form.value.first_name  = '';
  form.value.middle_name = '';
  form.value.suffix      = '';
  form.value.sex         = '';
  form.value.college     = '';
  form.value.program     = '';
  form.value.year_level  = '';
  form.value.section     = '';
}

async function selectStudent(s) {
  form.value.student_id_input = s.student_id;
  form.value.last_name        = s.last_name;
  form.value.first_name       = s.first_name;
  form.value.middle_name      = s.middle_name || '';
  form.value.suffix           = s.suffix || '';
  form.value.sex              = s.sex || '';
  form.value.college          = s.college || '';
  form.value.year_level       = s.year_level || '';
  form.value.section          = s.section || '';

  await nextTick();
  form.value.program = s.program || '';

  studentSearchQuery.value  = `${s.last_name}, ${s.first_name}`;
  showStudentDropdown.value = false;
  studentFound.value        = true;
}

function goBack() {
  if (isFacultyOrDean.value) {
    router.push({ name: 'dashboard' });
  } else {
    router.push({ name: 'referrals' });
  }
}

function handleSubmit() {
  error.value = '';
  const errs = {};
  const missing = [];

  if (!form.value.student_id_input) { errs.student_id_input = true; missing.push('Student ID'); }
  if (!form.value.last_name)        { errs.last_name = true;        missing.push('Last Name'); }
  if (!form.value.first_name)       { errs.first_name = true;       missing.push('First Name'); }
  if (!form.value.sex)              { errs.sex = true;              missing.push('Sex'); }
  if (!form.value.college)          { errs.college = true;          missing.push('College'); }
  if (!form.value.program)          { errs.program = true;          missing.push('Program'); }
  if (!form.value.year_level)       { errs.year_level = true;       missing.push('Year Level'); }
  if (!form.value.referral_type)    { errs.referral_type = true;    missing.push('Service Requested'); }
  if (!form.value.nature_of_concern){ errs.nature_of_concern = true;missing.push('Concern / Reason'); }

  fieldErrors.value = errs;

  if (missing.length) {
    error.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }

  showPreview.value = true;
}

async function confirmSubmit() {
  loading.value = true;
  try {
    let studentId = null;
    const searchRes = await studentAPI.index({ search: form.value.student_id_input });
    const found = searchRes.data.data?.find(
      s => s.student_id === form.value.student_id_input
    );

    if (found) {
      studentId = found.id;
    } else {
      const newStudent = await studentAPI.store({
        student_id:  form.value.student_id_input,
        first_name:  form.value.first_name,
        last_name:   form.value.last_name,
        middle_name: form.value.middle_name,
        sex:         form.value.sex,
        year_level:  form.value.year_level,
        college:     form.value.college,
        program:     form.value.program,
        section:     form.value.section,
      });
      studentId = newStudent.data.id;
    }

    await referralAPI.store({
      student_id:        studentId,
      referral_type:     form.value.referral_type,
      nature_of_concern: form.value.nature_of_concern,
      urgency_level:     'medium',
      is_self_referred:  form.value.referral_source === 'self',
      referrer_source:   form.value.referral_source,
    });

    showPreview.value = false;
    toast?.success('Student referred.');
    success.value = 'Student referred. GCU has been notified.';

    setTimeout(() => {
      if (isFacultyOrDean.value) {
        router.push({ name: 'dashboard' });
      } else {
        router.push({ name: 'referrals' });
      }
    }, 1500);

  } catch (e) {
    showPreview.value = false;
    error.value = e.response?.data?.message || 'Please fill in all required fields.';
    toast?.error(error.value);
  } finally {
    loading.value = false;
  }
}

function clearForm() {
  error.value   = '';
  success.value = '';
  studentSearchQuery.value = '';
  studentFound.value = false;
  form.value = {
    student_id_input: '', last_name: '', first_name: '',
    middle_name: '', suffix: '', sex: '', program: '', year_level: '',
    college: '', section: '',
    referrer_last_name:   auth.user?.last_name || '',
    referrer_first_name:  auth.user?.first_name || '',
    referrer_middle_name: auth.user?.middle_name || '',
    referral_type: '', referral_source: 'faculty', nature_of_concern: '',
  };
  fieldErrors.value = {};
}


function handleClearForm() {
  openClearConfirm();
}

onMounted(() => {
  form.value.referrer_last_name   = auth.user?.last_name || '';
  form.value.referrer_first_name  = auth.user?.first_name || '';
  form.value.referrer_middle_name = auth.user?.middle_name || '';
  fetchManagementData();
  fetchFormOptions();
});
</script>