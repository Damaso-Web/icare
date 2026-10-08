<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Audit Trail</h1>
      <p>Complete log of all user actions and system events for accountability.</p>
    </div>

    <!-- Filter Bar: one line on desktop (items shrink instead of wrapping); stacks on phones -->
    <div class="filter-bar audit-bar">
      <div class="sw ab-search">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          placeholder="Search name or description..."
          maxlength="100"
          @input="onSearchInput"
        />
      </div>
      <select v-model="filters.user_id" class="fsm ab-select" @change="fetchLogs()">
        <option value="">All Users</option>
        <option v-for="u in userList" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <select v-model="filters.action" class="fsm ab-select" @change="fetchLogs()">
        <option value="">All Actions</option>
        <option v-for="a in actionList" :key="a" :value="a">{{ toTitleCase(a) }}</option>
      </select>
      <!-- From - To in one box -->
      <div class="ab-range" title="Date range">
        <input v-model="filters.date_from" type="date" title="From date" :max="filters.date_to || undefined" @change="fetchLogs()" />
        <span>–</span>
        <input v-model="filters.date_to" type="date" title="To date" :min="filters.date_from || undefined" @change="fetchLogs()" />
      </div>
      <button class="ibtn ibtn-g ibtn-sm ab-btn" @click="resetFilters">Reset</button>
      <!-- One "Export as" button; picking a format downloads it right away -->
      <div ref="exportMenuEl" class="ab-export">
        <button class="ibtn ibtn-o ibtn-sm ab-btn" :disabled="exporting" @click="exportMenuOpen = !exportMenuOpen">
          <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          {{ exporting ? `Exporting ${exportFormat === 'pdf' ? 'PDF' : 'Excel'}...` : 'Export as' }}
          <svg v-if="!exporting" viewBox="0 0 24 24" style="width:12px;height:12px"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div v-if="exportMenuOpen" class="export-menu">
          <button type="button" @click="exportAs('pdf')"><strong>PDF</strong><small>Ready to print</small></button>
          <button type="button" @click="exportAs('excel')"><strong>Excel</strong><small>Editable spreadsheet</small></button>
        </div>
      </div>
    </div>

    <!-- Audit Log Table -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="logs.length === 0" class="empty-state">
        <h3>No audit logs found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Description</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="log in logs"
              :key="log.id"
              style="cursor:pointer"
              @click="openLog(log)"
            >
              <td style="font-family:var(--mono);font-size:11px;white-space:nowrap">{{ formatTimestamp(log.created_at) }}</td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="iav" style="width:24px;height:24px;font-size:9px">{{ initials(log.user_name) }}</div>
                  <div style="font-size:13px;font-weight:500">{{ log.user_name }}</div>
                </div>
              </td>
              <td>
                <span class="ibadge" style="font-size:10px" :style="roleStyle(log.user_role)">{{ roleLabel(log.user_role) }}</span>
              </td>
              <td>
                <span class="ibadge" :style="actionStyle(log.action)">{{ toTitleCase(log.action) }}</span>
              </td>
              <td style="font-size:12px;color:var(--slate);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ log.description }}</td>
              <td style="font-family:var(--mono);font-size:11px;color:var(--fog)">{{ log.ip_address }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">
          Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
        </span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- Log Detail Drawer -->
    <div v-if="selectedLog" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60" @click.self="selectedLog = null">
      <div style="position:fixed;top:0;right:0;width:min(480px,100vw);height:100vh;background:#fff;overflow-y:auto;box-shadow:-6px 0 40px rgba(0,0,0,.18)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Log Entry #{{ selectedLog.id }}</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="selectedLog = null">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Timestamp</div>
            <div style="font-family:var(--mono);font-size:13px">{{ formatTimestamp(selectedLog.created_at) }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">User</div>
            <div style="font-size:13px;font-weight:500">{{ selectedLog.user_name }}</div>
            <div style="font-size:11px;color:var(--stone)">{{ roleLabel(selectedLog.user_role) }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Action</div>
            <span class="ibadge" :style="actionStyle(selectedLog.action)">{{ toTitleCase(selectedLog.action) }}</span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Description</div>
            <div style="font-size:13px;color:var(--ink);line-height:1.6">{{ selectedLog.description }}</div>
          </div>
          <div v-if="selectedLog.model_type">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Affected Record</div>
            <div style="font-size:13px;color:var(--ink)">{{ selectedLog.model_type }} #{{ selectedLog.model_id }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">IP Address</div>
            <div style="font-family:var(--mono);font-size:13px">{{ selectedLog.ip_address }}</div>
          </div>
          <div v-if="selectedLog.old_values">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">Before</div>
            <pre style="background:var(--snow);border-radius:var(--r-sm);padding:10px 12px;font-size:11px;font-family:var(--mono);overflow-x:auto;border:1px solid var(--cloud)">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
          </div>
          <div v-if="selectedLog.new_values">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">After</div>
            <pre style="background:var(--mist);border-radius:var(--r-sm);padding:10px 12px;font-size:11px;font-family:var(--mono);overflow-x:auto;border:1px solid var(--mint)">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, inject } from 'vue';
import axios from 'axios';
import { auditAPI } from '../../api/index';
import { toTitleCase, localDateStr } from '../../utils/validators';

const toast = inject('toast');
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

const exportFormat = ref('pdf');
const exporting    = ref(false);
const exportMenuOpen = ref(false);
const exportMenuEl   = ref(null);

function exportAs(format) {
  exportFormat.value = format;
  exportMenuOpen.value = false;
  exportLogs();
}

// Clicking anywhere outside the menu closes it.
function closeExportMenu(e) {
  if (exportMenuOpen.value && exportMenuEl.value && !exportMenuEl.value.contains(e.target)) {
    exportMenuOpen.value = false;
  }
}
onMounted(() => document.addEventListener('click', closeExportMenu));
onBeforeUnmount(() => document.removeEventListener('click', closeExportMenu));

async function exportLogs() {
  exporting.value = true;
  try {
    const res = await axios.get(`${API_BASE}/audit-logs/export/${exportFormat.value}`, {
      ...authHeaders(),
      params: { ...filters.value },
      responseType: 'blob',
    });
    const ext = exportFormat.value === 'pdf' ? 'pdf' : 'xlsx';
    const blobUrl = window.URL.createObjectURL(new Blob([res.data]));
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = `iCARE-Audit-Trail-${localDateStr()}.${ext}`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(blobUrl);
  } catch (e) {
    toast?.error('Failed to export audit log.');
  } finally {
    exporting.value = false;
  }
}

const loading     = ref(true);
const logs        = ref([]);
const pagination  = ref({});
const selectedLog = ref(null);
const userList    = ref([]);
const actionList  = ref([]);
const emptyFilters = () => ({ search: '', user_id: '', action: '', date_from: '', date_to: '' });
const filters     = ref(emptyFilters());

// Only the latest request may update the table - a slow earlier search must
// not overwrite the results of a newer one.
let requestSeq = 0;

async function fetchLogs(page = 1) {
  const seq = ++requestSeq;
  loading.value = true;
  try {
    const res = await auditAPI.index({ ...filters.value, page });
    if (seq !== requestSeq) return;
    logs.value       = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
}

let searchTimer = null;
function onSearchInput() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => fetchLogs(), 400);
}

// Dropdown choices come from the log itself, so every recorded action and
// every user with activity can be filtered on. The core account actions are
// always offered, even before the first such entry exists.
const BASE_ACTIONS = ['login', 'login_failed', 'logout', 'password_change', 'profile_updated', 'created', 'updated', 'deleted', 'viewed'];
async function fetchFilterOptions() {
  try {
    const res = await auditAPI.filterOptions();
    userList.value   = res.data.users || [];
    actionList.value = [...new Set([...BASE_ACTIONS, ...(res.data.actions || [])])].sort();
  } catch (e) {
    console.error(e);
  }
}

function openLog(log) { selectedLog.value = log; }

// The API sends timestamps in UTC; show them as local date and time.
function formatTimestamp(value) {
  if (!value) return '-';
  const d = new Date(value);
  if (isNaN(d)) return value;
  return `${localDateStr(d)} ${d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
}

function changePage(page) { fetchLogs(page); }

function resetFilters() {
  clearTimeout(searchTimer);
  filters.value = emptyFilters();
  fetchLogs();
}

function actionStyle(action) {
  const styles = {
    login:          'background:var(--mist);color:var(--moss)',
    login_failed:   'background:var(--red-lt);color:var(--red)',
    logout:       'background:var(--cloud);color:var(--stone)',
    created:        'background:var(--blue-lt);color:var(--blue)',
    updated:        'background:var(--amber-lt);color:var(--amber)',
    deleted:        'background:var(--red-lt);color:var(--red)',
    viewed:         'background:var(--cloud);color:var(--stone)',
    acknowledged:   'background:var(--mist);color:var(--moss)',
    assigned:       'background:var(--blue-lt);color:var(--blue)',
    status_updated: 'background:var(--amber-lt);color:var(--amber)',
    closed:         'background:var(--cloud);color:var(--stone)',
    exported:       'background:var(--purple-lt);color:var(--purple)',
    report_sent:    'background:var(--purple-lt);color:var(--purple)',
  };
  return styles[action] || 'background:var(--cloud);color:var(--stone)';
}

function roleLabel(role) {
  const labels = {
    admin:          'Admin / GCU Head',
    gcu_staff:      'GCU Staff',
    sdu_head:       'SDU Head',
    tmdu_staff:     'TMDU Staff',
    faculty:        'Faculty',
    dean_secretary: "Dean's Secretary",
    system_admin:   'System Admin',
    student:        'Student',
  };
  return labels[role] || role;
}

function roleStyle(role) {
  const styles = {
    admin:          'background:#1a1a2e;color:#fff',
    gcu_staff:      'background:var(--mist);color:var(--moss)',
    sdu_head:       'background:var(--amber-lt);color:var(--amber)',
    tmdu_staff:     'background:var(--purple-lt);color:var(--purple)',
    faculty:        'background:var(--blue-lt);color:var(--blue)',
    dean_secretary: 'background:var(--cloud);color:var(--stone)',
  };
  return styles[role] || '';
}

function initials(name) {
  return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?';
}

onMounted(() => {
  fetchLogs();
  fetchFilterOptions();
});
</script>
<style scoped>
/* Desktop: everything stays on one line - the search box takes the spare
   room and gives it up first, the rest shrink a little before anything wraps. */
.audit-bar { flex-wrap: nowrap; }
.ab-search { flex: 1 1 200px; min-width: 130px; }
.ab-search .sin { width: 100%; }
.ab-select { flex: 0 1 140px; min-width: 100px; }
.ab-range {
  flex: 0 1 auto; min-width: 0; display: flex; align-items: center; gap: 4px;
  padding: 0 8px; height: 36px; background: #fff;
  border: 1.5px solid var(--silver); border-radius: var(--r-sm);
}
.ab-range:focus-within { border-color: var(--moss); }
.ab-range input {
  border: none; outline: none; background: none; min-width: 0; width: 118px;
  font-family: var(--font); font-size: 12.5px; color: var(--ink);
}
.ab-range span { color: var(--fog); font-size: 12px; }
.ab-btn { flex: none; white-space: nowrap; }
.ab-export { position: relative; flex: none; margin-left: auto; }

.export-menu {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 30;
  min-width: 180px; padding: 5px;
  background: #fff; border: 1px solid var(--cloud); border-radius: var(--r-sm);
  box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.export-menu button {
  display: block; width: 100%; padding: 7px 10px; border: none; background: none;
  border-radius: 6px; font-family: var(--font); text-align: left; cursor: pointer;
}
.export-menu button:hover { background: var(--mist); }
.export-menu strong { display: block; font-size: 12.5px; color: var(--ink); font-weight: 600; }
.export-menu small { display: block; font-size: 11px; color: var(--stone); }

/* Phones keep the existing stacked layout. */
@media (max-width: 860px) {
  .audit-bar { flex-wrap: wrap; }
  .ab-search, .ab-select, .ab-range { flex: 1 1 100%; }
  .ab-range input { flex: 1; width: auto; }
  .ab-export { margin-left: 0; flex: 1 1 auto; }
  .ab-export > .ibtn { width: 100%; justify-content: center; }
}
</style>
