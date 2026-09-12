<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Call Slips</h1>
      <p>Students who missed their appointment and need to be contacted.</p>
    </div>

    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="callSlips.length === 0" class="empty-state">
        <h3>No call slips</h3>
        <p>Students who miss appointments will appear here.</p>
      </div>
      <div v-else>
        <div v-for="a in callSlips" :key="a.id" style="padding:16px 18px;border-bottom:1px solid var(--cloud)">
          <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap">
            <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ a.student?.last_name }}, {{ a.student?.first_name }}</div>
            <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ a.student?.student_id }}</div>
          </div>
          <div style="font-size:11.5px;color:var(--stone);margin-top:2px">
            Missed appointment on {{ formatDate(a.appointment_date) }} at {{ a.start_time }} · {{ a.unit }}
          </div>
          <div style="display:flex;gap:5px;margin-top:6px;flex-wrap:wrap">
            <span class="ibadge" style="background:var(--red-lt);color:var(--red)">No-Show</span>
            <span v-if="a.call_slip_stage" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">{{ toTitleCase(a.call_slip_stage) }}</span>
          </div>
          <div v-if="a.call_slip_notes" style="font-size:12px;color:var(--stone);margin-top:8px;background:var(--snow);padding:8px 10px;border-radius:var(--r-sm)">
            {{ a.call_slip_notes }}
          </div>
          <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <button class="ibtn ibtn-o ibtn-sm" @click="openContact(a)">Mark Contacted</button>
            <button class="ibtn ibtn-sm" style="background:var(--mist);color:var(--moss);border:1.5px solid var(--mint)" @click="reschedule(a)">Send Reschedule Link</button>
            <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="openEscalate(a)">Escalate to Dept Chair</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact/Escalate Modal -->
    <div v-if="showModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ modalMode === 'contact' ? 'Mark as Contacted' : 'Escalate to Department Chair' }}</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label class="ifl">Notes</label>
            <textarea v-model="modalNotes" class="ifta" style="min-height:80px" placeholder="Details about contact attempt or reason for escalation..."></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="submitModal">Confirm</button>
            <button class="ibtn ibtn-o" @click="showModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { callSlipAPI } from '../../api/index';

const toast = inject('toast');

const loading   = ref(true);
const callSlips = ref([]);

const showModal   = ref(false);
const modalMode   = ref('contact');
const modalTarget = ref(null);
const modalNotes  = ref('');

function toTitleCase(str) {
  if (!str) return '';
  return str.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

async function fetchCallSlips() {
  loading.value = true;
  try {
    const res = await callSlipAPI.index();
    callSlips.value = res.data.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openContact(a) {
  modalMode.value = 'contact';
  modalTarget.value = a;
  modalNotes.value = '';
  showModal.value = true;
}

function openEscalate(a) {
  modalMode.value = 'escalate';
  modalTarget.value = a;
  modalNotes.value = '';
  showModal.value = true;
}

async function submitModal() {
  try {
    if (modalMode.value === 'contact') {
      await callSlipAPI.markContacted(modalTarget.value.id, { notes: modalNotes.value });
      toast?.success('Marked as contacted.');
    } else {
      await callSlipAPI.escalate(modalTarget.value.id, { notes: modalNotes.value });
      toast?.success('Escalated to Department Chair.');
    }
    showModal.value = false;
    fetchCallSlips();
  } catch (e) {
    toast?.error('Failed to update call slip.');
  }
}

async function reschedule(a) {
  try {
    await callSlipAPI.reschedule(a.id);
    toast?.success('Reschedule link sent to student.');
    fetchCallSlips();
  } catch (e) {
    toast?.error('Failed to send reschedule link.');
  }
}

onMounted(() => fetchCallSlips());
</script>