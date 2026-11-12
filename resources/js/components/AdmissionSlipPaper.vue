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

      <!-- Read-Only View -->
      <div v-if="readonly" class="asp-v" style="flex:1">
        <template v-if="(form.admission_dates || []).length">
          <div v-for="(e, i) in form.admission_dates" :key="i">{{ formatDateEntry(e) }}</div>
        </template>
        <template v-else>-</template>
      </div>

      <!-- Edit View -->
      <div v-else style="flex:1;display:flex;flex-direction:column;gap:8px;min-width:240px">
        <div v-if="(form.absence_ranges || []).length" class="asp-datelist">
          <div v-for="(range, i) in form.absence_ranges" :key="i" class="asp-rangeitem">
            
            <div class="asp-input-group">
              <input v-model="range.startDate" type="date" class="ifi" :max="today" @change="syncAdmissionDates" aria-label="Date" />
              <template v-if="range.isRange">
                <span class="asp-range-to">to</span>
                <input v-model="range.endDate" type="date" class="ifi" :max="today" :min="range.startDate" @change="syncAdmissionDates" aria-label="End date" />
              </template>
            </div>

            <div class="asp-input-group">
              <input v-model="range.startTime" type="time" class="ifi" @change="syncAdmissionDates" aria-label="Class time from" title="Class time (from)" />
              <span class="asp-range-to">to</span>
              <input v-model="range.endTime" type="time" class="ifi" @change="syncAdmissionDates" aria-label="Class time to" title="Class time (to)" />
            </div>

            <button type="button" class="asp-datedel" @click="removeRange(i)" aria-label="Remove this entry">&times;</button>
          </div>
        </div>
        <div class="asp-addrow">
          <button type="button" class="ibtn ibtn-o ibtn-sm" @click="addDate">+ Add Date</button>
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

    <div class="asp-footer-note">
      This document is not official unless signed by a guidance counselor.
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

// Initialize / migrate on every model swap.
watch(() => props.modelValue, (newVal) => {
  if (!newVal) return;
  if (!Array.isArray(newVal.admission_dates)) newVal.admission_dates = [];
  if (!Array.isArray(newVal.absence_ranges)) {
    newVal.absence_ranges = [];
    newVal.admission_dates.forEach(e => {
      newVal.absence_ranges.push({
        startDate: e.date,
        endDate:   e.date,
        isRange:   false,
        startTime: e.time_in  || '08:00',
        endTime:   e.time_out || '17:00',
      });
    });
  } else {
    newVal.absence_ranges.forEach(r => {
      if (!r.startTime) r.startTime = '08:00';
      if (!r.endTime) r.endTime = '17:00';
    });
  }
}, { immediate: true });

function addDate() {
  if (!Array.isArray(form.value.absence_ranges)) form.value.absence_ranges = [];
  form.value.absence_ranges.push({
    startDate: '',
    endDate:   '',
    isRange:   false,
    startTime: '08:00',
    endTime:   '17:00',
  });
}

function addRange() {
  if (!Array.isArray(form.value.absence_ranges)) form.value.absence_ranges = [];
  form.value.absence_ranges.push({
    startDate: '',
    endDate:   '',
    isRange:   true,
    startTime: '08:00',
    endTime:   '17:00',
  });
}

function removeRange(i) {
  if (!Array.isArray(form.value.absence_ranges)) return;
  form.value.absence_ranges.splice(i, 1);
  syncAdmissionDates();
}

function expandRanges() {
  const out = [];
  const seen = new Set();
  (form.value.absence_ranges || []).forEach(range => {
    if (!range || !range.startDate) return;
    const start = new Date(range.startDate + 'T00:00:00');
    if (isNaN(start.getTime())) return;
    let end = start;
    
    if (range.isRange && range.endDate) {
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
          time_in:  range.startTime || '08:00',
          time_out: range.endTime   || '17:00',
        });
      }
      cur.setDate(cur.getDate() + 1);
    }
  });
  out.sort((a, b) => (a.date < b.date ? -1 : a.date > b.date ? 1 : 0));
  return out;
}

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

const daysCountDisplay = computed(() => {
  const dates = form.value.admission_dates || [];
  if (!dates.length) return '-';
  const str = String(dates.length);
  return `${str} ${str === '1' ? 'day' : 'days'}`;
});

function formatDateEntry(e) {
  let s = longDate(e.date);
  const tIn = formatTime12(e.time_in || '08:00');
  const tOut = formatTime12(e.time_out || '17:00');
  s += `, ${tIn} - ${tOut}`;
  return s;
}

const admissionDatesText = computed(() => {
  const dates = (form.value.admission_dates || []).filter(e => isDateCovered(e.date));
  return dates.length ? dates.map(formatDateEntry).join('; ') : '-';
});
</script>

<style scoped>
.asp{background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);padding:22px 26px;font-size:13.5px;color:var(--ink);display:flex;flex-direction:column;gap:14px;position:relative;}
.asp-doc{display:flex;justify-content:space-between;gap:12px;font-size:11px;color:var(--stone);border-bottom:1px solid var(--cloud);padding-bottom:8px;}
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

.asp-footer-note {
  text-align: center;
  font-size: 11.5px;
  font-style: italic;
  color: var(--stone);
  margin-top: 20px;
  padding-top: 14px;
  border-top: 1px dashed var(--silver);
}

.asp-datelist{display:flex;flex-direction:column;gap:6px;}
.asp-rangeitem{display:flex;flex-wrap:wrap;gap:12px;align-items:center;border-bottom:1px solid var(--silver);padding:8px 2px;}
.asp-input-group{display:flex;align-items:center;gap:6px;}
.asp-rangeitem .ifi{width:auto;flex:0 1 135px;padding:5px 7px;font-size:12px;}
.asp-rangeitem input[type="time"]{flex:0 1 100px;}
.asp-range-to{color:var(--stone);font-size:11.5px;white-space:nowrap;}
.asp-rangeitem .asp-datedel{margin-left:auto;}
.asp-datedel{border:none;background:transparent;color:var(--stone);font-size:17px;line-height:1;cursor:pointer;padding:0 4px;font-style:normal;}
.asp-datedel:hover{color:var(--red);}
.asp-addrow{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:4px;}

@media (max-width:600px){
  .asp-grid,.asp-checks{grid-template-columns:minmax(0,1fr)}
  .asp-doc{flex-direction:column}
  .asp-input-group{flex:1 1 100%;}
  .asp-rangeitem .ifi{flex:1 1 auto;}
  .asp-rangeitem .asp-datedel{margin-left:0;}
}
</style>