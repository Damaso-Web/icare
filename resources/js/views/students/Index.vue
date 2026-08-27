<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Profiles</h1>
      <p>Search for a student by name or student ID to view their records.</p>
    </div>

    <!-- Search Bar -->
    <div class="filter-bar">
      <div class="sw" style="flex:1;max-width:400px">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          placeholder="Search name or student ID..."
          style="width:100%"
          @input="onSearchInput"
        />
      </div>
      <button v-if="filters.search" class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
    </div>

    <!-- Student List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!filters.search" class="empty-state">
        <h3>Search for a student</h3>
        <p>Type a name or student ID above to find their profile.</p>
      </div>
      <div v-else-if="students.length === 0" class="empty-state">
        <h3>No students found</h3>
        <p>Try a different name or student ID.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Student Name</th>
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
                    <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ s.student_id }}</div>
                  </div>
                </div>
              </td>
              <td style="text-align:right">
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
import { ref } from 'vue';
import { studentAPI } from '../../api/index';

const students   = ref([]);
const loading    = ref(false);
const pagination = ref({});
const filters    = ref({ search: '' });

let searchTimeout = null;

function onSearchInput() {
  clearTimeout(searchTimeout);
  if (!filters.value.search) {
    students.value = [];
    return;
  }
  searchTimeout = setTimeout(() => fetchStudents(), 400);
}

async function fetchStudents(page = 1) {
  if (!filters.value.search) {
    students.value = [];
    return;
  }
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
  filters.value = { search: '' };
  students.value = [];
  pagination.value = {};
}

function changePage(page) { fetchStudents(page); }

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}
</script>