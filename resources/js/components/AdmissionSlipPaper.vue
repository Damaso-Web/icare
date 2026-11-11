<!--
  Admission Slip (QF-OSS-GCU-09), laid out like the paper form. The same
  layout is used to fill it in (readonly = false, v-model on the form) and to
  show an issued slip (readonly = true).

  form: { absence_ranges: [{ startDate, endDate, startTime, endTime }],
          admission_dates: [{ date, time_in, time_out }],
          excused ('1' | '0' | ''), excused_dates: [date, ...] | undefined,
          remarks, causes: [], cause_activity, cause_others, actions: [],
          action_others }

  absence_ranges is the editing UI - each row is a start/end date span with
  a class-time range. admission_dates is the expanded form saved to the
  backend: one entry per calendar day in every range, carrying that range's
  time_in/time_out. It's derived from absence_ranges on every change
  (see syncAdmissionDates()).

  Whole-day / half-day is gone - every date counts as 1 day, and every date
  carries an explicit time range. Both the top "Date/s Absent/Tardy" field
  and the Excused/Unexcused "Covers" checkboxes render each expanded date
  with its time range so GCU never retypes anything.

  excused_dates is the subset of admission_dates' dates this verdict covers.
  Left undefined, every date counts as covered.
-->
<template>
  <div class="asp" :class="{ ro: readonly }">
    <div v-if="showDoc" class="asp-doc doc-header-hidden">
      <div><strong>Document Code:</strong> QF-OSS-GCU-09<br><strong>Revision No.:</strong> {{ doc.revision_no || '01' }}</div>
      <div style="text-align:right"><strong>Effectivity:</strong> {{ doc.effectivity || '07/04/23' }}<br><strong>Ctrl No.:</strong> {{ doc.ctrl_no || '-' }}</div>
    </div>
    <div class="asp-title">Admission Slip</div>

    <div class="asp-grid">
      <div class="asp-line"><span class="asp-k">Name:</span><span class="asp-v">{{ studentName }}</span></div>
      <div class="asp-line"><span class="asp-k">Date:</span><span class="asp-v">{{ issuedOn }}</span></div>
      <div class="asp-line"><span class="asp-k">Program &amp; Year Level:</span><span class="asp-v">{{ programYear }}</span></div>
      <div class="asp-line"><span class="asp-k">Sex:</span><span class="asp-v">{{ student?.sex || '-' }}</span></div>
    </div>

    <!-- Date/s Absent/Tardy ------------------------------------------------->
         Read-only renders the expanded list (one line per date with its
         time range). Editable renders one row per range - start/end date
         plus class-time range - and syncAdmissionDates() fans it out into
         individual dates for saving and for the Covers matrix below.
    -->
    <div class="asp-line asp-wrap" style="align-items:flex-start">
      <span class="asp-k">Date/s Absent/Tardy:</span>

      <div v-if="readonly" class="asp-v" style="flex:1">
        <template v-if="(form.admission_dates || []).length">
          <div v-for="(e, i) in form.admission_dates" :key="i">{{ formatDateEntry(e) }}</div>
        </template>
        <template v-else>-</template>
      </div>

      <div v-else style="flex:1;display:flex;flex-direction:column;gap:8px;min-width:240px">
        <div v-if="(form.absence_ranges || []).length" class="asp-datelist">
          <div v-for="(range, i) in form.absence_ranges" :key="i" class="asp-rangeitem">
            <input v-model="range.startDate" type="date" class="ifi" :max="today" @change="syncAdmissionDates" aria-label="Start date" />
            <span class="asp-range-to">to</span>
            <input v-model="range.endDate" type="date" class="ifi" :max="today" :min="range.startDate" @change="syncAdmissionDates" aria-label="End date" />
            <input v-model="range.startTime" type="time" class="ifi" @change="syncAdmissionDates" aria-label="Class time from" title="Class time (from)" />
            <span class="asp-range-to">to</span>
            <input v-model="range.endTime" type="time" class="ifi" @change="syncAdmissionDates" aria-label="Class time to" title="Class time (to)" />
            <button type="button" class="asp-datedel" @click="removeRange(i)" aria-label="Remove this date range">&times;</button>
          </div>
        </div>
        <div class="asp-addrow">
          <button type="button" class="ibtn ibtn-o ibtn-sm" @click="addRange">+ Add Date Range</button>
        </div>
      </div>
    </div>

    <div class="asp-line">
      <span class="asp-k">Number of day/s absent/tardy:</span>
      <span class="asp-v">{{ daysCountDisplay }}</span>
    </div>

    <div class="asp-sub">Cause/s of Absences:</div>
    <div class="asp-checks">
      <label v-for="c in CAUSES" :key="c.key" class="asp-check" :class="{ wide: c.key === 'official_activity' || c.key === 'others' }">
        <input type="checkbox" :value="c.key" v-model="form.causes" :disabled="readonly" :tabindex="readonly ? -1 : 0" />
        <span>{{ c.label }}</span>
        <template v-if="c.key === 'official_activity' && (form.causes.includes('official_activity') || !readonly)">
          <span v-if="readonly" class="asp-v">{{ form.cause_activity }}</span>
          <input v-else v-model="form.cause_activity" class="ifi asp-fill" maxlength="255" :disabled="!form.causes.includes('official_activity')" placeholder="name of the activity" />
        </template>
        <template v-if="c.key === 'others' && (form.causes.includes('others') || !readonly)">
          <span v-if="readonly" class="asp-v">{{ form.cause_others }}</span>
          <input v-else v-model="form.cause_others" class="ifi asp-fill" maxlength="255" :disabled="!form.causes.includes('others')" placeholder="e.g. vehicle broke down" />
        </template>
      </label>
    </div>

    <div class="asp-sub">Action/s taken:</div>
    <div class="asp-checks one">
      <label v-for="a in ACTIONS" :key="a.key" class="asp-check" :class="{ wide: a.key === 'others' }">
        <input type="checkbox" :value="a.key" v-model="form.actions" :disabled="readonly" :tabindex="readonly ? -1 : 0" />
        <span>{{ a.label }}</span>
        <template v-if="a.key === 'others' && (form.actions.includes('others') || !readonly)">
          <span v-if="readonly" class="asp-v">{{ form.action_others }}</span>
          <input v-else v-model="form.action_others" class="ifi asp-fill" maxlength="255" :disabled="!form.actions.includes('others')" />
        </template>
      </label>
    </div>

    <div class="asp-verdict">
      <label class="asp-check wide">
        <input type="radio" value="1" v-model="form.excused" :disabled="readonly" :tabindex="readonly ? -1 : 0" />
        <span class="asp-k">Excused</span>
      </label>
      <label class="asp-check wide">
        <input type="radio" value="0" v-model="form.excused" :disabled="readonly" :tabindex="readonly ? -1 : 0" />
        <span class="asp-k">Unexcused</span>
      </label>
      <!-- Covers matrix: only shown once a verdict is picked. Each checkbox
           is one expanded date (from the ranges above) with its time range,
           so 11-11-2026 to 11-16-2026 shows as 11, 12, 13, 14, 15, 16 - one
           checkbox each. -->
      <div v-if="form.excused === '1' || form.excused === '0'" class="asp-line asp-wrap" style="align-items:flex-start">
        <span class="asp-k">Covers:</span>
        <div v-if="readonly" class="asp-v" style="flex:1">{{ admissionDatesText }}</div>
        <div v-else class="asp-datelist" style="flex:1;min-width:240px">
          <label v-for="(e, i) in (form.admission_dates || [])" :key="i" class="asp-check" style="padding:4px 2px">
            <input type="checkbox" :checked="isDateCovered(e.date)" @change="toggleDateCovered(e.date)" />
            <span>{{ formatDateEntry(e) }}</span>
          </label>
        </div>
      </div>
      <div v-if="form.excused === '1' || form.excused === '0'" class="asp-line">
        <span class="asp-k">Remarks:</span>
        <span v-if="readonly" class="asp-v">{{ form.remarks || '-' }}</span>
        <input v-else v-model="form.remarks" class="ifi asp-fill" maxlength="1000" placeholder="optional remarks (e.g. documents verified)" />
      </div>
    </div>

    <div class="asp-sign">
      <div class="asp-signname">{{ personnel || '-' }}</div>
      <div class="asp-signlabel">Attending OSS Personnel</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { formatTime12 } from '../utils/validators';

const props = defineProps({
  modelValue: { type: Object, required: true },
  student:    { type: Object, default: () => ({}) },
  personnel:  { type: String, default: '' },
  issuedAt:   { type: String, default: '' },
  doc:        { type: Object, default: () => ({}) },
  readonly:   { type: Boolean, default: false },
  // The SIF card already shows the document header above the slip.
  showDoc:    { type: Boolean, default: true },
});
const form = computed(() => props.modelValue);

const CAUSES = [
  { key: 'health',            label: 'health-related concerns' },
  { key: 'personal',          label: 'personal concerns' },
  { key: 'filial',            label: 'filial responsibilities' },
  { key: 'socio_cultural',    label: 'socio-cultural concern' },
  { key: 'environmental',     label: 'environmental' },
  { key: 'behavioral',        label: 'behavioral' },
  { key: 'official_activity', label: 'official co/extra-curricular activity:' },
  { key: 'others',            label: 'others:' },
];
const ACTIONS = [
  { key: 'verified_documents', label: 'verified supporting document/s' },
  { key: 'interviewed',        label: 'interviewed with guidance' },
  { key: 'life_coaching',      label: 'life coaching' },
  { key: 'parent_conference',  label: 'parent conference' },
  { key: 'counseling',         label: 'counseling' },
  { key: 'others',             label: 'others:' },
];

const today = new Date().toLocaleDateString('en-CA');
const longDate = d => (d ? new Date(String(d).slice(0, 10) + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '-');
// Local-date formatter for our range expansion. Using toISOString() here
// would slide the date back one day in UTC+ timezones (e.g. UTC+8 midnight
// serializes as the previous day 16:00Z), so we build YYYY-MM-DD from the
// local getters instead.
function localISO(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}
const studentName = computed(() => {
  const s = props.student || {};
  return [s.first_name, s.middle_name, s.last_name, s.suffix].filter(Boolean).join(' ') || '-';
});
const programYear = computed(() => [props.student?.program, props.student?.year_level && `Year ${props.student.year_level}`].filter(Boolean).join(', ') || '-');
const issuedOn = computed(() => longDate(props.issuedAt || today));

// ---------------------------------------------------------------------------
// absence_ranges <-> admission_dates sync
// ---------------------------------------------------------------------------
// Initialize / migrate on every model swap. Legacy slips (pre-range) get
// absence_ranges reconstructed from their existing admission_dates so the
// editor shows something sensible; their time_in/time_out is carried across
// unchanged so nothing is lost on the first edit. New slips just get an
// empty array.
watch(() => props.modelValue, (newVal) => {
  if (!newVal) return;
  if (!Array.isArray(newVal.admission_dates)) newVal.admission_dates = [];
  if (!Array.isArray(newVal.absence_ranges)) {
    newVal.absence_ranges = [];
    newVal.admission_dates.forEach(e => {
      newVal.absence_ranges.push({
        startDate: e.date,
        endDate:   e.date,
        startTime: e.time_in  || '',
        endTime:   e.time_out || '',
      });
    });
  }
}, { immediate: true });

function addRange() {
  if (!Array.isArray(form.value.absence_ranges)) form.value.absence_ranges = [];
  form.value.absence_ranges.push({
    startDate: '',
    endDate:   '',
    startTime: '08:00',
    endTime:   '17:00',
  });
}

function removeRange(i) {
  if (!Array.isArray(form.value.absence_ranges)) return;
  form.value.absence_ranges.splice(i, 1);
  syncAdmissionDates();
}

// Expand every range into individual calendar days, each carrying that
// range's time_in/time_out. Dedupes by date, sorted ascending.
function expandRanges() {
  const out = [];
  const seen = new Set();
  (form.value.absence_ranges || []).forEach(range => {
    if (!range || !range.startDate) return;
    const start = new Date(range.startDate + 'T00:00:00');
    if (isNaN(start.getTime())) return;
    let end = start;
    if (range.endDate) {
      const e = new Date(range.endDate + 'T00:00:00');
      if (!isNaN(e.getTime())) end = e;
    }
    if (start > end) return;
    let cur = new Date(start);
    while (cur <= end) {
      const dateStr = localISO(cur);
      if (!seen.has(dateStr)) {
        seen.add(dateStr);
        out.push({
          date:     dateStr,
          time_in:  range.startTime || '',
          time_out: range.endTime   || '',
        });
      }
      cur.setDate(cur.getDate() + 1);
    }
  });
  out.sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  return out;
}

// Push the expanded list onto form.admission_dates, then reconcile
// excused_dates so it keeps referring to real dates only. New dates
// default to covered (matches the "every date covered" default); removed
// dates are dropped.
function syncAdmissionDates() {
  const oldDates = (form.value.admission_dates || []).map(e => e.date);
  const newEntries = expandRanges();
  const newDates = newEntries.map(e => e.date);
  form.value.admission_dates = newEntries;
  if (Array.isArray(form.value.excused_dates)) {
    form.value.excused_dates = form.value.excused_dates.filter(d => newDates.includes(d));
    newDates.forEach(d => {
      if (!oldDates.includes(d) && !form.value.excused_dates.includes(d)) {
        form.value.excused_dates.push(d);
      }
    });
  }
}

function isDateCovered(dateStr) {
  if (!Array.isArray(form.value.excused_dates)) return true;
  return form.value.excused_dates.includes(dateStr);
}
function toggleDateCovered(dateStr) {
  if (!Array.isArray(form.value.excused_dates)) {
    form.value.excused_dates = (form.value.admission_dates || [])
      .map(e => e.date)
      .filter(d => d !== dateStr);
    return;
  }
  const idx = form.value.excused_dates.indexOf(dateStr);
  if (idx === -1) form.value.excused_dates.push(dateStr);
  else form.value.excused_dates.splice(idx, 1);
}

// Every expanded date counts as 1 day. Whole/half-day is gone.
const daysCountDisplay = computed(() => {
  const dates = form.value.admission_dates || [];
  if (!dates.length) return '-';
  const str = String(dates.length);
  return `${str} ${str === '1' ? 'day' : 'days'}`;
});

// "November 11, 2026, 8:00 AM - 5:00 PM" - used by both the top
// Date/s Absent/Tardy list and the Covers checkboxes so the same date
// reads identically in both places.
function formatDateEntry(e) {
  let s = longDate(e.date);
  if (e.time_in || e.time_out) {
    s += ', ';
    if (e.time_in) {
      s += formatTime12(e.time_in);
      if (e.time_out) s += ` - ${formatTime12(e.time_out)}`;
    }
  }
  return s;
}
const admissionDatesText = computed(() => {
  const dates = (form.value.admission_dates || []).filter(e => isDateCovered(e.date));
  return dates.length ? dates.map(formatDateEntry).join('; ') : '-';
});
</script>

<style scoped>
.asp{background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);padding:22px 26px;font-size:13.5px;color:var(--ink);display:flex;flex-direction:column;gap:14px}
.asp-doc{display:flex;justify-content:space-between;gap:12px;font-size:11px;color:var(--stone);border-bottom:1px solid var(--cloud);padding-bottom:8px}
.asp-title{text-align:center;font-weight:700;letter-spacing:.8px;text-transform:uppercase;font-size:14px;margin-bottom:2px}
.asp-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:10px 24px}
.asp-line{display:flex;align-items:baseline;gap:8px;min-width:0;padding-bottom:2px}
.asp-wrap{flex-wrap:wrap}
.asp-k{font-weight:600;white-space:nowrap}
.asp-v{flex:1;min-width:40px;border-bottom:1px solid var(--silver);padding:0 4px 3px;color:var(--forest);font-style:italic}
.asp-fill{flex:1;min-width:120px;padding:6px 8px}
.asp-sub{font-weight:700;margin-top:6px}
.asp-checks{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px 32px;padding-left:4px}
.asp-checks.one{grid-template-columns:minmax(0,1fr)}
.asp-check{display:flex;align-items:center;gap:9px;min-width:0}
.asp-check.wide{grid-column:1/-1}
.asp-check span{overflow-wrap:anywhere}
.asp-check input[type=checkbox],.asp-check input[type=radio]{width:15px;height:15px;accent-color:var(--moss);flex-shrink:0}
.asp.ro input,.asp.ro label{pointer-events:none;cursor:default}
.asp.ro input:disabled{opacity:1}
.asp-verdict{display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--cloud);padding-top:14px}
.asp-sign{align-self:center;text-align:center;margin-top:14px;min-width:220px}
.asp-signname{font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--ink);padding:0 10px 2px}
.asp-signlabel{font-size:11.5px;color:var(--stone);margin-top:3px}
/* One row per date range: startDate / endDate / startTime / endTime plus a
   small × to drop the whole range. Reads like a paper form row. */
.asp-datelist{display:flex;flex-direction:column}
.asp-rangeitem{display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-bottom:1px solid var(--silver);padding:6px 2px 8px;font-size:13px}
.asp-rangeitem .ifi{width:auto;flex:0 1 135px;padding:5px 7px;font-size:12px}
.asp-range-to{color:var(--stone);font-size:11.5px;white-space:nowrap}
.asp-rangeitem .asp-datedel{margin-left:auto}
.asp-datedel{border:none;background:transparent;color:var(--stone);font-size:17px;line-height:1;cursor:pointer;padding:0 2px;font-style:normal}
.asp-datedel:hover{color:var(--red)}
.asp-addrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:2px}
@media (max-width:600px){
  .asp-grid,.asp-checks{grid-template-columns:minmax(0,1fr)}
  .asp-doc{flex-direction:column}
  .asp-rangeitem .ifi{flex:1 1 100%}
  .asp-rangeitem .asp-datedel{margin-left:0}
}
</style>