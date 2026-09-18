<template>
  <div style="max-width:820px;margin:0 auto;padding:32px 24px;background:#fff;font-family:var(--sans, sans-serif)">
    <div class="no-print" style="display:flex;justify-content:space-between;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
      </button>
      <button class="ibtn ibtn-p ibtn-sm" @click="window.print()">
        <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
      </button>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <div style="text-align:center;border-bottom:2px solid var(--forest);padding-bottom:16px;margin-bottom:20px">
        <div style="font-size:11px;color:var(--stone)">Benguet State University - Office of Student Services</div>
        <h1 style="margin:6px 0 2px;font-size:20px;color:var(--forest)">Student Case Study Report</h1>
        <div style="font-size:12px;color:var(--stone)">Generated {{ formatDate(new Date()) }}</div>
      </div>

      <section style="margin-bottom:18px">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Case Information</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
          <div><strong>Case Number:</strong> {{ caseFile.case_number }}</div>
          <div><strong>Status:</strong> {{ toTitleCase(caseFile.status) }}</div>
          <div><strong>Student:</strong> {{ caseFile.student?.last_name }}, {{ caseFile.student?.first_name }} {{ caseFile.student?.middle_name }}</div>
          <div><strong>Student ID:</strong> {{ caseFile.student?.student_id }}</div>
          <div><strong>College / Program:</strong> {{ caseFile.student?.college }} - {{ caseFile.student?.program }}</div>
          <div><strong>Year / Section:</strong> {{ caseFile.student?.year_level }} {{ caseFile.student?.section }}</div>
          <div><strong>Counselor:</strong> {{ caseFile.counselor?.name || '-' }}</div>
          <div><strong>Opened:</strong> {{ formatDate(caseFile.opened_date) }}</div>
          <div v-if="caseFile.closed_date"><strong>Closed:</strong> {{ formatDate(caseFile.closed_date) }}</div>
        </div>
      </section>

      <section style="margin-bottom:18px" v-if="caseFile.presenting_concern">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Presenting Concern</h2>
        <p style="font-size:13px;line-height:1.6;white-space:pre-line">{{ caseFile.presenting_concern }}</p>
      </section>

      <section style="margin-bottom:18px" v-if="caseFile.referrals?.length">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Referral History ({{ caseFile.referrals.length }})</h2>
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--cloud)">
              <th style="padding:4px 6px">Date</th>
              <th style="padding:4px 6px">Type</th>
              <th style="padding:4px 6px">Concern</th>
              <th style="padding:4px 6px">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in caseFile.referrals" :key="r.id" style="border-bottom:1px solid var(--cloud)">
              <td style="padding:4px 6px">{{ formatDate(r.created_at) }}</td>
              <td style="padding:4px 6px">{{ toTitleCase(r.referral_type) }}</td>
              <td style="padding:4px 6px">{{ r.nature_of_concern }}</td>
              <td style="padding:4px 6px">{{ toTitleCase(r.status) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <section style="margin-bottom:18px" v-if="caseFile.session_notes?.length">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Session Notes ({{ caseFile.session_notes.length }})</h2>
        <div v-for="n in caseFile.session_notes" :key="n.id" style="margin-bottom:10px;font-size:12.5px">
          <div style="font-weight:600">Session #{{ n.session_number }} - {{ toTitleCase(n.session_type) }} ({{ formatDate(n.session_date) }})</div>
          <div style="margin-top:2px"><strong>Observations:</strong> {{ n.observations }}</div>
          <div v-if="n.interventions" style="margin-top:2px"><strong>Interventions:</strong> {{ n.interventions }}</div>
          <div v-if="n.next_steps" style="margin-top:2px"><strong>Next Steps:</strong> {{ n.next_steps }}</div>
        </div>
      </section>

      <section style="margin-bottom:18px" v-if="caseFile.handoffs?.length">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Unit Handoffs</h2>
        <div v-for="h in caseFile.handoffs" :key="h.id" style="font-size:12.5px;margin-bottom:6px">
          {{ h.from_unit }} → {{ h.to_unit }} on {{ formatDate(h.created_at) }} - {{ h.reason }}
          <span v-if="h.acknowledged" style="color:var(--moss)"> (receipt confirmed)</span>
        </div>
      </section>

      <section style="margin-bottom:18px" v-if="caseFile.testing_record">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Psychological Testing</h2>
        <div style="font-size:12.5px">Status: {{ toTitleCase(caseFile.testing_record.status) }}</div>
      </section>

      <section style="margin-bottom:18px" v-if="caseFile.interventions_applied || caseFile.outcomes || caseFile.recommendations || caseFile.closure_summary">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Case Closure</h2>
        <div v-if="caseFile.interventions_applied" style="font-size:13px;margin-bottom:6px"><strong>Interventions Applied:</strong> {{ caseFile.interventions_applied }}</div>
        <div v-if="caseFile.outcomes" style="font-size:13px;margin-bottom:6px"><strong>Outcomes:</strong> {{ caseFile.outcomes }}</div>
        <div v-if="caseFile.recommendations" style="font-size:13px;margin-bottom:6px"><strong>Recommendations:</strong> {{ caseFile.recommendations }}</div>
        <div v-if="caseFile.closure_summary" style="font-size:13px;margin-bottom:6px"><strong>Closure Summary:</strong> {{ caseFile.closure_summary }}</div>
      </section>

      <div style="margin-top:40px;font-size:11px;color:var(--stone);text-align:center;border-top:1px solid var(--cloud);padding-top:10px">
        This report is confidential and intended solely for authorized OSS personnel.
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { caseAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const route = useRoute();
const loading = ref(true);
const caseFile = ref({});

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(async () => {
  try {
    const res = await caseAPI.summary(route.params.id);
    caseFile.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style>
@media print {
  .no-print { display: none !important; }
}
</style>
