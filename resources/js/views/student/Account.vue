<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Account</h1>
      <p>Update your personal information and password.</p>
    </div>

    <div style="max-width:520px;display:flex;flex-direction:column;gap:16px">

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Profile Information</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="profileError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ profileError }}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="profileForm.last_name" class="ifi" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="profileForm.first_name" class="ifi" />
            </div>
          </div>
          <div>
            <label class="ifl">Middle Name</label>
            <input v-model="profileForm.middle_name" class="ifi" />
          </div>
          <div>
            <label class="ifl">Email</label>
            <input v-model="profileForm.email" type="email" class="ifi" />
          </div>
          <div>
            <label class="ifl">Contact Number</label>
            <input v-model="profileForm.contact_number" class="ifi" placeholder="09XXXXXXXXX" />
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="saveProfile">Save Changes</button>
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
import { ref, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));
const profileForm = ref({ first_name: '', last_name: '', middle_name: '', email: '', contact_number: '' });
const profileError = ref('');
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' });
const pwError = ref('');

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
  } catch (e) {
    profileError.value = e.response?.data?.message || 'Failed to update profile.';
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
  profileError.value = 'Please fill in all required fields.';
}
}

onMounted(() => {
  profileForm.value = {
    first_name: student.value.first_name || '',
    last_name: student.value.last_name || '',
    middle_name: student.value.middle_name || '',
    email: student.value.email || '',
    contact_number: student.value.contact_number || '',
  };
});
</script>