<template>
  <div class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div class="auth-card auth-panel" style="width:100%;max-width:460px">
      <button type="button" @click="toLogin" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>

      <AuthBrand subtitle="Student Portal · Register" />

      <div class="icard" style="padding:22px">
        <template v-if="!sent">
          <p style="font-size:13px;color:var(--stone);margin-bottom:14px;line-height:1.6">
            First time here? Enter your Student ID and your email. We will send a one-time password to that email, which you can use to sign in. You will then be asked to change it.
          </p>
          <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:12.5px;margin-bottom:14px">{{ error }}</div>
          <form @submit.prevent="submit">
            <label class="ifl">Student ID</label>
            <input v-model="form.student_id" class="ifi" inputmode="numeric" maxlength="10" placeholder="e.g. 2302021" required @input="onId" />

            <label class="ifl" style="margin-top:14px;display:block">Email Address</label>
            <input v-model="form.email" type="email" class="ifi" maxlength="100" placeholder="Your personal email (eg. name@gmail.com)" required autocomplete="email" :style="emailIssue ? 'border-color:var(--red);border-width:1.5px' : ''" @input="error = ''" />
            <div v-if="emailIssue" style="font-size:11.5px;color:var(--red);margin-top:4px">{{ emailIssue }}</div>

            <!-- B521: consent stays unchecked until the student actively ticks it; the
                 notice itself is a modal (not just a label) so it is actually read-able. -->
            <label class="cons">
              <input v-model="consent" type="checkbox" />
              <span>
                I agree to the
                <button type="button" class="cons-link" @click.prevent="showPrivacy = true">Data Privacy Notice and Consent Authorization of the Office of Student Services.</button>
              </span>
            </label>

            <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" :disabled="loading || !consent || !form.student_id || !form.email.trim() || !!emailIssue">
              {{ loading ? 'Sending...' : 'Send One-Time Password' }}
            </button>
          </form>
        </template>

        <template v-else>
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Check your email</div>
          <p style="font-size:13px;color:var(--stone);line-height:1.6">
            We sent a one-time password to <strong>{{ maskedEmail }}</strong>. Sign in with your Student ID and that password. It expires in {{ expiresIn }} minutes. Also check your spam folder.
          </p>
          <button type="button" class="ibtn ibtn-p" style="width:100%;justify-content:center;margin-top:16px" @click="toLogin">Go to Sign In</button>
        </template>
      </div>
    </div>

    <!-- B521: Data Privacy Notice and Consent Authorization -->
    <div v-if="showPrivacy" class="priv-back" @click.self="showPrivacy = false" @keydown.esc="showPrivacy = false">
      <div ref="privDialog" class="priv-card" role="dialog" aria-modal="true" aria-labelledby="priv-title" tabindex="-1">
        <div class="priv-head">
          <h3 id="priv-title">Data Privacy Notice and Consent Authorization</h3>
          <div class="priv-sub">Office of Student Services, Benguet State University</div>
        </div>
        <div class="priv-body">
          <h4>Data Privacy Notice</h4>
          <p>
            Pursuant to the Data Privacy Act of 2012, its Implementing Rules and Regulations (IRR), and the
            Benguet State University (BSU) Data Privacy Policy, the Office of Student Services (OSS),
            including the Student Discipline Unit (SDU), Guidance and Counseling Unit (GCU), and Testing and
            Materials Development Unit (TMDU) at the La Trinidad Campus, is committed to keeping all
            sensitive personal information collected from students with the utmost confidentiality.
          </p>
          <p>
            Personal information is collected, accessed, used, and disclosed strictly on a "need to know
            basis" and only as reasonably required. Confidential information will not be communicated within
            or outside the University except to authorized personnel. Furthermore, only authorized hardware,
            software, and equipment shall be used in accessing, processing, and transmitting your personal
            data.
          </p>
          <h4>Student's Agreement, Consent, and Authorization</h4>
          <p>
            I have read and understood the Data Privacy Notice of Benguet State University (BSU). I hereby
            grant my full consent to the OSS-SDU, OSS-GCU, and OSS-TMDU to collect, process, and utilize
            necessary and relevant personal data through this medium for official university support
            purposes.
          </p>
          <p>
            I understand that the aforementioned units will abide by the data privacy policies, except in
            cases mandated by law or outside of institutional control. By proceeding, I authorize the secure
            handling of my information across these designated student support units.
          </p>
          <h4>Read more</h4>
          <p class="priv-links">
            <a href="https://privacy.gov.ph/data-privacy-act/" target="_blank" rel="noopener noreferrer">National Privacy Commission: Data Privacy Act</a>
            <a href="https://www.officialgazette.gov.ph/2012/08/15/republic-act-no-10173/" target="_blank" rel="noopener noreferrer">Official Gazette: full text of RA 10173</a>
          </p>
        </div>
        <div class="priv-foot">
          <button type="button" class="ibtn ibtn-p" @click="showPrivacy = false">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { skipConsentNextTime } from '../utils/consentSkip';
import { emailProblem } from '../utils/emailCheck';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const router = useRouter();

const form = ref({ student_id: '', email: '' });
// Checked as they type; nothing shown until something is typed.
const emailIssue = computed(() => (form.value.email.trim() ? emailProblem(form.value.email) : ''));
const consent = ref(false);
// B521: closable via the bottom button, outside click (@click.self above) or Escape.
const showPrivacy = ref(false);
const privDialog = ref(null);
const onPrivKey = e => { if (e.key === 'Escape') showPrivacy.value = false; };
watch(showPrivacy, async open => {
  if (open) {
    window.addEventListener('keydown', onPrivKey);
    document.body.style.overflow = 'hidden';
    await nextTick();
    privDialog.value?.focus();
  } else {
    window.removeEventListener('keydown', onPrivKey);
    document.body.style.overflow = '';
  }
});
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onPrivKey);
  document.body.style.overflow = '';
});
const loading = ref(false);
const sent = ref(false);
const error = ref('');
const maskedEmail = ref('');
const expiresIn = ref(30);

function onId(e) {
  form.value.student_id = e.target.value.replace(/\D/g, '').slice(0, 10);
  error.value = '';
}

function toLogin() {
  // The privacy notice was agreed to here, so go straight to the sign-in form.
  skipConsentNextTime('student');
  router.push({ name: 'student-login' });
}

async function submit() {
  if (loading.value) return;
  error.value = '';
  loading.value = true;
  try {
    const res = await axios.post(`${API_BASE}/student/register`, {
      student_id: form.value.student_id,
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
.cons-link { background: none; border: 0; padding: 0; margin: 0; font: inherit; font-size: inherit; color: var(--moss); text-decoration: underline; text-underline-offset: 2px; cursor: pointer; text-align: left; }
.cons-link:hover { color: var(--pine); }

/* B521: Data Privacy Notice modal - responsive, single close control. */
.priv-back { position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; padding: 16px; }
.priv-card { background: #fff; color: var(--ink); width: 100%; max-width: 560px; max-height: 88vh; display: flex; flex-direction: column; border-radius: var(--r-lg); box-shadow: var(--sh-lg); outline: none; font-size: 14px; }
.priv-head { padding: 18px 22px; border-bottom: 1px solid var(--cloud); }
.priv-head h3 { margin: 0; font-size: 16px; color: var(--forest); }
.priv-sub { margin-top: 2px; font-size: 12px; color: var(--stone); }
.priv-body { padding: 18px 22px; overflow-y: auto; line-height: 1.6; }
.priv-body h4 { margin: 14px 0 4px; font-size: 13px; color: var(--moss); text-transform: uppercase; letter-spacing: .6px; }
.priv-body h4:first-child { margin-top: 0; }
.priv-body p, .priv-body li { color: var(--slate); }
.priv-body ul { margin: 6px 0 0; padding-left: 20px; }
.priv-links { display: flex; flex-direction: column; gap: 6px; }
.priv-links a { color: var(--moss); font-weight: 600; word-break: break-word; }
.priv-foot { padding: 14px 22px; border-top: 1px solid var(--cloud); display: flex; justify-content: flex-end; }
@media (max-width: 420px) {
  .priv-card { max-height: 92vh; }
}
</style>