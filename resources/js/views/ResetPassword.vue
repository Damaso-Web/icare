<template>
  <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div style="width:100%;max-width:400px">
      <div style="text-align:center;margin-bottom:24px">
        <img :src="'/icare-logo.png'" alt="iCARE" style="width:52px;height:52px;border-radius:14px;object-fit:cover;margin:0 auto 12px;display:block" />
        <div style="font-family:var(--serif);font-style:italic;font-size:22px;color:var(--forest)">iCARE</div>
        <div style="font-size:12px;color:var(--fog);margin-top:2px">Reset Password</div>
      </div>

      <div class="icard" style="padding:22px">
        <template v-if="done">
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Password reset</div>
          <p style="font-size:13px;color:var(--stone);line-height:1.6">Your password has been reset. You can now sign in with your new password.</p>
          <button type="button" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" @click="toLogin">Go to Sign In</button>
        </template>
        <template v-else-if="!token || !email">
          <p style="font-size:13px;color:var(--red)">This reset link is incomplete. Please request a new one.</p>
          <button type="button" class="ibtn ibtn-o" style="width:100%;justify-content:center;margin-top:16px" @click="toForgot">Request a new link</button>
        </template>
        <form v-else @submit.prevent="submit">
          <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-bottom:14px">{{ error }}</div>
          <p style="font-size:13px;color:var(--stone);margin-bottom:14px">Choose a new password for <strong>{{ email }}</strong>.</p>

          <label class="ifl">New Password</label>
          <div style="position:relative;margin-bottom:6px">
            <input v-model="password" :type="show ? 'text' : 'password'" class="ifi" maxlength="64" style="padding-right:40px" autocomplete="new-password" />
            <button type="button" :aria-label="show ? 'Hide password' : 'Show password'" @click="show = !show" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);display:flex;padding:4px">
              <svg v-if="show" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              <svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div style="font-size:11.5px;color:var(--stone);line-height:1.6;margin-bottom:14px">
            8-64 characters with an uppercase letter, a lowercase letter, a number and a symbol (e.g. ! @ # $).
          </div>

          <label class="ifl">Confirm New Password</label>
          <input v-model="confirmation" :type="show ? 'text' : 'password'" class="ifi" maxlength="64" autocomplete="new-password" />
          <div v-if="confirmation && password !== confirmation" style="font-size:11.5px;color:var(--red);margin-top:5px">Passwords do not match.</div>

          <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" :disabled="loading">
            {{ loading ? 'Resetting...' : 'Reset Password' }}
          </button>
        </form>
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

const token = computed(() => String(route.query.token || ''));
const email = computed(() => String(route.query.email || ''));
const type = computed(() => (route.query.type === 'student' ? 'student' : 'staff'));

const password = ref('');
const confirmation = ref('');
const show = ref(false);
const loading = ref(false);
const error = ref('');
const done = ref(false);

function toLogin() { router.push({ name: type.value === 'student' ? 'student-login' : 'login' }); }
function toForgot() { router.push({ name: 'forgot-password', query: { type: type.value } }); }

function problem(pw) {
  if (pw.length < 8) return 'The password must be at least 8 characters.';
  if (pw.length > 64) return 'The password must not be longer than 64 characters.';
  if (!/[a-z]/.test(pw) || !/[A-Z]/.test(pw)) return 'The password must contain both uppercase and lowercase letters.';
  if (!/[0-9]/.test(pw)) return 'The password must contain at least one number.';
  if (!/[^A-Za-z0-9]/.test(pw)) return 'The password must contain at least one symbol (e.g. !, @, #, or $).';
  return '';
}

async function submit() {
  if (loading.value) return;
  error.value = '';
  if (password.value !== confirmation.value) { error.value = 'The two passwords do not match.'; return; }
  const p = problem(password.value);
  if (p) { error.value = p; return; }
  loading.value = true;
  try {
    await axios.post(`${API_BASE}/reset-password`, {
      token: token.value,
      email: email.value,
      type: type.value,
      password: password.value,
      password_confirmation: confirmation.value,
    });
    done.value = true;
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not reset the password. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>