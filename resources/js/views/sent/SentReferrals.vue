<!--
  Sent Referrals: every referral this account has sent (staff or student).
  Read-only summary; the unit handling it keeps the full record.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Sent Referrals</h1>
      <p>Referrals you have sent and where they stand.</p>
    </div>

    <div class="icard">
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:14px 18px;border-bottom:1px solid var(--cloud)">
        <div style="flex:1;min-width:200px">
          <ListFilters :model="filters" :selects="filterSelects" placeholder="Search referral no. or student..." @change="load(1)" />
        </div>
        <button 
          class="ibtn ibtn-sm" 
          :class="filters.archived ? 'ibtn-p' : 'ibtn-o'" 
          @click="filters.archived = !filters.archived; load(1)"
        >
          {{ filters.archived ? 'Viewing Archived' : 'View Archived' }}
        </button>
      </div>
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!rows.length" class="empty-state">
        <h3>{{ filtered ? 'No matching referrals' : 'No referrals sent yet' }}</h3>
        <p>{{ filtered ? 'Try a different search or reset the filters.' : 'Referrals you send will be kept here.' }}</p>
      </div>
      <div v-else class="ts">
        <table class="itable">
          <thead>
            <tr><th>Referral No.</th><th>Student</th><th>Type</th><th>Status</th><th>Date Sent</th><th></th></tr>
          </thead>
          <tbody>
            <tr 
              v-for="r in rows" 
              :key="r.id" 
              :style="'cursor:pointer;' + (r.is_archived ? 'opacity: 0.6; background: var(--snow); color: var(--stone);' : '')" 
              @click="selected = r; editing = false"
            >
              <td style="font-family:var(--mono);font-size:12px">{{ r.referral_code }}</td>
              <td>{{ r.student ? `${r.student.last_name}, ${r.student.first_name}` : '-' }}</td>
              <td>{{ title(r.referral_type) }}</td>
              <td>
                <!-- If archived, show ONLY the Archived badge. A pending
                     cancellation request takes over the tag next (B#,
                     11/4/2026) - the raw status underneath hasn't changed
                     yet, but what's pending is what matters here. -->
                <span v-if="r.is_archived" class="ibadge" style="background:var(--cloud);color:var(--stone)">Archived</span>
                <span v-else-if="r.void_requested_at" class="ibadge ibadge-cancellation_request">Cancellation Request</span>
                <span v-else class="ibadge" :class="'ibadge-' + r.status">{{ title(r.status) }}</span>
              </td>
              <td style="font-size:12px;white-space:nowrap">{{ fmt(r.created_at) }}</td>
              <td style="text-align:right"><button class="ibtn ibtn-o ibtn-sm" @click.stop="selected = r; editing = false">View</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="last > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:6px">
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === 1" @click="load(page - 1)">Prev</button>
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === last" @click="load(page + 1)">Next</button>
      </div>
    </div>

    <div v-if="selected" class="sr-overlay" @click.self="close">
      <div class="sr-box">
        <div style="padding:18px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Referral {{ selected.referral_code }}</div>
          <div style="font-size:12px;color:var(--stone)">{{ title(selected.referral_type) }} · {{ fmt(selected.created_at) }}</div>
        </div>
        <div style="padding:18px 22px;overflow-y:auto;display:flex;flex-direction:column;gap:12px">
          <div>
            <div class="sr-k">Student</div>
            <div class="sr-v">{{ selected.student ? `${selected.student.first_name} ${selected.student.last_name}` : '-' }}</div>
          </div>
          <div>
            <div class="sr-k">Status</div>
            <!-- If archived, show ONLY the Archived badge. A pending
                 cancellation request takes over the tag next - see the
                 table row above for why. -->
            <span v-if="selected.is_archived" class="ibadge" style="background:var(--cloud);color:var(--stone)">Archived</span>
            <span v-else-if="selected.void_requested_at" class="ibadge ibadge-cancellation_request">Cancellation Request</span>
            <span v-else class="ibadge" :class="'ibadge-' + selected.status">{{ title(selected.status) }}</span>
          </div>

          <template v-if="!editing">
            <div>
              <div class="sr-k">Reason for Referral</div>
              <div class="sr-v" style="white-space:pre-line;line-height:1.6">{{ selected.nature_of_concern }}</div>
            </div>
            <div v-if="canEdit(selected)" style="font-size:12px;color:var(--stone);background:var(--snow);border-radius:var(--r-sm);padding:8px 12px">
              GCU has not acknowledged this referral yet, so you can still edit it.
            </div>
            <!-- B#, 11/4/2026: shown to the sender while a request they
                 made is awaiting a decision from the handling unit. -->
            <div v-if="selected.void_requested_at" style="font-size:12px;color:var(--amber);background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:8px 12px">
              Cancellation requested{{ selected.void_reason ? `: "${selected.void_reason}"` : '' }}. Waiting on the handling unit's decision.
            </div>
          </template>

            <template v-else>
            <!-- CHANGE STUDENT UI -->
            <div>
              <label class="ifl">Student</label>
              <div v-if="!changeStudentMode" style="display:flex; justify-content:space-between; align-items:center; background:var(--snow); padding:10px 12px; border-radius:var(--r-sm);">
                <span style="font-size:13.5px; color:var(--ink)">{{ selected.student ? `${selected.student.first_name} ${selected.student.last_name}` : '-' }}</span>
                <button type="button" class="ibtn ibtn-o ibtn-sm" @click="toggleChangeStudent">Change Student</button>
              </div>
                            <div v-else style="position:relative; margin-top: 8px;">
                <!-- Reused Search UI from Create Referral -->
                <div class="sw" style="width: 100%;">
                  <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input 
                    v-model="studentQuery" 
                    class="sin" 
                    style="width: 100%;"
                    placeholder="Search student name or ID..." 
                    @input="searchStudents" 
                  />
                </div>

                <!-- Dropdown Results -->
                                <div v-if="studentQuery.length >= 1" style="position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid var(--cloud); border-radius:var(--r-sm); box-shadow:var(--sh-lg); z-index:50; max-height:200px; overflow-y:auto;">
                  
                  <!-- Loading State -->
                  <div v-if="searchingStudents" style="padding:14px; text-align:center; font-size:12px; color:var(--fog);">
                    Searching...
                  </div>
                  
                  <!-- No Results -->
                  <div v-else-if="!studentResults.length" style="padding:14px; text-align:center; font-size:12px; color:var(--fog);">
                    No students found.
                  </div>

                  <!-- Results -->
                  <div 
                    v-else
                    v-for="s in studentResults" 
                    :key="s.id" 
                    style="padding:10px 14px; cursor:pointer; border-bottom:1px solid var(--cloud); font-size:13px; transition:background 0.1s" 
                    @mouseover="$event.currentTarget.style.background='var(--foam)'"
                    @mouseleave="$event.currentTarget.style.background='#fff'"
                    @click="pickStudent(s)"
                  >
                    <div style="font-weight:600; color:var(--ink)">{{ s.last_name }}, {{ s.first_name }}</div>
                    <div style="font-family:var(--mono); font-size:11px; color:var(--fog)">{{ s.student_id }} · {{ s.college }}</div>
                  </div>
                </div>

                <button type="button" class="ibtn ibtn-o ibtn-sm" style="margin-top:8px" @click="changeStudentMode = false">Cancel Change</button>
              </div>
            </div>
            
            <div>
              <label class="ifl">Service Requested</label>
              <select v-model="form.referral_type" class="ifse">
                <option v-for="t in typeChoices" :key="t" :value="t">{{ title(t) }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Reason for Referral <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(at least 10 characters)</span></label>
              <textarea v-model="form.nature_of_concern" class="ifta" style="min-height:110px" maxlength="1000"></textarea>
            </div>
            <template v-if="selected.referral_type === 'disciplinary'">
              <div>
                <label class="ifl">Violation</label>
                <input v-model="form.violation_type" class="ifi" maxlength="255" />
              </div>
              <div>
                <label class="ifl">Incident Date</label>
                <input v-model="form.incident_date" type="date" class="ifi" />
              </div>
              <div>
                <label class="ifl">Incident Description</label>
                <textarea v-model="form.incident_description" class="ifta" style="min-height:80px" maxlength="2000"></textarea>
              </div>
            </template>
            <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
          </template>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:8px">
          <template v-if="!editing">
            <!-- Only show Edit and Cancel if it is NOT archived -->
            <template v-if="!selected.is_archived">
              <button v-if="canEdit(selected)" class="ibtn ibtn-p" @click="startEdit">Edit Referral</button>
              <button v-if="canEdit(selected)" class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="cancelReferral">Cancel Referral</button>
              <!-- B#, 11/4/2026: once GCU/SDU/TMDU has acknowledged or
                   scheduled it, it can no longer be cancelled outright -
                   only requested, until it moves past 'scheduled'. -->
              <button v-if="canRequestCancellation(selected)" class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openCancelModal">Request Cancellation</button>
            </template>

            <button class="ibtn ibtn-o" @click="close">Close</button>
          </template>
          <template v-else>
            <button class="ibtn ibtn-p" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
            <button class="ibtn ibtn-o" :disabled="saving" @click="editing = false">Cancel</button>
          </template>
        </div>
      </div>
    </div>

    <!-- B#, 11/4/2026: Request Cancellation - asks the handling unit to
         approve/deny cancelling an already acknowledged/scheduled
         referral, mirrored from referrals/Show.vue's own void-request
         modal so staff and students get the same flow here. -->
    <div v-if="showCancelModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:80;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCancelModal = false">
      <div class="icard" style="width:100%;max-width:440px">
        <div style="padding:16px 20px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Request Cancellation</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showCancelModal = false">✕</button>
        </div>
        <div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">
          <label class="ifl">Reason <span style="color:var(--fog);font-weight:400">(at least 10 characters)</span></label>
          <textarea v-model="cancelReason" class="ifta" style="min-height:100px" maxlength="1000" placeholder="e.g. no longer needed, issue already resolved directly..."></textarea>
          <div v-if="cancelError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ cancelError }}</div>
        </div>
        <div style="padding:14px 20px;border-top:1px solid var(--cloud);display:flex;gap:8px">
          <button class="ibtn ibtn-p" style="flex:1;justify-content:center" :disabled="requestingCancel" @click="submitCancelRequest">{{ requestingCancel ? 'Submitting...' : 'Submit Request' }}</button>
          <button class="ibtn ibtn-o" style="flex:1;justify-content:center" :disabled="requestingCancel" @click="showCancelModal = false">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, inject } from 'vue';
import ListFilters from '../../components/ListFilters.vue';
import { useRoute } from 'vue-router';
import api, { studentApi, referralAPI } from '../../api/index';
import { useAutoRefresh } from '../../utils/useAutoRefresh';

const route = useRoute();
const toast = inject('toast');
const confirmAction = inject('confirmAction', null);
const isStudent = !!route.meta.student;
const http = isStudent ? studentApi : api;
const base = isStudent ? '/student/outreach/referrals' : '/my/referrals';

const rows = ref([]);
const loading = ref(true);
const page = ref(1);
const last = ref(1);
const selected = ref(null);
const editing = ref(false);
const saving = ref(false);
const error = ref('');
const form = ref({});

// --- CHANGE STUDENT LOGIC ---
const changeStudentMode = ref(false);
const studentQuery = ref('');
const studentResults = ref([]);
const searchingStudents = ref(false);
let searchTimer = null;

function toggleChangeStudent() {
  changeStudentMode.value = !changeStudentMode.value;
  studentQuery.value = '';
  studentResults.value = [];
}

function searchStudents() {
  clearTimeout(searchTimer);
  const q = studentQuery.value.trim();
  if (q.length < 1) {
    studentResults.value = [];
    return;
  }
  searchTimer = setTimeout(async () => {
    searchingStudents.value = true;
    try {
      const res = await api.get('/students', { params: { search: q, is_active: 1, scope: 'all' } });
      // Filter out the currently selected student so you don't see them in the list
      const currentId = selected.value.student?.id || selected.value.student_id;
      studentResults.value = (res.data.data || []).filter(s => s.id !== currentId);
    } catch (e) {
      console.error("Search error:", e);
      studentResults.value = [];
    } finally {
      searchingStudents.value = false;
    }
  }, 300);
}

async function pickStudent(s) {
  // 1. Ask for confirmation first using your custom modal
  const ok = await confirmAction?.({
    title: 'Change Student?',
    message: `Are you sure you want to move this referral to ${s.first_name} ${s.last_name}? This will move it to their official record.`,
    confirmText: 'Yes, Change Student',
    cancelText: 'Cancel'
  });
  if (confirmAction && !ok) return;

  // 2. If confirmed, make the API call
  try {
    await api.put(`/referrals/${selected.value.id}/reassign-student`, { student_id: s.id });
    toast?.success('Referral successfully moved to ' + s.first_name + ' ' + s.last_name);
    
    // 3. Update the UI
    selected.value.student = s;
    const idx = rows.value.findIndex(x => x.id === selected.value.id);
    if (idx >= 0) rows.value[idx].student = s;
    
    toggleChangeStudent();
  } catch (e) {
    toast?.error(e.response?.data?.message || e.message || 'Failed to change student.');
  }
}


async function cancelReferral() {
  let ok = true;
  if (confirmAction) {
    ok = await confirmAction({ title: 'Cancel Referral?', message: 'Are you sure you want to cancel this referral? It will be moved to the archive.', confirmText: 'Yes, Cancel', cancelText: 'Never Mind' });
  } else {
    ok = window.confirm('Are you sure you want to cancel this referral?');
  }
  if (!ok) return;

  try {
    // THIS MUST BE http.put AND is_archived: 1
    await http.put(`${base}/${selected.value.id}`, { is_archived: 1 });
    toast?.success('Referral cancelled and archived.');
    selected.value.is_archived = 1;
    load(page.value);
  } catch (e) {
    toast?.error(e.response?.data?.message || e.message || 'Error cancelling referral.');
  }
}

const ALL_TYPES = ['class_attendance', 'counseling', 'academic_deficiency', 'leave_of_absence', 'withdrawal', 'readmission', 'shifting'];
// A referral keeps to its own unit, so a GCU referral can only change to another GCU service.
const typeChoices = computed(() => {
  const t = selected.value?.referral_type;
  return t === 'disciplinary' || t === 'psychological_testing' ? [t] : ALL_TYPES;
});
// The sender may correct it until GCU acknowledges it.
const canEdit = r => r && r.status === 'submitted' && !r.acknowledged_at && !r.is_archived;
// B#, 11/4/2026: once acknowledged, the sender can no longer cancel it
// directly (canEdit above) - they can only ask the handling unit to
// cancel it, and only up through 'scheduled' (mirrors the backend's
// Referral::canRequestCancellation()).
const canRequestCancellation = r => r && !!r.acknowledged_at && !r.is_archived && !r.void_requested_at
  && !['in_progress', 'referred_tmdu', 'referred_external', 'completed', 'closed', 'cancelled'].includes(r.status);

const showCancelModal = ref(false);
const cancelReason = ref('');
const cancelError = ref('');
const requestingCancel = ref(false);
function openCancelModal() {
  cancelReason.value = '';
  cancelError.value = '';
  showCancelModal.value = true;
}
async function submitCancelRequest() {
  if (cancelReason.value.trim().length < 10) {
    cancelError.value = 'Please explain why in at least 10 characters.';
    return;
  }
  requestingCancel.value = true;
  cancelError.value = '';
  try {
    // Staff and student use different endpoints (the shared sentList()
    // read is one route, but the staff void-request action lives at
    // /referrals/{id}/request-void while the student one is scoped
    // under their own /student/outreach/referrals base).
    const url = isStudent
      ? `${base}/${selected.value.id}/request-cancellation`
      : `/referrals/${selected.value.id}/request-void`;
    const res = await http.post(url, { reason: cancelReason.value.trim() });
    const idx = rows.value.findIndex(x => x.id === selected.value.id);
    if (idx >= 0) rows.value[idx] = { ...rows.value[idx], ...res.data };
    selected.value = { ...selected.value, ...res.data };
    showCancelModal.value = false;
    toast?.success('Cancellation request submitted.');
  } catch (e) {
    cancelError.value = e.response?.data?.message || 'Failed to submit the request.';
  } finally {
    requestingCancel.value = false;
  }
}

function close() { selected.value = null; editing.value = false; }
function startEdit() {
  const r = selected.value;
  form.value = {
    referral_type: r.referral_type,
    nature_of_concern: r.nature_of_concern || '',
    violation_type: r.violation_type || '',
    incident_date: r.incident_date ? String(r.incident_date).slice(0, 10) : '',
    incident_description: r.incident_description || '',
  };
  error.value = '';
  editing.value = true;
}
async function save() {
  error.value = '';
  if ((form.value.nature_of_concern || '').trim().length < 10) { error.value = 'Please write the reason in at least 10 characters.'; return; }
  saving.value = true;
  try {
    const payload = { referral_type: form.value.referral_type, nature_of_concern: form.value.nature_of_concern.trim() };
    if (selected.value.referral_type === 'disciplinary') {
      payload.violation_type = form.value.violation_type || null;
      payload.incident_date = form.value.incident_date || null;
      payload.incident_description = form.value.incident_description || null;
    }
    const res = await http.put(`${base}/${selected.value.id}`, payload);
    const idx = rows.value.findIndex(x => x.id === selected.value.id);
    if (idx >= 0) rows.value[idx] = { ...rows.value[idx], ...res.data };
    selected.value = rows.value[idx] || { ...selected.value, ...res.data };
    editing.value = false;
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not save the changes.';
  } finally {
    saving.value = false;
  }
}

// B597: a dashboard metric card can link here with ?status=... (a single
// value, or several joined by commas for a group like "in progress") to
// land already filtered.
const filters = reactive({ search: '', sort: 'newest', status: route.query.status || '', referral_type: '', archived: false });
const options = ref({ types: [], statuses: [], type_labels: {} });
const filtered = computed(() => !!(filters.search || filters.status || filters.referral_type || filters.sort !== 'newest'));
const filterSelects = computed(() => [
  { key: 'status', all: 'All Status', options: options.value.statuses.map(v => ({ value: v, label: title(v) })) },
  { key: 'referral_type', all: 'All Types', options: options.value.types.map(v => ({ value: v, label: options.value.type_labels?.[v] || title(v) })) },
]);

const fmt = d => (d ? new Date(d).toLocaleDateString() : '-');
const title = s => (s ? s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/\b(Sdu|Gcu|Tmdu)\b/g, m => m.toUpperCase()) : '');

async function load(p = 1, silent = false) {
  if (!silent) loading.value = true;
  try {
    const params = { page: p, sort: filters.sort, archived: filters.archived ? 1 : 0 };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.referral_type) params.referral_type = filters.referral_type;
    const res = await http.get(base, { params });
    options.value = { types: res.data.filter_options?.types || [], statuses: res.data.filter_options?.statuses || [], type_labels: res.data.filter_options?.type_labels || {} };
    rows.value = res.data.data || [];
    page.value = res.data.current_page || 1;
    last.value = res.data.last_page || 1;
    // Keep an open detail view in sync too (e.g. its status or a pending
    // cancellation request changes while the sender is looking at it).
    if (selected.value && !editing.value) {
      const fresh = rows.value.find(x => x.id === selected.value.id);
      if (fresh) selected.value = fresh;
    }
  } catch (e) {
    if (!silent) rows.value = [];
  } finally {
    if (!silent) loading.value = false;
  }
}
// Keeps the list (and an open detail view) current without a manual
// reload - e.g. the status moves, or a cancellation request gets a
// decision, while the sender is on this page.
useAutoRefresh(() => load(page.value, true), 25000);

onMounted(async () => {
  await load();
  // Automatically open the modal if the URL has ?view=ID
  if (route.query.view) {
    const id = Number(route.query.view);
    const existing = rows.value.find(r => r.id === id);
    if (existing) {
      selected.value = existing;
      editing.value = false;
    } else {
      // If it's not on the current page, fetch it directly
      try {
        const res = await http.get(`/referrals/${id}`);
        selected.value = res.data;
        editing.value = false;
      } catch (e) {
        toast?.error('Could not load the referral details.');
      }
    }
  }
});
</script>

<style scoped>
.sr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px}
.sr-box{background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--sh-lg)}
.sr-k{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);margin-bottom:3px}
.sr-v{font-size:13.5px;color:var(--ink)}
</style>