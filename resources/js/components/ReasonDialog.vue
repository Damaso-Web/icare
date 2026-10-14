<!--
  A confirmation dialog that makes the admin pick a reason from a dropdown
  before the action can go ahead (deactivate / reactivate an account, delete a
  Management record). The matching description appears under the dropdown, and
  "Other" (key 'other') makes the note required.

  props: reasons = [{ key, label, description }]
  emits: confirm({ reason, note }), cancel
  slots: default (extra content above the dropdown, e.g. a warning)
-->
<template>
  <div v-if="open" class="rd-back" @click.self="!busy && $emit('cancel')">
    <div class="rd-card" role="dialog" aria-modal="true">
      <div class="rd-head">{{ title }}</div>
      <div class="rd-body">
        <div v-if="message" class="rd-msg" v-html="message"></div>
        <slot />
        <div v-if="blockedMessage" class="rd-err">{{ blockedMessage }}</div>
        <div>
          <label class="ifl">{{ reasonLabel }} <span style="color:var(--red)">*</span></label>
          <select v-model="reason" class="ifse" :disabled="busy">
            <option value="" disabled>Select a reason</option>
            <option v-for="r in reasons" :key="r.key" :value="r.key">{{ r.label }}</option>
          </select>
        </div>
        <div v-if="current && current.description" class="rd-desc">{{ current.description }}</div>
        <div v-if="showNote">
          <label class="ifl">Note <span v-if="isOther" style="color:var(--red)">*</span><span v-else style="color:var(--fog);font-weight:400"> (optional)</span></label>
          <textarea v-model="note" class="ifi" rows="3" maxlength="500" :disabled="busy" :placeholder="isOther ? otherPlaceholder : 'Add a note (optional)'"></textarea>
          <div v-if="noteError" style="font-size:11px;color:var(--red);margin-top:4px">{{ noteError }}</div>
        </div>
        <div v-if="error" class="rd-err">{{ error }}</div>
        <div class="rd-actions">
          <button class="ibtn" :class="danger ? 'rd-danger' : 'ibtn-p'" :disabled="!valid || busy || !!blockedMessage" @click="$emit('confirm', { reason, note: note.trim() })">{{ busy ? 'Please wait...' : confirmText }}</button>
          <button class="ibtn ibtn-o" :disabled="busy" @click="$emit('cancel')">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  message: { type: String, default: '' },   // trusted HTML built by the caller (names are escaped there)
  reasons: { type: Array, default: () => [] },
  reasonLabel: { type: String, default: 'Reason' },
  confirmText: { type: String, default: 'Confirm' },
  otherPlaceholder: { type: String, default: 'Please describe the reason briefly. Do not include sensitive personal details.' },
  optionalNote: { type: Boolean, default: false }, // show a note box for every reason (required only for Other)
  danger: { type: Boolean, default: true },
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
  blockedMessage: { type: String, default: '' }, // set when the action is not allowed (e.g. record still in use)
});
defineEmits(['confirm', 'cancel']);

const reason = ref('');
const note = ref('');

watch(() => props.open, (v) => { if (v) { reason.value = ''; note.value = ''; } });

const current = computed(() => props.reasons.find(r => r.key === reason.value) || null);
const isOther = computed(() => reason.value === 'other');
const showNote = computed(() => isOther.value || (props.optionalNote && !!reason.value));
const noteError = computed(() => (isOther.value && note.value.trim() && !/\p{L}/u.test(note.value)) ? 'Please explain in words, not numbers only.' : '');
const valid = computed(() => {
  if (!reason.value) return false;
  if (isOther.value) return note.value.trim().length > 0 && !noteError.value;
  return !(note.value.trim() && !/\p{L}/u.test(note.value));
});
</script>

<style scoped>
.rd-back { position: fixed; inset: 0; background: rgba(0,0,0,.42); z-index: 80; display: flex; align-items: center; justify-content: center; padding: 20px; }
.rd-card { background: #fff; border-radius: var(--r-lg); width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto; box-shadow: var(--sh-lg); }
.rd-head { padding: 18px 22px; border-bottom: 1px solid var(--cloud); font-size: 15px; font-weight: 600; color: var(--ink); }
.rd-body { padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
.rd-msg { font-size: 13px; color: var(--slate); line-height: 1.6; }
.rd-desc { font-size: 12px; color: var(--slate); background: var(--snow); border: 1px solid var(--cloud); border-radius: var(--r-sm); padding: 10px 12px; line-height: 1.55; }
.rd-err { font-size: 12px; color: var(--red); background: var(--red-lt); border-radius: var(--r-sm); padding: 8px 10px; }
.rd-actions { display: flex; gap: 8px; }
.rd-danger { background: var(--red-lt); color: var(--red); border: 1.5px solid #f5c0c0; }
.rd-danger:disabled { opacity: .5; cursor: not-allowed; }
</style>