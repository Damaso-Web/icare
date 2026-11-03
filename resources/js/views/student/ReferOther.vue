<!--
  Refer a Student (student portal): a student asks GCU to reach out to another
  student. Found by exact Student ID Number or by first+last name (students
  cannot browse the masterlist - see StudentOutreachController::lookup()).
  Bug (11/3/2026): this page used to be a much simpler form than the one
  every other role uses (referrals/Create.vue) - no live search dropdown, no
  locked College/Program/Year Level/Section/Referral Source display, no
  confirm-before-submit preview. Rebuilt to match that form's layout and
  flow as closely as the student-safe lookup() endpoint allows: same fields,
  same live-search feel, same confirmation step - just reading from
  /student/outreach/lookup (exact ID or first+last name only) instead of the
  staff-only masterlist search, and "Referral Source" is fixed to "Student"
  rather than being derived from a staff account role.
-->
<template>
  <div class="fade-up">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" type="button" @click="goBack">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>Refer a Student</h1>
        <p>Tell GCU about a student who may need support. You can follow what you sent under Sent Referrals.</p>
      </div>
    </div>

    <div class="icard" style="max-width:820px;margin:0 auto">
      <div class="icard-body">
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin:22px 22px 0">{{ error }}</div>

        <form @submit.prevent="handleSubmit" style="padding:22px">

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
            Student Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <!-- Student search, live as you type - same ID-or-full-name lookup
               StudentIdLookup used, just shown as a type-ahead dropdown like
               the main form instead of a separate "Find" button click. -->
          <div style="margin-bottom:14px;position:relative">
            <label class="ifl">Search Student (ID or Full Name) <span style="color:var(--red)">*</span></label>
            <input
              v-model="studentSearchQuery"
              class="ifi"
              placeholder="e.g. 2302021 or Dela Cruz, Juan"
              maxlength="100"
              :disabled="!!target"
              @input="onStudentSearch"
              @focus="showStudentDropdown = studentSuggestions.length > 0"
              autocomplete="off"
            />
            <button v-if="target" type="button" class="ibtn ibtn-o ibtn-sm" style="margin-top:8px" @click="clearStudent">Change</button>

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

          <div style="margin-bottom:14px">
            <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
            <input class="ifi" :value="target?.student_id" placeholder="e.g. 2302021" readonly :style="lockedStyle('target')" />
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 100px 110px;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Last Name</label>
              <input class="ifi" :value="target?.last_name" placeholder="e.g. Dela Cruz" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input class="ifi" :value="target?.first_name" placeholder="e.g. Juan" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input class="ifi" :value="target?.middle_name" placeholder="e.g. Santos" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input class="ifi" :value="target?.suffix || 'None'" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Sex</label>
              <input class="ifi" :value="target?.sex" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">College</label>
              <input class="ifi" :value="target?.college" placeholder="Select college..." readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Program</label>
              <input class="ifi" :value="target?.program" placeholder="Select program..." readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Year Level</label>
              <input class="ifi" :value="target?.year_level" placeholder="Select year level..." readonly style="background:var(--snow);color:var(--stone)" />
            </div>
            <div>
              <label class="ifl">Section</label>
              <input class="ifi" :value="target?.section" placeholder="e.g. A" readonly style="background:var(--snow);color:var(--stone)" />
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
            Referral Details
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Service Requested <span style="color:var(--red)">*</span></label>
              <select v-model="form.referral_type" class="ifse" :style="errorStyle('referral_type')" @change="clearFieldError('referral_type')" required>
                <option value="" disabled hidden>Select a service...</option>
                <option v-for="s in SERVICES" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Referral Source</label>
              <!-- Not a choice: a student submitting this form is always the source. -->
              <input value="Student" class="ifi" readonly tabindex="-1" style="background:var(--snow);color:var(--slate);cursor:default" title="You're signed in as a student" />
            </div>
          </div>

          <div style="margin-bottom:14px">
            <label class="ifl">Concern / Reason for Referral <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(max 1000 characters)</span></label>
            <textarea
              v-model="form.nature_of_concern"
              class="ifta"
              style="min-height:130px"
              maxlength="1000"
              :style="reasonNumbersOnly ? 'border-color:var(--red);border-width:1.5px' : errorStyle('nature_of_concern')"
              @input="clearFieldError('nature_of_concern')"
            ></textarea>
            <div style="font-size:11px;color:var(--fog);margin-top:4px">{{ form.nature_of_concern.length }}/1000</div>
            <div v-if="reasonNumbersOnly" style="color:var(--red);font-size:11px;margin-top:4px">Please provide a valid referral reason. Numbers alone are not allowed.</div>
          </div>

          <div style="display:flex;justify-content:flex-end;gap:9px;margin-top:8px">
            <button type="submit" class="ibtn ibtn-p" :disabled="sending">{{ sending ? 'Sending...' : 'Refer Student' }}</button>
            <button type="button" class="ibtn ibtn-o" :disabled="sending || isFormEmpty" @click="openClearConfirm">Clear Form</button>
            <button type="button" class="ibtn ibtn-g" :disabled="sending" @click="goBack">Cancel</button>
          </div>

        </form>
      </div>
    </div>

    <!-- Confirm Referral Details - same layout/pattern as the main form's
         preview modal (including the nested College/Program grid; see the
         note in referrals/Create.vue about the mobile grid-collapse bug it
         works around). -->
    <div v-if="showPreview" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showPreview = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:600px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Referral Details</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone)">Please review before submitting:</div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div style="grid-column:span 2">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Student</div>
              <div style="font-size:13px;color:var(--ink)">{{ target?.last_name }}, {{ target?.first_name }} {{ target?.middle_name }} <span style="color:var(--fog)">({{ target?.student_id }})</span></div>
            </div>
            <div style="grid-column:span 2;display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                <div style="font-size:13px;color:var(--ink)">{{ target?.college }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ target?.program }}</div>
              </div>
            </div>
            <div style="grid-column:span 2">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service Requested</div>
              <div style="font-size:13px;color:var(--ink)">{{ serviceLabel(form.referral_type) }}</div>
            </div>
          </div>

          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Concern / Reason for Referral</div>
            <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver);white-space:pre-line">{{ form.nature_of_concern }}</div>
          </div>

          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="confirmSubmit" :disabled="sending">{{ sending ? 'Sending...' : 'Confirm & Submit' }}</button>
            <button class="ibtn ibtn-o" @click="showPreview = false">Go Back &amp; Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Clear Form confirmation -->
    <div v-if="showClearConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showClearConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Clear Form?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">All entered information will be cleared. This cannot be undone.</div>
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
import { ref, inject, computed } from 'vue';
import { useRouter } from 'vue-router';
import { studentApi } from '../../api/index';
import { hasLetter } from '../../utils/validators';

const router = useRouter();
const toast = inject('toast', null);

function goBack() {
  if (window.history.state && window.history.state.back) router.back();
  else router.push({ name: 'student-refer-choice' });
}

const SERVICES = [
  { value: 'counseling',          label: 'Counseling' },
  { value: 'class_attendance',    label: 'Class Admission Slip (absences / tardiness)' },
  { value: 'academic_deficiency', label: 'Academic Deficiency' },
  { value: 'leave_of_absence',    label: 'Leave of Absence' },
  { value: 'withdrawal',          label: 'Withdrawal' },
  { value: 'readmission',         label: 'Readmission' },
  { value: 'shifting',            label: 'Shifting' },
];
function serviceLabel(value) {
  return SERVICES.find(s => s.value === value)?.label || '-';
}

const target = ref(null);
const form = ref({ referral_type: '', nature_of_concern: '' });
const sending = ref(false);
const error = ref('');
const showPreview = ref(false);
const showClearConfirm = ref(false);

// B582: "12345" has 10+ characters but isn't a real reason.
const reasonNumbersOnly = computed(() =>
  !!form.value.nature_of_concern.trim() && !hasLetter(form.value.nature_of_concern)
);

const fieldErrors = ref({});
function clearFieldError(field) {
  if (fieldErrors.value[field]) fieldErrors.value = { ...fieldErrors.value, [field]: false };
}
function errorStyle(field) {
  return fieldErrors.value[field] ? 'border-color:var(--red);border-width:1.5px' : '';
}
function lockedStyle(field) {
  return fieldErrors.value[field]
    ? 'background:var(--snow);color:var(--stone);border-color:var(--red);border-width:1.5px'
    : 'background:var(--snow);color:var(--stone)';
}

const isFormEmpty = computed(() => !studentSearchQuery.value && !target.value && !form.value.referral_type && !form.value.nature_of_concern);

// ---- Student search (live, debounced) ----
const studentSearchQuery  = ref('');
const studentSuggestions  = ref([]);
const showStudentDropdown = ref(false);
const searchError         = ref('');
let studentSearchTimeout  = null;

function onStudentSearch() {
  clearTimeout(studentSearchTimeout);
  searchError.value = '';
  if (!studentSearchQuery.value.trim()) {
    studentSuggestions.value = [];
    showStudentDropdown.value = false;
    return;
  }
  studentSearchTimeout = setTimeout(async () => {
    try {
      const res = await studentApi.post('/student/outreach/lookup', { student_id: studentSearchQuery.value.trim() });
      studentSuggestions.value = res.data.matches || [];
      showStudentDropdown.value = studentSuggestions.value.length > 0;
      if (studentSuggestions.value.length === 1) selectStudent(studentSuggestions.value[0]);
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
  fieldErrors.value = { ...fieldErrors.value, target: false };
}

function clearStudent() {
  target.value = null;
  studentSearchQuery.value = '';
  studentSuggestions.value = [];
  showStudentDropdown.value = false;
  searchError.value = '';
}

function openClearConfirm() {
  if (isFormEmpty.value) return;
  showClearConfirm.value = true;
}
function doConfirmedClear() {
  showClearConfirm.value = false;
  clearStudent();
  form.value = { referral_type: '', nature_of_concern: '' };
  fieldErrors.value = {};
  error.value = '';
}

function handleSubmit() {
  error.value = '';
  const errs = {};
  const missing = [];
  if (!target.value)                { errs.target = true;             missing.push('Student'); }
  if (!form.value.referral_type)    { errs.referral_type = true;      missing.push('Service Requested'); }
  if (!form.value.nature_of_concern || form.value.nature_of_concern.trim().length < 10) {
    errs.nature_of_concern = true; missing.push('Concern / Reason for Referral (at least 10 characters)');
  }
  fieldErrors.value = errs;
  if (missing.length) { error.value = `Please fill in: ${missing.join(', ')}.`; return; }
  if (!hasLetter(form.value.nature_of_concern)) {
    fieldErrors.value = { ...fieldErrors.value, nature_of_concern: true };
    error.value = 'Please provide a valid referral reason. Numbers alone are not allowed.';
    return;
  }
  showPreview.value = true;
}

async function confirmSubmit() {
  sending.value = true;
  try {
    await studentApi.post('/student/outreach/referrals', {
      student_id: target.value.id,
      referral_type: form.value.referral_type,
      nature_of_concern: form.value.nature_of_concern.trim(),
    });
    showPreview.value = false;
    toast?.success('Your referral was sent to GCU.');
    router.push({ name: 'student-sent-referrals' });
  } catch (e) {
    showPreview.value = false;
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not send the referral. Please try again.';
    toast?.error(error.value);
  } finally {
    sending.value = false;
  }
}
</script>