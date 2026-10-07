<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Faculty Profile</h1>
      <p v-if="isDean">View and manage faculty of {{ auth.user?.college }}. Assign the Department Chair of each department.</p>
      <p v-else-if="isChair">View and manage faculty of {{ auth.user?.department }}.</p>
      <p v-else>View and manage faculty member accounts.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="search"
          type="text"
          class="sin"
          maxlength="15"
          placeholder="Search name, email, or employee ID..."
          style="width:220px"
          @keypress="blockSpecialKeypress"
          @input="onSearch"
        />
      </div>
      <select v-model="statusFilter" class="fsm" @change="page = 1; fetchItems()">
        <option value="">Sort by Status</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
      <select v-if="!isChair" v-model="deptFilter" class="fsm" @change="page = 1; fetchItems()">
        <option value="">All Departments</option>
        <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
      </select>
      <select v-model="sortOption" class="fsm" @change="page = 1; fetchItems()">
        <option value="created_at:desc">Newest First</option>
        <option value="created_at:asc">Oldest First</option>
        <option value="employee_id:asc">Employee ID: Ascending</option>
        <option value="employee_id:desc">Employee ID: Descending</option>
        <option value="last_name:asc">Name: A-Z</option>
        <option value="last_name:desc">Name: Z-A</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
      <button class="ibtn ibtn-o ibtn-sm" @click="openUpload">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload Faculty
      </button>
      <button class="ibtn ibtn-p ibtn-sm" style="margin-left:auto" @click="openForm(null)">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Faculty
      </button>
    </div>

    <!-- Faculty Table -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!items.length" class="empty-state">
        <h3>No faculty found</h3>
        <p>Try adjusting your search or filters, or add a faculty member / upload a masterlist (CSV / Excel).</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Employee ID</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in items" :key="u.id" :style="!u.is_active ? 'opacity:0.55;background:var(--snow)' : ''">
              <td style="font-family:var(--mono);font-size:13px;font-weight:600;cursor:pointer" @click="openView(u)">{{ u.employee_id || '-' }}</td>
              <td>
                <span class="ibadge" :style="u.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--ink);border:1px solid var(--fog)'">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openView(u)">View</button>
                  <button
                    class="ibtn ibtn-sm"
                    :style="u.is_active ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : 'background:var(--mint);color:var(--forest);border:1.5px solid var(--moss)'"
                    @click="askAction('toggle', u, (u.is_active ? 'Deactivate ' : 'Activate ') + u.name + '?')"
                  >
                    {{ u.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="lastPage > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">Showing {{ pageFrom }}-{{ pageTo }} of {{ total }}</span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="page <= 1" @click="page--; fetchItems()">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="page >= lastPage" @click="page++; fetchItems()">Next</button>
        </div>
      </div>
    </div>

    <!-- View Faculty Profile Modal -->
    <div v-if="viewTarget" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="viewTarget = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:22px;border-radius:var(--r-lg) var(--r-lg) 0 0;text-align:center">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 10px">
            {{ initials(viewTarget.first_name, viewTarget.last_name) }}
          </div>
          <div style="font-size:15px;font-weight:600;color:#fff">{{ viewTarget.last_name }}, {{ viewTarget.first_name }} {{ viewTarget.middle_name }} {{ viewTarget.suffix }}</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px">{{ viewTarget.email }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:12px">
          <div>
            <div class="vlabel">Employee ID</div>
            <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ viewTarget.employee_id || '-' }}</div>
          </div>
          <div>
            <div class="vlabel">Role</div>
            <span class="ibadge" :style="viewTarget.role === 'dept_chair' ? 'background:var(--amber-lt);color:var(--amber)' : 'background:var(--blue-lt);color:var(--blue)'">
              {{ viewTarget.role === 'dept_chair' ? 'Dept Chair' : 'Faculty' }}
            </span>
          </div>
          <div v-if="viewTarget.college">
            <div class="vlabel">College</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewTarget.college }}</div>
          </div>
          <div v-if="viewTarget.department">
            <div class="vlabel">Department</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewTarget.department }}</div>
          </div>
          <div v-if="viewTarget.contact_number">
            <div class="vlabel">Contact Number</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewTarget.contact_number }}</div>
          </div>
          <div>
            <div class="vlabel">Status</div>
            <span class="ibadge" :style="viewTarget.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--stone)'">
              {{ viewTarget.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div>
            <div class="vlabel">Last Login</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewTarget.last_login_at ? new Date(viewTarget.last_login_at).toLocaleDateString() : 'Never' }}</div>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px">
            <button class="ibtn ibtn-p" :disabled="!viewTarget.is_active" :style="{ flex:1, justifyContent:'center', opacity: viewTarget.is_active ? 1 : .5 }" @click="editFromView">Edit</button>
            <button class="ibtn ibtn-g" style="flex:1;justify-content:center" @click="viewTarget = null">Close</button>
          </div>
          <button v-if="isDean && viewTarget.role === 'faculty'" class="ibtn ibtn-o ibtn-sm" style="width:100%;justify-content:center" :disabled="!viewTarget.is_active" @click="chairFromView">Make Dept Chair</button>
          <button v-if="isDean && viewTarget.role === 'dept_chair'" class="ibtn ibtn-o ibtn-sm" style="width:100%;justify-content:center" @click="askFromView('removeChair', 'Remove ' + viewTarget.name + ' as Department Chair?')">Remove Chair</button>
          <button
            class="ibtn ibtn-sm"
            :disabled="!viewTarget.is_active"
            :style="{ width:'100%', justifyContent:'center', background:'var(--amber-lt)', color:'var(--amber)', border:'1.5px solid var(--amber)', opacity: viewTarget.is_active ? 1 : .5 }"
            @click="askFromView('reset', 'Reset the password of ' + viewTarget.name + '?')"
          >Reset Password</button>
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
            <div><label class="ifl">Suffix</label>
              <select v-model="form.suffix" class="ifse">
                <option value="">None</option>
                <option v-if="form.suffix && !SUFFIX_OPTIONS.includes(form.suffix)" :value="form.suffix">{{ form.suffix }}</option>
                <option v-for="x in SUFFIX_OPTIONS" :key="x" :value="x">{{ x }}</option>
              </select></div>
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
        <div class="fhead"><span>Upload Faculty</span></div>
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
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

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

const SUFFIX_OPTIONS = ['Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V'];
const API_ROOT = import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com';
const items = ref([]);
const loading = ref(true);
const search = ref('');
const deptFilter = ref('');
const statusFilter = ref('');
const sortOption = ref('created_at:desc');
const total = ref(0);
const pageFrom = ref(0);
const pageTo = ref(0);
const viewTarget = ref(null);
const page = ref(1);
const lastPage = ref(1);
const departments = ref([]);
const colleges = ref([]);
const allDepartments = ref([]);
let timer = null;

function onSearch() { search.value = safeSearchInput(search.value); clearTimeout(timer); timer = setTimeout(() => { page.value = 1; fetchItems(); }, 300); }

async function fetchItems() {
  loading.value = true;
  try {
    const [sortBy, sortDir] = sortOption.value.split(':');
    const res = await facultyAPI.index({
      search: search.value || undefined,
      department: deptFilter.value || undefined,
      is_active: statusFilter.value === '' ? undefined : statusFilter.value,
      sort_by: sortBy, sort_dir: sortDir,
      page: page.value,
    });
    items.value = res.data.data || [];
    lastPage.value = res.data.last_page || 1;
    total.value = res.data.total || 0;
    pageFrom.value = res.data.from || 0;
    pageTo.value = res.data.to || 0;
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

function resetFilters() {
  search.value = ''; statusFilter.value = ''; deptFilter.value = ''; sortOption.value = 'created_at:desc';
  page.value = 1; fetchItems();
}

function initials(first, last) { return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?'; }
function openView(u) { viewTarget.value = u; }
function editFromView() { const u = viewTarget.value; viewTarget.value = null; openForm(u); }
function chairFromView() { const u = viewTarget.value; viewTarget.value = null; openChair(u); }
function askFromView(kind, text) { const u = viewTarget.value; viewTarget.value = null; askAction(kind, u, text); }

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
.vlabel { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); margin-bottom: 3px; }
.fmodal { position: fixed; inset: 0; background: rgba(0,0,0,.42); z-index: 60; display: flex; align-items: center; justify-content: center; padding: 20px; }
.fbox { background: #fff; border-radius: var(--r-lg); width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: var(--sh-lg); }
.fhead { padding: 18px 22px; border-bottom: 1px solid var(--cloud); display: flex; align-items: center; justify-content: space-between; font-size: 15px; font-weight: 600; color: var(--ink); }
.fbody { padding: 20px 22px; }
.ffoot { padding: 14px 22px; border-top: 1px solid var(--cloud); display: flex; justify-content: flex-end; gap: 8px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 560px) { .grid2 { grid-template-columns: minmax(0, 1fr); } .ffoot { flex-wrap: wrap; } .ffoot .ibtn { flex: 1 1 auto; justify-content: center; } }
</style>