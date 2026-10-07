<template>
  <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div style="width:100%;max-width:400px">
      <button type="button" @click="goBack" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>

      <div style="text-align:center;margin-bottom:24px">
        <img :src="'/icare-logo.png'" alt="iCARE" style="width:52px;height:52px;border-radius:14px;object-fit:cover;margin:0 auto 12px;display:block" />
        <div style="font-family:var(--serif);font-style:italic;font-size:22px;color:var(--forest)">iCARE</div>
        <div style="font-size:12px;color:var(--fog);margin-top:2px">{{ isStudent ? 'Student Portal' : 'BSU Personnel' }} · Forgot Password</div>
      </div>

      <div class="icard" style="padding:22px">
        <template v-if="!sent">
          <p style="font-size:13px;color:var(--stone);margin-bottom:14px">
            Enter the email address of your account and we will send you a link to reset your password.
          </p>
          <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-bottom:14px">{{ error }}</div>
          <form @submit.prevent="submit">
            <label class="ifl">Email Address</label>
            <input v-model="email" type="email" class="ifi" maxlength="100" placeholder="name@bsu.edu.ph" required autocomplete="email" />
            <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" :disabled="loading || !email.trim()">
              {{ loading ? 'Sending...' : 'Send Reset Link' }}
            </button>
          </form>
        </template>
        <template v-else>
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Check your email</div>
          <p style="font-size:13px;color:var(--stone);line-height:1.6">
            If an account with <strong>{{ email }}</strong> exists, a password reset link has been sent to it. The link expires in 60 minutes.
            Also check your spam folder.
          </p>
          <button type="button" class="ibtn ibtn-o" style="width:100%;justify-content:center;margin-top:16px" @click="goBack">Back to Sign In</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const route = useRoute();
const router = useRouter();

const isStudent = computed(() => route.query.type === 'student');
const email = ref('');
const loading = ref(false);
const sent = ref(false);
const error = ref('');

function goBack() {
  router.push({ name: isStudent.value ? 'student-login' : 'login' });
}

async function submit() {
  if (loading.value) return;
  error.value = '';
  loading.value = true;
  try {
    await axios.post(`${API_BASE}/forgot-password`, {
      email: email.value.trim(),
      type: isStudent.value ? 'student' : 'staff',
    });
    sent.value = true;
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not send the reset link. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>