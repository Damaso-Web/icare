<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Testing</h1>
      <p>Track your TMDU psychological testing referral, from the Assessment of Fees form to your PAR release.</p>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <div v-else-if="records.length === 0" class="empty-state">
      <h3>No testing referrals yet</h3>
      <p>If your counselor refers you to TMDU for psychological testing, it will show up here.</p>
    </div>

    <div v-else style="display:flex;flex-direction:column;gap:14px">
      <div v-for="r in records" :key="r.id" class="icard" style="padding:18px 20px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px">
          <div>
            <div style="font-size:14px;font-weight:600;color:var(--ink)">Testing Referral #{{ r.id }}</div>
            <div style="font-size:12px;color:var(--stone)">Referred {{ formatDate(r.created_at) }}</div>
          </div>
          <span class="ibadge" :class="statusBadge(r.status)">{{ statusLabel(r.status) }}</span>
        </div>

        <!-- Status explainer -->
        <div style="font-size:13px;color:var(--slate);background:var(--snow);border-radius:var(--r-md);padding:10px 12px;margin-bottom:12px">
          {{ statusMessage(r.status) }}
        </div>

        <!-- Step: pick up Assessment of Fees form -->
        <div v-if="r.status === 'pending'" style="font-size:12px;color:var(--stone)">
          Check <router-link :to="{ name: 'student-appointments' }" style="color:var(--moss);font-weight:600">My Appointments</router-link> to set your Assessment of Fees form pickup schedule.
        </div>

        <!-- Step: upload OR photo to request testing schedule -->
        <div v-if="r.status === 'fee_form_pending'" style="border:1px solid var(--mint);background:var(--foam);border-radius:var(--r-md);padding:14px;display:flex;flex-direction:column;gap:10px">
          <div style="font-size:12px;font-weight:600;color:var(--moss)">Request Your Testing Schedule</div>
          <p style="font-size:12px;color:var(--stone);margin:0">Once you've paid at the Cashier, upload a clear photo of your Official Receipt (OR) to request your testing appointment. TMDU will set the date and time for you.</p>
          <input type="file" accept="image/*" class="ifi" @change="(e) => handleFileSelect(e, r.id)" />
          <div v-if="fileNames[r.id]" style="font-size:12px;color:var(--moss)">✓ Selected: {{ fileNames[r.id] }}</div>
          <button class="ibtn ibtn-p ibtn-sm" :disabled="submitting === r.id || !selectedFiles[r.id]" @click="submitOrPhoto(r.id)" style="align-self:flex-start">
            {{ submitting === r.id ? 'Submitting...' : 'Submit OR & Request Schedule' }}
          </button>
        </div>

        <!-- Step: testing scheduled -->
        <div v-if="r.status === 'scheduled' || r.status === 'in_progress'" style="font-size:12px;color:var(--stone)">
          See <router-link :to="{ name: 'student-appointments' }" style="color:var(--moss);font-weight:600">My Appointments</router-link> for your testing date, time, and what to bring.
        </div>

        <!-- Step: PAR scheduled -->
        <div v-if="r.status === 'par_scheduled'" style="font-size:12px;color:var(--stone)">
          See <router-link :to="{ name: 'student-appointments' }" style="color:var(--moss);font-weight:600">My Appointments</router-link> for when to pick up your Psychological Assessment Report (PAR).
        </div>

        <!-- Step: report sent -->
        <div v-if="r.status === 'report_sent'" style="font-size:12px;color:var(--stone)">
          Your PAR has been released and a copy sent to the counselor who referred you.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { studentTestingAPI } from '../../api/index';

const toast   = inject('toast');
const loading = ref(true);
const records = ref([]);
const submitting = ref(null);
const selectedFiles = ref({});
const fileNames = ref({});

function statusLabel(status) {
  return {
    pending:          'Pending',
    fee_form_pending: 'Fee Form Pending',
    or_submitted:     'OR Submitted',
    scheduled:        'Scheduled',
    in_progress:      'In Progress',
    completed:        'Completed',
    par_scheduled:    'PAR Scheduled',
    report_sent:      'Report Sent',
  }[status] || status;
}

function statusBadge(status) {
  return {
    pending:           'ibadge-pending',
    fee_form_pending:  'ibadge-pending',
    or_submitted:      'ibadge-scheduled',
    scheduled:         'ibadge-scheduled',
    in_progress:       'ibadge-in_progress',
    completed:         'ibadge-completed',
    par_scheduled:     'ibadge-scheduled',
    report_sent:       'ibadge-closed',
  }[status] || 'ibadge-pending';
}

function statusMessage(status) {
  return {
    pending:          "Your counselor has referred you to TMDU for psychological testing. Next, you'll set an appointment to pick up your Assessment of Fees form.",
    fee_form_pending: 'Please pick up your Assessment of Fees form, then pay at the Cashier and upload your OR below to request your testing schedule.',
    or_submitted:     'Your OR has been submitted. TMDU will review it and set your testing appointment.',
    scheduled:        'Your psychological testing appointment has been set. Remember to bring your OR and two sharpened pencils with eraser, and arrive 15 minutes early.',
    in_progress:      'Your testing is in progress.',
    completed:        'Your testing has been completed. TMDU will schedule when your PAR (Psychological Assessment Report) will be ready.',
    par_scheduled:    'Your PAR release appointment has been set.',
    report_sent:       'Your PAR has been released and sent to your referring counselor.',
  }[status] || '';
}

async function fetchRecords() {
  loading.value = true;
  try {
    const res = await studentTestingAPI.index();
    records.value = res.data.data || res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function handleFileSelect(event, recordId) {
  const file = event.target.files[0];
  if (file) {
    selectedFiles.value[recordId] = file;
    fileNames.value[recordId] = file.name;
  }
}

async function submitOrPhoto(recordId) {
  const file = selectedFiles.value[recordId];
  if (!file) return;

  submitting.value = recordId;
  try {
    const formData = new FormData();
    formData.append('or_photo', file);
    await studentTestingAPI.requestTesting(recordId, formData);
    toast?.success('OR submitted. TMDU will set your testing schedule.');
    delete selectedFiles.value[recordId];
    delete fileNames.value[recordId];
    await fetchRecords();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to submit OR photo.');
  } finally {
    submitting.value = null;
  }
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => fetchRecords());
</script>