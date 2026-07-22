<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Submit a Referral</h1>
      <p>Complete this form to refer a student to the Guidance &amp; Counseling Unit.</p>
    </div>

    <div class="icard" style="max-width:720px">
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

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div>
              <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
              <input v-model="form.student_id" class="ifi" placeholder="e.g. 2023-12345" required />
            </div>
            <div>
              <label class="ifl">Full Name <span style="color:var(--red)">*</span></label>
              <input v-model="form.student_name" class="ifi" placeholder="Last, First Middle" required />
            </div>
            <div>
              <label class="ifl">Program</label>
              <input v-model="form.program" class="ifi" placeholder="e.g. BSIT" />
            </div>
            <div>
              <label class="ifl">Year Level</label>
              <select v-model="form.year_level" class="ifse">
                <option>1st Year</option>
                <option>2nd Year</option>
                <option>3rd Year</option>
                <option>4th Year</option>
                <option>5th Year</option>
              </select>
            </div>
            <div>
              <label class="ifl">College <span style="color:var(--red)">*</span></label>
              <select v-model="form.college" class="ifse" required>
                <option value="">Select college...</option>
                <option>CIS</option>
                <option>CAS</option>
                <option>COE</option>
                <option>CHET</option>
                <option>CTE</option>
                <option>CHK</option>
              </select>
            </div>
            <div>
              <label class="ifl">Section / Class</label>
              <input v-model="form.section" class="ifi" placeholder="e.g. BSIT 3-A" />
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
                <option value="counseling">Class Attendance / Absent / Tardy On</option>
                <option value="academic_coaching">Academic Deficiency</option>
                <option value="psychological_testing">Psychological Testing</option>
                <option value="consultation">Scholarship / Grant Assistance</option>
                <option value="admission_slip">Student Organizations &amp; Activities Concerns</option>
                <option value="disciplinary">Student Housing (Dormitories)</option>
                <option value="other">For Student Employment (SA/SPES)</option>
                <option value="others">Others</option>
              </select>
            </div>
            <div>
              <label class="ifl">Urgency Level <span style="color:var(--red)">*</span></label>
              <select v-model="form.urgency_level" class="ifse" required>
                <option value="low">🟢 Low — can wait up to 2 weeks</option>
                <option value="medium">🟡 Medium — schedule this week</option>
                <option value="high">🔴 High — contact student ASAP</option>
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
              placeholder="Describe the student's concern in detail. Include observed behaviors, academic performance issues, or disciplinary incidents..."
              required
            ></textarea>
          </div>

          <div style="margin-bottom:14px">
            <label class="ifl">Previous Interventions (if any)</label>
            <textarea
              v-model="form.previous_interventions"
              class="ifta"
              style="min-height:60px"
              placeholder="Describe any prior support or actions already taken..."
            ></textarea>
          </div>

          <!-- Actions -->
          <div style="display:flex;gap:9px;margin-top:8px">
            <button type="submit" class="ibtn ibtn-p" :disabled="loading">
              <svg v-if="!loading" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ loading ? 'Submitting...' : 'Submit Referral' }}
            </button>
            <button type="button" class="ibtn ibtn-o" @click="clearForm">Clear Form</button>
            <router-link :to="{ name: 'referrals' }" class="ibtn ibtn-g">Cancel</router-link>
          </div>

        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, inject } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const toast  = inject('toast');
const error   = ref('');
const success = ref('');
const loading = ref(false);

const form = ref({
  student_id:            '',
  student_name:          '',
  program:               '',
  year_level:            '1st Year',
  college:               '',
  section:               '',
  referral_type:         '',
  urgency_level:         'medium',
  preferred_date:        '',
  referral_source:       'faculty',
  nature_of_concern:     '',
  previous_interventions:'',
});

async function handleSubmit() {
  error.value   = '';
  success.value = '';

  if (!form.value.student_id || !form.value.student_name || !form.value.college || !form.value.referral_type || !form.value.nature_of_concern) {
    error.value = 'Please fill in all required fields.';
    return;
  }

  loading.value = true;
  setTimeout(() => {
    loading.value = false;
    success.value = 'Referral submitted successfully! GCU has been notified.';
    toast?.success('Referral submitted successfully!');
    setTimeout(() => router.push({ name: 'referrals' }), 1500);
  }, 1000);
}

function clearForm() {
  error.value   = '';
  success.value = '';
  form.value = {
    student_id: '', student_name: '', program: '', year_level: '1st Year',
    college: '', section: '', referral_type: '', urgency_level: 'medium',
    preferred_date: '', referral_source: 'faculty',
    nature_of_concern: '', previous_interventions: '',
  };
}
</script>