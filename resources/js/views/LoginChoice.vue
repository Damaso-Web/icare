<template>
  <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--snow);padding:20px">
    <div style="width:100%;max-width:420px">
      <div style="text-align:center;margin-bottom:32px">
        <div style="width:56px;height:56px;background:var(--forest);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-family:var(--serif);font-style:italic;font-size:26px;color:var(--gold)">i</div>
        <div style="font-family:var(--serif);font-style:italic;font-size:24px;color:var(--forest)">iCARE</div>
        <div style="font-size:13px;color:var(--fog);margin-top:4px">BSU · Office of Student Services</div>
      </div>

      <div style="font-size:14px;color:var(--stone);text-align:center;margin-bottom:20px">
        Please select how you'd like to sign in
      </div>

      <div style="display:flex;flex-direction:column;gap:14px">
        <button
          class="icard"
          style="padding:22px;text-align:left;border:1.5px solid var(--cloud);cursor:pointer;background:#fff;display:flex;align-items:center;gap:16px;transition:border-color .15s"
          @mouseover="$event.currentTarget.style.borderColor='var(--moss)'"
          @mouseleave="$event.currentTarget.style.borderColor='var(--cloud)'"
          @click="router.push({ name: 'login' })"
        >
          <div style="width:44px;height:44px;background:var(--mist);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg viewBox="0 0 24 24" style="width:22px;height:22px;stroke:var(--moss);fill:none;stroke-width:2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div>
            <div style="font-size:14px;font-weight:600;color:var(--ink)">BSU Personnel</div>
            <div style="font-size:12px;color:var(--stone);margin-top:2px">Admin, GCU, SDU, TMDU, Faculty, Dean's Secretary</div>
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
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
const router = useRouter();
</script>