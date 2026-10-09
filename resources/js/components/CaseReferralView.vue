<template>
  <div>
    <!-- Document Code Header - read only (QF-OSS-01, same as Refer a Student) -->
    <div style="padding:10px 22px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
      <div style="font-size:11px;color:var(--stone)">
        <div><strong>Document Code:</strong> QF-OSS-01</div>
        <div><strong>Revision No.:</strong> {{ doc.revision_no || '01' }}</div>
      </div>
      <div style="font-size:11px;color:var(--stone);text-align:right">
        <div><strong>Effectivity:</strong> {{ docDate(doc.effectivity_date) }}</div>
        <div><strong>Ctrl No.:</strong> {{ doc.ctrl_no || '-' }}</div>
      </div>
    </div>

    <div style="padding:22px;display:flex;flex-direction:column;gap:16px">
      <div style="font-size:13px;font-weight:600;color:var(--ink)">Case Referral Slip</div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><div class="crv-k">Referral Code</div><div class="crv-v" style="font-family:var(--mono)">{{ data.referral_code }}</div></div>
        <div><div class="crv-k">Date Referred</div><div class="crv-v">{{ fmt(data.created_at) }}</div></div>
        <div><div class="crv-k">Student</div><div class="crv-v">{{ studentName }} <span style="color:var(--fog)">({{ data.student?.student_id }})</span></div></div>
        <div><div class="crv-k">College / Program</div><div class="crv-v">{{ [data.student?.college, data.student?.program].filter(Boolean).join(' · ') || '-' }}</div></div>
        <div><div class="crv-k">Referred By</div><div class="crv-v">{{ data.referrer_name || '-' }}</div></div>
        <div><div class="crv-k">From GCU Referral</div><div class="crv-v" style="font-family:var(--mono)">{{ data.source_referral?.referral_code || '-' }}</div></div>
      </div>

      <div>
        <div class="crv-k">Reason for Referral</div>
        <!-- Read only - the slip can't be edited once it has been sent. -->
        <div class="crv-box">{{ data.reason || '-' }}</div>
      </div>

      <div style="border-top:1px solid var(--cloud);padding-top:14px;display:flex;flex-direction:column;gap:12px">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <div class="crv-k" style="margin:0">Status</div>
          <span class="ibadge" :class="statusClass">{{ statusLabel }}</span>
          <span v-if="data.testing_record?.tester?.name" style="font-size:12px;color:var(--stone)">Tester: {{ data.testing_record.tester.name }}</span>
          <span v-if="data.testing_record?.testing_date" style="font-size:12px;color:var(--stone)">Test date: {{ fmt(data.testing_record.testing_date) }}</span>
        </div>

        <template v-if="data.testing_record?.status === 'test_results_issued'">
          <div v-if="data.testing_record.tests_administered?.length">
            <div class="crv-k">Psychological Tests Administered</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
              <span v-for="t in data.testing_record.tests_administered" :key="t" class="ibadge" style="background:var(--mist);color:var(--moss)">{{ t }}</span>
            </div>
          </div>
          <div>
            <div class="crv-k">Assessment Summary</div>
            <div class="crv-box">{{ data.testing_record.assessment_summary || '-' }}</div>
          </div>
          <div>
            <div class="crv-k">Recommended Actions</div>
            <div class="crv-box">{{ data.testing_record.recommendations || '-' }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <button v-if="data.testing_record.par_document" class="ibtn ibtn-o ibtn-sm" :disabled="downloading" @click="downloadPar">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              {{ downloading ? 'Opening...' : (data.testing_record.par_document.original_filename || 'View PAR File') }}
            </button>
            <span v-else style="font-size:12px;color:var(--fog)">No PAR file was attached.</span>
            <span style="font-size:11px;color:var(--fog)">Released by TMDU {{ fmt(data.testing_record.report_sent_at) }}</span>
          </div>
        </template>
        <div v-else style="font-size:12px;color:var(--stone)">The PAR will appear here once TMDU releases the results.</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import axios from 'axios';
import { caseReferralAPI } from '../api/index';

const props = defineProps({ data: { type: Object, required: true } });
const toast = inject('toast', null);

const doc = ref({});
const downloading = ref(false);

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

onMounted(async () => {
  try {
    const res = await axios.get(`${API_BASE}/document-settings/QF-OSS-01`, {
      headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
    });
    doc.value = res.data || {};
  } catch (e) { /* header falls back to the defaults */ }
});

const studentName = computed(() => {
  const s = props.data.student || {};
  return [s.last_name && `${s.last_name},`, s.first_name, s.middle_name, s.suffix].filter(Boolean).join(' ') || '-';
});

const STATUS = {
  pending:             ['Pending', 'ibadge-pending'],
  fee_form_pending:    ['Pending', 'ibadge-pending'],
  or_submitted:        ['Pending', 'ibadge-pending'],
  scheduled:           ['Scheduled for Testing', 'ibadge-scheduled'],
  in_progress:         ['Scheduled for Testing', 'ibadge-scheduled'],
  test_administered:   ['Test Administered', 'ibadge-completed'],
  awaiting_results:    ['Awaiting Results', 'ibadge-scheduled'],
  par_scheduled:       ['Awaiting Results', 'ibadge-scheduled'],
  test_results_issued: ['Results Released', 'ibadge-closed'],
};
const statusLabel = computed(() => {
  const st = props.data.testing_record?.status;
  if (!st) return props.data.acknowledged_at ? 'Acknowledged' : 'Sent to TMDU';
  // Until TMDU acknowledges the slip it is simply "Sent to TMDU".
  if (st === 'pending' && !props.data.acknowledged_at) return 'Sent to TMDU';
  return (STATUS[st] || [st])[0];
});
const statusClass = computed(() => {
  const st = props.data.testing_record?.status;
  if (!st || (st === 'pending' && !props.data.acknowledged_at)) return 'ibadge-submitted';
  return (STATUS[st] || [])[1] || 'ibadge-pending';
});

function fmt(d) { return d ? new Date(d).toLocaleDateString() : '-'; }
function docDate(d) {
  return d ? new Date(d).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: '2-digit' }) : '-';
}

async function downloadPar() {
  const d = props.data.testing_record?.par_document;
  if (!d || downloading.value) return;
  downloading.value = true;
  try {
    const res = await caseReferralAPI.downloadDocument(d.id);
    const url = URL.createObjectURL(res.data);
    window.open(url, '_blank');
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  } catch (e) {
    toast?.error('Could not open the PAR file.');
  } finally {
    downloading.value = false;
  }
}
</script>

<style>
.crv-k { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); margin-bottom: 3px; }
.crv-v { font-size: 13px; color: var(--ink); }
.crv-box { font-size: 13px; color: var(--slate); line-height: 1.6; background: var(--snow); padding: 10px 12px; border-radius: var(--r-sm); border-left: 2px solid var(--silver); white-space: pre-line; }
</style>