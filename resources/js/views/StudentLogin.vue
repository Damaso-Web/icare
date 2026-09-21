<template>
  <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div style="width:100%;max-width:400px">
      <button @click="router.push({ name: 'login-choice' })" style="background:none;border:none;color:var(--stone);font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin-bottom:16px;padding:0">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
      </button>

      <div style="text-align:center;margin-bottom:24px">
        <div style="width:52px;height:52px;background:var(--forest);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-family:var(--serif);font-style:italic;font-size:24px;color:var(--gold)">i</div>
        <div style="font-family:var(--serif);font-style:italic;font-size:22px;color:var(--forest)">iCARE</div>
        <div style="font-size:12px;color:var(--fog);margin-top:2px">Student Portal · BSU OSS</div>
      </div>

      <div class="icard">
        <div style="padding:24px">
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:16px">Student Login</div>

          <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:10px 12px;border-radius:var(--r-sm);font-size:13px;margin-bottom:14px">
            {{ error }}
          </div>

          <form @submit.prevent="handleLogin">
            <div style="margin-bottom:14px">
              <label class="ifl">Student ID</label>
              <input
                v-model="form.student_id"
                class="ifi"
                placeholder="e.g. 2302021"
                required
                inputmode="numeric"
                maxlength="10"
                pattern="[0-9]*"
                :style="error ? 'border-color:var(--red);border-width:1.5px' : ''"
                @input="onStudentIdInput"
              />
            </div>
            <div style="margin-bottom:18px">
            <label class="ifl">Password</label>
            <div style="position:relative">
                <input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    class="ifi"
                    placeholder="Enter Password"
                    required
                    :style="`padding-right:40px;${error ? 'border-color:var(--red);border-width:1.5px' : ''}`"
                    @keyup="checkCapsLock"
                    @input="onFieldEdit"
                  />
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);padding:4px;display:flex;align-items:center"
                >
                  <svg v-if="!showPassword" viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <svg v-if="showPassword" viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                    <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>
              <div v-if="capsLockOn" style="font-size:11px;color:var(--amber);margin-top:4px">⚠ Caps Lock is on</div>
            </div>
            <button type="submit" class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="loading">
              <span v-if="loading" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ loading ? 'Signing in...' : 'Sign In' }}
            </button>
          </form>
        </div>
      </div>

      <div style="text-align:center;margin-top:16px;font-size:12px;color:var(--fog)">
        Having trouble logging in? Contact the Office of Student Services.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const loading = ref(false);
const error   = ref('');
const showPassword = ref(false);
const capsLockOn = ref(false);

const form = ref({ student_id: '', password: '' });

const API_BASE = 'https://icare-backend-5jwe.onrender.com/api';

async function handleLogin() {
  error.value = '';
  loading.value = true;
  try {
    const res = await axios.post(`${API_BASE}/student/login`, form.value);
    localStorage.setItem('student_token', res.data.token);
    localStorage.setItem('student', JSON.stringify(res.data.student));
    router.push({ name: 'student-dashboard' });
  } catch (e) {
    const data = e.response?.data || {};
    const code = data.error || data.code;

    const MESSAGES = {
      invalid_student_id: 'No student account found with that Student ID.',
      student_not_found:  'No student account found with that Student ID.',
      invalid_password:   'Incorrect password. Please try again.',
      wrong_password:     'Incorrect password. Please try again.',
      account_inactive:   'This account is not yet activated. Please contact the Office of Student Services.',
      account_locked:     'This account has been locked. Please contact the Office of Student Services.',
    };

    error.value = MESSAGES[code]
      || data.message
      || 'Invalid Student ID or password.';
  } finally {
    loading.value = false;
  }
}

function onStudentIdInput(e) {
  // strip any non-digit and enforce max length
  form.value.student_id = e.target.value.replace(/\D/g, '').slice(0, 10);
  if (error.value) error.value = '';
}

function onFieldEdit() {
  if (error.value) error.value = '';
}

function checkCapsLock(e) {
  capsLockOn.value = e.getModifierState && e.getModifierState('CapsLock');
}
</script>