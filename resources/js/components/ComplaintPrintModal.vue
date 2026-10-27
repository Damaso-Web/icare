<!--
  One complaint on screen with a Print button. Used after filing and in the
  Sent Complaints lists, so a complaint can be printed at any time (not only
  right after it was submitted).
-->
<template>
  <div class="cp-overlay" @click.self="$emit('close')">
    <div class="cp-box">
      <div class="cp-head">
        <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ heading }}</div>
        <div v-if="subtitle" style="font-size:12px;color:var(--stone)">{{ subtitle }}</div>
      </div>
      <div class="cp-body">
        <div class="complaint-print">
          <div class="cp-print-title">Complaint {{ complaint.complaint_code }}</div>
          <ComplaintDetail :complaint="complaint" />
        </div>
      </div>
      <div class="cp-foot">
        <button class="ibtn ibtn-p" @click="print">Print Complaint</button>
        <button class="ibtn ibtn-o" @click="$emit('close')">{{ closeLabel }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import ComplaintDetail from './ComplaintDetail.vue';

defineProps({
  complaint: { type: Object, required: true },
  heading: { type: String, default: 'Complaint' },
  subtitle: { type: String, default: '' },
  closeLabel: { type: String, default: 'Close' },
});
defineEmits(['close']);

function print() {
  document.body.classList.add('printing-complaint');
  window.print();
  setTimeout(() => document.body.classList.remove('printing-complaint'), 500);
}
</script>

<style>
/* Printing a complaint: only the complaint is printed. */
@media print {
  body.printing-complaint * { visibility: hidden !important; }
  body.printing-complaint .complaint-print, body.printing-complaint .complaint-print * { visibility: visible !important; }
  body.printing-complaint .complaint-print { position: absolute; left: 0; top: 0; width: 100%; }
}
</style>

<style scoped>
.cp-overlay{position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px}
.cp-box{background:#fff;border-radius:var(--r-lg);width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--sh-lg)}
.cp-head{padding:18px 22px;border-bottom:1px solid var(--cloud)}
.cp-body{padding:18px 22px;overflow-y:auto}
.cp-foot{padding:14px 22px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:8px}
.cp-print-title{font-size:16px;font-weight:700;color:var(--ink);margin-bottom:12px}
</style>