<!--
  The notice a person must agree to - as a modal, the first time they click
  Sign In (or Register) each visit: the text (default slot), a checkbox they
  have to tick, then Continue. There is no Decline button; closing the modal
  without ticking and continuing simply leaves them signed out. Used for the
  student Data Privacy Notice and the personnel Confidentiality Agreement.
-->
<template>
  <div>
    <div ref="textEl" class="cg-text" tabindex="0" @scroll="checkEnd">
      <slot />
    </div>

    <label class="cg-check" :class="{ on: agreed, off: !atEnd }">
      <input v-model="agreed" type="checkbox" :disabled="!atEnd" />
      <span>{{ checkboxLabel }}</span>
    </label>

    <div class="cg-actions">
      <button type="button" class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="!agreed" @click="$emit('accept')">Continue</button>
    </div>
    <div v-if="!atEnd" class="cg-hint">Scroll to the end of the notice to enable the checkbox.</div>
    <div v-else-if="!agreed" class="cg-hint">Tick the box above to continue.</div>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { hashText, rememberConsent, consentStillValid, clearConsent } from '../utils/consentSkip';

// `type` ('staff' / 'student') is what the ticked box is remembered under.
// `visible` is whether the parent's modal wrapper is actually shown right
// now (Login.vue/StudentLogin.vue keep this component mounted-but-hidden
// behind v-show from page load, so its "already agreed" check can run
// immediately - see onMounted below). While hidden (display:none), the
// text box has no real layout - clientHeight/scrollHeight both read 0 - so
// checking whether it needs scrolling has to wait until it's actually
// visible, not happen once at mount.
const props = defineProps({
  checkboxLabel: { type: String, required: true },
  type: { type: String, default: '' },
  visible: { type: Boolean, default: true },
});
const emit = defineEmits(['accept']);

const agreed = ref(false);

// The checkbox stays disabled until the notice has been scrolled to the
// bottom - or is already fully visible with nothing to scroll.
const textEl = ref(null);
const atEnd = ref(false);
function checkEnd() {
  const el = textEl.value;
  if (!el) return;
  atEnd.value = el.scrollTop + el.clientHeight >= el.scrollHeight - 4;
}
watch(() => props.visible, (shown) => {
  if (shown) nextTick(checkEnd);
});
let textHash = '';
onMounted(() => {
  if (props.visible) checkEnd(); // a short notice that needs no scrolling is already "read"
  window.addEventListener('resize', checkEnd);
  // Already agreed to this exact text on this device before (saved in
  // localStorage - see consentSkip.js): come back ticked, with the checkbox
  // and Continue already enabled, and tell the parent right away so it can
  // skip showing this as a required step at all (it's kept mounted, just
  // hidden, precisely so this check runs on page load).
  textHash = hashText(textEl.value?.textContent);
  if (props.type && consentStillValid(props.type, textHash)) {
    atEnd.value = true;
    agreed.value = true;
    emit('accept');
  }
});
watch(agreed, (on) => {
  if (!props.type) return;
  if (on) rememberConsent(props.type, textHash || hashText(textEl.value?.textContent));
  else clearConsent(props.type);
});
onBeforeUnmount(() => window.removeEventListener('resize', checkEnd));
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
.cg-check.off { opacity: .55; cursor: not-allowed; }
.cg-check.off input { cursor: not-allowed; }
.cg-check.on { border-color: var(--moss); background: var(--foam); }
.cg-check input { width: 17px; height: 17px; margin-top: 1px; flex: none; accent-color: var(--moss); cursor: pointer; }

.cg-actions { display: flex; gap: 9px; }
.cg-actions .ibtn { flex: 1; justify-content: center; }
.cg-hint { margin-top: 8px; text-align: center; font-size: 11.5px; color: var(--fog); }
</style>