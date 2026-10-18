<!--
  Sent Complaints: every complaint this account has filed (staff or student),
  each with a Print option, so printing does not depend on the moment of filing.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Sent Complaints</h1>
      <p>Complaints you have filed. Open one to view or print it.</p>
    </div>

    <div class="icard">
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!rows.length" class="empty-state">
        <h3>No complaints sent yet</h3>
        <p>Complaints you file will be kept here.</p>
      </div>
      <div v-else class="ts">
        <table class="itable">
          <thead>
            <tr><th>Complaint No.</th><th>Student</th><th>Act of Misconduct</th><th>Date Filed</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in rows" :key="c.id" style="cursor:pointer" @click="open(c)">
              <td style="font-family:var(--mono);font-size:12px">{{ c.complaint_code }}</td>
              <td>{{ c.complainee ? `${c.complainee.last_name}, ${c.complainee.first_name}` : '-' }}</td>
              <td>{{ c.violation_type }}</td>
              <td style="font-size:12px;white-space:nowrap">{{ fmt(c.created_at) }}</td>
              <td style="text-align:right;white-space:nowrap">
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="open(c)">View</button>
                <button class="ibtn ibtn-p ibtn-sm" style="margin-left:6px" @click.stop="open(c, true)">Print</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="last > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:6px">
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === 1" @click="load(page - 1)">Prev</button>
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === last" @click="load(page + 1)">Next</button>
      </div>
    </div>

    <ComplaintPrintModal v-if="selected" :complaint="selected" heading="Complaint" :subtitle="selected.complaint_code" @close="selected = null" />
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import api, { studentApi } from '../../api/index';
import ComplaintPrintModal from '../../components/ComplaintPrintModal.vue';

const route = useRoute();
const isStudent = !!route.meta.student;
const http = isStudent ? studentApi : api;
const base = isStudent ? '/student/outreach/complaints' : '/my/complaints';

const rows = ref([]);
const loading = ref(true);
const page = ref(1);
const last = ref(1);
const selected = ref(null);

const fmt = d => (d ? new Date(d).toLocaleDateString() : '-');

async function load(p = 1) {
  loading.value = true;
  try {
    const res = await http.get(base, { params: { page: p } });
    rows.value = res.data.data || [];
    page.value = res.data.current_page || 1;
    last.value = res.data.last_page || 1;
  } catch (e) {
    rows.value = [];
  } finally {
    loading.value = false;
  }
}

async function open(c, thenPrint = false) {
  try {
    const res = await http.get(`${base}/${c.id}`);
    selected.value = res.data;
    if (thenPrint) {
      await nextTick();
      document.body.classList.add('printing-complaint');
      window.print();
      setTimeout(() => document.body.classList.remove('printing-complaint'), 500);
    }
  } catch (e) {
    selected.value = null;
  }
}

onMounted(() => load());
</script>