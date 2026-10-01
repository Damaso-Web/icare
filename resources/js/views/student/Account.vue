<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Account</h1>
      <p>Update your personal information and password.</p>
    </div>

    <div style="max-width:680px;display:flex;flex-direction:column;gap:16px">

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Profile Information</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="profileError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ profileError }}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="profileForm.last_name" class="ifi" @input="profileForm.last_name = profileForm.last_name.replace(/[^a-zA-Z\s'-]/g, '')" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="profileForm.first_name" class="ifi" @input="profileForm.first_name = profileForm.first_name.replace(/[^a-zA-Z\s'-]/g, '')" />
            </div>
          </div>
          <div>
            <label class="ifl">Middle Name</label>
            <input v-model="profileForm.middle_name" class="ifi" @input="profileForm.middle_name = profileForm.middle_name.replace(/[^a-zA-Z\s'-]/g, '')" />
          </div>
          <div>
            <label class="ifl">Suffix</label>
            <select v-model="profileForm.suffix" class="ifse">
              <option value="">None</option>
              <option value="Jr.">Jr.</option>
              <option value="Sr.">Sr.</option>
              <option value="I">I</option>
              <option value="II">II</option>
              <option value="III">III</option>
            </select>
          </div>
          <div>
            <label class="ifl">Email Address</label>
            <input v-model="profileForm.email" type="email" class="ifi" />
          </div>
          <div>
            <label class="ifl">Contact Number</label>
            <input
            v-model="profileForm.contact_number"
            class="ifi"
            placeholder="09XXXXXXXXX"
            maxlength="11"
            :style="profileError ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="profileForm.contact_number = profileForm.contact_number.replace(/[^0-9]/g, '').slice(0, 11); profileError = ''"
          />
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="isProfileUnchanged" @click="saveProfile">Save Changes</button>
        </div>
      </div>

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Family &amp; Educational Background</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">
          <div v-if="backgroundError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundError }}</div>

          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Family Information</div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Father</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.father_last_name" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.father_first_name" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.father_middle_name" class="ifi" maxlength="255" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.father_occupation" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.father_contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" @input="backgroundForm.father_contact_number = backgroundForm.father_contact_number.replace(/[^0-9]/g, '').slice(0, 11)" />
            </div>
          </div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate);margin-top:4px">Mother</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.mother_last_name" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.mother_first_name" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.mother_middle_name" class="ifi" maxlength="255" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.mother_occupation" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.mother_contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" @input="backgroundForm.mother_contact_number = backgroundForm.mother_contact_number.replace(/[^0-9]/g, '').slice(0, 11)" />
            </div>
          </div>

          <div style="height:1px;background:var(--cloud)"></div>

          <div style="display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Siblings Information</div>
            <button type="button" class="ibtn ibtn-o ibtn-sm" @click="addSibling">+ Add Sibling</button>
          </div>
          <div v-if="!backgroundForm.siblings.length" style="font-size:12.5px;color:var(--fog)">No siblings added yet.</div>
          <div
            v-for="(sib, idx) in backgroundForm.siblings"
            :key="idx"
            style="border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px;display:flex;flex-direction:column;gap:10px"
          >
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="sib.last_name" class="ifi" maxlength="255" />
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="sib.first_name" class="ifi" maxlength="255" />
              </div>
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="sib.middle_name" class="ifi" maxlength="255" />
              </div>
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr auto;gap:10px;align-items:end">
              <div>
                <label class="ifl">Age</label>
                <input v-model="sib.age" class="ifi" maxlength="3" @input="sib.age = sib.age.replace(/[^0-9]/g, '')" />
              </div>
              <div>
                <label class="ifl">Occupation / School</label>
                <input v-model="sib.occupation" class="ifi" maxlength="255" />
              </div>
              <button type="button" class="ibtn ibtn-o ibtn-sm" style="color:var(--red)" @click="removeSibling(idx)">Remove</button>
            </div>
          </div>

          <div style="height:1px;background:var(--cloud)"></div>

          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Educational Attainment</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Elementary School</label>
              <input v-model="backgroundForm.elementary_school" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.elementary_year_graduated" class="ifi" placeholder="e.g. 2016" maxlength="4" @input="backgroundForm.elementary_year_graduated = backgroundForm.elementary_year_graduated.replace(/[^0-9]/g, '')" />
            </div>
            <div>
              <label class="ifl">High School</label>
              <input v-model="backgroundForm.high_school" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.high_school_year_graduated" class="ifi" placeholder="e.g. 2020" maxlength="4" @input="backgroundForm.high_school_year_graduated = backgroundForm.high_school_year_graduated.replace(/[^0-9]/g, '')" />
            </div>
            <div>
              <label class="ifl">College / University</label>
              <input v-model="backgroundForm.college_school" class="ifi" maxlength="255" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.college_year_graduated" class="ifi" placeholder="Leave blank if ongoing" maxlength="4" @input="backgroundForm.college_year_graduated = backgroundForm.college_year_graduated.replace(/[^0-9]/g, '')" />
            </div>
          </div>

          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="isBackgroundUnchanged" @click="saveBackground">Save Changes</button>
        </div>
      </div>

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Change Password</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="pwError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwError }}</div>
          <div>
            <label class="ifl">Current Password</label>
            <input v-model="pwForm.current_password" type="password" class="ifi" />
          </div>
          <div>
            <label class="ifl">New Password</label>
            <input v-model="pwForm.password" type="password" class="ifi" />
          </div>
          <div>
            <label class="ifl">Confirm New Password</label>
            <input v-model="pwForm.password_confirmation" type="password" class="ifi" />
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="changePassword">Change Password</button>
        </div>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));
const profileForm = ref({ first_name: '', last_name: '', middle_name: '', suffix: '', email: '', contact_number: '' });
const profileSnapshot = ref('');
const isProfileUnchanged = computed(() => JSON.stringify(profileForm.value) === profileSnapshot.value);
const profileError = ref('');
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' });
const pwError = ref('');

function emptyBackgroundForm() {
  return {
    father_first_name: '', father_middle_name: '', father_last_name: '', father_occupation: '', father_contact_number: '',
    mother_first_name: '', mother_middle_name: '', mother_last_name: '', mother_occupation: '', mother_contact_number: '',
    siblings: [],
    elementary_school: '', elementary_year_graduated: '',
    high_school: '', high_school_year_graduated: '',
    college_school: '', college_year_graduated: '',
  };
}
const backgroundForm = ref(emptyBackgroundForm());
const backgroundSnapshot = ref('');
const isBackgroundUnchanged = computed(() => JSON.stringify(backgroundForm.value) === backgroundSnapshot.value);
const backgroundError = ref('');

function addSibling() {
  backgroundForm.value.siblings.push({ first_name: '', middle_name: '', last_name: '', age: '', occupation: '' });
}
function removeSibling(idx) {
  backgroundForm.value.siblings.splice(idx, 1);
}

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

async function saveProfile() {
  profileError.value = '';

  if (profileForm.value.contact_number && !/^09\d{9}$/.test(profileForm.value.contact_number)) {
    profileError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }

  try {
    const res = await axios.put(`${API_BASE}/student/profile`, profileForm.value, authHeaders());
    student.value = { ...student.value, ...res.data };
    localStorage.setItem('student', JSON.stringify(student.value));
    profileSnapshot.value = JSON.stringify(profileForm.value);
  } catch (e) {
    profileError.value = e.response?.data?.message || 'Failed to update profile.';
  }
}

async function saveBackground() {
  backgroundError.value = '';

  for (const sib of backgroundForm.value.siblings) {
    if (!sib.first_name?.trim() || !sib.last_name?.trim()) {
      backgroundError.value = 'Please fill in a first and last name for every sibling, or remove the empty row.';
      return;
    }
  }

  try {
    // The profile endpoint's response only echoes a trimmed set of basic
    // fields (same ones stored in localStorage), not the background fields,
    // so there's nothing useful to merge back - the form already holds
    // exactly what was just saved.
    await axios.put(`${API_BASE}/student/profile`, backgroundForm.value, authHeaders());
    backgroundSnapshot.value = JSON.stringify(backgroundForm.value);
  } catch (e) {
    backgroundError.value = e.response?.data?.message || 'Failed to update your family and educational background.';
  }
}

async function changePassword() {
  pwError.value = '';
  try {
    await axios.put(`${API_BASE}/student/password`, pwForm.value, authHeaders());
    student.value.must_change_password = false;
    localStorage.setItem('student', JSON.stringify(student.value));
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
  } catch (e) {
    pwError.value = e.response?.data?.message || 'Failed to change password.';
  }
}

onMounted(async () => {
  profileForm.value = {
    first_name: student.value.first_name || '',
    last_name: student.value.last_name || '',
    middle_name: student.value.middle_name || '',
    suffix: student.value.suffix || '',
    email: student.value.email || '',
    contact_number: student.value.contact_number || '',
  };
  profileSnapshot.value = JSON.stringify(profileForm.value);

  // The login/profile responses only carry a trimmed set of fields, so the
  // Family/Siblings/Education background has to be fetched separately from
  // the full student record.
  try {
    const res = await axios.get(`${API_BASE}/student/me`, authHeaders());
    const d = res.data || {};
    const siblings = Array.isArray(d.siblings) ? d.siblings : (d.siblings ? JSON.parse(d.siblings) : []);
    backgroundForm.value = {
      father_first_name: d.father_first_name || '',
      father_middle_name: d.father_middle_name || '',
      father_last_name: d.father_last_name || '',
      father_occupation: d.father_occupation || '',
      father_contact_number: d.father_contact_number || '',
      mother_first_name: d.mother_first_name || '',
      mother_middle_name: d.mother_middle_name || '',
      mother_last_name: d.mother_last_name || '',
      mother_occupation: d.mother_occupation || '',
      mother_contact_number: d.mother_contact_number || '',
      siblings,
      elementary_school: d.elementary_school || '',
      elementary_year_graduated: d.elementary_year_graduated || '',
      high_school: d.high_school || '',
      high_school_year_graduated: d.high_school_year_graduated || '',
      college_school: d.college_school || '',
      college_year_graduated: d.college_year_graduated || '',
    };
    backgroundSnapshot.value = JSON.stringify(backgroundForm.value);
  } catch (e) {
    // Non-fatal - the form just starts empty.
  }
});
</script>