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
import { ref, onMounted, inject } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/index';

const toast = inject('toast');
const auth  = useAuthStore();

const profileForm = ref({ first_name: '', last_name: '', middle_name: '', email: '', contact_number: '' });
const profileError = ref('');
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' });
const pwError = ref('');

async function saveProfile() {
  profileError.value = '';
  try {
    const res = await api.put('/me/profile', profileForm.value);
    auth.user = { ...auth.user, ...res.data };
    localStorage.setItem('user', JSON.stringify(auth.user));
    toast?.success('Profile updated successfully.');
  } catch (e) {
    profileError.value = e.response?.data?.message || 'Failed to update profile.';
  }
}

async function changePassword() {
  pwError.value = '';
  try {
    await api.put('/me/password', pwForm.value);
    toast?.success('Password changed successfully.');
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
  } catch (e) {
    pwError.value = e.response?.data?.message || 'Failed to change password.';
  }
}

onMounted(() => {
  profileForm.value = {
    first_name: auth.user?.first_name || '',
    last_name: auth.user?.last_name || '',
    middle_name: auth.user?.middle_name || '',
    email: auth.user?.email || '',
    contact_number: auth.user?.contact_number || '',
  };
});
</script>