<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Audit Trail</h1>
      <p>Complete log of all user actions and system events for accountability.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="filters.search" type="text" class="sin" placeholder="Search description or user..." style="width:220px" @input="fetchLogs" />
      </div>
      <select v-model="filters.action" class="fsm" @change="fetchLogs">
        <option value="">All Actions</option>
        <option value="login">Login</option>
        <option value="logout">Logout</option>
        <option value="created">Created</option>
        <option value="updated">Updated</option>
        <option value="deleted">Deleted</option>
        <option value="viewed">Viewed</option>
        <option value="acknowledged">Acknowledged</option>
        <option value="assigned">Assigned</option>
        <option value="status_updated">Status Updated</option>
        <option value="closed">Closed</option>
        <option value="exported">Exported</option>
      </select>
      <input v-model="filters.date_from" type="date" class="ifi" style="width:160px" @change="fetchLogs" />
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <button class="ibtn ibtn-o ibtn-sm" style="margin-left:auto">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </button>
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
              <td style="font-family:var(--mono);font-size:11px;white-space:nowrap">{{ log.created_at }}</td>
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
                <span class="ibadge" :style="actionStyle(log.action)">{{ log.action?.replace(/_/g,' ') }}</span>
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
          Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
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
            <div style="font-family:var(--mono);font-size:13px">{{ selectedLog.created_at }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">User</div>
            <div style="font-size:13px;font-weight:500">{{ selectedLog.user_name }}</div>
            <div style="font-size:11px;color:var(--stone)">{{ roleLabel(selectedLog.user_role) }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Action</div>
            <span class="ibadge" :style="actionStyle(selectedLog.action)">{{ selectedLog.action?.replace(/_/g,' ') }}</span>
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
import { ref, onMounted } from 'vue';
import { auditAPI } from '../../api/index';

const loading    = ref(true);
const logs       = ref([]);
const pagination = ref({});
const selectedLog = ref(null);
const filters    = ref({ search: '', action: '', date_from: '' });

async function fetchLogs(page = 1) {
  loading.value = true;
  try {
    const res = await auditAPI.index({ ...filters.value, page });
    logs.value       = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openLog(log) { selectedLog.value = log; }

function changePage(page) { fetchLogs(page); }

function resetFilters() {
  filters.value = { search: '', action: '', date_from: '' };
  fetchLogs();
}

function actionStyle(action) {
  const styles = {
    login:          'background:var(--mist);color:var(--moss)',
    logout:         'background:var(--cloud);color:var(--stone)',
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
    admin: 'Admin', gcu_staff: 'GCU Staff', sdu_head: 'SDU Head',
    tmdu_staff: 'TMDU Staff', faculty: 'Faculty', dean_secretary: "Dean's Secretary",
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

onMounted(() => fetchLogs());
</script>