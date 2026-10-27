<!--
  Version history of one document header: lists every saved version, lets the
  admin set an older version as active (with a confirmation), archive it (reason
  required) or delete it (reason required; blocked once documents used it).
-->
<template>
  <div class="dhv-back" @click.self="$emit('close')">
    <div class="dhv-card" role="dialog" aria-modal="true">
      <div class="dhv-head">
        <span>Version History: {{ label }} ({{ code }})</span>
        <button class="ibtn ibtn-o ibtn-sm" @click="$emit('close')">Close</button>
      </div>
      <div class="dhv-body">
        <div v-if="loading" style="color:var(--fog);font-size:13px">Loading...</div>
        <div v-else-if="error" class="dhv-err">{{ error }}</div>
        <div v-else style="overflow-x:auto">
          <table class="itbl" style="width:100%">
            <thead><tr><th>Version</th><th>Rev. No.</th><th>Effectivity</th><th>Ctrl No.</th><th>Status</th><th>Used in</th><th>Actions</th></tr></thead>
            <tbody>
              <tr v-for="v in versions" :key="v.id" :style="v.status === 'archived' ? 'opacity:.6' : ''">
                <td>v{{ v.version_no }}</td>
                <td>{{ v.revision_no }}</td>
                <td>{{ (v.effectivity_date || '').slice(0, 10) }}</td>
                <td>{{ v.ctrl_no || '-' }}</td>
                <td>
                  <span v-if="v.is_active" class="dhv-pill dhv-on">Active</span>
                  <span v-else-if="v.status === 'archived'" class="dhv-pill">Archived</span>
                  <span v-else class="dhv-pill">Inactive</span>
                </td>
                <td>{{ v.used_count }} {{ v.used_count === 1 ? 'document' : 'documents' }}</td>
                <td style="white-space:nowrap">
                  <button v-if="!v.is_active && v.status !== 'archived'" class="ibtn ibtn-o ibtn-sm" @click="askActivate(v)">Set as Active</button>
                  <button v-if="!v.is_active && v.status !== 'archived'" class="ibtn ibtn-o ibtn-sm" @click="askStatus(v, 'archive')">Archive</button>
                  <button v-if="!v.is_active" class="ibtn ibtn-o ibtn-sm" style="color:var(--red)" @click="askStatus(v, 'delete')">Delete</button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="actionError" class="dhv-err" style="margin-top:10px">{{ actionError }}</div>
        </div>
      </div>
    </div>

    <!-- Set as Active confirmation -->
    <div v-if="activating" class="dhv-back" style="z-index:90" @click.self="activating = null">
      <div class="dhv-card" style="max-width:420px">
        <div class="dhv-head">Set as active header?</div>
        <div class="dhv-body">
          <div style="font-size:13px">Version <strong>v{{ activating.version_no }}</strong> will become the active header and replace the current one for new documents. Documents already generated are not changed.</div>
          <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
            <button class="ibtn ibtn-p" :disabled="busy" @click="doActivate">{{ busy ? 'Please wait...' : 'Yes, Set as Active' }}</button>
            <button class="ibtn ibtn-o" :disabled="busy" @click="activating = null">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <ReasonDialog
      :open="!!ask"
      :title="ask?.mode === 'delete' ? 'Delete header version?' : 'Archive header version?'"
      :message="askMessage"
      :reasons="askReasons"
      :reason-label="ask?.mode === 'delete' ? 'Reason for Deletion' : 'Reason'"
      :confirm-text="ask?.mode === 'delete' ? 'Yes, Delete' : 'Yes, Archive'"
      :blocked-message="askBlocked"
      :busy="busy"
      :error="askError"
      @confirm="doStatus"
      @cancel="ask = null"
    />
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import ReasonDialog from './ReasonDialog.vue';
import { MANAGEMENT_DELETE_REASONS, MANAGEMENT_STATUS_REASONS } from '../constants/accountReasons';

const props = defineProps({ code: String, label: String });
const emit = defineEmits(['close', 'changed']);
const toast = inject('toast', null);

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const headers = () => ({ headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } });
const base = () => `${API_BASE}/document-settings/${props.code}`;

const versions = ref([]);
const loading = ref(true);
const error = ref('');
const actionError = ref('');
const busy = ref(false);
const activating = ref(null);
const ask = ref(null);
const askError = ref('');

async function load() {
  loading.value = true; error.value = '';
  try {
    versions.value = (await axios.get(`${base()}/versions`, headers())).data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load the version history.';
  } finally { loading.value = false; }
}

function askActivate(v) { actionError.value = ''; activating.value = v; }
async function doActivate() {
  busy.value = true;
  try {
    await axios.post(`${base()}/versions/${activating.value.id}/activate`, {}, headers());
    toast?.success?.('Header version set as active.');
    activating.value = null;
    await load(); emit('changed');
  } catch (e) {
    actionError.value = e.response?.data?.message || 'Could not set this version as active.';
    activating.value = null;
  } finally { busy.value = false; }
}

const askReasons = computed(() => ask.value
  ? (ask.value.mode === 'delete' ? MANAGEMENT_DELETE_REASONS['document header'] : MANAGEMENT_STATUS_REASONS['document header'].archived)
  : []);
const askBlocked = computed(() => {
  const a = ask.value; if (!a) return '';
  if (a.mode === 'delete' && a.v.used_count > 0) {
    return `This header was used in ${a.v.used_count} generated ${a.v.used_count === 1 ? 'document' : 'documents'}, so it cannot be deleted. Archive it instead.`;
  }
  return '';
});
const askMessage = computed(() => {
  const a = ask.value; if (!a) return '';
  return a.mode === 'delete'
    ? `Delete <strong>v${a.v.version_no}</strong> of ${props.code}? This cannot be undone.`
    : `Archive <strong>v${a.v.version_no}</strong> of ${props.code}? It stays on record but cannot be set as active.`;
});
function askStatus(v, mode) { actionError.value = ''; askError.value = ''; ask.value = { v, mode }; }
async function doStatus({ reason, note }) {
  const { v, mode } = ask.value;
  busy.value = true; askError.value = '';
  try {
    if (mode === 'delete') await axios.delete(`${base()}/versions/${v.id}`, { ...headers(), data: { reason, note } });
    else await axios.post(`${base()}/versions/${v.id}/archive`, { reason, note }, headers());
    toast?.success?.(mode === 'delete' ? 'Header version deleted.' : 'Header version archived.');
    ask.value = null;
    await load();
  } catch (e) {
    askError.value = e.response?.data?.message || 'Action failed.';
  } finally { busy.value = false; }
}

onMounted(load);
</script>

<style scoped>
.dhv-back { position: fixed; inset: 0; background: rgba(0,0,0,.42); z-index: 80; display: flex; align-items: center; justify-content: center; padding: 20px; }
.dhv-card { background: #fff; border-radius: var(--r); width: 100%; max-width: 820px; max-height: 90vh; display: flex; flex-direction: column; }
.dhv-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 14px 18px; font-weight: 700; font-size: 14px; border-bottom: 1px solid #eee; }
.dhv-body { padding: 16px 18px; overflow: auto; }
.dhv-err { background: var(--red-lt); border: 1px solid #f5c0c0; color: var(--red); padding: 8px 12px; border-radius: var(--r-sm); font-size: 12px; }
.dhv-pill { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: #eee; color: #555; }
.dhv-on { background: #e3f4e6; color: #1d7a34; font-weight: 600; }
</style>