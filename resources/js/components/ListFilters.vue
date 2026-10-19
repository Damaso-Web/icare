<!-- Search + Sort + dropdown filters + Reset, shared by the Sent Referrals, Sent Complaints and Complaints lists. -->
<template>
  <div class="filter-bar" style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <div class="sw" style="flex:1;min-width:200px;max-width:340px">
      <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input v-model="model.search" type="text" class="sin" maxlength="50" :placeholder="placeholder" style="width:100%" @keypress="blockSpecialKeypress" @input="onSearch" />
    </div>
    <select v-model="model.sort" class="fsm" @change="$emit('change')">
      <option value="newest">Sort: Newest First</option>
      <option value="oldest">Sort: Oldest First</option>
    </select>
    <select v-for="s in selects" :key="s.key" v-model="model[s.key]" class="fsm" @change="$emit('change')">
      <option value="">{{ s.all }}</option>
      <option v-for="o in s.options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
    <button class="ibtn ibtn-o ibtn-sm" @click="reset">Reset</button>
  </div>
</template>

<script setup>
import { safeSearchInput, blockSpecialKeypress } from '../utils/validators';

// model: reactive object holding search, sort and one key per select.
const props = defineProps({
  model: { type: Object, required: true },
  selects: { type: Array, default: () => [] },   // [{ key, all, options: [{ value, label }] }]
  placeholder: { type: String, default: 'Search...' },
});
const emit = defineEmits(['change']);

let timer = null;
function onSearch() {
  props.model.search = safeSearchInput(props.model.search);
  clearTimeout(timer);
  timer = setTimeout(() => emit('change'), 350);
}
function reset() {
  props.model.search = '';
  props.model.sort = 'newest';
  props.selects.forEach(s => { props.model[s.key] = ''; });
  emit('change');
}
</script>