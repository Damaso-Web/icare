<!--
  Admission Slip (QF-OSS-GCU-09), laid out like the paper form. The same
  layout is used to fill it in (readonly = false, v-model on the form) and to
  show an issued slip (readonly = true).

  form: { admission_dates: [{ date, duration: 'whole'|'half', time_in, time_out }],
          excused ('1' | '0' | ''), excused_dates: [date, ...] | undefined,
          remarks, causes: [], cause_activity, cause_others, actions: [],
          action_others }

  excused_dates is the subset of admission_dates' dates this Excused/
  Unexcused verdict covers. Left undefined, every date counts as covered -
  it's only materialized once a date is actually unchecked (see
  isDateCovered()/toggleDateCovered() below).

  Panel fix (11/8/2026, items #6/#7): a slip can now carry several dates
  absent/tardy, each its own Whole Day/Half Day entry (Half Day may also
  carry a time range). The slip still has ONE Excused/Unexcused verdict that
  covers every date on it. "Number of day/s absent/tardy" is counted
  automatically from the dates added (Whole Day = 1, Half Day = 0.5) - it is
  no longer a free-text field.
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

    <div class="asp-line asp-wrap" style="align-items:flex-start">
      <span class="asp-k">Date/s Absent/Tardy:</span>

      <div v-if="readonly" class="asp-v" style="flex:1">
        <template v-if="(form.admission_dates || []).length">
          <div v-for="(e, i) in form.admission_dates" :key="i">
            {{ longDate(e.date) }}<template v-if="e.duration === 'half'">, <template v-if="e.time_in">{{ formatTime12(e.time_in) }}<template v-if="e.time_out"> - {{ formatTime12(e.time_out) }}</template></template> (Half Day)</template><template v-else> (Whole Day)</template>
          </div>
        </template>
        <template v-else>-</template>
      </div>

      <div v-else style="flex:1;display:flex;flex-direction:column;gap:8px;min-width:240px">
        <div v-if="(form.admission_dates || []).length" class="asp-datelist">
          <div v-for="(e, i) in form.admission_dates" :key="i" class="asp-dateitem">
            <span>{{ longDate(e.date) }}<template v-if="e.duration === 'half'">, <template v-if="e.time_in">{{ formatTime12(e.time_in) }}<template v-if="e.time_out"> - {{ formatTime12(e.time_out) }}</template></template> (Half Day)</template><template v-else> (Whole Day)</template></span>
            <button type="button" class="asp-datedel" @click="removeDate(i)" aria-label="Remove this date">&times;</button>
          </div>
        </div>
        <div class="asp-addrow">
          <input v-model="newDate" type="date" class="ifi" :max="today" aria-label="Date absent or tardy" />
          <label class="asp-radioinline"><input type="radio" value="whole" v-model="newDuration" /> Whole Day</label>
          <label class="asp-radioinline"><input type="radio" value="half" v-model="newDuration" /> Half Day</label>
          <template v-if="newDuration === 'half'">
            <input v-model="newTimeIn" type="time" class="ifi" aria-label="Class time from" title="Class time (from)" />
            <span style="color:var(--stone)">to</span>
            <input v-model="newTimeOut" type="time" class="ifi" aria-label="Class time to" title="Class time (to)" />
          </template>
          <button type="button" class="ibtn ibtn-o ibtn-sm" @click="addDate">+ Add Date</button>
        </div>
        <div v-if="addDateError" style="font-size:11.5px;color:var(--red)">{{ addDateError }}</div>
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
      <!-- Panel fix (11/10/2026, items #6/#7): which of the dates added
           above this verdict covers - reusing that same list (never
           retyped). Every date is covered by default; GCU can uncheck any
           that this particular verdict doesn't apply to. -->
      <div v-if="form.excused" class="asp-line asp-wrap" style="align-items:flex-start">
        <span class="asp-k">Covers:</span>
        <div v-if="readonly" class="asp-v" style="flex:1">{{ admissionDatesText }}</div>
        <div v-else class="asp-datelist" style="flex:1;min-width:240px">
          <label v-for="(e, i) in (form.admission_dates || [])" :key="i" class="asp-check" style="padding:4px 2px">
            <input type="checkbox" :checked="isDateCovered(e.date)" @change="toggleDateCovered(e.date)" />
            <span>{{ formatDateEntry(e) }}</span>
          </label>
        </div>
      </div>
      <div v-if="form.excused" class="asp-line">
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
import { ref, computed } from 'vue';
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
const studentName = computed(() => {
  const s = props.student || {};
  return [s.first_name, s.middle_name, s.last_name, s.suffix].filter(Boolean).join(' ') || '-';
});
const programYear = computed(() => [props.student?.program, props.student?.year_level && `Year ${props.student.year_level}`].filter(Boolean).join(', ') || '-');
const issuedOn = computed(() => longDate(props.issuedAt || today));

// "Add Date" row - an entry is only pushed onto form.admission_dates once
// it passes these checks, so the list itself is always clean.
const newDate     = ref('');
const newDuration = ref('whole');
const newTimeIn   = ref('');
const newTimeOut  = ref('');
const addDateError = ref('');

function addDate() {
  addDateError.value = '';
  if (!newDate.value) { addDateError.value = 'Pick a date first.'; return; }
  if (!Array.isArray(form.value.admission_dates)) { form.value.admission_dates = []; }
  const list = form.value.admission_dates;
  if (list.some(e => e.date === newDate.value)) { addDateError.value = 'That date was already added.'; return; }
  if (newDuration.value === 'half' && newTimeIn.value && newTimeOut.value && newTimeOut.value <= newTimeIn.value) {
    addDateError.value = 'The end time must be after the start time.';
    return;
  }
  list.push({
    date:     newDate.value,
    duration: newDuration.value,
    time_in:  newDuration.value === 'half' ? (newTimeIn.value || '') : '',
    time_out: newDuration.value === 'half' ? (newTimeOut.value || '') : '',
  });
  list.sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  // Panel fix (11/10/2026, items #6/#7): a newly added date is covered by
  // the verdict by default, same as every other date - only materialize
  // excused_dates here if it's already been customized (undefined means
  // "every date is covered", so a new date is already included implicitly).
  if (Array.isArray(form.value.excused_dates)) {
    form.value.excused_dates.push(newDate.value);
  }
  newDate.value = '';
  newDuration.value = 'whole';
  newTimeIn.value = '';
  newTimeOut.value = '';
}
function removeDate(i) {
  const [removed] = form.value.admission_dates.splice(i, 1);
  if (removed && Array.isArray(form.value.excused_dates)) {
    const idx = form.value.excused_dates.indexOf(removed.date);
    if (idx !== -1) form.value.excused_dates.splice(idx, 1);
  }
}

// Panel fix (11/10/2026, items #6/#7): form.excused_dates is the explicit
// subset of admission_dates this verdict covers. Left undefined (a brand
// new slip, or one saved before this change), every date counts as
// covered - the array is only created once GCU actually unchecks one.
function isDateCovered(dateStr) {
  if (!Array.isArray(form.value.excused_dates)) return true;
  return form.value.excused_dates.includes(dateStr);
}
function toggleDateCovered(dateStr) {
  if (!Array.isArray(form.value.excused_dates)) {
    // First time this is touched: start from "every date covered" and
    // remove just the one being unchecked.
    form.value.excused_dates = (form.value.admission_dates || [])
      .map(e => e.date)
      .filter(d => d !== dateStr);
    return;
  }
  const idx = form.value.excused_dates.indexOf(dateStr);
  if (idx === -1) form.value.excused_dates.push(dateStr);
  else form.value.excused_dates.splice(idx, 1);
}

// Live count, so staff see it update as dates are added/removed - the
// backend recomputes the same way and that's what actually gets saved.
const daysCountDisplay = computed(() => {
  const dates = form.value.admission_dates || [];
  if (!dates.length) return '-';
  const total = dates.reduce((s, e) => s + (e.duration === 'half' ? 0.5 : 1), 0);
  const str = String(parseFloat(total.toFixed(1)));
  return `${str} ${str === '1' ? 'day' : 'days'}`;
});

// Panel fix (items #6/#7): one formatted line summarizing the same dates
// already entered in "Date/s Absent/Tardy" above, reused in the
// Excused/Unexcused verdict so GCU never retypes a date/time there.
function formatDateEntry(e) {
  let s = longDate(e.date);
  if (e.duration === 'half') {
    s += ', ';
    if (e.time_in) {
      s += formatTime12(e.time_in);
      if (e.time_out) s += ` - ${formatTime12(e.time_out)}`;
    }
    s += ' (Half Day)';
  } else {
    s += ' (Whole Day)';
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
.asp-inputs{display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1}
.asp-inputs .ifi{width:auto;flex:0 1 150px;padding:6px 8px}
.asp-fill{flex:1;min-width:120px;padding:6px 8px}
.asp-sub{font-weight:700;margin-top:6px}
.asp-checks{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px 32px;padding-left:4px}
.asp-checks.one{grid-template-columns:minmax(0,1fr)}
.asp-check{display:flex;align-items:center;gap:9px;min-width:0}
.asp-check.wide{grid-column:1/-1}
.asp-check span{overflow-wrap:anywhere}
.asp-check input[type=checkbox],.asp-check input[type=radio]{width:15px;height:15px;accent-color:var(--moss);flex-shrink:0}
/* A saved slip: ticks stay visible, but nothing can be changed - not even by
   clicking the label text (that used to toggle the box). Editing happens only
   in the "Update Admission Slip" window. */
.asp.ro input,.asp.ro label{pointer-events:none;cursor:default}
.asp.ro input:disabled{opacity:1}
.asp-verdict{display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--cloud);padding-top:14px}
.asp-sign{align-self:center;text-align:center;margin-top:14px;min-width:220px}
.asp-signname{font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--ink);padding:0 10px 2px}
.asp-signlabel{font-size:11.5px;color:var(--stone);margin-top:3px}
/* Date/s added so far - same underlined-row look as the rest of the paper
   form, not a web-app "badge" list, so a slip with several dates still
   reads like one document. */
.asp-datelist{display:flex;flex-direction:column}
.asp-dateitem{display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid var(--silver);padding:4px 2px 6px;font-size:13px;font-style:italic;color:var(--forest)}
.asp-datedel{border:none;background:transparent;color:var(--stone);font-size:17px;line-height:1;cursor:pointer;padding:0 2px;font-style:normal}
.asp-datedel:hover{color:var(--red)}
.asp-addrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:2px}
.asp-addrow .ifi{width:auto;flex:0 1 150px;padding:6px 8px}
.asp-radioinline{display:flex;align-items:center;gap:4px;font-size:12.5px;white-space:nowrap}
.asp-radioinline input{width:14px;height:14px;accent-color:var(--moss)}
@media (max-width:600px){
  .asp-grid,.asp-checks{grid-template-columns:minmax(0,1fr)}
  .asp-doc{flex-direction:column}
}
</style>