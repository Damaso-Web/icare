<!--
  Admission Slip (QF-OSS-GCU-09), laid out like the paper form. The same
  layout is used to fill it in (readonly = false, v-model on the form) and to
  show an issued slip (readonly = true).

  form: { admission_date, admission_time_in, admission_time_out, excused ('1' | '0' | ''),
          remarks, days_count, causes: [], cause_activity, cause_others, actions: [], action_others }
-->
<template>
  <div class="asp" :class="{ ro: readonly }">
    <div v-if="showDoc" class="asp-doc">
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

    <div class="asp-line asp-wrap">
      <span class="asp-k">Date/s Absent/Tardy:</span>
      <template v-if="readonly">
        <span class="asp-v">{{ longDate(form.admission_date) }}<template v-if="form.admission_time_in">, {{ formatTime12(form.admission_time_in) }}<template v-if="form.admission_time_out"> - {{ formatTime12(form.admission_time_out) }}</template></template></span>
      </template>
      <span v-else class="asp-inputs">
        <input v-model="form.admission_date" type="date" class="ifi" :max="today" aria-label="Date absent or tardy" />
        <input v-model="form.admission_time_in" type="time" class="ifi" aria-label="Class time from" title="Class time (from)" />
        <span style="color:var(--stone)">to</span>
        <input v-model="form.admission_time_out" type="time" class="ifi" aria-label="Class time to" title="Class time (to)" />
      </span>
    </div>

    <div class="asp-line">
      <span class="asp-k">Number of day/s absent/tardy:</span>
      <span v-if="readonly" class="asp-v">{{ form.days_count || '-' }}</span>
      <input v-else v-model="form.days_count" class="ifi asp-fill" maxlength="50" placeholder="e.g. 1 day, or 8-9 AM" />
    </div>

    <div class="asp-sub">Cause/s of Absences:</div>
    <div class="asp-checks">
      <label v-for="c in CAUSES" :key="c.key" class="asp-check" :class="{ wide: c.key === 'official_activity' || c.key === 'others' }">
        <input type="checkbox" :value="c.key" v-model="form.causes" :tabindex="readonly ? -1 : 0" />
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
        <input type="checkbox" :value="a.key" v-model="form.actions" :tabindex="readonly ? -1 : 0" />
        <span>{{ a.label }}</span>
        <template v-if="a.key === 'others' && (form.actions.includes('others') || !readonly)">
          <span v-if="readonly" class="asp-v">{{ form.action_others }}</span>
          <input v-else v-model="form.action_others" class="ifi asp-fill" maxlength="255" :disabled="!form.actions.includes('others')" />
        </template>
      </label>
    </div>

    <div class="asp-verdict">
      <label class="asp-check wide">
        <input type="radio" value="1" v-model="form.excused" :tabindex="readonly ? -1 : 0" />
        <span class="asp-k">Excused:</span>
        <span v-if="readonly" class="asp-v">{{ form.excused === '1' ? form.remarks : '' }}</span>
        <input v-else-if="form.excused === '1'" v-model="form.remarks" class="ifi asp-fill" maxlength="1000" placeholder="e.g. October 13, 2026, 8-9 AM only" />
      </label>
      <label class="asp-check wide">
        <input type="radio" value="0" v-model="form.excused" :tabindex="readonly ? -1 : 0" />
        <span class="asp-k">Unexcused:</span>
        <span v-if="readonly" class="asp-v">{{ form.excused === '0' ? form.remarks : '' }}</span>
        <input v-else-if="form.excused === '0'" v-model="form.remarks" class="ifi asp-fill" maxlength="1000" placeholder="details" />
      </label>
    </div>

    <div class="asp-sign">
      <div class="asp-signname">{{ personnel || '-' }}</div>
      <div class="asp-signlabel">Attending OSS Personnel</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
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
</script>

<style scoped>
.asp{background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);padding:16px 18px;font-size:13px;color:var(--ink);display:flex;flex-direction:column;gap:10px}
.asp-doc{display:flex;justify-content:space-between;gap:12px;font-size:11px;color:var(--stone);border-bottom:1px solid var(--cloud);padding-bottom:8px}
.asp-title{text-align:center;font-weight:700;letter-spacing:.8px;text-transform:uppercase;font-size:13px}
.asp-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:6px 16px}
.asp-line{display:flex;align-items:baseline;gap:6px;min-width:0}
.asp-wrap{flex-wrap:wrap}
.asp-k{font-weight:600;white-space:nowrap}
.asp-v{flex:1;min-width:40px;border-bottom:1px solid var(--silver);padding:0 4px 1px;color:var(--forest);font-style:italic}
.asp-inputs{display:flex;flex-wrap:wrap;gap:6px;align-items:center;flex:1}
.asp-inputs .ifi{width:auto;flex:0 1 150px;padding:6px 8px}
.asp-fill{flex:1;min-width:120px;padding:6px 8px}
.asp-sub{font-weight:700;margin-top:4px}
.asp-checks{display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;padding-left:6px}
.asp-checks.one{grid-template-columns:1fr}
.asp-check{display:flex;align-items:center;gap:8px;min-width:0}
.asp-check.wide{grid-column:1/-1}
.asp-check input[type=checkbox],.asp-check input[type=radio]{width:15px;height:15px;accent-color:var(--moss);flex-shrink:0}
/* A saved slip: ticks stay crisp, but nothing can be changed. */
.asp.ro input{pointer-events:none}
.asp-verdict{display:flex;flex-direction:column;gap:8px;border-top:1px solid var(--cloud);padding-top:10px}
.asp-sign{align-self:center;text-align:center;margin-top:10px;min-width:220px}
.asp-signname{font-weight:700;text-transform:uppercase;border-bottom:1px solid var(--ink);padding:0 10px 2px}
.asp-signlabel{font-size:11.5px;color:var(--stone);margin-top:3px}
@media (max-width:600px){
  .asp-grid,.asp-checks{grid-template-columns:1fr}
  .asp-doc{flex-direction:column}
}
</style>
