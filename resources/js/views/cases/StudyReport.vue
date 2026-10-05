<template>
  <div class="sr-page">
    <div class="no-print" style="display:flex;justify-content:space-between;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <button class="ibtn ibtn-p ibtn-sm" @click="printReport">
        <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
      </button>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <div style="text-align:center;border-bottom:2px solid var(--forest);padding-bottom:14px;margin-bottom:20px">
        <div style="font-size:11px;color:var(--stone)">Batangas State University - Office of Student Services</div>
        <h1 style="margin:6px 0 2px;font-size:20px;color:var(--forest)">Case Study Report</h1>
      </div>

      <!-- Student Info -->
      <section class="sr-sec">
        <h2>Student Info</h2>
        <div class="sr-grid">
          <div><strong>ID:</strong> {{ student.student_id || '-' }}</div>
          <div><strong>Full Name:</strong> {{ fullName }}</div>
          <div><strong>College:</strong> {{ student.college || '-' }}</div>
          <div><strong>Program:</strong> {{ student.program || '-' }}</div>
          <div><strong>Year and Section:</strong> {{ yearSection }}</div>
        </div>
      </section>

      <!-- Family Information -->
      <section class="sr-sec">
        <h2>Family Information</h2>
        <div class="sr-grid">
          <div>
            <div class="sr-sub">Father</div>
            <div><strong>Name:</strong> {{ parentName('father') }}</div>
            <div><strong>Occupation:</strong> {{ student.father_occupation || '-' }}</div>
            <div><strong>Contact No.:</strong> {{ student.father_contact_number || '-' }}</div>
          </div>
          <div>
            <div class="sr-sub">Mother</div>
            <div><strong>Name:</strong> {{ parentName('mother') }}</div>
            <div><strong>Occupation:</strong> {{ student.mother_occupation || '-' }}</div>
            <div><strong>Contact No.:</strong> {{ student.mother_contact_number || '-' }}</div>
          </div>
        </div>
      </section>

      <!-- Siblings Information -->
      <section class="sr-sec">
        <h2>Siblings Information</h2>
        <div v-if="!siblings.length" class="sr-muted">No siblings recorded.</div>
        <table v-else class="sr-table">
          <thead>
            <tr><th>Name</th><th style="width:60px">Age</th><th>Occupation / School</th></tr>
          </thead>
          <tbody>
            <tr v-for="(s, i) in siblings" :key="i">
              <td>{{ [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ') }}</td>
              <td>{{ s.age || '-' }}</td>
              <td>{{ s.occupation || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Educational Attainment -->
      <section class="sr-sec">
        <h2>Educational Attainment</h2>
        <table class="sr-table">
          <thead>
            <tr><th>Level</th><th>School</th><th style="width:120px">Year Graduated</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Elementary</td>
              <td>{{ student.elementary_school || '-' }}</td>
              <td>{{ student.elementary_year_graduated || '-' }}</td>
            </tr>
            <tr>
              <td>High School</td>
              <td>{{ student.high_school || '-' }}</td>
              <td>{{ student.high_school_year_graduated || '-' }}</td>
            </tr>
            <tr>
              <td>College</td>
              <td>{{ student.college_school || '-' }}</td>
              <td>{{ student.college_year_graduated || 'Ongoing' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Sessions -->
      <section class="sr-sec">
        <h2>Sessions</h2>
        <div v-if="!sessions.length" class="sr-muted">No session notes recorded yet.</div>
        <div v-for="n in sessions" :key="n.id" class="sr-session">
          <div class="sr-grid">
            <div><strong>Date:</strong> {{ monthYear(n.session_date) }}</div>
            <div><strong>Initial Concern (Service):</strong> {{ initialConcern(n) }}</div>
          </div>
          <div class="sr-block">
            <div class="sr-label">Session Notes</div>
            <div class="sr-text">{{ sessionNotesText(n) }}</div>
          </div>
          <div class="sr-block">
            <div class="sr-label">Remarks</div>
            <div class="sr-text">{{ remarksText(n) }}</div>
          </div>
          <div class="sr-block">
            <div class="sr-label">Conducted by</div>
            <div class="sr-text">{{ n.recorded_by?.name || '-' }}</div>
          </div>
        </div>
      </section>

      <div style="margin-top:40px;font-size:11px;color:var(--stone);text-align:center;border-top:1px solid var(--cloud);padding-top:10px">
        This report is confidential and intended solely for authorized OSS personnel.
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { caseAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const route = useRoute();
const loading = ref(true);
const caseFile = ref({});

const student = computed(() => caseFile.value.student || {});

const fullName = computed(() => {
  const s = student.value;
  return [s.first_name, s.middle_name, s.last_name, s.suffix].filter(Boolean).join(' ') || '-';
});

const yearSection = computed(() => {
  const s = student.value;
  return [s.year_level, s.section].filter(Boolean).join(' - ') || '-';
});

function parentName(who) {
  const s = student.value;
  return [s[`${who}_first_name`], s[`${who}_middle_name`], s[`${who}_last_name`]].filter(Boolean).join(' ') || '-';
}

const siblings = computed(() => {
  let list = student.value.siblings;
  if (typeof list === 'string') {
    try { list = JSON.parse(list); } catch (e) { list = []; }
  }
  return Array.isArray(list) ? list : [];
});

// Oldest first, so the report reads in the order the sessions happened.
const sessions = computed(() =>
  [...(caseFile.value.session_notes || [])].sort(
    (a, b) => new Date(a.session_date) - new Date(b.session_date) || a.id - b.id
  )
);

function monthYear(date) {
  if (!date) return '-';
  const d = new Date(date);
  if (isNaN(d)) return '-';
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

function initialConcern(n) {
  const ref = n.referral
    || (caseFile.value.referrals || []).find(r => r.id === n.referral_id);
  const service = ref?.referral_type ? toTitleCase(ref.referral_type) : null;
  const concern = ref?.nature_of_concern || caseFile.value.presenting_concern;
  return [service, concern].filter(Boolean).join(' | ') || '-';
}

function sessionNotesText(n) {
  return [n.observations, n.interventions && `Interventions: ${n.interventions}`]
    .filter(Boolean).join('\n') || '-';
}

function remarksText(n) {
  return [n.student_response && `Student response: ${n.student_response}`, n.next_steps && `Next steps: ${n.next_steps}`]
    .filter(Boolean).join('\n') || '-';
}

function printReport() {
  window.print();
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
.sr-page { max-width: 820px; margin: 0 auto; padding: 32px 24px; background: #fff; font-family: var(--sans, sans-serif); }
.sr-sec { margin-bottom: 18px; }
.sr-sec h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .5px; color: var(--forest); border-bottom: 1px solid var(--cloud); padding-bottom: 4px; margin: 0 0 8px; }
.sr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; font-size: 13px; }
.sr-sub { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--fog); margin-bottom: 3px; }
.sr-muted { font-size: 12.5px; color: var(--fog); }
.sr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.sr-table th, .sr-table td { text-align: left; padding: 5px 8px; border-bottom: 1px solid var(--cloud); }
.sr-session { border: 1px solid var(--cloud); border-radius: 6px; padding: 12px 14px; margin-bottom: 12px; page-break-inside: avoid; }
.sr-block { margin-top: 10px; font-size: 13px; }
.sr-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--fog); margin-bottom: 2px; }
.sr-text { white-space: pre-line; line-height: 1.55; }
@media print {
  .no-print { display: none !important; }
}
</style>
