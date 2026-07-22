<template>
  <div class="login-screen">
    <div class="login-box">

      <!-- Left Panel -->
      <div class="lp">
        <div class="lp-logo">
          <div class="lp-mark">i</div>
          <span class="lp-name">iCARE</span>
        </div>
        <div class="lp-hl">Integrated <em>Case Management</em> System</div>
        <p class="lp-desc">BSU Office of Student Services — GCU, SDU &amp; TMDU unified platform for referral, scheduling, and student case coordination.</p>
        <div class="lp-feats">
          <div class="lp-feat"><div class="lp-dot"></div>Referral intake &amp; status tracking</div>
          <div class="lp-feat"><div class="lp-dot"></div>Appointment scheduling with conflict detection</div>
          <div class="lp-feat"><div class="lp-dot"></div>Unified student case files</div>
          <div class="lp-feat"><div class="lp-dot"></div>GCU · SDU · TMDU cross-unit coordination</div>
          <div class="lp-feat"><div class="lp-dot"></div>Psychological testing &amp; assessment (TMDU)</div>
          <div class="lp-feat"><div class="lp-dot"></div>Reports, analytics &amp; audit logs</div>
        </div>
        <div class="lp-foot">Batangas State University · Batangas City · 2026</div>
      </div>

      <!-- Right Panel -->
      <div class="lf">
        <h2>Welcome back</h2>
        <p class="sub">Sign in to your iCARE account</p>

        <div v-if="error" class="login-error">{{ error }}</div>

        <form @submit.prevent="handleLogin">
          <div class="lf-field">
            <label class="lf-lbl">Email address</label>
            <input
              v-model="form.email"
              type="email"
              class="lf-in"
              placeholder="your@bsu.edu.ph"
              required
            />
          </div>
          <div class="lf-field">
            <label class="lf-lbl">Password</label>
            <input
              v-model="form.password"
              type="password"
              class="lf-in"
              placeholder="Password"
              required
            />
          </div>
          <button type="submit" class="btn-signin" :disabled="loading">
            <svg v-if="!loading" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
              <polyline points="10 17 15 12 10 7"/>
              <line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            <span v-if="loading" class="spinner"></span>
            {{ loading ? 'Signing in...' : 'Sign In' }}
          </button>
        </form>

        <p class="login-note">BSU Office of Student Services &copy; {{ new Date().getFullYear() }}</p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth   = useAuthStore();

const form    = ref({ email: '', password: '' });
const error   = ref('');
const loading = ref(false);

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  try {
    await auth.login(form.value.email, form.value.password);
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message || 'Login failed. Please try again.';
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.login-screen{
  min-height:100vh;background:var(--forest);
  display:flex;align-items:center;justify-content:center;
  padding:16px;position:relative;overflow:auto;
}
.login-screen::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 60% 50% at 15% 85%,rgba(46,148,88,.22) 0%,transparent 60%),
             radial-gradient(ellipse 40% 50% at 85% 15%,rgba(232,180,34,.09) 0%,transparent 55%);
}
.login-box{
  position:relative;z-index:1;display:grid;
  grid-template-columns:360px 420px;
  background:rgba(255,255,255,.03);
  border:1px solid rgba(255,255,255,.08);
  border-radius:var(--r-xl);overflow:hidden;
  box-shadow:0 32px 80px rgba(0,0,0,.55);
  width:100%;max-width:780px;
}
.lp{
  padding:44px 40px;
  background:linear-gradient(150deg,rgba(46,148,88,.14) 0%,rgba(10,30,18,.28) 100%);
  border-right:1px solid rgba(255,255,255,.05);
  display:flex;flex-direction:column;
}
.lp-logo{display:flex;align-items:center;gap:11px;margin-bottom:40px}
.lp-mark{
  width:36px;height:36px;background:var(--gold);
  border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;
  font-family:var(--serif);font-style:italic;font-size:16px;
  color:var(--forest);box-shadow:0 4px 14px rgba(232,180,34,.35);flex-shrink:0;
}
.lp-name{font-size:19px;font-weight:600;color:#fff;letter-spacing:-.3px}
.lp-hl{font-family:var(--serif);font-style:italic;font-size:32px;line-height:1.15;color:#fff;margin-bottom:14px}
.lp-hl em{color:var(--sage);font-style:normal}
.lp-desc{font-size:12.5px;color:rgba(255,255,255,.42);line-height:1.75;margin-bottom:32px}
.lp-feats{display:flex;flex-direction:column;gap:9px;flex:1}
.lp-feat{display:flex;align-items:center;gap:9px;font-size:12px;color:rgba(255,255,255,.58)}
.lp-dot{width:5px;height:5px;border-radius:50%;background:var(--sage);flex-shrink:0}
.lp-foot{font-size:9.5px;color:rgba(255,255,255,.18);margin-top:28px;letter-spacing:.5px;text-transform:uppercase}
.lf{padding:44px 40px;background:#fff;display:flex;flex-direction:column;justify-content:center}
.lf h2{font-size:24px;font-weight:600;color:var(--ink);margin-bottom:4px;letter-spacing:-.3px}
.lf .sub{font-size:13px;color:var(--stone);margin-bottom:28px}
.lf-field{margin-bottom:16px}
.lf-lbl{display:block;font-size:10.5px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--slate);margin-bottom:6px}
.lf-in{
  width:100%;border:1.5px solid var(--silver);border-radius:var(--r-sm);
  padding:11px 13px;font-family:var(--font);font-size:13.5px;color:var(--ink);
  background:var(--snow);outline:none;transition:border-color .15s,box-shadow .15s;
}
.lf-in:focus{border-color:var(--moss);background:#fff;box-shadow:0 0 0 3px rgba(30,110,67,.1)}
.btn-signin{
  width:100%;padding:12px 16px;background:var(--moss);color:#fff;
  border:none;border-radius:var(--r-sm);font-family:var(--font);
  font-size:14px;font-weight:600;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:background .15s,box-shadow .15s,transform .1s;margin-top:4px;
}
.btn-signin:hover{background:var(--pine);box-shadow:0 6px 20px rgba(30,110,67,.28);transform:translateY(-1px)}
.btn-signin:disabled{opacity:.7;cursor:not-allowed;transform:none}
.login-note{font-size:11px;color:var(--fog);text-align:center;margin-top:16px}
.login-error{
  background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);
  padding:10px 13px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px;
}
.spinner{
  width:14px;height:14px;border:2px solid rgba(255,255,255,.3);
  border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:768px){
  .login-box{grid-template-columns:1fr;max-width:400px}
  .lp{display:none}
}
</style>