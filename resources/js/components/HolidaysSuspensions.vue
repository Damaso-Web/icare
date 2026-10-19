<!--
  School Calendar > Holidays and Suspensions. Active entries block their date for
  appointments. Edit / Archive / Reactivate / Delete follow the Management rules:
  reasons are required, archived entries are read-only, and an entry that is linked
  to records or whose date has passed cannot be deleted (Archive instead).
-->
<template>
  <div class="icard">
    <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between">
      <span class="icard-title">Holidays and Suspensions</span>
      <button v-if="canManage" class="ibtn ibtn-p ibtn-sm" @click="openForm()">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Entry
      </button>
    </div>
    <div style="padding:12px 16px;font-size:12px;color:var(--stone);border-bottom:1px solid var(--cloud)">
      Active entries block their date in the appointment calendar. Archived entries no longer block the date but are kept for history.
    </div>
    <div style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input v-model="search" class="ifi" style="max-width:240px" maxlength="60" placeholder="Search name..." />
      <select v-model="typeFilter" class="fsm">
        <option value="all">All Types</option>
        <option v-for="(label, key) in TYPES" :key="key" :value="key">{{ label }}</option>
      </select>
      <select v-model="statusFilter" class="fsm">
        <option value="all">All Records</option>
        <option value="active">Active</option>
        <option value="archived">Archived</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="search = ''; typeFilter = 'all'; statusFilter = 'all'">Reset</button>
    </div>

    <div v-if="loading" style="text-align:center;padding:36px;color:var(--fog);font-size:13px">Loading...</div>
    <div v-else-if="!entries.length" class="empty-state"><h3>No holidays or suspensions yet</h3><p>Add one to block that date for appointments.</p></div>
    <div v-else-if="!rows.length" class="empty-state"><h3>No matching records</h3><p>Try a different search or filter, or press Reset.</p></div>
    <div class="ts" v-else>
      <table class="itable">
        <thead><tr><th>No.</th><th>Date</th><th>Name</th><th>Type</th><th>Status</th><th v-if="canManage"></th></tr></thead>
        <tbody>
          <tr v-for="(e, i) in rows" :key="e.id" :style="e.status === 'archived' ? 'opacity:.65' : ''">
            <td>{{ i + 1 }}</td>
            <td>{{ fmt(e.entry_date) }}</td>
            <td style="font-weight:600">{{ e.name }}</td>
            <td>{{ e.type_label }}</td>
            <td><span class="ibadge" :style="e.status === 'active' ? 'background:var(--mint);color:var(--moss)' : 'background:#eee;color:#666'">{{ e.status === 'active' ? 'Active' : 'Archived' }}</span></td>
            <td v-if="canManage">
              <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
                <button class="ibtn ibtn-o ibtn-sm" :disabled="e.status !== 'active'" :title="e.status !== 'active' ? 'Archived entries are read-only.' : ''" @click="openForm(e)">Edit</button>
                <button v-if="e.status === 'active'" class="ibtn ibtn-o ibtn-sm" @click="askArchive(e)">Archive</button>
                <button v-if="e.status === 'archived' && !e.is_past" class="ibtn ibtn-p ibtn-sm" @click="askReactivate(e)">Reactivate</button>
                <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="askDelete(e)">Delete</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit -->
    <div v-if="form" class="hs-back" @click.self="closeForm">
      <div class="hs-card">
        <div class="hs-head">{{ form.id ? 'Edit Entry' : 'Add Entry' }}</div>
        <div class="hs-body">
          <div v-if="formError" class="hs-err">{{ formError }}</div>
          <div><label class="ifl">Date <span style="color:var(--red)">*</span></label><input v-model="form.entry_date" type="date" class="ifi" /></div>
          <div><label class="ifl">Name <span style="color:var(--red)">*</span></label><input v-model="form.name" class="ifi" maxlength="120" placeholder="e.g. All Saints' Day" /></div>
          <div>
            <label class="ifl">Type <span style="color:var(--red)">*</span></label>
            <select v-model="form.type" class="ifse">
              <option value="" disabled>Select a type</option>
              <option v-for="(label, key) in TYPES" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!formValid || busy" @click="saveForm">{{ busy ? 'Saving...' : 'Save' }}</button>
            <button class="ibtn ibtn-o" :disabled="busy" @click="closeForm">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Archive / Reactivate / Delete -->
    <ReasonDialog
      :open="!!ask"
      :title="dlg.title"
      :message="dlg.message"
      :reasons="dlg.reasons"
      :reason-label="dlg.reasonLabel"
      :confirm-text="dlg.confirm"
      :danger="ask?.mode !== 'reactivate'"
      :extra-disabled="needsAck && !ack"
      :blocked-message="dlg.blocked"
      :busy="busy"
      :error="askError"
      @confirm="doAsk"
      @cancel="ask = null"
    >
      <label v-if="needsAck" style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" v-model="ack" /> I understand</label>
      <button v-if="ask?.mode === 'delete' && dlg.blocked" class="ibtn ibtn-p ibtn-sm" style="align-self:flex-start" @click="switchToArchive">Archive Instead</button>
    </ReasonDialog>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import ReasonDialog from './ReasonDialog.vue';
import { MANAGEMENT_DELETE_REASONS, MANAGEMENT_STATUS_REASONS } from '../constants/accountReasons';

defineProps({ canManage: { type: Boolean, default: false } });
const toast = inject('toast', null);

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
const headers = () => ({ headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } });
const url = (extra = '') => `${API_BASE}/management/calendar-entries${extra}`;
const TYPES = { holiday: 'Holiday', class_suspension: 'Class Suspension', office_closed: 'Office Closed' };
const TAB = 'holidays and suspensions';

const entries = ref([]);
const loading = ref(true);
const busy = ref(false);
const search = ref('');
const typeFilter = ref('all');
const statusFilter = ref('all');

const rows = computed(() => entries.value.filter(e =>
  (typeFilter.value === 'all' || e.type === typeFilter.value) &&
  (statusFilter.value === 'all' || e.status === statusFilter.value) &&
  (!search.value.trim() || e.name.toLowerCase().includes(search.value.trim().toLowerCase()))
));

const fmt = (d) => d ? new Date(String(d).slice(0, 10) + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
const describe = (e) => `<strong>"${esc(e.name)}"</strong> (${e.type_label} · ${fmt(e.entry_date)})`;
const esc = (t) => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

async function load() {
  loading.value = true;
  try { entries.value = (await axios.get(url(), headers())).data; }
  catch (e) { toast?.error?.('Could not load holidays and suspensions.'); }
  finally { loading.value = false; }
}

// ---- Add / Edit ----
const form = ref(null);
const formError = ref('');
const formValid = computed(() => form.value && form.value.entry_date && form.value.name.trim() && form.value.type);
function openForm(e = null) {
  formError.value = '';
  form.value = e ? { id: e.id, entry_date: String(e.entry_date).slice(0, 10), name: e.name, type: e.type } : { entry_date: '', name: '', type: '' };
}
function closeForm() { form.value = null; }
async function saveForm() {
  if (busy.value) return;
  busy.value = true; formError.value = '';
  try {
    const { id, ...body } = form.value;
    if (id) await axios.put(url(`/${id}`), body, headers()); else await axios.post(url(), body, headers());
    toast?.success?.(id ? 'Entry updated.' : 'Entry added.');
    form.value = null;
    await load();
  } catch (e) {
    const errs = e.response?.data?.errors;
    formError.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not save the entry.';
  } finally { busy.value = false; }
}

// ---- Archive / Reactivate / Delete ----
const ask = ref(null);       // { mode, e }
const askError = ref('');
const ack = ref(false);

const needsAck = computed(() => ask.value?.mode === 'archive' && !ask.value.e.is_past && ask.value.e.linked.appointments > 0);

const dlg = computed(() => {
  const a = ask.value;
  if (!a) return { title: '', message: '', reasons: [], reasonLabel: 'Reason', confirm: 'Confirm', blocked: '' };
  const e = a.e;
  if (a.mode === 'archive') {
    const warn = needsAck.value
      ? `This date is still upcoming, and ${e.linked.appointments} appointment${e.linked.appointments === 1 ? ' was' : 's were'} rescheduled or cancelled because of it. Archiving will make this date available for booking again. Those appointments will not be restored automatically.`
      : (e.is_past ? 'Past appointments are not affected.' : 'The date will no longer be blocked in the appointment calendar. The record is kept for history and for appointments linked to it.');
    return { title: 'Archive this entry?', message: `${describe(e)} will be archived.<br>${warn}`, reasons: MANAGEMENT_STATUS_REASONS[TAB].archived, reasonLabel: 'Reason for archiving', confirm: 'Archive', blocked: '' };
  }
  if (a.mode === 'reactivate') {
    return { title: 'Reactivate this entry?', message: `${describe(e)} will block its date in the appointment calendar again.`, reasons: MANAGEMENT_STATUS_REASONS[TAB].active, reasonLabel: 'Reason for reactivating', confirm: 'Reactivate', blocked: '' };
  }
  const blocked = (e.is_past || e.linked_total > 0)
    ? `"${e.name}" is linked to ${e.linked.appointments} appointment${e.linked.appointments === 1 ? '' : 's'} and ${e.linked.notifications} notification${e.linked.notifications === 1 ? '' : 's'}, or its date has already passed. Deleting it would remove the reason shown in those records. Archive it instead.`
    : '';
  return { title: blocked ? "This entry can't be deleted" : 'Delete this entry?', message: blocked ? '' : `Delete ${describe(e)}? This cannot be undone. No appointments, reschedules or notifications are linked to it.`, reasons: MANAGEMENT_DELETE_REASONS[TAB], reasonLabel: 'Reason for deletion', confirm: 'Delete', blocked };
});

function openAsk(mode, e) { askError.value = ''; ack.value = false; ask.value = { mode, e }; }
const askArchive = (e) => openAsk('archive', e);
const askReactivate = (e) => openAsk('reactivate', e);
const askDelete = (e) => openAsk('delete', e);
function switchToArchive() {
  const e = ask.value.e;
  if (e.status === 'archived') { ask.value = null; return; }
  openAsk('archive', e);
}

async function doAsk({ reason, note }) {
  if (busy.value) return;
  const { mode, e } = ask.value;
  busy.value = true; askError.value = '';
  try {
    if (mode === 'delete') await axios.delete(url(`/${e.id}`), { ...headers(), data: { reason, note } });
    else await axios.post(url(`/${e.id}/${mode}`), { reason, note, acknowledged: ack.value }, headers());
    toast?.success?.({ archive: 'Entry archived.', reactivate: 'Entry reactivated.', delete: 'Entry deleted.' }[mode]);
    ask.value = null;
    await load();
  } catch (err) {
    const errs = err.response?.data?.errors;
    askError.value = (errs && Object.values(errs)[0]?.[0]) || err.response?.data?.message || 'Action failed.';
  } finally { busy.value = false; }
}

onMounted(load);
</script>

<style scoped>
.hs-back { position: fixed; inset: 0; background: rgba(0,0,0,.42); z-index: 76; display: flex; align-items: center; justify-content: center; padding: 20px; }
.hs-card { background: #fff; border-radius: var(--r-lg); width: 100%; max-width: 420px; }
.hs-head { padding: 16px 20px; border-bottom: 1px solid var(--cloud); font-weight: 600; font-size: 15px; }
.hs-body { padding: 18px 20px; display: flex; flex-direction: column; gap: 14px; }
.hs-err { font-size: 12px; color: var(--red); background: var(--red-lt); border-radius: var(--r-sm); padding: 8px 10px; }
</style>