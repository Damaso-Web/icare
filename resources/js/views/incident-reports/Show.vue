<!--
  A Student Incident Report: SDU's file on one student. Their complaints, the
  IR's status, and the referrals SDU has made for them to GCU (handoffs).
  Laid out like GCU's Student Information File.
-->
<template>
  <div class="fade-up">
    <div v-if="loading" style="text-align:center;padding:60px">
      <div style="width:28px;height:28px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <div v-else-if="!report" class="icard">
      <div class="empty-state">
        <h3>Incident report not found</h3>
        <p>This student has no complaints on file.</p>
        <button class="ibtn ibtn-o ibtn-sm" style="margin-top:12px" @click="$router.push({ name: 'incident-reports' })">Back to Student Incident Reports</button>
      </div>
    </div>

    <template v-else>
      <!-- Header: who this is, where the IR stands, and what SDU can do with it -->
      <div class="ir-head">
        <button class="ibtn ibtn-o ibtn-sm" title="Back" @click="$router.push({ name: 'incident-reports' })">
          <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </button>
        <div style="min-width:0">
          <div class="ir-title">{{ student.last_name }}, {{ student.first_name }}</div>
          <div class="ir-sub">Incident Report · <span style="font-family:var(--mono)">{{ student.student_id }}</span></div>
        </div>
        <span class="ibadge" :class="'ir-' + report.status" style="font-size:12px">{{ statusLabel(report.status) }}</span>

        <div class="ir-actions">
          <!-- The status of the whole IR is set here, not on each complaint -->
          <div ref="statusMenuEl" style="position:relative">
            <button class="ibtn ibtn-o ibtn-sm" :disabled="savingStatus" @click="statusMenuOpen = !statusMenuOpen">
              {{ savingStatus ? 'Saving...' : 'Update Status' }}
              <svg viewBox="0 0 24 24" style="width:12px;height:12px"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div v-if="statusMenuOpen" class="ir-menu">
              <button v-for="s in STATUSES" :key="s.value" type="button" :class="{ current: s.value === report.status }" @click="setStatus(s.value)">
                <span class="ibadge" :class="'ir-' + s.value">{{ s.label }}</span>
                <small>{{ s.hint }}</small>
              </button>
            </div>
          </div>
          <button class="ibtn ibtn-p ibtn-sm" @click="referToGcu">
            <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            Refer to GCU
          </button>
        </div>
      </div>

      <div class="ir-cols">
        <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

          <!-- Complaints -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Complaints</span>
              <span class="ibadge" style="background:var(--cloud);color:var(--ink)">{{ report.complaints.length }} on file</span>
            </div>
            <div class="ts">
              <table class="itable">
                <thead>
                  <tr>
                    <th>Complaint No.</th>
                    <th>Act of Misconduct</th>
                    <th>Date of Incident</th>
                    <th>Filed By</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in report.complaints" :key="c.id" style="cursor:pointer" :class="{ 'ir-row-on': c.id === highlightId }" @click="activeComplaint = c">
                    <td style="font-family:var(--mono);font-size:12px">{{ c.complaint_code }}</td>
                    <td>{{ c.violation_type }}</td>
                    <td style="font-size:12px;white-space:nowrap">{{ formatDate(c.incident_date) }}</td>
                    <td>{{ c.filed_by?.name || '-' }}</td>
                    <td style="text-align:right"><button class="ibtn ibtn-o ibtn-sm" @click.stop="activeComplaint = c">View</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Handoffs: the referrals SDU made for this student -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Referred to GCU</span>
              <span v-if="report.handoffs.length" class="ibadge ibadge-callslip">{{ report.handoffs.length }} referral{{ report.handoffs.length === 1 ? '' : 's' }}</span>
            </div>
            <div v-if="!report.handoffs.length" class="icard-body" style="font-size:13px;color:var(--stone)">
              SDU has not referred this student to GCU yet. Use <strong>Refer to GCU</strong> above when the student needs guidance or counseling - the form opens already filled in from this Incident Report.
            </div>
            <div v-else class="ts">
              <table class="itable">
                <thead>
                  <tr>
                    <th>Referral No.</th>
                    <th>Service Requested</th>
                    <th>Date Referred</th>
                    <th>Status at GCU</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="h in report.handoffs" :key="h.id">
                    <td style="font-family:var(--mono);font-size:12px">{{ h.referral_code }}</td>
                    <td>{{ toTitleCase(h.referral_type) }}</td>
                    <td style="font-size:12px;white-space:nowrap">{{ formatDate(h.created_at) }}</td>
                    <td><span class="ibadge" :class="'ibadge-' + h.status">{{ toTitleCase(h.status) }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Student Information -->
        <div class="icard" style="align-self:start">
          <div class="icard-header"><span class="icard-title">Student Information</span></div>
          <div class="icard-body" style="display:flex;flex-direction:column;gap:12px">
            <div style="display:flex;align-items:center;gap:10px">
              <div class="iav" style="width:40px;height:40px;font-size:14px">{{ initials }}</div>
              <div>
                <div style="font-size:14px;font-weight:600;color:var(--ink)">{{ student.last_name }}, {{ student.first_name }} {{ student.middle_name }}</div>
                <div style="font-size:11.5px;color:var(--fog);font-family:var(--mono)">{{ student.student_id }}</div>
              </div>
            </div>
            <div v-for="f in studentFields" :key="f.label">
              <div class="ir-label">{{ f.label }}</div>
              <div style="font-size:13px;color:var(--ink)">{{ f.value || '-' }}</div>
            </div>
            <div v-if="student.is_active === false" class="ibadge" style="background:var(--cloud);color:var(--stone);align-self:flex-start">Inactive student account</div>
          </div>
        </div>
      </div>
    </template>

    <!-- The whole complaint -->
    <div v-if="activeComplaint" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="activeComplaint = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:680px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;display:flex;flex-direction:column">
        <div style="padding:16px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;gap:12px">
          <div>
            <div style="font-size:16px;font-weight:600;color:var(--ink)">Complaint <span style="font-family:var(--mono)">{{ activeComplaint.complaint_code }}</span></div>
            <div style="font-size:11.5px;color:var(--fog);margin-top:2px">Filed {{ formatDate(activeComplaint.created_at) }}</div>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="activeComplaint = null">✕</button>
        </div>
        <div style="padding:18px 22px;overflow-y:auto">
          <ComplaintDetail :complaint="activeComplaint" :student="student" />
        </div>
        <div style="padding:12px 22px;border-top:1px solid var(--cloud);background:var(--snow);display:flex;justify-content:flex-end">
          <button class="ibtn ibtn-o ibtn-sm" @click="activeComplaint = null">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted, onBeforeUnmount } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import ComplaintDetail from '../../components/ComplaintDetail.vue';
import { toTitleCase } from '../../utils/validators';

const route  = useRoute();
const router = useRouter();
const toast  = inject('toast');

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const STATUSES = [
  { value: 'pending',      label: 'Pending',      hint: 'Not looked into yet' },
  { value: 'under_review', label: 'Under Review', hint: 'SDU is looking into it' },
  { value: 'resolved',     label: 'Resolved',     hint: 'SDU has finished with it' },
];
const statusLabel = s => STATUSES.find(x => x.value === s)?.label || s;

const loading = ref(true);
const report  = ref(null);
const student = computed(() => report.value?.student || {});
const activeComplaint = ref(null);
// Arriving from the Complaints page, the complaint that was open there is marked.
const highlightId = computed(() => Number(route.query.complaint) || null);

const initials = computed(() => `${student.value.first_name?.[0] || ''}${student.value.last_name?.[0] || ''}`.toUpperCase() || '?');
const studentFields = computed(() => [
  { label: 'College',        value: student.value.college },
  { label: 'Program',        value: student.value.program },
  { label: 'Year Level',     value: [student.value.year_level, student.value.section].filter(Boolean).join(' · ') },
  { label: 'Sex',            value: student.value.sex },
  { label: 'Contact Number', value: student.value.contact_number },
  { label: 'Email',          value: student.value.email },
]);

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '-';
}

async function fetchReport() {
  loading.value = true;
  try {
    report.value = (await axios.get(`${API_BASE}/incident-reports/${route.params.id}`, authHeaders())).data;
  } catch (e) {
    report.value = null;
  } finally {
    loading.value = false;
  }
}

// ---- Status of the IR ----
const statusMenuOpen = ref(false);
const statusMenuEl   = ref(null);
const savingStatus   = ref(false);

async function setStatus(status) {
  statusMenuOpen.value = false;
  if (status === report.value.status || savingStatus.value) return;
  savingStatus.value = true;
  try {
    report.value = (await axios.patch(`${API_BASE}/incident-reports/${route.params.id}/status`, { status }, authHeaders())).data;
    toast?.success(`Incident Report set to ${statusLabel(status)}.`);
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Could not update the status.');
  } finally {
    savingStatus.value = false;
  }
}

function closeStatusMenu(e) {
  if (statusMenuOpen.value && statusMenuEl.value && !statusMenuEl.value.contains(e.target)) statusMenuOpen.value = false;
}

// ---- Refer to GCU ----
// Opens the usual Refer a Student form already filled in from this IR: the
// student, and the complaints as the concern. Sending it creates a normal
// referral from SDU, which then shows above as a handoff.
function referToGcu() {
  const intro = "Referred by the Student Discipline Unit from the student's Incident Report.";
  const head  = c => `${c.complaint_code} - ${c.violation_type} (incident on ${formatDate(c.incident_date)})`;
  // The form takes 1,000 characters. With the narrations when they fit;
  // otherwise just the list of complaints, so nothing is cut mid-sentence.
  const full  = `${intro}\n\n${report.value.complaints.map(c => `${head(c)}: ${String(c.description || '').trim()}`).join('\n\n')}`;
  const brief = `${intro}\n\n${report.value.complaints.map(head).join('\n')}\n\nThe full narration of each complaint is in the Incident Report.`;
  const concern = full.length <= 1000 ? full : brief;

  sessionStorage.setItem('icare.referPrefill', JSON.stringify({
    student_pk:        student.value.id,
    nature_of_concern: concern,
    return_to:         { name: 'incident-report-show', params: { id: student.value.id } },
    from_label:        `Incident Report of ${student.value.last_name}, ${student.value.first_name}`,
  }));
  router.push({ name: 'referral-create-form' });
}

onMounted(() => {
  fetchReport();
  document.addEventListener('click', closeStatusMenu);
});
onBeforeUnmount(() => document.removeEventListener('click', closeStatusMenu));
</script>

<style scoped>
.ir-head { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.ir-title { font-family: var(--serif); font-style: italic; font-size: 26px; line-height: 1.15; color: var(--forest); }
.ir-sub { font-size: 12px; color: var(--stone); margin-top: 2px; }
.ir-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }
.ir-cols { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 16px; align-items: start; }
.ir-label { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); margin-bottom: 2px; }
.ir-row-on td { background: var(--foam); }

.ir-menu {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; min-width: 230px; padding: 5px;
  background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-sm); box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.ir-menu button {
  display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;
  padding: 8px 10px; border: none; background: none; border-radius: 6px; font-family: var(--font); cursor: pointer;
}
.ir-menu button:hover { background: var(--snow); }
.ir-menu button.current { background: var(--mist); }
.ir-menu small { font-size: 11px; color: var(--stone); }

@media (max-width: 860px) {
  .ir-cols { grid-template-columns: minmax(0, 1fr); }
  .ir-title { font-size: 22px; }
  .ir-actions { margin-left: 0; width: 100%; }
  .ir-actions > *, .ir-actions .ibtn { flex: 1 1 auto; justify-content: center; }
}
</style>
