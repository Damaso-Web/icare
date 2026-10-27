<!--
  The whole of one complaint, read-only: who filed it, against whom, the act
  of misconduct, the narration and any attachments. Used by SDU's Complaints
  page and inside a Student Incident Report.
-->
<template>
  <div class="cd">
    <div class="cd-grid">
      <div>
        <div class="cd-label">Complainant</div>
        <div class="cd-box">
          <strong>{{ complaint.complainant_name }}</strong>
          <div class="cd-muted">{{ complaint.complainant_address }}</div>
        </div>
      </div>
      <div>
        <div class="cd-label">Complainee</div>
        <div class="cd-box">
          <strong>{{ complaineeName }}</strong>
          <div v-if="complaint.complainee_position" class="cd-muted">{{ complaint.complainee_position }}</div>
          <div v-if="complaint.complainee_college" class="cd-muted">{{ complaint.complainee_college }}</div>
          <div v-if="complaint.complainee_department" class="cd-muted">{{ complaint.complainee_department }}</div>
          <div v-if="complaint.complainee_office" class="cd-muted">Office: {{ complaint.complainee_office }}</div>
          <div v-if="complaint.complainee_address" class="cd-muted">{{ complaint.complainee_address }}</div>
        </div>
      </div>
    </div>

    <div class="cd-facts">
      <div>
        <div class="cd-label">Act of Misconduct</div>
        <div class="cd-value">{{ complaint.violation_type }}</div>
      </div>
      <div>
        <div class="cd-label">Date of Incident</div>
        <div class="cd-value">{{ formatDate(complaint.incident_date) }}</div>
      </div>
      <div>
        <div class="cd-label">Date Filed</div>
        <div class="cd-value">{{ formatDate(complaint.created_at) }}</div>
      </div>
      <div>
        <div class="cd-label">Filed By</div>
        <div class="cd-value">{{ complaint.filer_name || '-' }}</div>
      </div>
    </div>

    <div>
      <div class="cd-label">Narration of Facts</div>
      <div class="cd-box" style="white-space:pre-line;line-height:1.6">{{ complaint.description }}</div>
    </div>

    <div v-if="complaint.attachments?.length">
      <div class="cd-label">Attachments</div>
      <div style="display:flex;flex-direction:column;gap:6px">
        <a v-for="att in complaint.attachments" :key="att.id" href="#" class="cd-file" @click.prevent="openAttachment(att)">
          <span class="ibadge" style="background:var(--cloud);color:var(--stone)">{{ att.category === 'evidence' ? 'Evidence' : 'Affidavit' }}</span>
          {{ att.original_filename }}
        </a>
      </div>
    </div>

    <div class="cd-muted" style="font-size:11.5px">✓ The Certification / Statement of Non-Forum Shopping was agreed to when this was filed.</div>
  </div>
</template>

<script setup>
import { computed, inject } from 'vue';
import api from '../api/index';
import { saveBlob } from '../utils/download';

const toast = inject('toast', null);

const props = defineProps({
  complaint: { type: Object, required: true },
  // The student, when the complaint was loaded without its complainee (inside an Incident Report).
  student: { type: Object, default: null },
});

const complaineeName = computed(() => {
  const s = props.complaint.complainee || props.student;
  return s ? `${s.last_name}, ${s.first_name}${s.middle_name ? ' ' + s.middle_name : ''}` : '-';
});

// Opens through the API (with the login token) so it works without the
// storage symlink. PDFs and images preview in a new tab; Word files download.
async function openAttachment(att) {
  const previewable = /\.(pdf|jpe?g|png)$/i.test(att.original_filename || '');
  const w = previewable ? window.open('', '_blank') : null; // opened first so the popup isn't blocked
  try {
    const res = await api.get(`/complaints/${props.complaint.id}/attachments/${att.id}`, { responseType: 'blob' });
    if (previewable && w) {
      w.location = URL.createObjectURL(res.data);
    } else {
      saveBlob(res.data, att.original_filename || 'attachment');
    }
  } catch (e) {
    w?.close();
    toast?.error(e?.response?.status === 404
      ? 'This file is no longer available. Ask the sender to upload it again.'
      : 'Unable to open this file.');
  }
}

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
}
</script>

<style scoped>
.cd { display: flex; flex-direction: column; gap: 14px; font-size: 13px; color: var(--ink); }
.cd-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cd-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.cd-label { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); margin-bottom: 4px; }
.cd-value { font-size: 13px; color: var(--ink); }
.cd-box { background: var(--snow); border-radius: var(--r-sm); padding: 10px 12px; }
.cd-muted { color: var(--stone); font-size: 12.5px; }
.cd-file { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--blue); text-decoration: underline; }
@media (max-width: 860px) {
  .cd-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>