<template>
  <div class="sr-page">
    <div class="no-print" style="display:flex;justify-content:space-between;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="goBack">
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

    <div v-else-if="loadError" style="text-align:center;padding:44px;color:var(--red);font-size:13px">{{ loadError }}</div>

    <template v-else>
      <div style="text-align:center;border-bottom:2px solid var(--forest);padding-bottom:14px;margin-bottom:20px">
        <div style="font-size:11px;color:var(--stone)">Batangas State University - Office of Student Services</div>
        <h1 style="margin:6px 0 2px;font-size:20px;color:var(--forest)">Case Study Report</h1>
      </div>

      <!-- Student Information -->
      <section class="sr-sec">
        <h2>Student Information</h2>
        <div class="sr-grid2">
          <div><span class="sr-k">Student ID</span>{{ student.student_id || '-' }}</div>
          <div><span class="sr-k">Full Name</span>{{ fullName }}</div>
        </div>
        <div class="sr-grid3" style="margin-top:10px">
          <div><span class="sr-k">College</span>{{ student.college || '-' }}</div>
          <div><span class="sr-k">Program</span>{{ student.program || '-' }}</div>
          <div><span class="sr-k">Year and Section</span>{{ yearSection }}</div>
        </div>
      </section>

      <!-- Family Information -->
      <section class="sr-sec">
        <h2>Family Information</h2>
        <table class="sr-table sr-family">
          <thead>
            <tr><th style="width:26%"></th><th>Father</th><th>Mother</th><th>Legal Guardian</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in familyRows" :key="row.label">
              <th>{{ row.label }}</th>
              <td v-for="who in ['father', 'mother', 'guardian']" :key="who">{{ row[who] || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Siblings Information -->
      <section class="sr-sec">
        <h2>Siblings Information</h2>
        <div class="sr-muted" style="margin-bottom:6px">Siblings (brothers &amp; sisters), arranged from the eldest to youngest</div>
        <div v-if="!siblings.length" class="sr-muted">No siblings recorded.</div>
        <table v-else class="sr-table">
          <thead>
            <tr>
              <th style="width:34px">#</th>
              <th>Name</th>
              <th style="width:54px">Age</th>
              <th>Highest Educational Attainment</th>
              <th>Civil Status</th>
              <th>Occupation (write student if still studying)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(s, i) in siblings" :key="i">
              <td>{{ i + 1 }}</td>
              <td>{{ [s.first_name, s.middle_name, s.last_name].filter(Boolean).join(' ') || '-' }}</td>
              <td>{{ s.age || '-' }}</td>
              <td>{{ s.educational_attainment || '-' }}</td>
              <td>{{ s.civil_status || '-' }}</td>
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
            <tr><th style="width:24%">School Level</th><th>School</th><th style="width:110px">Year Graduated</th><th>Achievements</th></tr>
          </thead>
          <tbody>
            <tr v-for="lvl in educationRows" :key="lvl.level">
              <td>{{ lvl.level }}</td>
              <td>{{ lvl.school || '-' }}</td>
              <td>{{ lvl.year || '-' }}</td>
              <td class="sr-text">{{ lvl.achievements || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Case History -->
      <section class="sr-sec">
        <h2>Case History <span class="sr-count">({{ historyRows.length }} {{ historyRows.length === 1 ? 'entry' : 'entries' }})</span></h2>
        <div v-if="!historyRows.length" class="sr-muted">Nothing recorded yet.</div>
        <table v-else class="sr-table sr-history">
          <thead>
            <tr>
              <th style="width:76px">Date</th>
              <th style="width:24%">Initial Concern (Service)</th>
              <th>Sessions, Follow-ups, Parent Conference and Referrals</th>
              <th style="width:22%">Remarks</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in historyRows" :key="row.key">
              <td>{{ row.date }}</td>
              <td>{{ row.service }}</td>
              <td class="sr-text">{{ row.notes }}</td>
              <td>{{ row.conductedBy || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <div style="margin-top:40px;font-size:11px;color:var(--stone);text-align:center;border-top:1px solid var(--cloud);padding-top:10px">
        This report is confidential and intended solely for authorized OSS personnel.
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { caseAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const loadError = ref('');
const caseFile = ref({});

const student = computed(() => caseFile.value?.student || {});

function fullNameOf(first, middle, last, suffix) {
  return [first, middle, last, suffix].filter(Boolean).join(' ');
}

const fullName = computed(() => {
  const s = student.value;
  return fullNameOf(s.first_name, s.middle_name, s.last_name, s.suffix) || '-';
});

const yearSection = computed(() => {
  const s = student.value;
  return [s.year_level, s.section].filter(Boolean).join(' - ') || '-';
});

const familyRows = computed(() => {
  const s = student.value;
  const guardianName = fullNameOf(s.guardian_first_name, s.guardian_middle_name, s.guardian_last_name);
  return [
    { label: 'Name',
      father: fullNameOf(s.father_first_name, s.father_middle_name, s.father_last_name),
      mother: fullNameOf(s.mother_first_name, s.mother_middle_name, s.mother_last_name),
      guardian: guardianName },
    { label: 'Age', father: s.father_age, mother: s.mother_age, guardian: s.guardian_age },
    { label: 'Occupation', father: s.father_occupation, mother: s.mother_occupation, guardian: s.guardian_occupation },
    { label: 'Highest Educational Attainment', father: s.father_educational_attainment, mother: s.mother_educational_attainment, guardian: s.guardian_educational_attainment },
    { label: 'Contact No.', father: s.father_contact_number, mother: s.mother_contact_number, guardian: s.guardian_contact },
  ];
});

const siblings = computed(() => {
  let list = student.value.siblings;
  if (typeof list === 'string') {
    try { list = JSON.parse(list); } catch (e) { list = []; }
  }
  return Array.isArray(list) ? list.filter(x => x && typeof x === 'object') : [];
});

const educationRows = computed(() => {
  const s = student.value;
  const rows = [
    { level: 'Senior High School', school: s.senior_high_school, year: s.senior_high_year_graduated, achievements: s.senior_high_achievements },
    { level: 'Junior High School', school: s.high_school, year: s.high_school_year_graduated, achievements: s.high_school_achievements },
    { level: 'Elementary', school: s.elementary_school, year: s.elementary_year_graduated, achievements: s.elementary_achievements },
  ];
  if (s.college_school) {
    rows.unshift({ level: 'College', school: s.college_school, year: s.college_year_graduated || 'Ongoing', achievements: '' });
  }
  return rows;
});

function monthYear(date) {
  if (!date) return '-';
  const d = new Date(date);
  if (isNaN(d)) return '-';
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
}

// Everything done for each referral, in date order: initial and later
// sessions (session notes), follow-up sessions, parent conferences and
// Refer to TMDU. Each row sits under the service of the referral it belongs
// to (its initial concern). A referral with nothing recorded yet still gets
// one row. TMDU's own Case Referral Slips are not listed on their own: they
// appear as the "Referred to TMDU" row of the referral they came from.
const historyRows = computed(() => {
  const all = Array.isArray(caseFile.value?.referrals) ? caseFile.value.referrals.filter(Boolean) : [];
  const referrals = all
    .filter(r => !r.testing_record && !r.complaint_id)
    .sort((a, b) => new Date(a.created_at) - new Date(b.created_at) || a.id - b.id);
  const slips = Array.isArray(caseFile.value?.parent_conference_slips) ? caseFile.value.parent_conference_slips : [];
  const handoffs = Array.isArray(caseFile.value?.handoffs) ? caseFile.value.handoffs : [];
  const firstId = referrals[0]?.id;

  const rows = [];
  for (const r of referrals) {
    const service = r.referral_type ? toTitleCase(String(r.referral_type).replace(/_/g, ' ')) : '-';
    const items = [];

    (Array.isArray(r.session_notes) ? r.session_notes : []).filter(Boolean).forEach((n, i) => {
      items.push({
        key: `n${n.id}`,
        when: n.session_date || r.created_at,
        notes: [
          i === 0 ? 'Initial Session' : `Session #${n.session_number || i + 1}`,
          n.observations,
          n.interventions && `Interventions: ${n.interventions}`,
          n.student_response && `Student response: ${n.student_response}`,
          n.next_steps && `Next steps: ${n.next_steps}`,
        ].filter(Boolean).join('\n'),
        by: n.recorded_by?.name || '',
      });
    });

    (Array.isArray(r.appointments) ? r.appointments : [])
      .filter(a => a && a.appointment_type === 'follow_up_session')
      .forEach(a => {
        const outcome = a.checked_in ? 'Attended' : (a.status === 'no_show' ? 'No show' : (a.status === 'cancelled' ? 'Cancelled' : 'Scheduled'));
        items.push({
          key: `f${a.id}`,
          when: a.appointment_date,
          notes: [`Follow-up Session (${outcome})`, a.notes].filter(Boolean).join('\n'),
          by: a.checked_in_by?.name || a.staff?.name || '',
        });
      });

    // A slip without a referral (older records) goes under the first referral.
    slips.filter(sl => (sl.referral_id ?? firstId) === r.id).forEach(sl => {
      items.push({
        key: `p${sl.id}`,
        when: sl.conference_date || sl.issued_at,
        notes: [
          'Parent Conference',
          sl.reason && `Reason: ${sl.reason}`,
          sl.remarks && `Remarks: ${sl.remarks}`,
          sl.notes && `Notes: ${sl.notes}`,
        ].filter(Boolean).join('\n'),
        by: sl.issued_by?.name || '',
      });
    });

    // Refer to TMDU: the TMDU referral made from this one, with its result once released.
    all.filter(t => t.testing_record && t.testing_record.source_referral_id === r.id).forEach(t => {
      const tr = t.testing_record;
      const handoff = handoffs.find(h => h.referral_id === t.id);
      items.push({
        key: `t${t.id}`,
        when: t.created_at,
        notes: [
          'Referred to TMDU (Psychological Testing)',
          t.nature_of_concern && `Reason: ${t.nature_of_concern}`,
          tr.report_sent_to_gcu && tr.assessment_summary ? `PAR result: ${tr.assessment_summary}` : `TMDU status: ${toTitleCase(String(tr.status || 'pending').replace(/_/g, ' '))}`,
          tr.report_sent_to_gcu && tr.recommendations && `Recommendations: ${tr.recommendations}`,
        ].filter(Boolean).join('\n'),
        by: handoff?.from_user?.name || '',
      });
    });

    if (!items.length) {
      rows.push({ key: `r${r.id}`, date: monthYear(r.created_at), service, notes: '-', conductedBy: '' });
      continue;
    }
    items
      .sort((a, b) => new Date(a.when) - new Date(b.when))
      .forEach(it => rows.push({ key: it.key, date: monthYear(it.when), service, notes: it.notes || '-', conductedBy: it.by }));
  }
  return rows;
});

// Opened in a new tab there is no history to go back to, so close the tab
// (or fall back to the case list) instead of doing nothing.
function goBack() {
  if (window.history.state && window.history.state.back) { router.back(); return; }
  if (window.opener) { window.close(); return; }
  router.push('/cases');
}

function printReport() {
  window.print();
}

onMounted(async () => {
  try {
    const res = await caseAPI.summary(route.params.id);
    caseFile.value = res.data && typeof res.data === 'object' ? res.data : {};
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Could not load the Case Study Report.';
  } finally {
    loading.value = false;
  }
});
</script>

<style>
.sr-page { max-width: 860px; margin: 0 auto; padding: 32px 24px; background: #fff; font-family: var(--sans, sans-serif); }
.sr-sec { margin-bottom: 18px; }
.sr-sec h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .5px; color: var(--forest); border-bottom: 1px solid var(--cloud); padding-bottom: 4px; margin: 0 0 8px; }
.sr-count { font-size: 11px; font-weight: 400; text-transform: none; letter-spacing: 0; color: var(--stone); }
.sr-grid2 { display: grid; grid-template-columns: 1fr 2fr; gap: 10px 16px; font-size: 13px; }
.sr-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px 16px; font-size: 13px; }
.sr-span2 { grid-column: span 2; }
.sr-span3 { grid-column: span 3; }
.sr-k { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--fog); margin-bottom: 2px; }
.sr-muted { font-size: 12.5px; color: var(--fog); }
.sr-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.sr-table th, .sr-table td { text-align: left; padding: 5px 8px; border: 1px solid var(--cloud); vertical-align: top; }
.sr-table thead th { background: var(--snow); font-size: 11px; text-transform: uppercase; color: var(--stone); }
.sr-family tbody th { background: var(--snow); font-size: 11px; color: var(--stone); font-weight: 600; }
.sr-history tr { page-break-inside: avoid; }
.sr-sub-text { font-size: 11.5px; color: var(--stone); margin-top: 2px; }
.sr-text { white-space: pre-line; line-height: 1.5; }
@media print {
  .no-print { display: none !important; }
}
</style>