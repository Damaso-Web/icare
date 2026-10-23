<template>
  <div class="login-wrap auth-page">
    <div class="login-card auth-panel" :style="{ maxWidth: '440px' }">

      <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:16px">
        <button @click="goBack" aria-label="Back" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;padding:0">
          <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </button>
        <router-link v-if="!otpToken" :to="{ name: 'login-choice' }" style="font-size:12px;color:var(--moss)">Change sign-in type</router-link>
      </div>

      <!-- Logo -->
      <AuthBrand :subtitle="subtitle" />

      <!-- The sign-in form is shown right away. The Confidentiality Agreement
           is a link (like a Terms & Conditions link) rather than a full-page
           block shown up front; it's only required - as a modal the person
           must scroll, tick and accept - the first time they click Sign In
           each visit (see onSignInClick / the ConsentGate modal below). -->
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

      <form v-else @submit.prevent="onSignInClick">
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
        <div style="text-align:center;margin-top:10px;font-size:11.5px;color:var(--fog)">
          By signing in, you agree to the
          <button type="button" class="link-btn" @click.stop="openAgreement(false)">Confidentiality Agreement</button>.
        </div>
      </form>

      <div v-if="!otpToken && audience !== 'oss'" class="change-type">First time here? <router-link :to="{ name: 'staff-register' }">Register as faculty, department chair, dean, or dean's secretary</router-link></div>
      <LoginHelp />

      <div style="text-align:center;margin-top:14px;font-size:10px;color:var(--fog);opacity:.8">
        iCARE: An Integrated Case Management System for Benguet State University-Office of Student Services
      </div>

    </div>

    <!-- Confidentiality Agreement - a link to read it any time (openAgreement(false)),
         and a required step - must scroll, tick, Continue - the first time Sign
         In is clicked each visit (openAgreement(true) via onSignInClick). -->
    <!-- v-show, not v-if: ConsentGate stays mounted (just hidden) from page
         load so its "already agreed on this device" check runs immediately,
         letting Sign In skip the modal entirely when it's already been
         accepted here before - see ConsentGate.vue's onMounted. -->
    <div v-show="showAgreement" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:80;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeAgreement">
      <div class="auth-panel" style="background:#fff;border-radius:var(--r-lg);max-width:480px;width:100%;max-height:90vh;overflow-y:auto;padding:28px 26px;box-shadow:var(--sh-lg)">
        <ConsentGate
          type="staff"
          checkbox-label="I have read and agree to the Confidentiality Agreement above."
          @accept="onAgreementAccept"
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
      </div>
    </div>
  </div>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import ConsentGate from '../components/ConsentGate.vue';
import LoginHelp from '../components/LoginHelp.vue';
import { consumeConsentSkip } from '../utils/consentSkip';
import { ref, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { applyRememberMe, rememberedId } from '../utils/session';

const router = useRouter();
const route  = useRoute();
const auth   = useAuthStore();

// "audience" distinguishes Faculty vs OSS Personnel sign-in (LoginChoice now
// offers both separately instead of one combined "BSU Personnel" card).
// Both still hit the same /login endpoint - this only changes the subtitle
// and whether the faculty/dean self-register link is shown.
const audience = computed(() => route.query.audience || '');
const subtitle = computed(() => {
  if (audience.value === 'faculty') return 'Faculty Sign In';
  if (audience.value === 'oss') return 'OSS Personnel Sign In';
  return 'BSU Personnel Sign In';
});

// The Confidentiality Agreement is a link the person can read any time
// (openAgreement(false)), and is only required - as a modal they must
// scroll, tick and accept - the first time they click Sign In each visit
// to this page (openAgreement(true), via onSignInClick). Coming back here
// right after accepting it elsewhere (e.g. from "Forgot password") skips
// that first prompt, same as before.
const agreed = ref(consumeConsentSkip('staff'));
const showAgreement = ref(false);
const pendingLogin  = ref(false);

function goBack() {
  router.push({ name: 'login-choice' });
}

function openAgreement(gatingLogin) {
  pendingLogin.value = gatingLogin;
  showAgreement.value = true;
}
function closeAgreement() {
  showAgreement.value = false;
  pendingLogin.value = false;
}
function onAgreementAccept() {
  agreed.value = true;
  showAgreement.value = false;
  if (pendingLogin.value) {
    pendingLogin.value = false;
    handleLogin();
  }
}

function onSignInClick() {
  if (!agreed.value) {
    openAgreement(true);
    return;
  }
  handleLogin();
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
    // A lock set by the server comes with its own message (how long is left).
    error.value = e.response?.status === 429
      ? (e.response?.data?.locked ? e.response.data.message : 'Too many attempts. Please wait a minute and try again.')
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
.change-type { text-align: center; margin-top: 16px; font-size: 12.5px; }
.change-type a { color: var(--moss); font-weight: 600; }
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
.link-btn { background: none; border: none; padding: 0; color: var(--moss); font-weight: 600; text-decoration: underline; cursor: pointer; font-size: inherit; font-family: inherit; }
</style>