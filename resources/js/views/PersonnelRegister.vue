<template>
  <div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div class="auth-card auth-panel" style="width:100%;max-width:460px">
      <button type="button" @click="toLogin" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>

      <AuthBrand subtitle="BSU Personnel · Register" />

      <div class="icard" style="padding:22px">
        <template v-if="!sent">
          <p style="font-size:13px;color:var(--stone);margin-bottom:14px;line-height:1.6">
            For faculty and deans. Enter your Employee ID and your BSU email (the one on file with the Office of Student Services). We will send a one-time password to that email, which you can use to sign in. You will then be asked to change it.
          </p>
          <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-bottom:14px">{{ error }}</div>
          <form @submit.prevent="submit">
            <label class="ifl">Employee ID</label>
            <input v-model="form.employee_id" class="ifi" maxlength="50" placeholder="Your Employee ID" required @input="error = ''" />

            <label class="ifl" style="margin-top:14px;display:block">Email Address</label>
            <input v-model="form.email" type="email" class="ifi" maxlength="100" placeholder="name@bsu.edu.ph" required autocomplete="email" @input="error = ''" />

            <label class="cons">
              <input v-model="consent" type="checkbox" />
              <span>I acknowledge my data protection responsibilities and agree to the Confidentiality Agreement.</span>
            </label>

            <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" :disabled="loading || !consent || !form.employee_id || !form.email.trim()">
              {{ loading ? 'Sending...' : 'Send One-Time Password' }}
            </button>
          </form>
        </template>

        <template v-else>
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Check your email</div>
          <p style="font-size:13px;color:var(--stone);line-height:1.6">
            We sent a one-time password to <strong>{{ maskedEmail }}</strong>. Sign in with your email and that password. It expires in {{ expiresIn }} minutes. Also check your spam folder.
          </p>
          <button type="button" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" @click="toLogin">Go to Sign In</button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { skipConsentNextTime } from '../utils/consentSkip';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const router = useRouter();

const form = ref({ employee_id: '', email: '' });
const consent = ref(false);
const loading = ref(false);
const sent = ref(false);
const error = ref('');
const maskedEmail = ref('');
const expiresIn = ref(30);

function toLogin() {
  // The privacy notice was agreed to here, so go straight to the sign-in form.
  skipConsentNextTime('staff');
  router.push({ name: 'login' });
}

async function submit() {
  if (loading.value) return;
  error.value = '';
  loading.value = true;
  try {
    const res = await axios.post(`${API_BASE}/register`, {
      employee_id: form.value.employee_id.trim(),
      email: form.value.email.trim(),
      consent_accepted: consent.value,
    });
    maskedEmail.value = res.data.masked_email || form.value.email;
    expiresIn.value = res.data.expires_in || 30;
    sent.value = true;
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0])
      || (e.response?.status === 429 ? 'Too many attempts. Please wait a minute and try again.' : e.response?.data?.message)
      || 'Could not send the one-time password. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.cons { display: flex; gap: 9px; align-items: flex-start; margin-top: 16px; font-size: 12.5px; line-height: 1.5; color: var(--ink); cursor: pointer; }
.cons input { margin-top: 2px; accent-color: var(--moss); }
</style>