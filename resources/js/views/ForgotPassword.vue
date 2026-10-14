<template>
  <div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div class="auth-card auth-panel" style="width:100%;max-width:460px">
      <button type="button" @click="goBack" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>

      <AuthBrand :subtitle="(isStudent ? 'Student Portal' : 'BSU Personnel') + ' · Forgot Password'" />

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
            If an account with <strong>{{ email }}</strong> exists, a password reset link has been sent to it. The link expires in 5 minutes.
            Also check your spam folder.
          </p>
          <div v-if="resentNote" style="background:var(--mist);color:var(--moss);border-radius:var(--r-sm);padding:8px 12px;font-size:12.5px;margin-top:12px">{{ resentNote }}</div>
          <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-top:12px">{{ error }}</div>
          <!-- Didn't get it? Send it again, once a minute at most (the server allows one per minute). -->
          <div style="font-size:12.5px;color:var(--stone);margin-top:14px;text-align:center">
            Didn't receive the email?
            <button v-if="cooldown === 0" type="button" class="link-btn" :disabled="loading" @click="resend">{{ loading ? 'Sending...' : 'Resend email' }}</button>
            <span v-else style="color:var(--fog)">You can resend in {{ cooldown }}s</span>
          </div>
          <button type="button" class="ibtn ibtn-o" style="width:100%;justify-content:center;margin-top:16px" @click="goBack">Back to Sign In</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import { ref, computed, onBeforeUnmount } from 'vue';
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
const resentNote = ref('');

// One reset email per minute (config/auth.php passwords.*.throttle = 60).
const COOLDOWN_SECONDS = 60;
const cooldown = ref(0);
let cooldownTimer = null;
function startCooldown() {
  cooldown.value = COOLDOWN_SECONDS;
  clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    cooldown.value = Math.max(0, cooldown.value - 1);
    if (cooldown.value === 0) clearInterval(cooldownTimer);
  }, 1000);
}
onBeforeUnmount(() => clearInterval(cooldownTimer));

async function resend() {
  resentNote.value = '';
  await submit();
  if (!error.value) {
    resentNote.value = 'A new reset link was sent. Please check your inbox (and spam folder). Only the newest link works.';
  }
}

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
    startCooldown();
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not send the reset link. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.link-btn{background:none;border:none;padding:0;color:var(--moss);font-weight:600;text-decoration:underline;cursor:pointer;font-size:inherit}
.link-btn:disabled{opacity:.6;cursor:default}
</style>