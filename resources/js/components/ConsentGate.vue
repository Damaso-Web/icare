<!--
  The notice a person must agree to before the sign-in form is shown: the
  text (default slot), a checkbox they have to tick, then Continue / Decline.
  Used for the student Data Privacy Notice and the personnel Confidentiality
  Agreement.
-->
<template>
  <div>
    <div ref="textEl" class="cg-text" tabindex="0">
      <slot />
    </div>

    <label class="cg-check" :class="{ on: agreed }">
      <input v-model="agreed" type="checkbox" />
      <span>{{ checkboxLabel }}</span>
    </label>

    <div class="cg-actions">
      <button type="button" class="ibtn ibtn-p" :disabled="!agreed" @click="$emit('accept')">Continue</button>
      <button type="button" class="ibtn ibtn-g" @click="$emit('decline')">Decline</button>
    </div>
    <div v-if="!agreed" class="cg-hint">Tick the box above to continue.</div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ checkboxLabel: { type: String, required: true } });
defineEmits(['accept', 'decline']);

const agreed = ref(false);
</script>

<style scoped>
.cg-text {
  max-height: 44vh; overflow-y: auto; padding: 16px 18px; margin-bottom: 14px;
  border: 1px solid #e1ebe5; border-radius: 12px; background: #fbfdfc;
  font-size: 12.5px; line-height: 1.7; color: var(--slate); outline: none;
}
.cg-text:focus-visible { border-color: var(--moss); }
.cg-text :deep(h3) { font-size: 14.5px; font-weight: 700; color: var(--forest); margin: 0 0 8px; }
.cg-text :deep(h3:not(:first-child)) { margin-top: 18px; padding-top: 14px; border-top: 1px dashed var(--silver); }
.cg-text :deep(p) { margin: 0 0 10px; }
.cg-text :deep(p:last-child) { margin-bottom: 0; }
.cg-text :deep(ol) { margin: 0 0 10px; padding-left: 18px; }
.cg-text :deep(li) { margin-bottom: 8px; }
.cg-text :deep(strong) { color: var(--ink); }

.cg-check {
  display: flex; align-items: flex-start; gap: 10px; padding: 11px 13px; margin-bottom: 14px;
  border: 1.5px solid var(--silver); border-radius: 12px; background: #fff; cursor: pointer;
  font-size: 12.5px; line-height: 1.5; color: var(--ink); transition: border-color .13s, background .13s;
}
.cg-check.on { border-color: var(--moss); background: var(--foam); }
.cg-check input { width: 17px; height: 17px; margin-top: 1px; flex: none; accent-color: var(--moss); cursor: pointer; }

.cg-actions { display: flex; gap: 9px; }
.cg-actions .ibtn { flex: 1; justify-content: center; }
.cg-hint { margin-top: 8px; text-align: center; font-size: 11.5px; color: var(--fog); }
</style>
