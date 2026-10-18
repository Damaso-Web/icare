<!--
  Sent Referrals: every referral this account has sent (staff or student).
  Read-only summary; the unit handling it keeps the full record.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Sent Referrals</h1>
      <p>Referrals you have sent and where they stand.</p>
    </div>

    <div class="icard">
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!rows.length" class="empty-state">
        <h3>No referrals sent yet</h3>
        <p>Referrals you send will be kept here.</p>
      </div>
      <div v-else class="ts">
        <table class="itable">
          <thead>
            <tr><th>Referral No.</th><th>Student</th><th>Type</th><th>Status</th><th>Date Sent</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" style="cursor:pointer" @click="selected = r">
              <td style="font-family:var(--mono);font-size:12px">{{ r.referral_code }}</td>
              <td>{{ r.student ? `${r.student.last_name}, ${r.student.first_name}` : '-' }}</td>
              <td>{{ title(r.referral_type) }}</td>
              <td><span class="ibadge" :class="'ibadge-' + r.status">{{ title(r.status) }}</span></td>
              <td style="font-size:12px;white-space:nowrap">{{ fmt(r.created_at) }}</td>
              <td style="text-align:right"><button class="ibtn ibtn-o ibtn-sm" @click.stop="selected = r">View</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="last > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:6px">
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === 1" @click="load(page - 1)">Prev</button>
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === last" @click="load(page + 1)">Next</button>
      </div>
    </div>

    <div v-if="selected" class="sr-overlay" @click.self="selected = null">
      <div class="sr-box">
        <div style="padding:18px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Referral {{ selected.referral_code }}</div>
          <div style="font-size:12px;color:var(--stone)">{{ title(selected.referral_type) }} · {{ fmt(selected.created_at) }}</div>
        </div>
        <div style="padding:18px 22px;overflow-y:auto;display:flex;flex-direction:column;gap:12px">
          <div>
            <div class="sr-k">Student</div>
            <div class="sr-v">{{ selected.student ? `${selected.student.first_name} ${selected.student.last_name}` : '-' }}</div>
          </div>
          <div>
            <div class="sr-k">Status</div>
            <span class="ibadge" :class="'ibadge-' + selected.status">{{ title(selected.status) }}</span>
          </div>
          <div>
            <div class="sr-k">Reason for Referral</div>
            <div class="sr-v" style="white-space:pre-line;line-height:1.6">{{ selected.nature_of_concern }}</div>
          </div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--cloud)">
          <button class="ibtn ibtn-o" @click="selected = null">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import api, { studentApi } from '../../api/index';

const route = useRoute();
const isStudent = !!route.meta.student;
const http = isStudent ? studentApi : api;
const base = isStudent ? '/student/outreach/referrals' : '/my/referrals';

const rows = ref([]);
const loading = ref(true);
const page = ref(1);
const last = ref(1);
const selected = ref(null);

const fmt = d => (d ? new Date(d).toLocaleDateString() : '-');
const title = s => (s ? s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : '');

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
onMounted(() => load());
</script>

<style scoped>
.sr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px}
.sr-box{background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--sh-lg)}
.sr-k{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);margin-bottom:3px}
.sr-v{font-size:13.5px;color:var(--ink)}
</style>