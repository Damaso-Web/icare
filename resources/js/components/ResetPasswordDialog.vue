<!--
  Administrator "Reset Password" confirmation for a student or an employee.
  Shows who is affected, what will happen, when/how/by whom the password was
  last changed (stronger warning if within the last hour), and requires a reason
  from the list for this record type ("Other" needs a note). The parent does the
  API call on `confirm`.

  props: kind = 'student' | 'employee', name, fetchInfo() -> { last_changed_at, how, within_hour }
  emits: confirm({ reason, note }), cancel
-->
<template>
  <ReasonDialog
    :open="open"
    :title="kind === 'student' ? 'Reset Student Password?' : 'Reset Employee Password?'"
    :message="message"
    :reasons="reasons"
    reason-label="Reason for reset"
    confirm-text="Reset Password"
    other-placeholder="Briefly describe the reason..."
    :danger="false"
    :busy="busy"
    :error="error"
    @confirm="$emit('confirm', $event)"
    @cancel="$emit('cancel')"
  >
    <div v-if="loading" style="font-size:12px;color:var(--fog)">Loading password details...</div>
    <div v-else class="rp-info">
      <div v-if="info?.last_changed_at">Password last changed: <strong>{{ fmt(info.last_changed_at) }}</strong><span v-if="info.how"> ({{ info.how }})</span>.</div>
      <div v-else>No record of a previous password change.</div>
    </div>
    <div v-if="info?.within_hour" class="rp-warn">
      Warning: this password was changed less than an hour ago. Resetting it now will replace the password the account owner just set.
    </div>
    <div class="rp-note">
      Use this only as a last resort, when the {{ kind === 'student' ? 'student' : 'employee' }} cannot use "Forgot password?" on the login page.
    </div>
  </ReasonDialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import ReasonDialog from './ReasonDialog.vue';
import { PASSWORD_RESET_REASONS } from '../constants/accountReasons';

const props = defineProps({
  open: Boolean,
  kind: { type: String, default: 'student' },
  name: { type: String, default: '' },
  fetchInfo: { type: Function, default: null },
  busy: Boolean,
  error: { type: String, default: '' },
});
defineEmits(['confirm', 'cancel']);

const info = ref(null);
const loading = ref(false);
const reasons = computed(() => PASSWORD_RESET_REASONS[props.kind === 'student' ? 'student' : 'employee']);
const esc = (t) => String(t).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const message = computed(() =>
  `A temporary password will be generated for <strong>${esc(props.name)}</strong> and will replace the existing password. They will be signed out of all devices, notified by email, and required to change the temporary password at their next login.`);
const fmt = (d) => new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });

watch(() => props.open, async (v) => {
  if (!v) return;
  info.value = null;
  if (!props.fetchInfo) return;
  loading.value = true;
  try { info.value = await props.fetchInfo(); } catch { info.value = null; }
  finally { loading.value = false; }
});
</script>

<style scoped>
.rp-info { font-size: 12.5px; color: var(--slate); background: var(--snow); border: 1px solid var(--cloud); border-radius: var(--r-sm); padding: 10px 12px; line-height: 1.55; }
.rp-warn { font-size: 12.5px; color: var(--red); background: var(--red-lt); border: 1px solid #f5c0c0; border-radius: var(--r-sm); padding: 10px 12px; line-height: 1.55; font-weight: 600; }
.rp-note { font-size: 12px; color: var(--stone); line-height: 1.5; }
</style>