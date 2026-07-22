<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Testing Records</h1>
      <p>Psychological testing queue and assessment records managed by TMDU.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <select v-model="filterStatus" class="fsm" @change="filter">
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
          <span class="ibadge ibadge-pending">{{ pending.length }} pending</span>
        </div>
        <div v-if="filtered.length === 0" class="empty-state">
          <h3>No testing records found</h3>
          <p>No records match your current filters.</p>
        </div>
        <div v-else>
          <div
            v-for="t in filtered"
            :key="t.id"
            class="qr"
            @click="openRecord(t)"
          >
            <div class="qav">{{ initials(t.student_first, t.student_last) }}</div>
            <div class="qi">
              <div class="qn">
                {{ t.student_first }} {{ t.student_last }}
                <span class="qid">{{ t.student_id }}</span>
              </div>
              <div class="qmeta">
                Referred by {{ t.referred_by }} · {{ formatDate(t.created_at) }}
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
            <div style="font-size:12px;color:var(--stone)">{{ selectedRecord.student_first }} {{ selectedRecord.student_last }}</div>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="selectedRecord = null">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">

          <!-- Status Update -->
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
import { ref, computed } from 'vue';

const filterStatus  = ref('');
const selectedRecord = ref(null);

const availableTests = ['MMPI-2', 'SCL-90', 'Beck Depression Inventory', 'Hamilton Anxiety Scale', 'Raven\'s Progressive Matrices', 'WAIS-IV'];

const records = ref([
  { id: 1, student_first: 'Ana',  student_last: 'Versoza',  student_id: '2021-0089', referred_by: 'Dr. Maria Reyes',  status: 'scheduled',   tests_administered: ['MMPI-2', 'SCL-90'],            testing_date: '2026-07-15', assessment_summary: '', findings: '', recommendations: '', created_at: '2026-07-03' },
  { id: 2, student_first: 'Luz',  student_last: 'Bacani',   student_id: '2020-0201', referred_by: 'Ms. Ana Cruz',     status: 'pending',     tests_administered: [],                              testing_date: '',           assessment_summary: '', findings: '', recommendations: '', created_at: '2026-07-06' },
  { id: 3, student_first: 'Tito', student_last: 'Ramos',    student_id: '2022-0033', referred_by: 'Dr. Maria Reyes',  status: 'completed',   tests_administered: ['Beck Depression Inventory'],   testing_date: '2026-07-08', assessment_summary: 'Moderate depression symptoms observed.', findings: 'BDI score of 21 indicating moderate depression.', recommendations: 'Continued counseling and possible referral to psychiatrist.', created_at: '2026-06-28' },
  { id: 4, student_first: 'Nina', student_last: 'Castillo', student_id: '2023-0099', referred_by: 'Ms. Ana Cruz',     status: 'report_sent', tests_administered: ['Hamilton Anxiety Scale'],      testing_date: '2026-07-01', assessment_summary: 'Mild to moderate anxiety.', findings: 'HAM-A score of 18.', recommendations: 'Stress management sessions recommended.', created_at: '2026-06-25' },
]);

const filtered = computed(() => {
  return records.value.filter(r => !filterStatus.value || r.status === filterStatus.value);
});

const pending = computed(() => records.value.filter(r => r.status === 'pending'));

const stats = computed(() => [
  { label: 'Pending',     value: records.value.filter(r => r.status === 'pending').length,     iconBg: 'var(--amber-lt)', iconColor: 'var(--amber)', icon: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>' },
  { label: 'In Progress', value: records.value.filter(r => r.status === 'in_progress').length, iconBg: 'var(--blue-lt)',  iconColor: 'var(--blue)',  icon: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>' },
  { label: 'Completed',   value: records.value.filter(r => r.status === 'completed').length,   iconBg: 'var(--mist)',     iconColor: 'var(--moss)',  icon: '<polyline points="20 6 9 17 4 12"/>' },
  { label: 'Report Sent', value: records.value.filter(r => r.status === 'report_sent').length, iconBg: 'var(--purple-lt)',iconColor: 'var(--purple)',icon: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>' },
]);

function openRecord(t) { selectedRecord.value = { ...t, tests_administered: [...(t.tests_administered || [])] }; }

function toggleTest(test) {
  if (!selectedRecord.value.tests_administered) selectedRecord.value.tests_administered = [];
  const idx = selectedRecord.value.tests_administered.indexOf(test);
  if (idx === -1) selectedRecord.value.tests_administered.push(test);
  else selectedRecord.value.tests_administered.splice(idx, 1);
}

function saveRecord() {
  const idx = records.value.findIndex(r => r.id === selectedRecord.value.id);
  if (idx !== -1) records.value[idx] = { ...selectedRecord.value };
  selectedRecord.value = null;
}

function sendToGcu() {
  selectedRecord.value.status = 'report_sent';
  saveRecord();
}

function filter() {}
function resetFilters() { filterStatus.value = ''; }
function initials(first, last) { return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?'; }
function formatDate(date) { return date ? new Date(date).toLocaleDateString() : '—'; }
</script>