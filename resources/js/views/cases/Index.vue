<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Case Files</h1>
      <p>Unified case histories, session notes, and intervention records.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="search" type="text" class="sin" placeholder="Search student name or case number..." style="width:240px" @input="filterCases"/>
      </div>
      <select v-model="filterStatus" class="fsm" @change="filterCases">
        <option value="">All Status</option>
        <option value="open">Open</option>
        <option value="in_progress">In Progress</option>
        <option value="awaiting_testing">Awaiting Testing</option>
        <option value="on_hold">On Hold</option>
        <option value="resolved">Resolved</option>
        <option value="closed">Closed</option>
      </select>
      <select v-model="filterUnit" class="fsm" @change="filterCases">
        <option value="">All Units</option>
        <option value="GCU">GCU</option>
        <option value="SDU">SDU</option>
        <option value="TMDU">TMDU</option>
      </select>
      <select v-model="filterType" class="fsm" @change="filterCases">
        <option value="">All Types</option>
        <option value="counseling">Counseling</option>
        <option value="academic_coaching">Academic Coaching</option>
        <option value="admission_slip">Admission Slip</option>
        <option value="psychological_testing">Psychological Testing</option>
        <option value="disciplinary">Disciplinary</option>
        <option value="consultation">Consultation</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
    </div>

    <!-- Cases List -->
    <div class="icard">
      <div v-if="filtered.length === 0" class="empty-state">
        <h3>No cases found</h3>
        <p>Try adjusting your filters.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Case No.</th>
              <th>Student</th>
              <th>Type</th>
              <th>Unit</th>
              <th>Counselor</th>
              <th>Sessions</th>
              <th>Status</th>
              <th>Opened</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in filtered"
              :key="c.id"
              style="cursor:pointer"
              @click="$router.push({ name: 'case-show', params: { id: c.id } })"
            >
              <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="iav">{{ initials(c.student_first, c.student_last) }}</div>
                  <div>
                    <div style="font-weight:600;color:var(--ink)">{{ c.student_first }} {{ c.student_last }}</div>
                    <div style="font-size:11px;color:var(--fog)">{{ c.student_id }}</div>
                  </div>
                </div>
              </td>
              <td>{{ c.case_type?.replace(/_/g,' ') }}</td>
              <td><span class="ibadge" :class="'unit-' + c.current_unit.toLowerCase()">{{ c.current_unit }}</span></td>
              <td style="font-size:12px">{{ c.counselor_name }}</td>
              <td style="text-align:center">{{ c.total_sessions }}</td>
              <td><span class="ibadge" :class="'ibadge-' + c.status">{{ c.status?.replace(/_/g,' ') }}</span></td>
              <td style="font-size:12px">{{ formatDate(c.opened_date) }}</td>
              <td>
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'case-show', params: { id: c.id } })">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const search       = ref('');
const filterStatus = ref('');
const filterUnit   = ref('');
const filterType   = ref('');

const cases = ref([
  { id: 1, case_number: 'CASE-2026-0001', student_first: 'Maria',  student_last: 'Santos',   student_id: '2023-0041', case_type: 'counseling',          current_unit: 'GCU',  counselor_name: 'Dr. Maria Reyes',  total_sessions: 2, status: 'in_progress',    opened_date: '2026-07-01' },
  { id: 2, case_number: 'CASE-2026-0002', student_first: 'Rico',   student_last: 'Bautista', student_id: '2023-0112', case_type: 'disciplinary',         current_unit: 'SDU',  counselor_name: 'Mr. Ramon Valdez', total_sessions: 0, status: 'open',           opened_date: '2026-07-02' },
  { id: 3, case_number: 'CASE-2026-0003', student_first: 'Ana',    student_last: 'Versoza',  student_id: '2021-0089', case_type: 'psychological_testing', current_unit: 'TMDU', counselor_name: 'Ms. Grace Tamayo', total_sessions: 1, status: 'awaiting_testing',opened_date: '2026-07-03' },
  { id: 4, case_number: 'CASE-2026-0004', student_first: 'Ben',    student_last: 'Agbayani', student_id: '2024-0023', case_type: 'counseling',          current_unit: 'GCU',  counselor_name: 'Dr. Maria Reyes',  total_sessions: 3, status: 'in_progress',    opened_date: '2026-07-04' },
  { id: 5, case_number: 'CASE-2026-0005', student_first: 'Carla',  student_last: 'Pines',    student_id: '2022-0155', case_type: 'admission_slip',       current_unit: 'GCU',  counselor_name: 'Ms. Ana Cruz',     total_sessions: 1, status: 'closed',         opened_date: '2026-06-28' },
  { id: 6, case_number: 'CASE-2026-0006', student_first: 'Danny',  student_last: 'Cordero',  student_id: '2023-0078', case_type: 'disciplinary',         current_unit: 'GCU',  counselor_name: 'Dr. Maria Reyes',  total_sessions: 1, status: 'in_progress',    opened_date: '2026-07-05' },
  { id: 7, case_number: 'CASE-2026-0007', student_first: 'Luz',    student_last: 'Bacani',   student_id: '2020-0201', case_type: 'psychological_testing', current_unit: 'TMDU', counselor_name: 'Ms. Grace Tamayo', total_sessions: 2, status: 'awaiting_testing',opened_date: '2026-07-06' },
]);

const filtered = computed(() => {
  return cases.value.filter(c => {
    const matchSearch = !search.value ||
      `${c.student_first} ${c.student_last}`.toLowerCase().includes(search.value.toLowerCase()) ||
      c.case_number.toLowerCase().includes(search.value.toLowerCase()) ||
      c.student_id.includes(search.value);
    const matchStatus = !filterStatus.value || c.status === filterStatus.value;
    const matchUnit   = !filterUnit.value   || c.current_unit === filterUnit.value;
    const matchType   = !filterType.value   || c.case_type === filterType.value;
    return matchSearch && matchStatus && matchUnit && matchType;
  });
});

function filterCases() {}
function resetFilters() {
  search.value       = '';
  filterStatus.value = '';
  filterUnit.value   = '';
  filterType.value   = '';
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}
</script>