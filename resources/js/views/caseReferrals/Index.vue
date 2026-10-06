<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Case Referrals (Internal)</h1>
      <p>The Refer to TMDU forms GCU has sent, with their status and released results (PAR).</p>
    </div>

    <div class="icard">
      <div style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;gap:10px;flex-wrap:wrap">
        <input v-model="search" class="ifi" style="max-width:260px" placeholder="Search student name or ID..." @input="onSearch" />
        <select v-model="status" class="fsm" @change="fetchItems">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="scheduled">Scheduled for Testing</option>
          <option value="test_administered">Test Administered</option>
          <option value="awaiting_results">Awaiting Results</option>
          <option value="test_results_issued">Results Released</option>
        </select>
      </div>

      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!items.length" class="empty-state">
        <h3>No case referrals yet</h3>
        <p>Referrals sent to TMDU from a Student Information File will appear here.</p>
      </div>
      <div v-else class="ts">
        <table class="itable">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in items" :key="r.id" style="cursor:pointer" @click="selected = r">
              <td style="font-family:var(--mono);font-size:12px">{{ r.student?.student_id }}</td>
              <td>{{ r.student?.last_name }}, {{ r.student?.first_name }}</td>
              <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
              <td><button class="ibtn ibtn-o ibtn-sm" @click.stop="selected = r">View</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Floating modal: the filled-out, uneditable form -->
    <div v-if="selected" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="selected = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Referral for Psychological Testing</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="selected = null">✕</button>
        </div>
        <CaseReferralView :data="selected" />
        <div style="padding:0 22px 22px;display:flex;gap:8px">
          <button class="ibtn ibtn-p" @click="$router.push({ name: 'case-referral-show', params: { id: selected.id } })">View Case Referral</button>
          <button class="ibtn ibtn-o" @click="selected = null">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { caseReferralAPI } from '../../api/index';
import CaseReferralView from '../../components/CaseReferralView.vue';

const items = ref([]);
const loading = ref(true);
const search = ref('');
const status = ref('');
const selected = ref(null);

const BADGES = {
  pending:             { label: 'Pending', cls: 'ibadge-pending' },
  scheduled:           { label: 'Scheduled for Testing', cls: 'ibadge-scheduled' },
  in_progress:         { label: 'Scheduled for Testing', cls: 'ibadge-scheduled' },
  test_administered:   { label: 'Test Administered', cls: 'ibadge-completed' },
  awaiting_results:    { label: 'Awaiting Results', cls: 'ibadge-scheduled' },
  test_results_issued: { label: 'Results Released', cls: 'ibadge-closed' },
};
function badge(r) {
  const st = r.testing_record?.status;
  if (!st || (st === 'pending' && !r.acknowledged_at)) return { label: 'Sent to TMDU', cls: 'ibadge-submitted' };
  return BADGES[st] || { label: st, cls: 'ibadge-pending' };
}

function formatDate(d) { return d ? new Date(d).toLocaleDateString() : '-'; }

let timer = null;
function onSearch() {
  clearTimeout(timer);
  timer = setTimeout(fetchItems, 350);
}

async function fetchItems() {
  loading.value = true;
  try {
    const res = await caseReferralAPI.index({ search: search.value || undefined, status: status.value || undefined });
    items.value = res.data.data || res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(fetchItems);
</script>