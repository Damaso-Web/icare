<template>
  <main class="auth-page" style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div class="auth-card auth-panel" style="width:100%;max-width:480px">
      <AuthBrand />

      <div style="font-size:14px;color:var(--stone);text-align:center;margin-bottom:20px">
        Please select how you'd like to sign in
      </div>

      <div style="display:flex;flex-direction:column;gap:14px">
        <!-- Faculty and OSS Personnel are now two separate sign-in options
             (previously one combined "BSU Personnel" card). Both still use
             the same underlying sign-in form - the audience query param only
             changes the page's subtitle and whether the self-register link
             for faculty/deans is shown. -->
        <button
          class="icard"
          style="padding:22px;text-align:left;border:1.5px solid var(--cloud);cursor:pointer;background:#fff;display:flex;align-items:center;gap:16px;transition:border-color .15s"
          @mouseover="$event.currentTarget.style.borderColor='var(--moss)'"
          @mouseleave="$event.currentTarget.style.borderColor='var(--cloud)'"
          @click="router.push({ name: 'login', query: { audience: 'faculty' } })"
        >
          <div style="width:44px;height:44px;background:var(--mist);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:var(--moss);fill:none;stroke-width:2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
          </div>
          <div>
            <div style="font-size:14px;font-weight:600;color:var(--ink)">Faculty</div>
            <div style="font-size:12px;color:var(--stone);margin-top:2px">Faculty, Dean, Department Chair, Dean's Secretary</div>
          </div>
        </button>

        <button
          class="icard"
          style="padding:22px;text-align:left;border:1.5px solid var(--cloud);cursor:pointer;background:#fff;display:flex;align-items:center;gap:16px;transition:border-color .15s"
          @mouseover="$event.currentTarget.style.borderColor='var(--moss)'"
          @mouseleave="$event.currentTarget.style.borderColor='var(--cloud)'"
          @click="router.push({ name: 'login', query: { audience: 'oss' } })"
        >
          <div style="width:44px;height:44px;background:var(--mist);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:var(--moss);fill:none;stroke-width:2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div>
            <div style="font-size:14px;font-weight:600;color:var(--ink)">OSS Personnel</div>
            <div style="font-size:12px;color:var(--stone);margin-top:2px">Admin, GCU, SDU, TMDU</div>
          </div>
        </button>

        <button
          class="icard"
          style="padding:22px;text-align:left;border:1.5px solid var(--cloud);cursor:pointer;background:#fff;display:flex;align-items:center;gap:16px;transition:border-color .15s"
          @mouseover="$event.currentTarget.style.borderColor='var(--moss)'"
          @mouseleave="$event.currentTarget.style.borderColor='var(--cloud)'"
          @click="router.push({ name: 'student-login' })"
        >
          <div style="width:44px;height:44px;background:var(--blue-lt);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:var(--blue);fill:none;stroke-width:2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
          </div>
          <div>
            <div style="font-size:14px;font-weight:600;color:var(--ink)">Student</div>
            <div style="font-size:12px;color:var(--stone);margin-top:2px">View your referrals and appointments</div>
          </div>
        </button>
      </div>

      <!-- B542: a quiet text link, not a bordered full-width button, so it
           reads as secondary navigation rather than a third option alongside
           the two login cards above. -->
      <div style="text-align:center;margin-top:20px">
        <button type="button" class="back-landing" @click="router.push('/')">
          <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
          Back to Home
        </button>
      </div>

      <!-- The system's full name sits at the bottom, as on BSU Personnel sign-in, so the top stays light. -->
      <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--stone)">
        iCARE: An Integrated Case Management System for Benguet State University-Office of Student Services
      </div>
    </div>
  </main>
</template>

<script setup>
import AuthBrand from '../components/AuthBrand.vue';
import { useRouter } from 'vue-router';
const router = useRouter();
// Once accepted on this device, the Confidentiality Agreement / Data Privacy
// Notice stays accepted (saved in localStorage - see consentSkip.js) and
// isn't asked again here, including after switching sign-in type or logging
// out and back in. It's only re-asked if the agreement's own text changes.
</script>

<style scoped>
/* B542: plain text link - no border, background or full-width container. */
.back-landing{display:inline-flex;align-items:center;gap:6px;padding:4px 2px;border:0;background:none;color:var(--stone);font-family:var(--font);font-size:13px;font-weight:500;cursor:pointer;border-radius:4px;transition:color .15s}
.back-landing svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;transition:transform .15s}
.back-landing:hover,.back-landing:focus-visible{color:var(--moss);outline:none}
.back-landing:hover svg,.back-landing:focus-visible svg{transform:translateX(-2px)}
.back-landing:focus-visible{outline:2px solid var(--moss);outline-offset:2px}
</style>