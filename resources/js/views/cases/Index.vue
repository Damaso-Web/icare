<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Information Files</h1>
      <p>{{ filters.archived ? 'Archived files: closed cases and deactivated students. View only.' : "Each student's complete record in one place." }}</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          maxlength="50"
          placeholder="Search student name or case number..."
          style="width:240px"
          @keypress="blockSpecialKeypress"
          @input="onSearchInput"
        />
      </div>
      <select v-model="filters.status" class="fsm" :disabled="filters.archived" :title="filters.archived ? 'Status does not apply to archived records.' : ''" @change="fetchCases">
        <option value="">All Status</option>
        <option value="open">Open</option>
        <option value="on_observation">On Observation</option>
        <option value="resolved">Resolved</option>
      </select>
      <button
      class="ibtn ibtn-sm"
      :class="filters.severe ? '' : 'ibtn-o'"
      :style="filters.severe ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : ''"
      @click="filters.severe = !filters.severe; fetchCases()"
    >
    Severe Cases
    </button>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <!-- B#, 11/4/2026: the only place to set a student's Ctrl No. is the
           individual SIF page (students/Show.vue), one at a time. This lets
           GCU catch up on the whole backlog of "No Ctrl No. yet" files in
           one pass instead of opening each one. -->
      <button v-if="!filters.archived" class="ibtn ibtn-o ibtn-sm" @click="openBulkCtrlNo">
        <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Assign Ctrl No.
      </button>
      <button class="ibtn ibtn-sm" :class="filters.archived ? 'ibtn-p' : 'ibtn-o'" style="margin-left:auto" @click="filters.archived = !filters.archived; filters.status = ''; fetchCases()">
        {{ filters.archived ? 'Viewing Archived' : 'View Archived' }}
      </button>
    </div>

    <!-- Cases List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="cases.length === 0" class="empty-state">
        <h3>No cases found</h3>
        <p>Try adjusting your filters.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Ctrl No.</th>
              <th>Student Name</th>
              <th>Date Submitted</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="c in cases"
              :key="c.id"
              :style="'cursor:pointer;' + (filters.archived ? 'background:var(--snow);color:var(--stone)' : '')"
              @click="$router.push({ name: 'student-show', params: { id: c.student?.id }, query: { ctx: 'cases' } })"
            >
              <!-- The Ctrl No. is the case's name; cases whose student has no client no. yet show the old number -->
              <td>
                <span v-if="ctrlNo(c)" style="font-family:var(--mono);font-size:13px;font-weight:700;color:var(--forest)">{{ ctrlNo(c) }}</span>
                <span v-else style="font-family:var(--mono);font-size:11px;color:var(--stone)" title="No Ctrl No. yet - open the file to set the client number">{{ c.case_number }}</span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <div class="iav">{{ initials(c.student?.first_name, c.student?.last_name) }}</div>
                  <div :style="'font-weight:600;color:' + (filters.archived ? 'var(--stone)' : 'var(--ink)')">{{ c.student?.last_name }}, {{ c.student?.first_name }} {{ c.student?.middle_name }}</div>
                  <span v-if="filters.archived && c.student?.is_active === false" class="ibadge" style="background:var(--cloud);color:var(--stone)">Deactivated</span>
                  <span v-else-if="filters.archived" class="ibadge" style="background:var(--cloud);color:var(--stone)">Archived</span>
                  <span v-if="reassignedAwayFromMe(c)" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">Reassigned</span>
                  <span v-if="c.is_severe" class="ibadge" style="background:var(--red-lt);color:var(--red)">Severe</span>
                </div>
              </td>
              <!-- opened_date is stamped with today() at the moment the founding
                   referral is submitted (ReferralController@store), so it IS the
                   case's date submitted - no backend change needed for the rename. -->
              <td style="font-size:12px">{{ formatDate(c.opened_date) }}</td>
              <td>
                <button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'student-show', params: { id: c.student?.id }, query: { ctx: 'cases' } })">View</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">
          Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
        </span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- Bulk Assign Ctrl No. - every active student with a case but no
         client no. yet, in one list, each with an editable suggested number
         instead of opening every SIF individually. -->
    <div v-if="bulkCtrlOpen" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeBulkCtrlNo">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:620px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Assign Ctrl No.</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="closeBulkCtrlNo">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="bulkCtrlLoading" style="text-align:center;padding:30px">
            <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
          </div>
          <template v-else-if="!bulkCtrlRows.length">
            <div class="empty-state">
              <h3>Nothing to assign</h3>
              <p>Every active student with a case already has a Ctrl No.</p>
            </div>
          </template>
          <template v-else>
            <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--stone);line-height:1.6">
              {{ bulkCtrlRows.length }} student(s) have no Ctrl No. yet. Numbers below are suggested in order - edit any of them before saving, as long as each stays unique.
            </div>
            <div v-if="bulkCtrlError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ bulkCtrlError }}
            </div>
            <div style="max-height:360px;overflow-y:auto;border:1px solid var(--cloud);border-radius:var(--r-sm)">
              <table class="itable">
                <thead>
                  <tr><th style="width:60px">Row</th><th>Student</th><th style="width:150px">Ctrl No.</th></tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in bulkCtrlRows" :key="row.id">
                    <td style="font-size:12px">{{ i + 1 }}</td>
                    <td style="font-size:12.5px">{{ row.last_name }}, {{ row.first_name }} {{ row.middle_name }}<div style="font-family:var(--mono);font-size:10.5px;color:var(--fog)">{{ row.student_id }}</div></td>
                    <td>
                      <div style="display:flex;align-items:center;gap:4px;font-family:var(--mono);font-size:12px">
                        <span>{{ ctrlPrefix || '-' }}-</span>
                        <input
                          v-model="row.draft"
                          class="ifi"
                          style="width:90px;padding:4px 8px;font-family:var(--mono)"
                          maxlength="6"
                          inputmode="numeric"
                          @input="row.draft = String(row.draft ?? '').replace(/\D/g, '')"
                        />
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>
        <div v-if="bulkCtrlRows.length" style="padding:14px 22px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:8px">
          <button class="ibtn ibtn-p" :disabled="bulkCtrlSaving" @click="saveBulkCtrlNo">
            <span v-if="bulkCtrlSaving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
            {{ bulkCtrlSaving ? 'Saving...' : `Save ${bulkCtrlRows.length} Ctrl No.` }}
          </button>
          <button class="ibtn ibtn-o" :disabled="bulkCtrlSaving" @click="closeBulkCtrlNo">Cancel</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAutoRefresh } from '../../utils/useAutoRefresh';
import { useRoute } from 'vue-router';
import { caseAPI, studentAPI } from '../../api/index';
import { safeSearchInput, blockSpecialKeypress } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';

const route = useRoute();
const auth  = useAuthStore();

// B562: this case was taken off the signed-in counselor and given to
// someone else - still shown here (index() loads every case regardless of
// counselor) but flagged so it reads as read-only history, not active work.
function reassignedAwayFromMe(c) {
  return !!c.previous_counselor_id && c.previous_counselor_id === auth.user?.id && c.primary_counselor_id !== auth.user?.id;
}

const cases      = ref([]);

// Ctrl No. = "<year>-<term>" (set in Management for QF-OSS-GCU-01) + the student's client no.
const ctrlPrefix = ref('');
function ctrlNo(c) {
  const n = c.student?.sif_client_no;
  return n && ctrlPrefix.value ? `${ctrlPrefix.value}-${String(n).padStart(4, '0')}` : '';
}
const loading    = ref(true);
const pagination = ref({});
const filters    = ref({ search: '', status: '', archived: false, severe: false });

async function fetchCases(page = 1, silent = false) {
  if (!silent) loading.value = true;
  try {
    const res = await caseAPI.index({
      ...filters.value,
      archived: filters.value.archived ? 1 : 0,
      severe:   filters.value.severe   ? 1 : 0,
      page,    });
    cases.value      = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    if (!silent) console.error(e);
  } finally {
    if (!silent) loading.value = false;
  }
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  fetchCases();
}

function resetFilters() {
  filters.value = { search: '', status: '', archived: filters.value.archived, severe: false };
  fetchCases();
}

function changePage(page) { fetchCases(page); }

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

// ---- Bulk Assign Ctrl No. ----
const bulkCtrlOpen    = ref(false);
const bulkCtrlLoading = ref(false);
const bulkCtrlSaving  = ref(false);
const bulkCtrlError   = ref('');
const bulkCtrlRows    = ref([]); // [{ id, student_id, first_name, middle_name, last_name, draft }]

async function openBulkCtrlNo() {
  bulkCtrlOpen.value  = true;
  bulkCtrlError.value = '';
  bulkCtrlLoading.value = true;
  try {
    const res = await studentAPI.missingClientNo();
    const nextNumber = res.data?.next_number || 1;
    bulkCtrlRows.value = (res.data?.students || []).map((s, i) => ({
      ...s,
      draft: String(nextNumber + i),
    }));
  } catch (e) {
    bulkCtrlError.value = e.response?.data?.message || 'Could not load the list.';
  } finally {
    bulkCtrlLoading.value = false;
  }
}

function closeBulkCtrlNo() {
  if (bulkCtrlSaving.value) return;
  bulkCtrlOpen.value = false;
  bulkCtrlRows.value = [];
  bulkCtrlError.value = '';
}

async function saveBulkCtrlNo() {
  if (bulkCtrlSaving.value) return;
  bulkCtrlError.value = '';

  const updates = [];
  for (const row of bulkCtrlRows.value) {
    const n = Number(row.draft);
    if (!row.draft || !Number.isInteger(n) || n < 1) {
      bulkCtrlError.value = `${row.last_name}, ${row.first_name}'s Ctrl No. must be a number.`;
      return;
    }
    updates.push({ student_id: row.id, sif_client_no: n });
  }

  bulkCtrlSaving.value = true;
  try {
    await studentAPI.bulkUpdateClientNo({ updates });
    closeBulkCtrlNo();
    fetchCases(pagination.value?.current_page || 1);
  } catch (e) {
    bulkCtrlError.value = e.response?.data?.message || 'Could not save these Ctrl No. - nothing was changed.';
  } finally {
    bulkCtrlSaving.value = false;
  }
}

onMounted(() => {
  studentAPI.documentSettings('QF-OSS-GCU-01').then(res => { ctrlPrefix.value = res.data?.ctrl_no || ''; }).catch(() => {});
  // Arriving from a Dashboard stat card (?status=open) pre-filters the list
  // to match what the card said, instead of dumping the user on an
  // unfiltered "All Status" view.
  if (route.query.status && typeof route.query.status === 'string') {
    filters.value.status = route.query.status;
  }
  fetchCases();
});

// Keep the list current without a manual reload.
useAutoRefresh(() => fetchCases(pagination.value?.current_page || 1, true), 25000);
</script>