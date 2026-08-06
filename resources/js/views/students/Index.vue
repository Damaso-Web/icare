<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Profiles</h1>
      <p>Search and view student records and case histories.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="filters.search" type="text" class="sin" placeholder="Search name or student ID..." style="width:240px" @input="fetchStudents"/>
      </div>
      <select v-model="filters.college" class="fsm" @change="fetchStudents">
        <option value="">All Colleges</option>
        <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="filters.year_level" class="fsm" @change="fetchStudents">
        <option value="">All Year Levels</option>
        <option>1st Year</option>
        <option>2nd Year</option>
        <option>3rd Year</option>
        <option>4th Year</option>
        <option>5th Year</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
    </div>

    <!-- Student List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="students.length === 0" class="empty-state">
        <h3>No students found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Student</th>
              <th>Student ID</th>
              <th>Year Level</th>
              <th>College</th>
              <th>Program</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="s in students"
              :key="s.id"
              style="cursor:pointer"
              @click="$router.push({ name: 'student-show', params: { id: s.id } })"
            >
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="iav">{{ initials(s.first_name, s.last_name) }}</div>
                  <div>
                    <div style="font-weight:600;color:var(--ink)">{{ s.last_name }}, {{ s.first_name }} {{ s.middle_name }}</div>
                    <div style="font-size:11px;color:var(--fog)">{{ s.email }}</div>
                  </div>
                </div>
              </td>
              <td style="font-family:var(--mono);font-size:12px">{{ s.student_id }}</td>
              <td>{{ s.year_level }}</td>
              <td style="font-size:12px">{{ s.college }}</td>
              <td style="font-size:12px">{{ s.program }}</td>
              <td>
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'student-show', params: { id: s.id } })">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">
          Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
        </span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { studentAPI } from '../../api/index';
import { COLLEGES } from '../../constants/colleges';

const colleges   = COLLEGES;
const students   = ref([]);
const loading    = ref(true);
const pagination = ref({});
const filters    = ref({ search: '', college: '', year_level: '' });

async function fetchStudents(page = 1) {
  loading.value = true;
  try {
    const res = await studentAPI.index({ ...filters.value, page });
    students.value   = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value = { search: '', college: '', year_level: '' };
  fetchStudents();
}

function changePage(page) { fetchStudents(page); }

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

onMounted(() => fetchStudents());
</script>