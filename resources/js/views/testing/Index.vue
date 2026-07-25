<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Testing Records</h1>
      <p>Psychological testing queue and assessment records managed by TMDU.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <select v-model="filterStatus" class="fsm" @change="fetchRecords">
        <option value="">All Status</option>
        <option value="pending">Pending</option>
        <option value="scheduled">Scheduled</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="report_sent">Report Sent</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
    </div>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:16px">

      <!-- Testing Queue -->
      <div class="icard">
        <div class="icard-header">
          <span class="icard-title">Testing Queue</span>
          <span class="ibadge ibadge-pending">{{ pendingCount }} pending</span>
        </div>
        <div v-if="loading" style="text-align:center;padding:44px">
          <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
        </div>
        <div v-else-if="records.length === 0" class="empty-state">
          <h3>No testing records found</h3>
          <p>No records match your current filters.</p>
        </div>
        <div v-else>
          <div
            v-for="t in records"
            :key="t.id"
            class="qr"
            @click="openRecord(t)"
          >
            <div class="qav">{{ initials(t.student?.first_name, t.student?.last_name) }}</div>
            <div class="qi">
              <div class="qn">
                {{ t.student?.first_name }} {{ t.student?.last_name }}
                <span class="qid">{{ t.student?.student_id }}</span>
              </div>
              <div class="qmeta">
                Referred by {{ t.referred_by?.name }} · {{ formatDate(t.created_at) }}
              </div>
              <div v-if="t.tests_administered?.length" style="font-size:12px;color:var(--slate);margin-top:4px">
                Tests: {{ t.tests_administered.join(', ') }}
              </div>
              <div class="qtags">
                <span class="ibadge" :class="'ibadge-' + t.status">{{ t.status?.replace(/_/g,' ') }}</span>
                <span class="ibadge unit-tmdu">TMDU</span>
              </div>
            </div>
            <div class="qacts">
              <button class="ibtn ibtn-p ibtn-sm" @click.stop="openRecord(t)">View</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats -->
      <div style="display:flex;flex-direction:column;gap:14px">
        <div class="stat-card" v-for="stat in stats" :key="stat.label">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px">
            <div class="stat-icon" :style="{ background: stat.iconBg }">
              <svg viewBox="0 0 24 24" :style="{ color: stat.iconColor }" v-html="stat.icon"></svg>
            </div>
          </div>
          <div class="stat-num">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>

    </div>

    <!-- Record Detail Drawer -->
    <div v-if="selectedRecord" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60" @click.self="selectedRecord = null">
      <div style="position:fixed;top:0;right:0;width:min(520px,100vw);height:100vh;background:#fff;overflow-y:auto;box-shadow:-6px 0 40px rgba(0,0,0,.18)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:flex-start;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div>
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Testing Record #{{ selectedRecord.id }}</div>
            <div style="font-size:12px;color:var(--stone)">{{ selectedRecord.student?.first_name }} {{ selectedRecord.student?.last_name }}</div>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="selectedRecord = null">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">

          <!-- Status -->
          <div>
            <label class="ifl">Status</label>
            <select v-model="selectedRecord.status" class="ifse">
              <option value="pending">Pending</option>
              <option value="scheduled">Scheduled</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="report_sent">Report Sent</option>
            </select>
          </div>

          <!-- Tests Administered -->
          <div>
            <label class="ifl">Tests Administered</label>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px">
              <span
                v-for="test in availableTests"
                :key="test"
                style="padding:4px 10px;border-radius:20px;font-size:11px;cursor:pointer;border:1.5px solid var(--silver);transition:all .1s"
                :style="{
                  background: selectedRecord.tests_administered?.includes(test) ? 'var(--moss)' : '#fff',
                  color: selectedRecord.tests_administered?.includes(test) ? '#fff' : 'var(--slate)',
                  borderColor: selectedRecord.tests_administered?.includes(test) ? 'var(--moss)' : 'var(--silver)',
                }"
                @click="toggleTest(test)"
              >
                {{ test }}
              </span>
            </div>
          </div>

          <!-- Testing Date -->
          <div>
            <label class="ifl">Testing Date</label>
            <input v-model="selectedRecord.testing_date" type="date" class="ifi" />
          </div>

          <!-- Assessment Summary -->
          <div>
            <label class="ifl">Assessment Summary</label>
            <textarea v-model="selectedRecord.assessment_summary" class="ifta" placeholder="Summarize the assessment results..."></textarea>
          </div>

          <!-- Findings -->
          <div>
            <label class="ifl">Findings</label>
            <textarea v-model="selectedRecord.findings" class="ifta" placeholder="Detail the findings from the tests..."></textarea>
          </div>

          <!-- Recommendations -->
          <div>
            <label class="ifl">Recommendations</label>
            <textarea v-model="selectedRecord.recommendations" class="ifta" placeholder="Provide recommendations based on findings..."></textarea>
          </div>

          <!-- Actions -->
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="saveRecord">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              Save
            </button>
            <button class="ibtn ibtn-blue" @click="sendToGcu" v-if="selectedRecord.status === 'completed'">
              Send Report to GCU
            </button>
            <button class="ibtn ibtn-o" @click="selectedRecord = null">Cancel</button>
          </div>

        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { testingAPI } from '../../api/index';

const toast        = inject('toast');
const filterStatus = ref('');
const selectedRecord = ref(null);
const loading      = ref(true);
const records      = ref([]);

const availableTests = ['MMPI-2', 'SCL-90', 'Beck Depression Inventory', 'Hamilton Anxiety Scale', "Raven's Progressive Matrices", 'WAIS-IV'];

const pendingCount = computed(() => records.value.filter(r => r.status === 'pending').length);

const stats = computed(() => [
  { label: 'Pending',     value: records.value.filter(r => r.status === 'pending').length,     iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
  { label: 'In Progress', value: records.value.filter(r => r.status === 'in_progress').length, iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { label: 'Completed',   value: records.value.filter(r => r.status === 'completed').length,   iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>' },
  { label: 'Report Sent', value: records.value.filter(r => r.status === 'report_sent').length, iconBg: 'var(--purple-lt)',iconColor: 'var(--purple)',icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>' },
]);

async function fetchRecords() {
  loading.value = true;
  try {
    const res = await testingAPI.index({ status: filterStatus.value });
    records.value = res.data.data || res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openRecord(t) {
  selectedRecord.value = { ...t, tests_administered: [...(t.tests_administered || [])] };
}

function toggleTest(test) {
  if (!selectedRecord.value.tests_administered) selectedRecord.value.tests_administered = [];
  const idx = selectedRecord.value.tests_administered.indexOf(test);
  if (idx === -1) selectedRecord.value.tests_administered.push(test);
  else selectedRecord.value.tests_administered.splice(idx, 1);
}

async function saveRecord() {
  try {
    await testingAPI.update(selectedRecord.value.id, {
      status:              selectedRecord.value.status,
      tests_administered:  selectedRecord.value.tests_administered,
      testing_date:        selectedRecord.value.testing_date,
      assessment_summary:  selectedRecord.value.assessment_summary,
      findings:            selectedRecord.value.findings,
      recommendations:     selectedRecord.value.recommendations,
    });
    const idx = records.value.findIndex(r => r.id === selectedRecord.value.id);
    if (idx !== -1) records.value[idx] = { ...selectedRecord.value };
    selectedRecord.value = null;
    toast?.success('Testing record saved.');
  } catch (e) {
    toast?.error('Failed to save record.');
  }
}

async function sendToGcu() {
  try {
    await testingAPI.sendToGcu(selectedRecord.value.id, {
      assessment_summary: selectedRecord.value.assessment_summary,
      findings:           selectedRecord.value.findings,
      recommendations:    selectedRecord.value.recommendations,
    });
    selectedRecord.value.status = 'report_sent';
    const idx = records.value.findIndex(r => r.id === selectedRecord.value.id);
    if (idx !== -1) records.value[idx].status = 'report_sent';
    selectedRecord.value = null;
    toast?.success('Report sent to GCU.');
  } catch (e) {
    toast?.error('Failed to send report.');
  }
}

function resetFilters() {
  filterStatus.value = '';
  fetchRecords();
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

onMounted(() => fetchRecords());
</script>