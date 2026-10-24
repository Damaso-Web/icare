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
      <!-- B547: scoped to the Current academic year by default so admins aren't
           reading last year's holidays after the term rolls over; "All Academic
           Years" (or picking a specific one) lifts that scope on demand. -->
      <select v-model="ayFilter" class="fsm">
        <option value="current">Current Academic Year{{ currentAy ? ` (${currentAy.start_year}-${currentAy.start_year + 1})` : '' }}</option>
        <option value="all">All Academic Years</option>
        <option v-for="a in ayOptions" :key="a.id" :value="String(a.id)">{{ a.start_year }}-{{ a.start_year + 1 }}</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="search = ''; typeFilter = 'all'; statusFilter = 'all'; ayFilter = 'current'">Reset</button>
    </div>

    <div v-if="loading" style="text-align:center;padding:36px;color:var(--fog);font-size:13px">Loading...</div>
    <div v-else-if="!entries.length" class="empty-state"><h3>No holidays or suspensions yet</h3><p>Add one to block that date for appointments.</p></div>
    <div v-else-if="!rows.length" class="empty-state"><h3>No matching records</h3><p>Try a different search or filter, or press Reset.</p></div>
    <div class="ts" v-else>
      <table class="itable">
        <thead><tr><th>No.</th><th>Date</th><th>Academic Year</th><th>Name</th><th>Type</th><th>Status</th><th v-if="canManage"></th></tr></thead>
        <tbody>
          <tr v-for="(e, i) in rows" :key="e.id" :style="e.status === 'archived' ? 'opacity:.65' : ''">
            <td>{{ i + 1 }}</td>
            <td>{{ fmt(e.entry_date) }}</td>
            <td>{{ ayLabel(e.entry_date) }}</td>
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
          <!-- B547: picking an Academic Year here just bounds the date picker to
               that term's range, as a guard rail - it defaults to the Current term
               on Add so new entries land in the right place without the admin
               having to remember today's academic year. -->
          <div>
            <label class="ifl">Academic Year</label>
            <select v-model.number="formAyId" class="ifse">
              <option :value="null">No restriction</option>
              <option v-for="a in ayOptions" :key="a.id" :value="a.id">{{ a.start_year }}-{{ a.start_year + 1 }}{{ a.is_current ? ' (Current)' : '' }}</option>
            </select>
          </div>
          <div><label class="ifl">Date <span style="color:var(--red)">*</span></label><input v-model="form.entry_date" type="date" class="ifi" :min="formDateMin" :max="formDateMax" /></div>
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

    <!-- B534: this date affects existing appointments - ask how to handle them. -->
    <div v-if="holidayConflict" class="hs-back" @click.self="!busy && (holidayConflict = null)">
      <div class="hs-card" style="max-width:460px">
        <div class="hs-head">Holiday Conflict</div>
        <div class="hs-body">
          <div style="font-size:13px;color:var(--ink);line-height:1.55">{{ holidayConflict.message }}</div>
          <div style="display:flex;flex-direction:column;gap:8px">
            <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0;justify-content:flex-start;text-align:left" :disabled="busy" @click="pickHolidayResolution('auto_cancel')">
              <strong>A.</strong>&nbsp; Auto-cancel these appointments and notify students with a rebooking link
            </button>
            <button class="ibtn ibtn-sm" style="background:var(--blue-lt);color:var(--blue);border:1.5px solid var(--blue);justify-content:flex-start;text-align:left" :disabled="busy" @click="pickHolidayResolution('auto_reschedule')">
              <strong>B.</strong>&nbsp; Auto-reschedule each to the next business day, same time (subject to availability)
            </button>
            <button class="ibtn ibtn-o ibtn-sm" style="justify-content:flex-start;text-align:left" :disabled="busy" @click="pickHolidayResolution('manual_review')">
              <strong>C.</strong>&nbsp; Leave them flagged for manual review
            </button>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" :disabled="busy" @click="holidayConflict = null">Cancel, don't add the entry</button>
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
import { ref, computed, inject, onMounted, watch } from 'vue';
import axios from 'axios';
import ReasonDialog from './ReasonDialog.vue';
import { MANAGEMENT_DELETE_REASONS, MANAGEMENT_STATUS_REASONS } from '../constants/accountReasons';

// B547: academicYears can be supplied by the parent (School Calendar tab, which
// already keeps its own copy live) so "Set as Current" there updates this list's
// filtering and the Add form's default in real time, with no refetch here and no
// page refresh. Used standalone (prop omitted), it falls back to loading its own.
const props = defineProps({ canManage: { type: Boolean, default: false }, academicYears: { type: Array, default: null } });
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

// B547: "current" scopes to the active academic year when one is set (falls
// through to no date restriction if none is marked current yet); "all" lifts
// the scope; any other value is a specific academic year's id to view instead.
const ayFilter = ref('current');
function ayRangeFor(id) {
  return effectiveAcademicYears.value.find(a => a.id === id) || null;
}
const rows = computed(() => entries.value.filter(e => {
  if (typeFilter.value !== 'all' && e.type !== typeFilter.value) return false;
  if (statusFilter.value !== 'all' && e.status !== statusFilter.value) return false;
  if (search.value.trim() && !e.name.toLowerCase().includes(search.value.trim().toLowerCase())) return false;
  if (ayFilter.value !== 'all') {
    const ay = ayFilter.value === 'current' ? currentAy.value : ayRangeFor(Number(ayFilter.value));
    if (ay) {
      const d = String(e.entry_date).slice(0, 10);
      if (d < String(ay.year_start).slice(0, 10) || d > String(ay.year_end).slice(0, 10)) return false;
    }
  }
  return true;
}));

const fmt = (d) => d ? new Date(String(d).slice(0, 10) + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
const describe = (e) => `<strong>"${esc(e.name)}"</strong> (${e.type_label} · ${fmt(e.entry_date)})`;
const esc = (t) => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

async function load() {
  loading.value = true;
  try { entries.value = (await axios.get(url(), headers())).data; }
  catch (e) { toast?.error?.('Could not load holidays and suspensions.'); }
  finally { loading.value = false; }
}

// B533: each entry's Academic Year is computed from its date against the Academic
// Years list, not stored or typed in by hand, so it is always correct and moves on
// its own whenever the admin adds/edits academic years or changes which one is current.
const internalAcademicYears = ref([]);
// B547: when the parent (School Calendar tab) hands down its own live list, use
// that - it's the same reactive array the parent updates after "Set as Current",
// so this component's filtering and Add-form default follow along automatically.
const effectiveAcademicYears = computed(() => props.academicYears !== null ? props.academicYears : internalAcademicYears.value);
async function loadAcademicYears() {
  if (props.academicYears !== null) return;
  try { internalAcademicYears.value = (await axios.get(`${API_BASE}/management/academic-years`, headers())).data; }
  catch (e) { /* non-critical: the Academic Year column just shows "-" */ }
}
function ayLabel(dateStr) {
  const d = String(dateStr).slice(0, 10);
  const ay = effectiveAcademicYears.value.find(a => a.year_start && a.year_end && d >= String(a.year_start).slice(0, 10) && d <= String(a.year_end).slice(0, 10));
  return ay ? `${ay.start_year}-${ay.start_year + 1}${ay.is_current ? ' (Current)' : ''}` : '-';
}
const currentAy = computed(() => effectiveAcademicYears.value.find(a => a.is_current) || null);
const ayOptions = computed(() => [...effectiveAcademicYears.value].sort((a, b) => b.start_year - a.start_year));

// ---- Add / Edit ----
const form = ref(null);
const formError = ref('');
const formValid = computed(() => form.value && form.value.entry_date && form.value.name.trim() && form.value.type);
// B547: which Academic Year the date picker is bounded to. Defaults to the
// entry's own academic year when editing, or the Current term when adding -
// "No restriction" is always available by picking it from the dropdown.
const formAyId = ref(null);
const formAy = computed(() => formAyId.value ? effectiveAcademicYears.value.find(a => a.id === formAyId.value) || null : null);
const formDateMin = computed(() => formAy.value ? String(formAy.value.year_start).slice(0, 10) : null);
const formDateMax = computed(() => formAy.value ? String(formAy.value.year_end).slice(0, 10) : null);
watch(formAyId, () => {
  if (!form.value || !form.value.entry_date) return;
  if ((formDateMin.value && form.value.entry_date < formDateMin.value) || (formDateMax.value && form.value.entry_date > formDateMax.value)) {
    form.value.entry_date = '';
  }
});
function openForm(e = null) {
  formError.value = '';
  if (e) {
    form.value = { id: e.id, entry_date: String(e.entry_date).slice(0, 10), name: e.name, type: e.type };
    const match = effectiveAcademicYears.value.find(a => a.year_start && a.year_end &&
      form.value.entry_date >= String(a.year_start).slice(0, 10) && form.value.entry_date <= String(a.year_end).slice(0, 10));
    formAyId.value = match ? match.id : null;
  } else {
    form.value = { entry_date: '', name: '', type: '' };
    formAyId.value = currentAy.value ? currentAy.value.id : null;
  }
}
function closeForm() { form.value = null; }
async function saveForm() {
  if (busy.value) return;
  busy.value = true; formError.value = '';
  try {
    const { id, ...body } = form.value;
    if (id) { await axios.put(url(`/${id}`), body, headers()); afterSaved(id); return; }
    await createEntry(body);
  } catch (e) {
    const errs = e.response?.data?.errors;
    formError.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not save the entry.';
    busy.value = false;
  }
}

function afterSaved(hadId, resolvedCount = 0) {
  toast?.success?.(hadId
    ? 'Entry updated.'
    : (resolvedCount ? `Entry added. ${resolvedCount} affected appointment${resolvedCount === 1 ? '' : 's'} processed.` : 'Entry added.'));
  form.value = null;
  busy.value = false;
  load();
}

// B534 (4): if creating the entry would affect existing appointments, the
// backend refuses with 409 and a count instead of creating it; show the
// warning and ask how to proceed, then resubmit with that choice.
const holidayConflict = ref(null); // { body, message, count }
async function createEntry(body, resolution = null) {
  try {
    const res = await axios.post(url(), resolution ? { ...body, resolution } : body, headers());
    holidayConflict.value = null;
    afterSaved(false, res.data.resolved_conflicts || 0);
  } catch (e) {
    if (e.response?.status === 409 && e.response.data?.needs_resolution) {
      holidayConflict.value = { body, message: e.response.data.message, count: e.response.data.conflict_count };
      busy.value = false;
      return;
    }
    throw e;
  }
}
function pickHolidayResolution(resolution) {
  if (!holidayConflict.value || busy.value) return;
  busy.value = true; formError.value = '';
  createEntry(holidayConflict.value.body, resolution).catch(e => {
    const errs = e.response?.data?.errors;
    formError.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not save the entry.';
    holidayConflict.value = null;
    busy.value = false;
  });
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
    // B536: show why it was archived in the first place, right where the admin is
    // choosing the reactivation reason, so the two stay easy to compare.
    const original = e.archive_reason
      ? `<br><br>Archived for: <strong>${esc(e.archive_reason)}</strong>${e.archive_note ? ' - ' + esc(e.archive_note) : ''}`
      : '';
    return { title: 'Reactivate this entry?', message: `${describe(e)} will block its date in the appointment calendar again.${original}`, reasons: MANAGEMENT_STATUS_REASONS[TAB].active, reasonLabel: 'Reason for reactivating', confirm: 'Reactivate', blocked: '' };
  }
  if (e.is_past || e.linked_total > 0) {
    // B550: check for a genuine duplicate - another entry on the same date and
    // type - before enforcing the usual block. The backend makes the final,
    // authoritative call (it also checks nothing of its own is tied to this
    // specific record), but surfacing it here means a confirmed duplicate
    // isn't stuck behind "Archive Instead" with no way to actually remove it.
    const dup = entries.value.find(x => x.id !== e.id && x.type === e.type && String(x.entry_date).slice(0, 10) === String(e.entry_date).slice(0, 10));
    if (dup) {
      return {
        title: 'Delete this entry?',
        message: `${describe(e)} shares its date with another ${TYPES[e.type]} entry ("${esc(dup.name)}", ${dup.status === 'active' ? 'active' : 'archived'}). If this is a mistaken duplicate with nothing of its own tied to it, choose "Duplicate Entry" below - the system will double-check before removing it.`,
        reasons: MANAGEMENT_DELETE_REASONS[TAB], reasonLabel: 'Reason for deletion', confirm: 'Delete', blocked: '',
      };
    }
    const blocked = `"${e.name}" is linked to ${e.linked.appointments} appointment${e.linked.appointments === 1 ? '' : 's'} and ${e.linked.notifications} notification${e.linked.notifications === 1 ? '' : 's'}, or its date has already passed. Deleting it would remove the reason shown in those records. Archive it instead.`;
    return { title: "This entry can't be deleted", message: '', reasons: MANAGEMENT_DELETE_REASONS[TAB], reasonLabel: 'Reason for deletion', confirm: 'Delete', blocked };
  }
  return { title: 'Delete this entry?', message: `Delete ${describe(e)}? This cannot be undone. No appointments, reschedules or notifications are linked to it.`, reasons: MANAGEMENT_DELETE_REASONS[TAB], reasonLabel: 'Reason for deletion', confirm: 'Delete', blocked: '' };
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

onMounted(() => { load(); loadAcademicYears(); });
</script>

<style scoped>
.hs-back { position: fixed; inset: 0; background: rgba(0,0,0,.42); z-index: 76; display: flex; align-items: center; justify-content: center; padding: 20px; }
.hs-card { background: #fff; border-radius: var(--r-lg); width: 100%; max-width: 420px; }
.hs-head { padding: 16px 20px; border-bottom: 1px solid var(--cloud); font-weight: 600; font-size: 15px; }
.hs-body { padding: 18px 20px; display: flex; flex-direction: column; gap: 14px; }
.hs-err { font-size: 12px; color: var(--red); background: var(--red-lt); border-radius: var(--r-sm); padding: 8px 10px; }
</style>