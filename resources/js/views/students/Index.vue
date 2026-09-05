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
      <button class="ibtn ibtn-p ibtn-sm" style="margin-left:auto" @click="openAddModal">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Student
      </button>
    </div>
    <button class="ibtn ibtn-o ibtn-sm" @click="showImportModal = true">
    <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
    Upload Masterlist
  </button>

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
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in students" :key="s.id">
              <td style="cursor:pointer" @click="$router.push({ name: 'student-show', params: { id: s.id } })">
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="iav">{{ initials(s.first_name, s.last_name) }}</div>
                  <div>
                    <div style="font-weight:600;color:var(--ink)">{{ s.last_name }}, {{ s.first_name }} {{ s.middle_name }}</div>
                    <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ s.student_id }}</div>
                  </div>
                </div>
              </td>
              <td>
                <span class="ibadge" :style="s.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--stone)'">
                  {{ s.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td style="text-align:right">
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'student-show', params: { id: s.id } })">View</button>
                  <button
                  class="ibtn ibtn-sm"
                  :style="s.is_active ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : 'background:var(--mist);color:var(--moss);border:1.5px solid var(--mint)'"
                  @click.stop="s.is_active ? markGraduated(s) : toggleActive(s)"
                >
                  {{ s.is_active ? 'Mark Graduated' : 'Activate' }}
                </button>
                </div>
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

    <!-- Add Student Modal -->
    <div v-if="showAddModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showAddModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Add New Student</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showAddModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
              <input
              v-model="form.student_id_input"
              class="ifi"
              placeholder="e.g. 2302021"
              @input="form.student_id_input = onlyDigits(form.student_id_input)"
              required
            />
            </div>
            <div></div>
            <div>
              <input v-model="form.last_name" class="ifi" placeholder="Dela Cruz" @input="form.last_name = onlyLetters(form.last_name)" required />
              <input v-model="addForm.last_name" class="ifi" placeholder="Dela Cruz" />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input v-model="form.first_name" class="ifi" placeholder="Juan" @input="form.first_name = onlyLetters(form.first_name)" required />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="form.middle_name" class="ifi" placeholder="Santos" @input="form.middle_name = onlyLetters(form.middle_name)" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input v-model="form.suffix" class="ifi" placeholder="Jr." @input="form.suffix = onlyLettersStrict(form.suffix)" />            </div>
            <div>
              <label class="ifl">College</label>
              <select v-model="addForm.college" class="ifse" @change="addForm.program = ''">
                <option value="">Select college...</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Program</label>
              <select v-model="addForm.program" class="ifse" :disabled="!addForm.college">
                <option value="">Select program...</option>
                <option v-for="p in availablePrograms" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Year Level</label>
              <select v-model="addForm.year_level" class="ifse">
                <option value="">Select...</option>
                <option>1st Year</option>
                <option>2nd Year</option>
                <option>3rd Year</option>
                <option>4th Year</option>
                <option>5th Year</option>
              </select>
            </div>
            <div>
              <label class="ifl">Section</label>
              <input v-model="addForm.section" class="ifi" placeholder="e.g. A" />
            </div>
            <div>
              <label class="ifl">Email</label>
              <input v-model="addForm.email" class="ifi" placeholder="student@bsu.edu.ph" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="form.referrer_contact" class="ifi" placeholder="e.g. 09171234567" @input="form.referrer_contact = contactNumberInput(form.referrer_contact)" />
            </div>
          </div>
          <div style="display:flex;gap:8px;padding-top:4px">
            <button class="ibtn ibtn-p" @click="saveStudent" :disabled="saving">
              <svg v-if="!saving" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ saving ? 'Saving...' : 'Add Student' }}
            </button>
            <button class="ibtn ibtn-o" @click="showAddModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- Import Modal -->
<div v-if="showImportModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showImportModal = false">
  <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
    <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
      <div style="font-size:15px;font-weight:600;color:var(--ink)">Upload Student Masterlist</div>
      <button class="ibtn ibtn-g ibtn-sm" @click="showImportModal = false">✕</button>
    </div>
    <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
      <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--stone);line-height:1.6">
        Upload a <strong>.csv</strong> file with columns: <code>student_id, first_name, last_name, middle_name, sex, email, contact_number, college, program, year_level, section</code>. Only <code>student_id</code>, <code>first_name</code>, and <code>last_name</code> are required. You can upload per college or the whole school in one file.
      </div>
      <div>
        <label class="ifl">CSV File</label>
        <input type="file" accept=".csv" class="ifi" @change="handleFileSelect" />
      </div>
      <div v-if="importResult" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
        ✓ {{ importResult.created }} students added, {{ importResult.skipped }} skipped.
        <div v-if="importResult.errors?.length" style="margin-top:6px;font-size:11px;color:var(--red)">
          <div v-for="(err, i) in importResult.errors" :key="i">{{ err }}</div>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="ibtn ibtn-p" @click="uploadFile" :disabled="!selectedFile || importing">
          <span v-if="importing" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
          {{ importing ? 'Uploading...' : 'Upload' }}
        </button>
        <button class="ibtn ibtn-o" @click="showImportModal = false">Close</button>
      </div>
    </div>
  </div>
</div>
</template>

<script setup>
import { ref, computed, inject } from 'vue';
import { studentAPI } from '../../api/index';
import { COLLEGES } from '../../constants/colleges';
import { PROGRAMS_BY_COLLEGE } from '../../constants/programs';
import { onlyDigits, onlyLetters, onlyLettersStrict, contactNumberInput, isValidEmail } from '../../utils/validators';

const toast   = inject('toast');
const colleges = COLLEGES;

const students   = ref([]);
const loading    = ref(false);
const saving     = ref(false);
const pagination = ref({});
const filters    = ref({ search: '' });
const showAddModal = ref(false);
const showImportModal = ref(false);
const selectedFile    = ref(null);
const importing       = ref(false);
const importResult    = ref(null);

const addForm = ref({
  student_id: '', last_name: '', first_name: '', middle_name: '', suffix: '',
  college: '', program: '', year_level: '', section: '', email: '', contact_number: '',
});

const availablePrograms = computed(() => PROGRAMS_BY_COLLEGE[addForm.value.college] || []);

let searchTimeout = null;

function onSearchInput() {
  clearTimeout(searchTimeout);
  if (!filters.value.search) {
    students.value = [];
    return;
  }
  searchTimeout = setTimeout(() => fetchStudents(), 400);
}

function handleFileSelect(e) {
  selectedFile.value = e.target.files[0];
  importResult.value = null;
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

function openAddModal() {
  addForm.value = {
    student_id: '', last_name: '', first_name: '', middle_name: '', suffix: '',
    college: '', program: '', year_level: '', section: '', email: '', contact_number: '',
  };
  showAddModal.value = true;
}

async function saveStudent() {
  if (!addForm.value.student_id || addForm.value.student_id.length !== 7 || !addForm.value.last_name || !addForm.value.first_name) {
    toast?.error('Please fill in Student ID (7 digits), Last Name, and First Name.');
    return;
  }
  saving.value = true;
  try {
    await studentAPI.store(addForm.value);
    toast?.success('Student created successfully.');
    showAddModal.value = false;
    filters.value.search = addForm.value.student_id;
    fetchStudents();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to create student.');
  } finally {
    saving.value = false;
  }
}

async function toggleActive(s) {
  try {
    await studentAPI.toggleActive(s.id);
    s.is_active = !s.is_active;
    toast?.success(`Student ${s.is_active ? 'activated' : 'deactivated'}.`);
  } catch (e) {
    toast?.error('Failed to update student status.');
  }
}


async function markGraduated(s) {
  try {
    await studentAPI.graduate(s.id);
    s.is_active = false;
    toast?.success('Student marked as graduated. Records preserved.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark as graduated.');
  }
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}
async function uploadFile() {
  if (!selectedFile.value) return;
  importing.value = true;
  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);
    const res = await studentAPI.import(formData);
    importResult.value = res.data;
    toast?.success(`${res.data.created} students imported successfully.`);
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to import file.');
  } finally {
    importing.value = false;
  }
}
</script>