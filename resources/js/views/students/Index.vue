<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>{{ showArchived ? 'Inactive Students' : 'Student Profiles' }}</h1>
      <p>{{ showArchived ? 'View and reactivate inactive or graduated student records.' : 'Search for a student to view their records.' }}</p>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button
        class="ibtn ibtn-sm"
        :style="!showArchived ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="switchTab(false)"
      >
        Active Students
      </button>
      <button
        class="ibtn ibtn-sm"
        :style="showArchived ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="switchTab(true)"
      >
        Inactive Students
      </button>
    </div>

    <!-- Search Bar -->
    <div class="filter-bar">
      <div class="sw" style="flex:1;max-width:400px">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          :placeholder="showArchived ? 'Search inactive student name or ID...' : 'Search name or student ID...'"
          style="width:100%"
          @input="onSearchInput"
        />
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
      <button v-if="!showArchived" class="ibtn ibtn-o ibtn-sm" @click="openImportModal">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload Masterlist
      </button>
      <button v-if="!showArchived" class="ibtn ibtn-p ibtn-sm" style="margin-left:auto" type="button" @click="openAddModal">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Student
      </button>
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
                    v-if="s.is_active"
                    class="ibtn ibtn-sm"
                    style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0"
                    @click.stop="confirmGraduate(s)"
                  >
                    Deactivate
                  </button>
                  <button
                    v-else
                    class="ibtn ibtn-sm"
                    style="background:var(--mist);color:var(--moss);border:1.5px solid var(--mint)"
                    @click.stop="toggleActive(s)"
                  >
                    Unarchive
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
                v-model="addForm.student_id"
                class="ifi"
                placeholder="e.g. 2302021"
                @input="addForm.student_id = onlyDigits(addForm.student_id)"
              />
            </div>
            <div>
              <label class="ifl">Sex</label>
              <select v-model="addForm.sex" class="ifse">
                <option value="">Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input v-model="addForm.last_name" class="ifi" placeholder="Dela Cruz" @input="addForm.last_name = onlyLetters(addForm.last_name)" />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input v-model="addForm.first_name" class="ifi" placeholder="Juan" @input="addForm.first_name = onlyLetters(addForm.first_name)" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="addForm.middle_name" class="ifi" placeholder="Santos" @input="addForm.middle_name = onlyLetters(addForm.middle_name)" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input v-model="addForm.suffix" class="ifi" placeholder="Jr." @input="addForm.suffix = onlyLettersStrict(addForm.suffix)" />
            </div>
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
              <input
                v-model="addForm.section"
                class="ifi"
                placeholder="e.g. A"
                maxlength="1"
                @input="addForm.section = addForm.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase()"
              />
            </div>
            <div>
              <label class="ifl">Email</label>
              <input v-model="addForm.email" class="ifi" placeholder="student@bsu.edu.ph" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="addForm.contact_number" class="ifi" placeholder="09XXXXXXXXX" @input="addForm.contact_number = contactNumberInput(addForm.contact_number)" />
            </div>
            <div>
              <label class="ifl">Guardian Name</label>
              <input v-model="addForm.guardian_name" class="ifi" placeholder="Guardian full name" @input="addForm.guardian_name = onlyLetters(addForm.guardian_name)" />
            </div>
            <div>
              <label class="ifl">Guardian Contact</label>
              <input v-model="addForm.guardian_contact" class="ifi" placeholder="09XXXXXXXXX" @input="addForm.guardian_contact = contactNumberInput(addForm.guardian_contact)" />
            </div>
            <div>
              <label class="ifl">Guardian Relationship</label>
              <select v-model="addForm.guardian_relationship" class="ifse">
                <option value="">Select...</option>
                <option>Mother</option>
                <option>Father</option>
                <option>Guardian</option>
                <option>Sibling</option>
                <option>Relative</option>
              </select>
            </div>
          </div>
          <div v-if="addError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
            {{ addError }}
          </div>
          <div style="display:flex;gap:8px;padding-top:4px">
            <button class="ibtn ibtn-p" type="button" @click="saveStudent" :disabled="saving">
              <svg v-if="!saving" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ saving ? 'Saving...' : 'Add Student' }}
            </button>
            <button class="ibtn ibtn-o" type="button" @click="clearAddForm">Clear Form</button>
            <button class="ibtn ibtn-o" type="button" @click="showAddModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Import Modal — Step 1: Select File -->
    <div v-if="showImportModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeImportModal">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Upload Student Masterlist</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="closeImportModal">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--stone);line-height:1.6">
            Download the template below, fill it in, then upload it here.
          </div>
          <a href="/templates/student_masterlist_template.xlsx" download class="ibtn ibtn-o" style="width:100%;justify-content:center">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Template
          </a>
          <div>
            <label class="ifl">File</label>
            <input type="file" accept=".csv,.xlsx,.xls" class="ifi" @change="handleFileSelect" />
          </div>
          <div v-if="previewError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
            {{ previewError }}
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="loadPreview" :disabled="!selectedFile || loadingPreview">
              <span v-if="loadingPreview" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ loadingPreview ? 'Reading File...' : 'Preview' }}
            </button>
            <button class="ibtn ibtn-o" @click="closeImportModal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Import Modal — Step 2: Preview + Decisions -->
    <div v-if="showPreviewModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showPreviewModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div>
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Review Before Uploading</div>
            <div style="font-size:12px;color:var(--stone)">{{ previewData.total }} record(s) found — {{ previewData.duplicates }} duplicate(s)</div>
          </div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showPreviewModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:10px">

          <div v-if="previewData.duplicates > 0" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber)">
            ⚠ Some Student IDs already exist in the system. Choose whether to <strong>Update</strong> the existing record or <strong>Skip</strong> it for each one below.
          </div>

          <div style="max-height:340px;overflow-y:auto;border:1px solid var(--cloud);border-radius:var(--r-sm)">
            <table class="itable">
              <thead>
                <tr>
                  <th>Row</th>
                  <th>Student ID</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in previewData.preview" :key="item.row">
                  <td style="font-size:12px">{{ item.row }}</td>
                  <td style="font-family:var(--mono);font-size:12px">{{ item.student_id || '—' }}</td>
                  <td>
                    <span v-if="!item.valid" class="ibadge" style="background:var(--red-lt);color:var(--red)">Invalid</span>
                    <span v-else-if="item.is_duplicate" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">Duplicate</span>
                    <span v-else class="ibadge" style="background:var(--mist);color:var(--moss)">New</span>
                  </td>
                  <td>
                    <select v-if="item.is_duplicate && item.valid" v-model="decisions[item.row - 2]" class="fsm" style="font-size:11px;padding:4px 8px">
                      <option value="update">Update existing</option>
                      <option value="skip">Skip this one</option>
                    </select>
                    <span v-else-if="!item.valid" style="font-size:11px;color:var(--stone)">Will be skipped</span>
                    <span v-else style="font-size:11px;color:var(--moss)">Will be added</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="importResult" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
            ✓ {{ importResult.created }} added, {{ importResult.updated }} updated, {{ importResult.skipped }} skipped.
            <div v-if="importResult.errors?.length" style="margin-top:6px;font-size:11px;color:var(--red)">
              <div v-for="(err, i) in importResult.errors" :key="i">{{ err }}</div>
            </div>
          </div>

          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="confirmImport" :disabled="importing">
              <span v-if="importing" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ importing ? 'Uploading...' : 'Confirm Upload' }}
            </button>
            <button class="ibtn ibtn-o" @click="showPreviewModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Deactivate Confirmation Modal -->
    <div v-if="showGraduateModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showGraduateModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Deactivate / Mark Graduated</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            This should only be done when the student has officially <strong>graduated</strong>. Their records will be preserved and can be reactivated later if needed.
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" v-model="graduateConfirmed" id="gradConfirm" style="width:15px;height:15px;accent-color:var(--moss)" />
            <label for="gradConfirm" style="font-size:13px;color:var(--slate);cursor:pointer">I confirm this student has graduated.</label>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" :disabled="!graduateConfirmed" @click="doGraduate">Confirm</button>
            <button class="ibtn ibtn-o" @click="showGraduateModal = false">Cancel</button>
          </div>
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
import { onlyLetters, onlyLettersStrict, onlyDigits, contactNumberInput } from '../../utils/validators';

const toast   = inject('toast');
const colleges = COLLEGES;

const students   = ref([]);
const loading    = ref(false);
const saving     = ref(false);
const pagination = ref({});
const filters    = ref({ search: '' });
const showArchived = ref(false);
const showAddModal    = ref(false);
const showGraduateModal = ref(false);
const graduateConfirmed = ref(false);
const studentToGraduate = ref(null);
const addError = ref('');

const addForm = ref({
  student_id: '', last_name: '', first_name: '', middle_name: '', suffix: '', sex: '',
  college: '', program: '', year_level: '', section: '', email: '', contact_number: '',
  guardian_name: '', guardian_contact: '', guardian_relationship: '',
});

const availablePrograms = computed(() => PROGRAMS_BY_COLLEGE[addForm.value.college] || []);

// Import flow state
const showImportModal  = ref(false);
const showPreviewModal = ref(false);
const selectedFile     = ref(null);
const loadingPreview   = ref(false);
const previewError     = ref('');
const previewData      = ref({ preview: [], total: 0, duplicates: 0, token: '' });
const decisions        = ref({});
const importing        = ref(false);
const importResult     = ref(null);

let searchTimeout = null;

function switchTab(archived) {
  showArchived.value = archived;
  filters.value.search = '';
  students.value = [];
  pagination.value = {};
}

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
    const params = { ...filters.value, page };
    params.is_active = showArchived.value ? 0 : 1;
    const res = await studentAPI.index(params);
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
  clearAddForm();
  showAddModal.value = true;
}

function clearAddForm() {
  addForm.value = {
    student_id: '', last_name: '', first_name: '', middle_name: '', suffix: '', sex: '',
    college: '', program: '', year_level: '', section: '', email: '', contact_number: '',
    guardian_name: '', guardian_contact: '', guardian_relationship: '',
  };
  addError.value = '';
}

async function saveStudent() {
  addError.value = '';
  if (!addForm.value.student_id || !addForm.value.last_name || !addForm.value.first_name) {
    addError.value = 'Please fill in all required fields.';
    return;
  }
  saving.value = true;
  try {
    await studentAPI.store(addForm.value);
    toast?.success('Student added successfully.');
    showAddModal.value = false;
  } catch (e) {
    addError.value = e.response?.data?.message || 'Please fill in all required fields.';
  } finally {
    saving.value = false;
  }
}

function confirmGraduate(s) {
  studentToGraduate.value = s;
  graduateConfirmed.value = false;
  showGraduateModal.value = true;
}

async function doGraduate() {
  if (!studentToGraduate.value) return;
  try {
    await studentAPI.graduate(studentToGraduate.value.id);
    showGraduateModal.value = false;
    toast?.success('Student marked as inactive/graduated. Records preserved.');
    fetchStudents();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Please fill in all required fields.');
  }
}

async function toggleActive(s) {
  try {
    await studentAPI.toggleActive(s.id);
    toast?.success('Student reactivated.');
    fetchStudents();
  } catch (e) {
    toast?.error('Please fill in all required fields.');
  }
}

// --- Import flow ---
function openImportModal() {
  selectedFile.value = null;
  previewError.value = '';
  showImportModal.value = true;
}

function closeImportModal() {
  showImportModal.value = false;
}

function handleFileSelect(e) {
  selectedFile.value = e.target.files[0];
  previewError.value = '';
}

async function loadPreview() {
  if (!selectedFile.value) return;
  loadingPreview.value = true;
  previewError.value = '';
  try {
    const formData = new FormData();
    formData.append('file', selectedFile.value);
    const res = await studentAPI.importPreview(formData);
    previewData.value = res.data;

    // Default decisions: update for duplicates
    decisions.value = {};
    res.data.preview.forEach((item, idx) => {
      if (item.is_duplicate) decisions.value[idx] = 'update';
      else decisions.value[idx] = 'create';
    });

    importResult.value = null;
    showImportModal.value = false;
    showPreviewModal.value = true;
  } catch (e) {
    previewError.value = e.response?.data?.message || 'Failed to read file. Please check the format.';
  } finally {
    loadingPreview.value = false;
  }
}

async function confirmImport() {
  importing.value = true;
  try {
    const res = await studentAPI.importConfirm({
      token: previewData.value.token,
      decisions: decisions.value,
    });
    importResult.value = res.data;
    toast?.success(`${res.data.created} added, ${res.data.updated} updated.`);
    fetchStudents();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to complete import.');
  } finally {
    importing.value = false;
  }
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}
</script>