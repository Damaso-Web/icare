<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Submit a Referral</h1>
      <p>Complete this form to refer a student to the Office of Student Services.</p>
    </div>

    <div class="icard" style="max-width:820px">

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
              placeholder="Type student ID or name..."
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
                <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ s.student_id }} · {{ s.college || '—' }}</div>
              </div>
            </div>
            <div v-if="studentFound" style="font-size:11px;color:var(--moss);margin-top:4px">
              ✓ Existing student found — details auto-filled
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
            <input
            v-model="form.student_id_input"
            class="ifi"
            placeholder="e.g. 2302021"
            @input="form.student_id_input = onlyDigits(form.student_id_input)"
            required
          />
          </div>

          <!-- Name Fields + Sex -->
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 100px 110px;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input v-model="form.last_name" class="ifi" placeholder="Dela Cruz" @input="form.last_name = onlyLetters(form.last_name)" required />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input v-model="form.first_name" class="ifi" placeholder="Juan" @input="form.first_name = onlyLetters(form.first_name)" required />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="form.middle_name" class="ifi" placeholder="Santos" @input="form.middle_name = onlyLetters(form.middle_name)" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input v-model="form.suffix" class="ifi" placeholder="Jr." @input="form.suffix = onlyLettersStrict(form.suffix)" />
            </div>
            <div>
              <label class="ifl">Sex <span style="color:var(--red)">*</span></label>
              <select v-model="form.sex" class="ifse" required>
                <option value="">Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">College <span style="color:var(--red)">*</span></label>
              <select v-model="form.college" class="ifse" @change="form.program = ''" required>
                <option value="">Select college...</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Program <span style="color:var(--red)">*</span></label>
              <select v-model="form.program" class="ifse" required :disabled="!form.college">
                <option value="">Select program...</option>
                <option v-if="form.program && !availablePrograms.includes(form.program)" :value="form.program">{{ form.program }}</option>
                <option v-for="p in availablePrograms" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Year Level <span style="color:var(--red)">*</span></label>
              <select v-model="form.year_level" class="ifse" required>
                <option value="">Select year level...</option>
                <option>1st Year</option>
                <option>2nd Year</option>
                <option>3rd Year</option>
                <option>4th Year</option>
                <option>5th Year</option>
              </select>
            </div>
            <div>
              <label class="ifl">Section</label>
              <input
                v-model="form.section"
                class="ifi"
                placeholder="e.g. A"
                maxlength="1"
                @input="form.section = form.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase()"
              />
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
            Referred By
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="margin-bottom:14px;position:relative">
            <label class="ifl">Name of Referrer <span style="color:var(--red)">*</span></label>
            <input
              v-model="form.referrer_name_input"
              class="ifi"
              placeholder="Full name of person referring"
              @input="onReferrerSearch"
              @focus="showReferrerDropdown = referrerSuggestions.length > 0"
              autocomplete="off"
              required
            />
            <div
              v-if="showReferrerDropdown && referrerSuggestions.length > 0"
              style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);box-shadow:var(--sh-lg);z-index:50;max-height:220px;overflow-y:auto;margin-top:4px"
            >
              <div
                v-for="r in referrerSuggestions"
                :key="r.id"
                style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--cloud);transition:background .1s"
                @mouseover="$event.currentTarget.style.background='var(--foam)'"
                @mouseleave="$event.currentTarget.style.background='#fff'"
                @click="selectReferrer(r)"
              >
                <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ r.name }}</div>
                <div style="font-size:11px;color:var(--fog)">{{ r.email }} · {{ roleLabel(r.role) }}</div>
              </div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Position / Role</label>
              <select v-model="form.referrer_position" class="ifse" @change="onPositionChange">
                <option value="">Select...</option>
                <option value="instructor">Instructor / Adviser</option>
                <option value="department_chair">Department Chair</option>
                <option value="dean">Dean</option>
                <option value="oss_staff">OSS Staff</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div v-if="['instructor','department_chair','dean'].includes(form.referrer_position)">
              <label class="ifl">Department / College</label>
              <select v-model="form.referrer_department" class="ifse">
                <option value="">Select college...</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="form.referrer_contact" class="ifi" placeholder="e.g. 09171234567" @input="form.referrer_contact = contactNumberInput(form.referrer_contact)" />
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
            Referral Details
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Service Requested <span style="color:var(--red)">*</span></label>
              <select v-model="form.referral_type" class="ifse" required>
                <option value="">Select service...</option>
                <option value="counseling">Class Attendance / Absent / Tardy</option>
                <option value="academic_coaching">Academic Deficiency</option>
                <option value="psychological_testing">Psychological Testing</option>
                <option value="consultation">Scholarship / Grant Assistance</option>
                <option value="admission_slip">Student Organizations &amp; Activities Concerns</option>
                <option value="disciplinary">Student Housing (Dormitories)</option>
                <option value="others">For Student Employment (SA/SPES)</option>
                <option value="other">Others</option>
              </select>
            </div>
            <div>
              <label class="ifl">Referral Source</label>
              <select v-model="form.referral_source" class="ifse">
                <option value="faculty">Faculty Referral</option>
                <option value="sdu">SDU Referral</option>
                <option value="self">Self-Referral</option>
                <option value="dean">Dean's Office</option>
                <option value="parent">Parent / Guardian</option>
              </select>
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="ifl">Concern / Reason for Referral <span style="color:var(--red)">*</span></label>
            <textarea
              v-model="form.nature_of_concern"
              class="ifta"
              placeholder="Describe the student's concern in detail..."
              required
            ></textarea>
          </div>

          <div style="display:flex;gap:9px;margin-top:8px">
            <button type="submit" class="ibtn ibtn-p" :disabled="loading">
              <svg v-if="!loading" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ loading ? 'Submitting...' : 'Submit Referral' }}
            </button>
            <button type="button" class="ibtn ibtn-o" @click="clearForm">Clear Form</button>
            <button type="button" class="ibtn ibtn-g" @click="goBack">Cancel</button>
          </div>

        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject, computed, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import { referralAPI, studentAPI, userAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { COLLEGES } from '../../constants/colleges';
import { PROGRAMS_BY_COLLEGE } from '../../constants/programs';
import { onlyDigits, onlyLetters, onlyLettersStrict, contactNumberInput, isValidEmail } from '../../utils/validators';

const router   = useRouter();
const toast    = inject('toast');
const auth     = useAuthStore();
const colleges = COLLEGES;

const error   = ref('');
const success = ref('');
const loading = ref(false);
const studentFound = ref(false);

const studentSearchQuery   = ref('');
const studentSuggestions   = ref([]);
const showStudentDropdown  = ref(false);
let studentSearchTimeout = null;

const referrerSuggestions   = ref([]);
const showReferrerDropdown  = ref(false);
let referrerSearchTimeout = null;

const isFacultyOrDean = computed(() =>
  auth.user?.role === 'faculty' || auth.user?.role === 'dean_secretary'
);

const availablePrograms = computed(() => PROGRAMS_BY_COLLEGE[form.value.college] || []);

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
  referrer_name_input:   '',
  referrer_position:     '',
  referrer_department:   '',
  referrer_contact:      '',
  referral_type:         '',
  referral_source:       'faculty',
  nature_of_concern:     '',
});

function roleLabel(role) {
  const labels = {
    admin: 'Admin / GCU Head', gcu_staff: 'GCU Staff', sdu_head: 'SDU Head',
    tmdu_staff: 'TMDU Staff', faculty: 'Faculty', dean_secretary: "Dean's Secretary",
  };
  return labels[role] || role;
}

function onPositionChange() {
  form.value.referrer_department = '';
}

function onStudentSearch() {
  clearTimeout(studentSearchTimeout);
  studentFound.value = false;
  if (!studentSearchQuery.value || studentSearchQuery.value.length < 2) {
    studentSuggestions.value = [];
    showStudentDropdown.value = false;
    return;
  }
  studentSearchTimeout = setTimeout(async () => {
    try {
      const res = await studentAPI.index({ search: studentSearchQuery.value });
      studentSuggestions.value = res.data.data || [];
      showStudentDropdown.value = studentSuggestions.value.length > 0;
    } catch (e) {
      studentSuggestions.value = [];
    }
  }, 350);
}

async function selectStudent(s) {
  form.value.student_id_input = s.student_id;
  form.value.last_name        = s.last_name;
  form.value.first_name       = s.first_name;
  form.value.middle_name      = s.middle_name || '';
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

function onReferrerSearch() {
  clearTimeout(referrerSearchTimeout);
  if (!form.value.referrer_name_input || form.value.referrer_name_input.length < 2) {
    referrerSuggestions.value = [];
    showReferrerDropdown.value = false;
    return;
  }
  referrerSearchTimeout = setTimeout(async () => {
    try {
      const res = await userAPI.index({ search: form.value.referrer_name_input });
      referrerSuggestions.value = res.data.data || [];
      showReferrerDropdown.value = referrerSuggestions.value.length > 0;
    } catch (e) {
      referrerSuggestions.value = [];
    }
  }, 350);
}

function selectReferrer(r) {
  form.value.referrer_name_input = r.name;
  form.value.referrer_contact    = r.contact_number || '';

  const ossRoles = ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'];
  if (ossRoles.includes(r.role)) {
    form.value.referrer_position = 'oss_staff';
    form.value.referrer_oss_unit = r.unit || '';
    form.value.referrer_department = '';
  } else if (r.role === 'faculty') {
    form.value.referrer_position   = 'instructor';
    form.value.referrer_department = r.college || '';
    form.value.referrer_oss_unit   = '';
  } else if (r.role === 'dean_secretary') {
    form.value.referrer_position   = 'other';
    form.value.referrer_department = r.college || '';
    form.value.referrer_oss_unit   = '';
  }

  showReferrerDropdown.value = false;
}

function goBack() {
  if (isFacultyOrDean.value) {
    router.push({ name: 'dashboard' });
  } else {
    router.push({ name: 'referrals' });
  }
}

async function handleSubmit() {
  error.value   = '';
  success.value = '';

  if (!form.value.student_id_input || form.value.student_id_input.length !== 7) {
    error.value = 'Student ID must be exactly 7 digits.';
    return;
  }

  if (!form.value.last_name || !form.value.first_name || !form.value.sex ||
      !form.value.college || !form.value.referral_type ||
      !form.value.nature_of_concern) {
    error.value = 'Please fill in all required fields.';
    return;
  }

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

    toast?.success('Referral submitted successfully!');
    success.value = 'Referral submitted successfully! GCU has been notified.';

    setTimeout(() => {
      if (isFacultyOrDean.value) {
        router.push({ name: 'dashboard' });
      } else {
        router.push({ name: 'referrals' });
      }
    }, 1500);

  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to submit referral.';
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
    college: '', section: '', referrer_name_input: '',
    referrer_position: '', referrer_department: '',
    referrer_contact: '', referral_type: '',
    referral_source: 'faculty', nature_of_concern: '',
  };
}
</script>