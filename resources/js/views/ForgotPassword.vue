<template>
  <div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div class="auth-card auth-panel" style="width:100%;max-width:460px">
      <button v-if="step !== 'done'" type="button" aria-label="Back" @click="goBack" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>

      <AuthBrand :subtitle="(isStudent ? 'Student Portal' : 'BSU Personnel') + ' · Forgot Password'" />

      <div class="icard" style="padding:22px">

        <!-- Step 1: email -->
        <template v-if="step === 'email'">
          <p style="font-size:13px;color:var(--stone);margin-bottom:14px">
            {{ isStudent
              ? 'Enter the email address registered to your student account and we will email you a 6-digit code to reset your password.'
              : 'Enter your BSU email address (ending in @bsu.edu.ph) and we will email you a 6-digit code to reset your password.' }}
          </p>
          <div v-if="error" class="fp-err">{{ error }}</div>
          <form @submit.prevent="requestCode">
            <label class="ifl">Email Address</label>
            <input v-model="email" type="email" class="ifi" maxlength="100" :placeholder="isStudent ? 'Email registered to your student account' : 'name@bsu.edu.ph'" required autocomplete="email" />
            <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" :disabled="loading || !email.trim()">
              {{ loading ? 'Sending...' : 'Send Code' }}
            </button>
          </form>
        </template>

        <!-- Step 2: code + new password -->
        <template v-else-if="step === 'code'">
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:8px">Enter your code</div>
          <p style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:12px">
            We emailed a 6-digit code to <strong>{{ email }}</strong>. It expires in {{ minutes }} minutes. Also check your spam folder.
          </p>
          <div class="fp-safe">
            <strong>Stay safe:</strong> iCARE never sends a link to reset your password and never asks for this code by message or call. Type the code only on this page.
          </div>
          <div v-if="note" class="fp-ok">{{ note }}</div>
          <div v-if="error" class="fp-err">{{ error }}</div>

          <form @submit.prevent="resetPassword">
            <label class="ifl">6-digit code</label>
            <input v-model="otp" class="ifi fp-otp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="••••••" @input="otp = otp.replace(/\D/g, '').slice(0, 6)" />

            <label class="ifl" style="margin-top:14px;display:block">New Password</label>
            <div style="position:relative;margin-bottom:6px">
              <input v-model="password" :type="show ? 'text' : 'password'" class="ifi" maxlength="64" style="padding-right:40px" autocomplete="new-password" />
              <button type="button" :aria-label="show ? 'Hide password' : 'Show password'" @click="show = !show" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--stone);display:flex">
                <svg v-if="show" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <PasswordChecklist :password="password" />

            <label class="ifl" style="margin-top:14px;display:block">Confirm New Password</label>
            <input v-model="confirmation" :type="show ? 'text' : 'password'" class="ifi" maxlength="64" autocomplete="new-password" />
            <div v-if="confirmation && password !== confirmation" style="font-size:11.5px;color:var(--red);margin-top:5px">Passwords do not match.</div>

            <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" :disabled="loading || otp.length !== 6">
              {{ loading ? 'Resetting...' : 'Reset Password' }}
            </button>
          </form>

          <div style="font-size:12.5px;color:var(--stone);margin-top:14px;text-align:center">
            Didn't get a code?
            <button v-if="cooldown === 0" type="button" class="link-btn" :disabled="loading" @click="resend">{{ loading ? 'Sending...' : 'Send a new code' }}</button>
            <span v-else style="color:var(--fog)">You can request a new one in {{ cooldown }}s</span>
          </div>
        </template>

        <!-- Step 3: done -->
        <template v-else>
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Password reset</div>
          <p style="font-size:13px;color:var(--stone);line-height:1.6">Your password has been reset and you were signed out of all devices. You can now sign in with your new password.</p>
          <button type="button" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" @click="goBack">Go to Sign In</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import PasswordChecklist from '../components/PasswordChecklist.vue';
import { ref, computed, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { skipConsentNextTime } from '../utils/consentSkip';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const route = useRoute();
const router = useRouter();

const isStudent = computed(() => route.query.type === 'student');
const type = computed(() => (isStudent.value ? 'student' : 'staff'));

const step = ref('email');           // email -> code -> done
const email = ref('');
const otp = ref('');
const password = ref('');
const confirmation = ref('');
const show = ref(false);
const loading = ref(false);
const error = ref('');
const note = ref('');
const minutes = ref(10);

// One code per minute (the server allows one per minute per account).
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

function firstError(e, fallback) {
  const errs = e.response?.data?.errors;
  return (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || fallback;
}

function goBack() {
  skipConsentNextTime(type.value);
  router.push({ name: isStudent.value ? 'student-login' : 'login' });
}

async function sendCode() {
  const res = await axios.post(`${API_BASE}/forgot-password`, { email: email.value.trim(), type: type.value });
  minutes.value = res.data?.expires_in_minutes || 10;
  startCooldown();
}

async function requestCode() {
  if (loading.value) return;
  error.value = ''; note.value = '';
  loading.value = true;
  try {
    await sendCode();
    step.value = 'code';
  } catch (e) {
    error.value = firstError(e, 'Could not send the code. Please try again.');
  } finally {
    loading.value = false;
  }
}

async function resend() {
  if (loading.value) return;
  error.value = ''; note.value = '';
  loading.value = true;
  try {
    await sendCode();
    otp.value = '';
    note.value = 'A new code was sent. Only the newest code works.';
  } catch (e) {
    error.value = firstError(e, 'Could not send a new code. Please try again.');
  } finally {
    loading.value = false;
  }
}

function problem(pw) {
  if (pw.length < 8) return 'The password must be at least 8 characters.';
  if (pw.length > 64) return 'The password must not be longer than 64 characters.';
  if (!/[a-z]/.test(pw) || !/[A-Z]/.test(pw)) return 'The password must contain both uppercase and lowercase letters.';
  if (!/[0-9]/.test(pw)) return 'The password must contain at least one number.';
  if (!/[^A-Za-z0-9]/.test(pw)) return 'The password must contain at least one symbol (e.g. !, @, #, or $).';
  return '';
}

async function resetPassword() {
  if (loading.value) return;
  error.value = ''; note.value = '';
  if (otp.value.length !== 6) { error.value = 'Enter the 6-digit code from your email.'; return; }
  if (password.value !== confirmation.value) { error.value = 'The two passwords do not match.'; return; }
  const p = problem(password.value);
  if (p) { error.value = p; return; }
  loading.value = true;
  try {
    await axios.post(`${API_BASE}/reset-password`, {
      email: email.value.trim(),
      type: type.value,
      otp: otp.value,
      password: password.value,
      password_confirmation: confirmation.value,
    });
    step.value = 'done';
  } catch (e) {
    error.value = firstError(e, 'Could not reset the password. Please try again.');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.link-btn{background:none;border:none;padding:0;color:var(--moss);font-weight:600;text-decoration:underline;cursor:pointer;font-size:inherit}
.link-btn:disabled{opacity:.6;cursor:default}
.fp-err{background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-bottom:14px}
.fp-ok{background:var(--mist);color:var(--moss);border-radius:var(--r-sm);padding:8px 12px;font-size:12.5px;margin-bottom:14px}
.fp-safe{background:var(--amber-lt);color:var(--amber);border-radius:var(--r-sm);padding:9px 12px;font-size:12px;line-height:1.5;margin-bottom:14px}
.fp-otp{font-family:var(--mono);font-size:22px;letter-spacing:8px;text-align:center}
</style>