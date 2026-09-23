<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Refer Student</h1>
      <p>Choose what you'd like to do.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:760px">
      <button type="button" class="icard choice-card" @click="goTo('referral-create-form')">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--moss)" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        <h3 style="margin:12px 0 6px">Refer a Student</h3>
        <p style="font-size:13px;color:var(--slate);margin:0">
          Submit a referral for counseling, academic concerns, testing, or other support services.
        </p>
      </button>

      <button type="button" class="icard choice-card" @click="goTo('complaint-create')">
        <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="var(--red)" stroke-width="2">
          <path d="M12 9v4"/><path d="M12 17h.01"/>
          <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        </svg>
        <h3 style="margin:12px 0 6px">File a Complaint</h3>
        <p style="font-size:13px;color:var(--slate);margin:0">
          Report an act of misconduct against a student. Reviewed by the SDU Head.
        </p>
      </button>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
function goTo(name) {
  router.push({ name });
}
</script>

<style scoped>
.choice-card {
  text-align: left;
  cursor: pointer;
  border: 1.5px solid var(--cloud);
  background: #fff;
  padding: 24px;
  transition: border-color .15s, transform .15s;
}
.choice-card:hover {
  border-color: var(--moss);
  transform: translateY(-2px);
}
</style>