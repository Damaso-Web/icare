<!--
  Feedback Slip (QF-OSS-03), laid out like the paper form - same visual
  language as AdmissionSlipPaper.vue so the two issued slips look like they
  belong to the same office rather than one being a styled form and the
  other a plain list of badges and a notes box.

  Display-only (there's no "fill it in" mode here - the compose form on the
  referral page stays its own checklist/textarea UI); this renders one
  already-sent slip.

  slip: { feedback_checklist: [], feedback_notes, feedback_referred_other_text,
          feedback_others_text, sent_at, sent_to_name, sent_to_role,
          sent_by: { name } }
-->
<template>
  <div class="fsp">
    <div v-if="showDoc" class="fsp-doc doc-header-hidden">
      <div><strong>Document Code:</strong> QF-OSS-03<br><strong>Revision No.:</strong> {{ doc.revision_no || '01' }}</div>
      <div style="text-align:right"><strong>Effectivity:</strong> {{ doc.effectivity || '-' }}<br><strong>Ctrl No.:</strong> {{ doc.ctrl_no || '-' }}</div>
    </div>
    <div class="fsp-title">Feedback Slip</div>

    <div class="fsp-grid">
      <div class="fsp-line"><span class="fsp-k">Name:</span><span class="fsp-v">{{ studentName }}</span></div>
      <div class="fsp-line"><span class="fsp-k">Date:</span><span class="fsp-v">{{ sentOn }}</span></div>
      <div class="fsp-line" v-if="slip.sent_to_name"><span class="fsp-k">Sent To:</span><span class="fsp-v">{{ slip.sent_to_name }}<span v-if="slip.sent_to_role"> ({{ slip.sent_to_role }})</span></span></div>
    </div>

    <div class="fsp-sub">Intervention/s or Assistance Provided:</div>
    <div class="fsp-checks">
      <label v-for="item in CHECKLIST" :key="item.key" class="fsp-check" :class="{ wide: item.key === 'referred_other' || item.key === 'others' }">
        <input type="checkbox" :checked="checked(item.key)" disabled tabindex="-1" />
        <span>{{ item.label }}</span>
        <span v-if="item.key === 'referred_other' && checked('referred_other')" class="fsp-v">{{ slip.feedback_referred_other_text }}</span>
        <span v-if="item.key === 'others' && checked('others')" class="fsp-v">{{ slip.feedback_others_text }}</span>
      </label>
    </div>

    <div class="fsp-sub">Remarks:</div>
    <div class="fsp-notes">{{ slip.feedback_notes || '-' }}</div>

    <div class="fsp-sign">
      <div class="fsp-signname">{{ slip.sent_by?.name || slip.sentBy?.name || 'GCU' }}</div>
      <div class="fsp-signlabel">Attending OSS Personnel</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  slip:     { type: Object, required: true },
  student:  { type: Object, default: () => ({}) },
  doc:      { type: Object, default: () => ({}) },
  // The SIF card already shows the document header above the slip.
  showDoc:  { type: Boolean, default: true },
});

const CHECKLIST = [
  { key: 'interview',             label: 'Interview' },
  { key: 'counseling',            label: 'Counseling' },
  { key: 'psychological_testing', label: 'Psychological Testing' },
  { key: 'referred_scholarship',  label: 'Referred to Scholarship Sponsor/s' },
  { key: 'referred_other',        label: 'Referred to:' },
  { key: 'others',                label: 'Others:' },
];

function checked(key) {
  return Array.isArray(props.slip?.feedback_checklist) && props.slip.feedback_checklist.includes(key);
}

const longDate = d => (d ? new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '-');
const studentName = computed(() => {
  const s = props.student || {};
  return [s.first_name, s.middle_name, s.last_name, s.suffix].filter(Boolean).join(' ') || '-';
});
const sentOn = computed(() => longDate(props.slip?.sent_at));
</script>

<style scoped>
.fsp{background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);padding:16px 18px;font-size:13px;color:var(--ink);display:flex;flex-direction:column;gap:10px}
.fsp-doc{display:flex;justify-content:space-between;gap:12px;font-size:11px;color:var(--stone);border-bottom:1px solid var(--cloud);padding-bottom:8px}
.fsp-title{text-align:center;font-weight:700;letter-spacing:.8px;text-transform:uppercase;font-size:13px}
.fsp-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:6px 16px}
.fsp-line{display:flex;align-items:baseline;gap:6px;min-width:0}
.fsp-k{font-weight:600;white-space:nowrap}
.fsp-v{flex:1;min-width:40px;border-bottom:1px solid var(--silver);padding:0 4px 1px;color:var(--forest);font-style:italic}
.fsp-sub{font-weight:700;margin-top:4px}
.fsp-checks{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;padding-left:6px}
.fsp-check{display:flex;align-items:center;gap:8px;min-width:0}
.fsp-check.wide{grid-column:1/-1}
.fsp-check input[type=checkbox]{width:15px;height:15px;accent-color:var(--moss);flex-shrink:0;pointer-events:none}
.fsp-notes{font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver);white-space:pre-line}
.fsp-sign{align-self:center;text-align:center;margin-top:10px;min-width:220px}
.fsp-signname{font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--ink);padding:0 10px 2px}
.fsp-signlabel{font-size:11.5px;color:var(--stone);margin-top:3px}
@media (max-width:600px){
  .fsp-grid,.fsp-checks{grid-template-columns:1fr}
  .fsp-doc{flex-direction:column}
}
</style>