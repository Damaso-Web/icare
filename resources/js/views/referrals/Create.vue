<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Submit a Referral</h1>
      <p>Complete this form to refer a student to the Office of Student Services.</p>
    </div>

    <div class="icard" style="max-width:780px">

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

        <!-- Success / Error -->
        <div v-if="success" style="background:var(--mist);border:1px solid var(--mint);color:var(--forest);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px;display:flex;align-items:center;gap:8px">
          <svg viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
          {{ success }}
        </div>
        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px">
          {{ error }}
        </div>

        <form @submit.prevent="handleSubmit">

          <!-- Student Information -->
          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px">
            Student Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <!-- Student ID -->
          <div style="margin-bottom:14px">
            <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
            <input
              v-model="form.student_id_input"
              class="ifi"
              placeholder="e.g. 2302021"
              pattern="[0-9]{7}"
              maxlength="7"
              title="Student ID must be exactly 7 numbers"
              @input="form.student_id_input = form.student_id_input.replace(/[^0-9]/g, '').slice(0, 7)"
              required
            />
            <div v-if="form.student_id_input && form.student_id_input.length !== 7" style="font-size:11px;color:var(--red);margin-top:4px">
              Student ID must be exactly 7 digits
            </div>
          </div>

          <!-- Name Fields -->
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 120px;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input v-model="form.last_name" class="ifi" placeholder="Dela Cruz" required />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input v-model="form.first_name" class="ifi" placeholder="Juan" required />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="form.middle_name" class="ifi" placeholder="Santos" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input v-model="form.suffix" class="ifi" placeholder="Jr." />
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">College <span style="color:var(--red)">*</span></label>
              <select v-model="form.college" class="ifse" required>
                <option value="">Select college...</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Program <span style="color:var(--red)">*</span></label>
              <input v-model="form.program" class="ifi" placeholder="e.g. Bachelor of Science in Information Technology" required />
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
              <input v-model="form.section" class="ifi" placeholder="e.g. A" />
            </div>
          </div>

          <!-- Referred By Section -->
          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:14px;margin-top:8px">
            Referred By
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Name of Referrer <span style="color:var(--red)">*</span></label>
              <input v-model="form.referrer_name_input" class="ifi" placeholder="Full name of person referring" required />
            </div>
            <div>
              <label class="ifl">Position / Role</label>
              <input v-model="form.referrer_position" class="ifi" placeholder="e.g. Instructor, Adviser" />
            </div>
            <div>
              <label class="ifl">Department / College</label>
              <input v-model="form.referrer_department" class="ifi" placeholder="e.g. College of Information Sciences" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="form.referrer_contact" class="ifi" placeholder="e.g. 09171234567" />
            </div>
          </div>

          <!-- Referral Details -->
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
              <label class="ifl">Preferred Date</label>
              <input v-model="form.preferred_date" type="date" class="ifi" />
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

          <!-- Actions -->
          <div style="display:flex;gap:9px;margin-top:8px">
            <button type="submit" class="ibtn ibtn-p" :disabled="loading || (form.student_id_input && form.student_id_input.length !== 7)">
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
import { ref, inject, computed } from 'vue';
import { useRouter } from 'vue-router';
import { referralAPI, studentAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { COLLEGES } from '../../constants/colleges';

const router   = useRouter();
const toast    = inject('toast');
const auth     = useAuthStore();
const colleges = COLLEGES;

const error   = ref('');
const success = ref('');
const loading = ref(false);

const isFacultyOrDean = computed(() =>
  auth.user?.role === 'faculty' || auth.user?.role === 'dean_secretary'
);

const form = ref({
  student_id_input:      '',
  last_name:             '',
  first_name:            '',
  middle_name:           '',
  suffix:                '',
  program:               '',
  year_level:            '',
  college:               '',
  section:               '',
  referrer_name_input:   '',
  referrer_position:     '',
  referrer_department:   '',
  referrer_contact:      '',
  referral_type:         '',
  preferred_date:        '',
  referral_source:       'faculty',
  nature_of_concern:     '',
});

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

  if (!form.value.last_name || !form.value.first_name ||
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
  form.value = {
    student_id_input: '', last_name: '', first_name: '',
    middle_name: '', suffix: '', program: '', year_level: '',
    college: '', section: '', referrer_name_input: '',
    referrer_position: '', referrer_department: '',
    referrer_contact: '', referral_type: '', preferred_date: '',
    referral_source: 'faculty', nature_of_concern: '',
  };
}
</script>