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
              <input v-model="form.student_id" class="ifi" placeholder="e.g. 2302021" required />
            </div>
            <div style="margin-bottom:18px">
              <label class="ifl">Password</label>
              <input v-model="form.password" type="password" class="ifi" required />
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
    error.value = e.response?.data?.message || 'Invalid Student ID or password.';
  } finally {
    loading.value = false;
  }
}
</script>