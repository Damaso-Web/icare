<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Backup &amp; Recovery</h1>
      <p>Back up referral, appointment, and case data - and restore from the most recent verified backup if needed.</p>
    </div>

    <!-- Actions -->
    <div class="filter-bar" style="margin-bottom:20px">
      <button class="ibtn ibtn-p ibtn-sm" :disabled="running" @click="runBackup('data')">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        {{ running ? 'Running...' : 'Back Up Data Now' }}
      </button>
      <button class="ibtn ibtn-o ibtn-sm" :disabled="running" @click="runBackup('config')">
        Back Up Configuration Now
      </button>
      <button class="ibtn ibtn-o ibtn-sm" style="margin-left:auto" @click="fetchBackups">Refresh</button>
    </div>

    <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:12px 14px;font-size:12.5px;color:var(--amber);margin-bottom:16px">
      ⚠ A scheduled backup also runs automatically once a day. Only <strong>verified</strong> backups can be restored from. Restoring is destructive - it replaces current data with what's in the backup.
    </div>

    <!-- Restore Actions -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Restore Data</span></div>
        <div class="icard-body">
          <div v-if="!latestVerified.data" style="font-size:13px;color:var(--stone)">No verified data backup available yet.</div>
          <template v-else>
            <div style="font-size:12.5px;color:var(--stone);margin-bottom:10px">
              Most recent verified backup: <strong>{{ formatDate(latestVerified.data.created_at) }}</strong>
              ({{ Object.values(latestVerified.data.row_counts || {}).reduce((a,b) => a+b, 0) }} rows)
            </div>
            <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openRestore('data')">
              Restore Data
            </button>
          </template>
        </div>
      </div>
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Restore Configuration</span></div>
        <div class="icard-body">
          <div v-if="!latestVerified.config" style="font-size:13px;color:var(--stone)">No verified configuration backup available yet.</div>
          <template v-else>
            <div style="font-size:12.5px;color:var(--stone);margin-bottom:10px">
              Most recent verified backup: <strong>{{ formatDate(latestVerified.config.created_at) }}</strong>
              ({{ Object.values(latestVerified.config.row_counts || {}).reduce((a,b) => a+b, 0) }} rows)
            </div>
            <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openRestore('config')">
              Restore Configuration
            </button>
          </template>
        </div>
      </div>
    </div>

    <!-- History -->
    <div class="icard">
      <div class="icard-header"><span class="icard-title">Backup History</span></div>
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="backups.length === 0" class="empty-state">
        <h3>No backups yet</h3>
        <p>Run a backup above, or wait for the daily scheduled backup.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Trigger</th>
              <th>Status</th>
              <th>Rows</th>
              <th>Initiated By</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in backups" :key="b.id">
              <td style="font-size:12px">{{ formatDate(b.created_at) }}</td>
              <td>{{ toTitleCase(b.type) }}</td>
              <td>{{ toTitleCase(b.trigger) }}</td>
              <td>
                <span class="ibadge" :style="statusStyle(b)">{{ b.status === 'completed' ? (b.verified ? 'Verified' : 'Unverified') : toTitleCase(b.status) }}</span>
              </td>
              <td style="font-size:12px">{{ Object.values(b.row_counts || {}).reduce((a,c) => a+c, 0) }}</td>
              <td style="font-size:12px">{{ b.initiated_by?.name || 'Scheduled' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div v-if="restoreTarget" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:460px;padding:22px">
        <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Confirm Restore</div>
        <div style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:14px">
          This will permanently replace current {{ restoreTarget }} with the backup from {{ formatDate(restoreBackup?.created_at) }}. This cannot be undone.
          Type <strong style="font-family:var(--mono)">RESTORE-{{ restoreBackup?.id }}</strong> below to confirm.
        </div>
        <input v-model="confirmText" class="ifi" placeholder="Type the confirmation text..." style="margin-bottom:14px" />
        <div style="display:flex;gap:8px">
          <button
            class="ibtn"
            style="flex:1;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0"
            :disabled="restoring || confirmText !== ('RESTORE-' + restoreBackup?.id)"
            @click="doRestore"
          >
            {{ restoring ? 'Restoring...' : 'Yes, Restore' }}
          </button>
          <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="restoreTarget = null">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { backupAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const toast = inject('toast');

const loading = ref(true);
const running = ref(false);
const restoring = ref(false);
const backups = ref([]);
const latestVerified = ref({ data: null, config: null });

const restoreTarget = ref(null);
const restoreBackup = ref(null);
const confirmText = ref('');

function formatDate(date) {
  return date ? new Date(date).toLocaleString() : '-';
}

function statusStyle(b) {
  if (b.status === 'failed') return 'background:var(--red-lt);color:var(--red)';
  if (b.status === 'completed' && b.verified) return 'background:var(--mist);color:var(--moss)';
  if (b.status === 'completed') return 'background:var(--amber-lt);color:var(--amber)';
  return 'background:var(--cloud);color:var(--stone)';
}

async function fetchBackups() {
  loading.value = true;
  try {
    const res = await backupAPI.index();
    backups.value = res.data.data || res.data;
    latestVerified.value.data = backups.value.find(b => b.type === 'data' && b.status === 'completed' && b.verified) || null;
    latestVerified.value.config = backups.value.find(b => b.type === 'config' && b.status === 'completed' && b.verified) || null;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function runBackup(type) {
  running.value = true;
  try {
    await backupAPI.run(type);
    toast?.success('Backup completed.');
    await fetchBackups();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Backup failed.');
  } finally {
    running.value = false;
  }
}

function openRestore(type) {
  restoreTarget.value = type;
  restoreBackup.value = latestVerified.value[type];
  confirmText.value = '';
}

async function doRestore() {
  restoring.value = true;
  try {
    if (restoreTarget.value === 'data') {
      await backupAPI.restoreData(confirmText.value);
    } else {
      await backupAPI.restoreConfig(confirmText.value);
    }
    toast?.success('Restore completed.');
    restoreTarget.value = null;
    await fetchBackups();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Restore failed.');
  } finally {
    restoring.value = false;
  }
}

onMounted(() => fetchBackups());
</script>
