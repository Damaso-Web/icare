<template>
  <div class="login-wrap">
    <div class="login-card">

      <!-- Logo -->
      <div style="text-align:center;margin-bottom:28px">
        <div style="width:52px;height:52px;background:var(--forest);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-family:var(--serif);font-style:italic;font-size:24px;color:var(--gold)">i</div>
        <div style="font-family:var(--serif);font-style:italic;font-size:22px;color:var(--forest)">iCARE</div>
        <div style="font-size:12px;color:var(--fog);margin-top:2px">BSU · Office of Student Services</div>
      </div>

      <!-- Error -->
      <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:11px 14px;border-radius:var(--r-sm);font-size:13px;margin-bottom:16px">
        {{ error }}
      </div>

      <form @submit.prevent="handleLogin">
        <div style="margin-bottom:14px">
          <label class="ifl">Email Address</label>
          <input
            v-model="form.email"
            type="email"
            class="ifi"
            placeholder="name@bsu.edu.ph"
            required
            autocomplete="email"
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
              style="padding-right:44px;width:100%;box-sizing:border-box"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:4px;display:flex;align-items:center;justify-content:center;color:var(--fog)"
            >
              <svg v-if="!showPassword" viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg v-if="showPassword" viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
        </div>
        <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="loading">
          <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
          {{ loading ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>

      <div style="text-align:center;margin-top:20px;font-size:12px;color:var(--fog)">
        iCARE — Integrated Case Management and Referral System<br>
        Batangas State University
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router       = useRouter();
const auth         = useAuthStore();
const form         = ref({ email: '', password: '' });
const error        = ref('');
const loading      = ref(false);
const showPassword = ref(false);

async function handleLogin() {
  error.value   = '';
  loading.value = true;
  try {
    await auth.login(form.value.email, form.value.password);
    router.push({ name: 'dashboard' });
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid email or password.';
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
}
</style>