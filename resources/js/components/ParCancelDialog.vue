<!--
  Cancel a PAR release appointment (TMDU). Staff choose either to cancel it
  only, or to cancel it and "Schedule for Re-release of PAR" right away with
  a new date and time. Used on Testing Record Details and TMDU Appointments.

    <ParCancelDialog v-if="parCancelTarget" :appointment="parCancelTarget"
                     @close="parCancelTarget = null" @done="reload" />
-->
<template>
  <div style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="!busy && emit('close')">
    <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:460px;overflow:hidden;box-shadow:var(--sh-lg)">
      <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
        <div>
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Cancel PAR Release</div>
          <div style="font-size:12px;color:var(--stone)">{{ appointment.student?.last_name ? `${appointment.student.last_name}, ${appointment.student.first_name} · ` : '' }}{{ formatDate(appointment.appointment_date) }} · {{ formatTime12(appointment.start_time) }}</div>
        </div>
        <button class="ibtn ibtn-g ibtn-sm" :disabled="busy" @click="emit('close')">✕</button>
      </div>
      <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
        <div style="display:flex;flex-direction:column;gap:8px">
          <label style="display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1.5px solid var(--cloud);border-radius:var(--r-sm);cursor:pointer" :style="mode === 'rerelease' ? 'border-color:var(--moss);background:var(--foam)' : ''">
            <input v-model="mode" type="radio" value="rerelease" style="margin-top:3px" />
            <span>
              <span style="display:block;font-size:13px;font-weight:600;color:var(--ink)">Schedule for Re-release of PAR</span>
              <span style="font-size:12px;color:var(--stone)">Cancel this one and set a new date for the student to collect the PAR.</span>
            </span>
          </label>
          <label style="display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1.5px solid var(--cloud);border-radius:var(--r-sm);cursor:pointer" :style="mode === 'cancel' ? 'border-color:var(--red);background:var(--red-lt)' : ''">
            <input v-model="mode" type="radio" value="cancel" style="margin-top:3px" />
            <span>
              <span style="display:block;font-size:13px;font-weight:600;color:var(--ink)">Cancel only</span>
              <span style="font-size:12px;color:var(--stone)">A new PAR release can be scheduled later from the Testing Record.</span>
            </span>
          </label>
        </div>

        <div v-if="mode === 'rerelease'" style="display:flex;flex-direction:column;gap:8px">
          <div>
            <label class="ifl">New Date <span style="color:var(--red)">*</span></label>
            <input v-model="form.appointment_date" type="date" class="ifi" :min="today" />
          </div>
          <div style="display:flex;gap:8px">
            <div style="flex:1">
              <label class="ifl">From <span style="color:var(--red)">*</span></label>
              <input v-model="form.start_time" type="time" min="08:00" max="16:00" class="ifi" />
            </div>
            <div style="flex:1">
              <label class="ifl">To <span style="color:var(--red)">*</span></label>
              <input v-model="form.end_time" type="time" min="08:00" max="16:00" class="ifi" />
            </div>
          </div>
          <div v-if="formError" style="color:var(--red);font-size:11.5px">{{ formError }}</div>
        </div>

        <div>
          <label class="ifl">Reason <span style="color:var(--fog);font-weight:400">(optional)</span></label>
          <input v-model="form.reason" class="ifi" maxlength="500" placeholder="Why is this PAR release being cancelled?" />
        </div>

        <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>

        <div style="display:flex;gap:8px">
          <button
            class="ibtn"
            :class="mode === 'rerelease' ? 'ibtn-p' : ''"
            style="flex:1;justify-content:center"
            :style="mode === 'cancel' ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : ''"
            :disabled="busy || (mode === 'rerelease' && !!formError) || (mode === 'rerelease' && !ready)"
            @click="submit"
          >{{ busy ? 'Saving...' : (mode === 'rerelease' ? 'Cancel and Schedule Re-release' : 'Yes, Cancel PAR Release') }}</button>
          <button class="ibtn ibtn-o" style="flex:1;justify-content:center" :disabled="busy" @click="emit('close')">Go Back</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue';
import { testingAPI } from '../api/index';
import { formatTime12 } from '../utils/validators';

const props = defineProps({ appointment: { type: Object, required: true } });
const emit = defineEmits(['close', 'done']);
const toast = inject('toast', null);

const mode = ref('rerelease');
const form = ref({ appointment_date: '', start_time: '', end_time: '', reason: '' });
const busy = ref(false);
const error = ref('');
const today = new Date().toLocaleDateString('en-CA');

const ready = computed(() => !!(form.value.appointment_date && form.value.start_time && form.value.end_time));
const formError = computed(() => {
  const f = form.value;
  if (f.appointment_date) {
    const day = new Date(f.appointment_date + 'T00:00:00').getDay();
    if (day === 0 || day === 6) return 'TMDU is closed on weekends. Please choose Monday to Friday.';
    if (f.appointment_date < today) return 'Choose today or a later date.';
  }
  if (f.start_time && f.end_time) {
    if (f.start_time < '08:00' || f.end_time > '16:00') return 'PAR release must be between 8:00 AM and 4:00 PM.';
    if (f.start_time >= f.end_time) return 'The end time must be after the start time.';
  }
  return '';
});

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
}

async function submit() {
  busy.value = true;
  error.value = '';
  try {
    const extra = { reason: form.value.reason.trim() || undefined };
    if (mode.value === 'rerelease') {
      Object.assign(extra, { appointment_date: form.value.appointment_date, start_time: form.value.start_time, end_time: form.value.end_time });
    }
    const res = await testingAPI.parAction(props.appointment.id, 'cancel', extra);
    toast?.success(res.data?.message || 'PAR release cancelled.');
    emit('done', res.data);
    emit('close');
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not cancel the PAR release.';
  } finally {
    busy.value = false;
  }
}
</script>
