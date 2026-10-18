<!--
  Finds one student by their exact Student ID Number (students cannot browse
  the list). Emits the found student { id, name, college, program }.
-->
<template>
  <div>
    <label class="ifl">Student ID Number <span style="color:var(--red)">*</span></label>
    <div style="display:flex;gap:8px">
      <input v-model="idNo" class="ifi" maxlength="50" placeholder="Enter the student's ID Number" :disabled="!!modelValue" @keyup.enter="find" />
      <button v-if="!modelValue" type="button" class="ibtn ibtn-o" :disabled="busy || !idNo.trim()" @click="find">{{ busy ? 'Searching...' : 'Find' }}</button>
      <button v-else type="button" class="ibtn ibtn-o" @click="clear">Change</button>
    </div>
    <div v-if="modelValue" style="margin-top:8px;font-size:13px;color:var(--ink);background:var(--snow);border-radius:var(--r-sm);padding:8px 12px">
      <strong>{{ modelValue.name }}</strong>
      <span v-if="modelValue.college" style="color:var(--stone)"> · {{ modelValue.college }}</span>
    </div>
    <div v-if="error" style="margin-top:6px;font-size:12px;color:var(--red)">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { studentApi } from '../api/index';

const props = defineProps({ modelValue: { type: Object, default: null } });
const emit = defineEmits(['update:modelValue']);

const idNo = ref('');
const busy = ref(false);
const error = ref('');

async function find() {
  error.value = '';
  busy.value = true;
  try {
    const res = await studentApi.post('/student/outreach/lookup', { student_id: idNo.value.trim() });
    emit('update:modelValue', res.data);
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not look that student up. Please try again.';
  } finally {
    busy.value = false;
  }
}
function clear() {
  idNo.value = '';
  emit('update:modelValue', null);
}
</script>