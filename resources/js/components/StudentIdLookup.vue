<!--
  Finds one student by their exact Student ID Number or by their full name
  (students cannot browse the list). If two students share a name, the
  student picks the right one. Emits the chosen { id, name, college, program, is_me }.
  allowSelf: when false (complaints), choosing yourself is refused here.
-->
<template>
  <div>
    <label class="ifl">Student ID Number or Full Name <span style="color:var(--red)">*</span></label>
    <div style="display:flex;gap:8px">
      <input v-model="query" class="ifi" maxlength="100" placeholder="Student ID Number, or full name (e.g. Juan Dela Cruz)" :disabled="!!modelValue" @keyup.enter="find" />
      <button v-if="!modelValue" type="button" class="ibtn ibtn-o" :disabled="busy || !query.trim()" @click="find">{{ busy ? 'Searching...' : 'Find' }}</button>
      <button v-else type="button" class="ibtn ibtn-o" @click="clear">Change</button>
    </div>
    <div style="margin-top:4px;font-size:11px;color:var(--fog)">Type the exact ID Number, or the first and last name of the student.</div>
    <div v-if="modelValue" style="margin-top:8px;font-size:13px;color:var(--ink);background:var(--snow);border-radius:var(--r-sm);padding:8px 12px">
      <strong>{{ modelValue.name }}</strong>
      <span v-if="modelValue.is_me" style="color:var(--moss)"> (you)</span>
      <span v-if="modelValue.college" style="color:var(--stone)"> · {{ modelValue.college }}</span>
      <span v-if="modelValue.program" style="color:var(--stone)"> · {{ modelValue.program }}</span>
    </div>
    <div v-if="choices.length" style="margin-top:8px;border:1px solid var(--cloud);border-radius:var(--r-sm)">
      <div style="padding:8px 12px;font-size:12px;color:var(--stone)">More than one student matches. Choose the right one:</div>
      <button v-for="c in choices" :key="c.id" type="button" class="pick" @click="choose(c)">
        <strong>{{ c.name }}</strong>
        <span style="color:var(--stone)"> · {{ c.college }} · {{ c.program }}</span>
      </button>
    </div>
    <div v-if="error" style="margin-top:6px;font-size:12px;color:var(--red)">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { studentApi } from '../api/index';

const props = defineProps({
  modelValue: { type: Object, default: null },
  allowSelf: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue']);

const query = ref('');
const busy = ref(false);
const error = ref('');
const choices = ref([]);

function choose(c) {
  error.value = '';
  if (c.is_me && !props.allowSelf) {
    error.value = 'You cannot choose yourself here.';
    return;
  }
  choices.value = [];
  emit('update:modelValue', c);
}

async function find() {
  error.value = '';
  choices.value = [];
  busy.value = true;
  try {
    const res = await studentApi.post('/student/outreach/lookup', { student_id: query.value.trim() });
    const matches = res.data.matches || [];
    const usable = props.allowSelf ? matches : matches.filter(m => !m.is_me);
    if (!usable.length) error.value = 'You cannot choose yourself here.';
    else if (usable.length === 1) choose(usable[0]);
    else choices.value = usable;
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not look that student up. Please try again.';
  } finally {
    busy.value = false;
  }
}
function clear() {
  query.value = '';
  choices.value = [];
  error.value = '';
  emit('update:modelValue', null);
}
</script>

<style scoped>
.pick{display:block;width:100%;text-align:left;background:#fff;border:0;border-top:1px solid var(--cloud);padding:8px 12px;font-size:13px;cursor:pointer}
.pick:hover{background:var(--snow)}
</style>