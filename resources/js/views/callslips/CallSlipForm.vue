<template>
  <div style="max-width:720px;margin:0 auto;padding:32px 24px;background:#fff;font-family:var(--sans, sans-serif)">
    <div class="no-print" style="display:flex;justify-content:space-between;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back
      </button>
      <button class="ibtn ibtn-p ibtn-sm" @click="window.print()">
        <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
      </button>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <div style="text-align:center;border-bottom:2px solid var(--forest);padding-bottom:16px;margin-bottom:20px">
        <div style="font-size:11px;color:var(--stone)">Benguet State University - Office of Student Services</div>
        <h1 style="margin:6px 0 2px;font-size:20px;color:var(--forest)">Call Slip Form</h1>
      </div>

      <div style="padding:10px 14px;border:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow);margin-bottom:20px;font-size:11px;color:var(--stone)">
        <div>
          <div><strong>Document Code:</strong> QF-OSS-02</div>
          <div><strong>Revision No.:</strong> 01</div>
        </div>
        <div style="text-align:right">
          <div><strong>Date Issued:</strong> {{ formatDate(new Date()) }}</div>
          <div><strong>Ctrl No.:</strong> CS-{{ appointment.id }}</div>
        </div>
      </div>

      <section style="margin-bottom:18px">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Student Information</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
          <div><strong>Name:</strong> {{ appointment.student?.last_name }}, {{ appointment.student?.first_name }} {{ appointment.student?.middle_name }}</div>
          <div><strong>Student ID:</strong> {{ appointment.student?.student_id }}</div>
          <div><strong>College / Program:</strong> {{ appointment.student?.college }} - {{ appointment.student?.program }}</div>
          <div><strong>Contact:</strong> {{ appointment.student?.contact_number || '-' }}</div>
        </div>
      </section>

      <section style="margin-bottom:18px">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Missed / Rescheduled Appointment</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">
          <div><strong>Appointment Code:</strong> {{ appointment.appointment_code }}</div>
          <div><strong>Type:</strong> {{ toTitleCase(appointment.appointment_type) }}</div>
          <div><strong>Original Date:</strong> {{ formatDate(appointment.appointment_date) }} · {{ appointment.start_time }}</div>
          <div><strong>Unit:</strong> {{ appointment.unit }}</div>
          <div><strong>Times Rescheduled:</strong> {{ appointment.reschedule_count || 0 }}</div>
          <div><strong>Status:</strong> {{ toTitleCase(appointment.status) }}</div>
        </div>
      </section>

      <section style="margin-bottom:18px">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Reason for Call Slip</h2>
        <p style="font-size:13px;line-height:1.6">
          <template v-if="appointment.no_show_escalated">Student did not show up for the scheduled appointment.</template>
          <template v-else-if="(appointment.reschedule_count || 0) >= 3">Appointment has been rescheduled {{ appointment.reschedule_count }} times, reaching the office's reschedule limit.</template>
          <template v-else>See notes below.</template>
        </p>
      </section>

      <section style="margin-bottom:18px" v-if="appointment.call_slip_notes">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Notes</h2>
        <p style="font-size:13px;line-height:1.6;white-space:pre-line">{{ appointment.call_slip_notes }}</p>
      </section>

      <section style="margin-bottom:18px">
        <h2 style="font-size:13px;text-transform:uppercase;letter-spacing:.5px;color:var(--forest);border-bottom:1px solid var(--cloud);padding-bottom:4px;margin-bottom:8px">Call Slip Stage</h2>
        <div style="font-size:13px">{{ appointment.call_slip_stage ? toTitleCase(appointment.call_slip_stage) : 'Pending action' }}</div>
      </section>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:56px;font-size:12.5px">
        <div style="border-top:1px solid var(--ink);padding-top:6px;text-align:center">Guidance Counselor Signature</div>
        <div style="border-top:1px solid var(--ink);padding-top:6px;text-align:center">Student Signature</div>
      </div>

      <div style="margin-top:40px;font-size:11px;color:var(--stone);text-align:center;border-top:1px solid var(--cloud);padding-top:10px">
        This form is issued by the Office of Student Services for follow-up purposes only.
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { appointmentAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';

const route = useRoute();
const loading = ref(true);
const appointment = ref({});

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(async () => {
  try {
    const res = await appointmentAPI.show(route.params.id);
    appointment.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style>
@media print {
  .no-print { display: none !important; }
}
</style>
