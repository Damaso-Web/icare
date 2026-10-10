<template>
  <div class="login-wrap auth-page">
    <div class="login-card" :style="{ maxWidth: agreed ? '400px' : '540px' }">

      <button @click="goBack" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>

      <!-- Logo -->
      <AuthBrand subtitle="BSU Personnel Sign In" />

      <!-- Confidentiality agreement - shown every time, before the sign-in form -->
      <ConsentGate
        v-if="!agreed"
        checkbox-label="I acknowledge my data protection responsibilities and agree to the Confidentiality Agreement."
        @accept="agreed = true"
        @decline="router.push({ name: 'login-choice' })"
      >
        <h3>Personnel Confidentiality and Data Handling Agreement</h3>
        <p>
          Pursuant to the Data Privacy Act of 2012 and the BSU Data Privacy Policy, authorized personnel are legally obligated to maintain the absolute confidentiality of all student records, referrals, and case files accessed through the iCARE system.
        </p>
        <p>
          As an authorized user (Admin, GCU, SDU, TMDU, Faculty, or Dean's Secretary), you acknowledge that the information within this system is highly sensitive.
        </p>
        <p>By proceeding, you formally agree to the following terms:</p>
        <ol>
          <li><strong>Authorized Access Only:</strong> You will access, process, and disclose student information strictly on a "need to know" basis to facilitate official student support, academic, or disciplinary functions.</li>
          <li><strong>Strict Non-Disclosure:</strong> You will not share, download, verbally communicate, or transmit confidential student profiles, session notes, or disciplinary records to any unauthorized individuals within or outside the University.</li>
          <li><strong>System Security:</strong> You will secure your account credentials, ensure you log out of shared devices, and only access the iCARE platform using authorized institutional or secured personal hardware.</li>
        </ol>
        <p>
          Violations of this confidentiality agreement may result in the revocation of system access and subject the user to university disciplinary action.
        </p>
      </ConsentGate>

      <template v-else>
      <!-- Error -->
      <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px">
        {{ error }}
      </div>

      <!-- Step 2: emailed one-time code (only when the server has OTP turned on) -->
      <form v-if="otpToken" @submit.prevent="handleVerify">
        <p style="font-size:13px;color:var(--stone);margin-bottom:14px">
          We sent a 6-digit verification code to <strong>{{ emailHint }}</strong>. It expires in 10 minutes.
        </p>
        <div style="margin-bottom:20px">
          <label class="ifl">Verification Code</label>
          <input v-model="otp" class="ifi" inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="one-time-code" required @input="otp = otp.replace(/\D/g, '')" />
        </div>
        <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="loading || otp.length !== 6">
          {{ loading ? 'Verifying...' : 'Verify & Log In' }}
        </button>
        <button type="button" class="ibtn ibtn-o" style="width:100%;justify-content:center;margin-top:8px" @click="otpToken = ''; otp = ''; error = ''">Back</button>
      </form>

      <form v-else @submit.prevent="handleLogin">
        <div style="margin-bottom:14px">
          <label class="ifl">Email Address</label>
          <input
            v-model="form.email"
            type="email"
            class="ifi"
            placeholder="name@bsu.edu.ph"
            required
            autocomplete="off"
          />
        </div>
        <div style="margin-bottom:20px">
          <label class="ifl">Password</label>
          <div style="position:relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="ifi"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
              style="padding-right:40px"
              @keyup="checkCapsLock"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);padding:4px;display:flex;align-items:center"
            >
              <!-- Eye open -->
              <svg v-if="!showPassword" viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <!-- Eye closed -->
              <svg v-if="showPassword" viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <div v-if="capsLockOn" style="font-size:11px;color:var(--amber);margin-top:4px">⚠ Caps Lock is on</div>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin:-10px 0 16px">
          <label class="remember" title="Fills in your email next time and keeps you signed in after closing the browser. Leave it off on a shared computer.">
            <input v-model="rememberMe" type="checkbox" /> Remember me
          </label>
          <router-link :to="{ name: 'forgot-password', query: { type: 'staff' } }" style="font-size:12px;color:var(--moss)">Forgot password?</router-link>
        </div>
        <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="loading">
          <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
      </template>

      <div style="text-align:center;margin-top:20px;font-size:12px;color:var(--fog)">
        iCARE - Integrated Case Management and Referral System<br>
        Benguet State University
      </div>

    </div>
  </div>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import ConsentGate from '../components/ConsentGate.vue';
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { applyRememberMe, rememberedId } from '../utils/session';

const router = useRouter();
const auth   = useAuthStore();

// The Confidentiality Agreement is agreed to afresh on every visit to this page.
const agreed = ref(false);
function goBack() {
  if (agreed.value && !otpToken.value) {
    agreed.value = false;
  } else {
    router.push({ name: 'login-choice' });
  }
}

// An email remembered on this browser is filled in, with the box already ticked.
const form = ref({ email: rememberedId('staff'), password: '' });
const rememberMe = ref(!!rememberedId('staff'));
const error       = ref('');
const loading     = ref(false);
const showPassword = ref(false);
const capsLockOn = ref(false);
const otpToken  = ref('');
const otp       = ref('');
const emailHint = ref('');

function checkCapsLock(e) {
  capsLockOn.value = e.getModifierState && e.getModifierState('CapsLock');
}

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  try {
    const r = await auth.login(form.value.email, form.value.password);
    if (r.otpRequired) {
      otpToken.value  = r.otpToken;
      emailHint.value = r.emailHint;
    } else {
      applyRememberMe('staff', rememberMe.value, form.value.email);
      router.push({ name: 'dashboard' });
    }
  } catch (e) {
    error.value = e.response?.status === 429
      ? 'Too many attempts. Please wait a minute and try again.'
      : (e.response?.data?.message || 'Invalid email or password.');
  } finally {
    loading.value = false;
  }
}

async function handleVerify() {
  error.value   = '';
  loading.value = true;
  try {
    await auth.verifyOtp(otpToken.value, otp.value);
    applyRememberMe('staff', rememberMe.value, form.value.email);
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.response?.status === 429
      ? 'Too many attempts. Please wait a minute and try again.'
      : (e.response?.data?.message || 'Verification failed.');
    if (e.response?.status === 422 && /expired|Too many/.test(e.response?.data?.message || '')) {
      otpToken.value = ''; otp.value = '';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--snow);
  padding: 20px;
}
.login-card {
  background: #fff;
  border-radius: var(--r-lg);
  box-shadow: var(--sh-lg);
  padding: 36px 32px;
  width: 100%;
  max-width: 400px;
  transition: max-width .2s;
}
</style>