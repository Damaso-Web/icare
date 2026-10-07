<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Faculty</h1>
      <p v-if="isDean">All faculty of {{ auth.user?.college }}. Add or upload faculty and assign the Department Chair of each department.</p>
      <p v-else-if="isChair">Faculty of {{ auth.user?.department }}. Add or upload faculty under your department.</p>
      <p v-else>Faculty, Deans' faculty and Department Chairs.</p>
    </div>

    <div class="icard">
      <div class="filter-bar" style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input v-model="search" class="ifi" style="max-width:240px" maxlength="100" placeholder="Search name, email or employee ID..." @input="onSearch" />
        <select v-if="!isChair" v-model="deptFilter" class="fsm" @change="fetchItems">
          <option value="">All Departments</option>
          <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
        </select>
        <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
          <button class="ibtn ibtn-o" @click="openUpload">Upload Faculty</button>
          <button class="ibtn ibtn-p" @click="openForm(null)">+ Add Faculty</button>
        </div>
      </div>

      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!items.length" class="empty-state">
        <h3>No faculty yet</h3>
        <p>Add a faculty member or upload a masterlist (CSV / Excel).</p>
      </div>
      <div v-else class="ts">
        <table class="itable">
          <thead>
            <tr><th>Name</th><th>Employee ID</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="u in items" :key="u.id">
              <td>{{ u.last_name }}, {{ u.first_name }}</td>
              <td style="font-family:var(--mono);font-size:12px">{{ u.employee_id || '-' }}</td>
              <td style="font-size:12px">{{ u.email }}</td>
              <td style="font-size:12px">{{ u.department || '-' }}</td>
              <td>
                <span class="ibadge" :class="u.role === 'dept_chair' ? 'ibadge-scheduled' : 'ibadge-pending'">
                  {{ u.role === 'dept_chair' ? 'Dept Chair' : 'Faculty' }}
                </span>
              </td>
              <td><span class="ibadge" :class="u.is_active ? 'ibadge-completed' : 'ibadge-closed'">{{ u.is_active ? 'Active' : 'Inactive' }}</span></td>
              <td style="white-space:nowrap">
                <button class="ibtn ibtn-o ibtn-sm" @click="openForm(u)">Edit</button>
                <button v-if="isDean && u.role === 'faculty'" class="ibtn ibtn-o ibtn-sm" @click="openChair(u)">Make Dept Chair</button>
                <button v-if="isDean && u.role === 'dept_chair'" class="ibtn ibtn-o ibtn-sm" @click="askAction('removeChair', u, 'Remove ' + u.name + ' as Department Chair?')">Remove Chair</button>
                <button class="ibtn ibtn-o ibtn-sm" @click="askAction('reset', u, 'Reset the password of ' + u.name + '?')">Reset PW</button>
                <button class="ibtn ibtn-o ibtn-sm" @click="askAction('toggle', u, (u.is_active ? 'Deactivate ' : 'Activate ') + u.name + '?')">{{ u.is_active ? 'Deactivate' : 'Activate' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="lastPage > 1" style="padding:12px 18px;display:flex;gap:8px;justify-content:flex-end;align-items:center">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="page <= 1" @click="page--; fetchItems()">Prev</button>
          <span style="font-size:12px;color:var(--stone)">Page {{ page }} of {{ lastPage }}</span>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="page >= lastPage" @click="page++; fetchItems()">Next</button>
        </div>
      </div>
    </div>

    <!-- Add / Edit modal -->
    <div v-if="formOpen" class="fmodal" @click.self="formOpen = false">
      <div class="fbox">
        <div class="fhead"><span>{{ editing ? 'Edit Faculty' : 'Add Faculty' }}</span><button class="ibtn ibtn-g ibtn-sm" @click="formOpen = false">✕</button></div>
        <div class="fbody">
          <div class="grid2">
            <div><label class="ifl">First Name *</label><input v-model="form.first_name" class="ifi" /></div>
            <div><label class="ifl">Last Name *</label><input v-model="form.last_name" class="ifi" /></div>
            <div><label class="ifl">Middle Name</label><input v-model="form.middle_name" class="ifi" /></div>
            <div><label class="ifl">Suffix</label><input v-model="form.suffix" class="ifi" /></div>
            <div><label class="ifl">Email *</label><input v-model="form.email" type="email" class="ifi" /></div>
            <div><label class="ifl">Employee ID</label><input v-model="form.employee_id" class="ifi" /></div>
            <div><label class="ifl">Contact Number</label><input v-model="form.contact_number" class="ifi" maxlength="11" /></div>
            <div v-if="needsCollegePick && !editing">
              <label class="ifl">College *</label>
              <select v-model="form.college" class="ifse" @change="form.department = ''">
                <option value="">Select college</option>
                <option v-for="c in colleges" :key="c.id" :value="c.name">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Department *</label>
              <input v-if="isChair" :value="auth.user?.department" class="ifi" disabled />
              <select v-else v-model="form.department" class="ifse" :disabled="needsCollegePick && !editing && !form.college">
                <option value="">{{ needsCollegePick && !editing && !form.college ? 'Select a college first' : 'Select department' }}</option>
                <option v-for="d in formDepartments" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>
          </div>
          <div v-if="!needsCollegePick || editing" style="font-size:12px;color:var(--stone);margin-top:10px">College: <strong>{{ editing?.college || auth.user?.college || 'n/a' }}</strong></div>
          <div v-if="formError" style="color:var(--red);font-size:12px;margin-top:10px">{{ formError }}</div>
        </div>
        <div class="ffoot">
          <button class="ibtn ibtn-o" @click="formOpen = false">Cancel</button>
          <button class="ibtn ibtn-p" :disabled="saving" @click="saveForm">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </div>
    </div>

    <!-- Assign chair modal -->
    <div v-if="chairTarget" class="fmodal" @click.self="chairTarget = null">
      <div class="fbox" style="max-width:420px">
        <div class="fhead"><span>Assign Department Chair</span><button class="ibtn ibtn-g ibtn-sm" @click="chairTarget = null">✕</button></div>
        <div class="fbody">
          <p style="font-size:13px;margin-bottom:12px">Make <strong>{{ chairTarget.name }}</strong> the Department Chair of:</p>
          <select v-model="chairDept" class="ifse">
            <option value="">Select department</option>
            <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
          </select>
          <p style="font-size:11px;color:var(--stone);margin-top:8px">The current chair of that department (if any) goes back to regular faculty.</p>
        </div>
        <div class="ffoot">
          <button class="ibtn ibtn-o" @click="chairTarget = null">Cancel</button>
          <button class="ibtn ibtn-p" :disabled="!chairDept || saving" @click="confirmChair">Assign</button>
        </div>
      </div>
    </div>

    <!-- Confirm modal -->
    <div v-if="confirm" class="fmodal" @click.self="confirm = null">
      <div class="fbox" style="max-width:400px">
        <div class="fhead"><span>Please confirm</span></div>
        <div class="fbody"><p style="font-size:13px">{{ confirm.text }}</p></div>
        <div class="ffoot">
          <button class="ibtn ibtn-o" @click="confirm = null">Cancel</button>
          <button class="ibtn ibtn-p" :disabled="saving" @click="runConfirm">Yes, continue</button>
        </div>
      </div>
    </div>

    <!-- Temp password modal -->
    <div v-if="tempInfo" class="fmodal" @click.self="tempInfo = null">
      <div class="fbox" style="max-width:420px">
        <div class="fhead"><span>Temporary Password</span></div>
        <div class="fbody">
          <p style="font-size:13px;margin-bottom:8px">{{ tempInfo.name }} must change this at first login. Share it privately.</p>
          <div style="font-family:var(--mono);font-size:16px;background:var(--snow);padding:10px 12px;border-radius:8px">{{ tempInfo.password }}</div>
        </div>
        <div class="ffoot"><button class="ibtn ibtn-p" @click="tempInfo = null">Done</button></div>
      </div>
    </div>

    <!-- Upload modal -->
    <div v-if="uploadOpen" class="fmodal" @click.self="closeUpload">
      <div class="fbox" style="max-width:760px">
        <div class="fhead"><span>Upload Faculty</span><button class="ibtn ibtn-g ibtn-sm" @click="closeUpload">✕</button></div>
        <div class="fbody">
          <template v-if="!result">
            <p style="font-size:12px;color:var(--stone);margin-bottom:10px">
              CSV or Excel with columns: Last Name, First Name, Middle Name, Suffix, Email, Employee ID, Contact Number<span v-if="!isChair">, Department</span>.
              Everyone is added as <strong>Faculty</strong> of {{ isChair ? auth.user?.department : auth.user?.college }}.
            </p>
            <a href="/templates/faculty_masterlist_template.xlsx" download class="ibtn ibtn-o ibtn-sm" style="margin-bottom:10px">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Download Template
            </a>
            <div><input type="file" accept=".csv,.xlsx,.xls,.txt" @change="onFile" /></div>
            <div v-if="uploadError" style="color:var(--red);font-size:12px;margin-top:10px">{{ uploadError }}</div>
            <div v-if="preview.length" style="margin-top:14px;max-height:300px;overflow:auto">
              <table class="itable">
                <thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Result</th><th>Action</th></tr></thead>
                <tbody>
                  <tr v-for="(r, i) in preview" :key="i">
                    <td>{{ r.row }}</td>
                    <td>{{ r.name }}</td>
                    <td style="font-size:12px">{{ r.email }}</td>
                    <td style="font-size:12px">
                      <span v-if="!r.valid" style="color:var(--red)">{{ r.reasons.join(' ') }}</span>
                      <span v-else-if="r.is_duplicate">Already exists</span>
                      <span v-else>New</span>
                    </td>
                    <td>
                      <select v-if="r.valid" v-model="decisions[i]" class="fsm">
                        <option value="create" v-if="!r.is_duplicate">Add</option>
                        <option value="update" v-if="r.is_duplicate">Update</option>
                        <option value="skip">Skip</option>
                      </select>
                      <span v-else style="color:var(--fog);font-size:12px">Skipped</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
          <template v-else>
            <p style="font-size:13px"><strong>{{ result.created }}</strong> added, <strong>{{ result.updated }}</strong> updated, <strong>{{ result.skipped }}</strong> skipped.</p>
            <div v-if="result.errors?.length" style="color:var(--red);font-size:12px;margin-top:8px">
              <div v-for="e in result.errors" :key="e">{{ e }}</div>
            </div>
            <div v-if="result.passwords?.length" style="margin-top:12px">
              <p style="font-size:12px;color:var(--stone);margin-bottom:6px">Temporary passwords (shown once - copy them now):</p>
              <div style="max-height:220px;overflow:auto">
                <table class="itable">
                  <thead><tr><th>Name</th><th>Email</th><th>Temp Password</th></tr></thead>
                  <tbody><tr v-for="p in result.passwords" :key="p.email"><td>{{ p.name }}</td><td style="font-size:12px">{{ p.email }}</td><td style="font-family:var(--mono)">{{ p.temp_password }}</td></tr></tbody>
                </table>
              </div>
            </div>
          </template>
        </div>
        <div class="ffoot">
          <button class="ibtn ibtn-o" @click="closeUpload">{{ result ? 'Close' : 'Cancel' }}</button>
          <button v-if="!result && preview.length" class="ibtn ibtn-p" :disabled="saving" @click="confirmUpload">{{ saving ? 'Saving...' : 'Confirm Upload' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import axios from 'axios';
import { facultyAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';

const auth  = useAuthStore();
const toast = inject('toast', null);

const isDean  = computed(() => auth.user?.role === 'dean');
const isChair = computed(() => auth.user?.role === 'dept_chair');
// Admin-side roles have no college of their own, so adding faculty needs a College pick.
const needsCollegePick = computed(() => !isDean.value && !isChair.value);
const formDepartments = computed(() => {
  if (!needsCollegePick.value || editing.value) return departments.value;
  const c = colleges.value.find(x => x.name === form.value.college);
  return c ? allDepartments.value.filter(d => d.college_id === c.id).map(d => d.name) : [];
});

const API_ROOT = import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com';
const items = ref([]);
const loading = ref(true);
const search = ref('');
const deptFilter = ref('');
const page = ref(1);
const lastPage = ref(1);
const departments = ref([]);
const colleges = ref([]);
const allDepartments = ref([]);
let timer = null;

function onSearch() { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; fetchItems(); }, 300); }

async function fetchItems() {
  loading.value = true;
  try {
    const res = await facultyAPI.index({ search: search.value || undefined, department: deptFilter.value || undefined, page: page.value });
    items.value = res.data.data || [];
    lastPage.value = res.data.last_page || 1;
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Could not load faculty.');
  } finally {
    loading.value = false;
  }
}

// Departments of the signed-in user's own college (Dean picks from these).
async function fetchDepartments() {
  try {
    const headers = { headers: { Authorization: `Bearer ${localStorage.getItem('token')}`, Accept: 'application/json' } };
    const [cRes, dRes] = await Promise.all([
      axios.get(`${API_ROOT}/api/management/colleges`, headers),
      axios.get(`${API_ROOT}/api/management/departments`, headers),
    ]);
    colleges.value = cRes.data;
    allDepartments.value = dRes.data;
    const mine = cRes.data.find(c => c.name === auth.user?.college);
    // Dean/Chair see their own college's departments; admin-side roles have no
    // college of their own, so they get every department (and pick a college
    // first when adding).
    departments.value = mine
      ? dRes.data.filter(d => d.college_id === mine.id).map(d => d.name)
      : [...new Set(dRes.data.map(d => d.name))];
  } catch (e) { /* the list just stays empty */ }
}

// ---- add / edit ----
const formOpen = ref(false);
const editing = ref(null);
const saving = ref(false);
const formError = ref('');
const blank = () => ({ first_name: '', last_name: '', middle_name: '', suffix: '', email: '', employee_id: '', contact_number: '', department: '', college: '' });
const form = ref(blank());
const tempInfo = ref(null);

function openForm(u) {
  editing.value = u;
  formError.value = '';
  form.value = u ? { ...blank(), ...u } : blank();
  formOpen.value = true;
}

async function saveForm() {
  if (saving.value) return;
  formError.value = '';
  const f = form.value;
  if (!f.first_name || !f.last_name || !f.email) { formError.value = 'First name, last name and email are required.'; return; }
  if (needsCollegePick.value && !editing.value && !f.college) { formError.value = 'Please select a college.'; return; }
  if (!isChair.value && !f.department) { formError.value = 'Please select a department.'; return; }
  saving.value = true;
  try {
    const payload = { ...f };
    if (editing.value) {
      await facultyAPI.update(editing.value.id, payload);
      toast?.success('Faculty updated.');
    } else {
      const res = await facultyAPI.store(payload);
      tempInfo.value = { name: res.data.user.name, password: res.data.temp_password };
      toast?.success('Faculty added.');
    }
    formOpen.value = false;
    fetchItems();
  } catch (e) {
    const errs = e.response?.data?.errors;
    formError.value = errs ? Object.values(errs).flat()[0] : (e.response?.data?.message || 'Could not save.');
  } finally {
    saving.value = false;
  }
}

// ---- confirm actions (reset, toggle, remove chair) ----
const confirm = ref(null);
function askAction(kind, u, text) { confirm.value = { kind, u, text }; }
async function runConfirm() {
  const { kind, u } = confirm.value;
  saving.value = true;
  try {
    if (kind === 'toggle') { await facultyAPI.toggleActive(u.id); toast?.success('Status updated.'); }
    if (kind === 'removeChair') { await facultyAPI.removeChair(u.id); toast?.success('Department Chair removed.'); }
    if (kind === 'reset') {
      const res = await facultyAPI.resetPassword(u.id);
      tempInfo.value = { name: u.name, password: res.data.temp_password };
    }
    confirm.value = null;
    fetchItems();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Action failed.');
  } finally {
    saving.value = false;
  }
}

// ---- assign chair ----
const chairTarget = ref(null);
const chairDept = ref('');
function openChair(u) { chairTarget.value = u; chairDept.value = u.department || ''; }
async function confirmChair() {
  saving.value = true;
  try {
    await facultyAPI.assignChair(chairTarget.value.id, { department: chairDept.value });
    toast?.success('Department Chair assigned.');
    chairTarget.value = null;
    fetchItems();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Could not assign.');
  } finally {
    saving.value = false;
  }
}

// ---- upload ----
const uploadOpen = ref(false);
const preview = ref([]);
const decisions = ref({});
const uploadToken = ref('');
const uploadError = ref('');
const result = ref(null);

function openUpload() { uploadOpen.value = true; preview.value = []; decisions.value = {}; result.value = null; uploadError.value = ''; }
function closeUpload() { uploadOpen.value = false; if (result.value) fetchItems(); }

async function onFile(e) {
  const file = e.target.files?.[0];
  if (!file) return;
  uploadError.value = '';
  const fd = new FormData();
  fd.append('file', file);
  try {
    const res = await facultyAPI.importPreview(fd);
    preview.value = res.data.preview || [];
    uploadToken.value = res.data.token;
    const d = {};
    preview.value.forEach((r, i) => { d[i] = !r.valid ? 'skip' : (r.is_duplicate ? 'skip' : 'create'); });
    decisions.value = d;
    if (!preview.value.length) uploadError.value = 'No rows found in that file.';
  } catch (err) {
    uploadError.value = err.response?.data?.message || 'Could not read that file.';
  }
}

async function confirmUpload() {
  saving.value = true;
  try {
    const res = await facultyAPI.importConfirm({ token: uploadToken.value, decisions: decisions.value });
    result.value = res.data;
  } catch (err) {
    uploadError.value = err.response?.data?.message || 'Upload failed.';
  } finally {
    saving.value = false;
  }
}

onMounted(() => { fetchItems(); fetchDepartments(); });
</script>

<style scoped>
.fmodal { position: fixed; inset: 0; background: rgba(0,0,0,.42); z-index: 60; display: flex; align-items: center; justify-content: center; padding: 20px; }
.fbox { background: #fff; border-radius: var(--r-lg); width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: var(--sh-lg); }
.fhead { padding: 18px 22px; border-bottom: 1px solid var(--cloud); display: flex; align-items: center; justify-content: space-between; font-size: 15px; font-weight: 600; color: var(--ink); }
.fbody { padding: 20px 22px; }
.ffoot { padding: 14px 22px; border-top: 1px solid var(--cloud); display: flex; justify-content: flex-end; gap: 8px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) { .grid2 { grid-template-columns: minmax(0, 1fr); } .ffoot { flex-wrap: wrap; } .ffoot .ibtn { flex: 1 1 auto; justify-content: center; } }
</style>