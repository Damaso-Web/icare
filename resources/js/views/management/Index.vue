<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Management</h1>
      <p>Manage Colleges, Programs, Departments, referral form options, and document headers used across iCARE.</p>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
      <button
        v-for="t in tabs"
        :key="t.key"
        class="ibtn ibtn-sm"
        :style="activeTab === t.key ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="selectTab(t.key)"
      >
        {{ t.label }}
      </button>
    </div>

    <!-- ============ COLLEGES ============ -->
    <div v-if="activeTab === 'colleges'" class="icard">
      <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between">
        <span class="icard-title">Colleges</span>
        <button class="ibtn ibtn-p ibtn-sm" @click="openCollegeModal()">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add College
        </button>
      </div>
      <div v-if="loadingColleges" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="colleges.length === 0" class="empty-state">
        <h3>No colleges yet</h3>
        <p>Add one to get started.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr><th>ID</th><th>Name</th><th>Abbrev.</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="c in colleges" :key="c.id">
              <td style="font-family:var(--mono);font-size:12px">{{ c.id }}</td>
              <td>{{ c.name }}</td>
              <td>{{ c.abbrev || '-' }}</td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openCollegeModal(c)">Edit</button>
                  <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="deleteCollege(c)">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ============ PROGRAMS ============ -->
    <div v-if="activeTab === 'programs'" class="icard">
      <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <span class="icard-title">Programs</span>
        <div style="display:flex;gap:8px;align-items:center">
          <select v-model="programCollegeFilter" class="fsm" @change="fetchPrograms">
            <option value="">All Colleges</option>
            <option v-for="c in colleges" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <button class="ibtn ibtn-p ibtn-sm" @click="openProgramModal()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Program
          </button>
        </div>
      </div>
      <div v-if="loadingPrograms" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="programs.length === 0" class="empty-state">
        <h3>No programs found</h3>
        <p>Add one, or add a College first.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr><th>ID</th><th>Name</th><th>College</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="p in programs" :key="p.id">
              <td style="font-family:var(--mono);font-size:12px">{{ p.id }}</td>
              <td>{{ p.name }}</td>
              <td>{{ p.college?.name || '-' }}</td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openProgramModal(p)">Edit</button>
                  <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="deleteProgram(p)">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ============ DEPARTMENTS ============ -->
    <div v-if="activeTab === 'departments'" class="icard">
      <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <span class="icard-title">Departments</span>
        <div style="display:flex;gap:8px;align-items:center">
          <select v-model="departmentCollegeFilter" class="fsm" @change="fetchDepartments">
            <option value="">All Colleges</option>
            <option v-for="c in colleges" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
          <button class="ibtn ibtn-p ibtn-sm" @click="openDepartmentModal()">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Department
          </button>
        </div>
      </div>
      <div v-if="loadingDepartments" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="departments.length === 0" class="empty-state">
        <h3>No departments found</h3>
        <p>Add one, or add a College first.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr><th>ID</th><th>Name</th><th>College</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="d in departments" :key="d.id">
              <td style="font-family:var(--mono);font-size:12px">{{ d.id }}</td>
              <td>{{ d.name }}</td>
              <td>{{ d.college?.name || '-' }}</td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openDepartmentModal(d)">Edit</button>
                  <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="deleteDepartment(d)">Delete</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ============ WELLNESS SERVICES (referral_type) ============ -->
    <div v-if="activeTab === 'wellness'" class="icard">
      <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between">
        <span class="icard-title">Wellness Services</span>
        <button class="ibtn ibtn-p ibtn-sm" @click="openFormOptionModal()">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Service
        </button>
      </div>
      <div style="padding:12px 18px;font-size:12px;color:var(--fog);border-bottom:1px solid var(--cloud)">
        Populates "Service Requested" on the Refer Student form (counseling, testing, and other non-disciplinary services). Each service is tagged GCU or TMDU so filters and queues route correctly. Editing a Label only changes how it displays - existing referrals keep their stored value.
      </div>
      <FormOptionTable
        :loading="loadingFormOptions"
        :options="formOptions"
        :show-unit="true"
        @edit="openFormOptionModal"
        @delete="deleteFormOption"
      />
    </div>

    <!-- ============ DISCIPLINARY SERVICES / ACTS OF MISCONDUCT (act_of_misconduct) ============ -->
    <div v-if="activeTab === 'disciplinary'" class="icard">
      <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between">
        <span class="icard-title">Disciplinary Services (Acts of Misconduct)</span>
        <button class="ibtn ibtn-p ibtn-sm" @click="openFormOptionModal()">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Act of Misconduct
        </button>
      </div>
      <div style="padding:12px 18px;font-size:12px;color:var(--fog);border-bottom:1px solid var(--cloud)">
        Populates the Act of Misconduct list used when filing a Complaint / SDU referral. Editing a Label only changes how it displays - existing records keep their stored value.
      </div>
      <FormOptionTable
        :loading="loadingFormOptions"
        :options="formOptions"
        @edit="openFormOptionModal"
        @delete="deleteFormOption"
      />
    </div>

    <!-- ============ REFERRAL SOURCES (referral_source) ============ -->
    <div v-if="activeTab === 'sources'" class="icard">
      <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between">
        <span class="icard-title">Referral Sources</span>
        <button class="ibtn ibtn-p ibtn-sm" @click="openFormOptionModal()">
          <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Source
        </button>
      </div>
      <div style="padding:12px 18px;font-size:12px;color:var(--fog);border-bottom:1px solid var(--cloud)">
        Populates "Referral Source" on the Refer Student form. Editing a Label only changes how it displays - existing referrals keep their stored value.
      </div>
      <FormOptionTable
        :loading="loadingFormOptions"
        :options="formOptions"
        @edit="openFormOptionModal"
        @delete="deleteFormOption"
      />
    </div>

    <!-- ============ DOCUMENT HEADERS ============ -->
    <div v-if="activeTab === 'documents'" style="display:flex;flex-direction:column;gap:16px">
      <div v-if="!isAdmin" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber)">
        Only Admin can edit document headers. You can view the current values below.
      </div>

      <!-- Referral Slip (QF-OSS-01) -->
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Referral Slip / Form (QF-OSS-01)</span></div>
        <div class="icard-body">
          <div v-if="docError.referral" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px;margin-bottom:12px">{{ docError.referral }}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
              <label class="ifl">Revision No.</label>
              <input v-model="referralDoc.revision_no" class="ifi" placeholder="e.g. 01" :disabled="!isAdmin" />
            </div>
            <div>
              <label class="ifl">Effectivity Date</label>
              <input v-model="referralDoc.effectivity_date" type="date" class="ifi" :disabled="!isAdmin" />
            </div>
            <div>
              <label class="ifl">Ctrl No. - Year</label>
              <input v-model="referralDoc.ctrl_no_year" class="ifi" placeholder="e.g. 26" maxlength="4" :disabled="!isAdmin" />
            </div>
            <div>
              <label class="ifl">Ctrl No. - Term</label>
              <select v-model="referralDoc.ctrl_no_term" class="ifse" :disabled="!isAdmin">
                <option value="1">1 (First Sem)</option>
                <option value="2">2 (Second Sem)</option>
                <option value="S">S (Summer / Mid-Year)</option>
              </select>
            </div>
          </div>
          <div style="font-size:11px;color:var(--fog);margin-top:10px">Applies to all referral forms, current and future.</div>
          <button v-if="isAdmin" class="ibtn ibtn-p ibtn-sm" style="margin-top:12px" :disabled="savingDoc.referral" @click="saveDocSettings('QF-OSS-01', referralDoc, 'referral')">
            {{ savingDoc.referral ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>

      <!-- Feedback Slip (QF-OSS-03) -->
      <div class="icard">
        <div class="icard-header"><span class="icard-title">Feedback Slip (QF-OSS-03)</span></div>
        <div class="icard-body">
          <div v-if="docError.feedback" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px;margin-bottom:12px">{{ docError.feedback }}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
              <label class="ifl">Revision No.</label>
              <input v-model="feedbackDoc.revision_no" class="ifi" placeholder="e.g. 01" :disabled="!isAdmin" />
            </div>
            <div>
              <label class="ifl">Effectivity Date</label>
              <input v-model="feedbackDoc.effectivity_date" type="date" class="ifi" :disabled="!isAdmin" />
            </div>
            <div>
              <label class="ifl">Ctrl No. - Year</label>
              <input v-model="feedbackDoc.ctrl_no_year" class="ifi" placeholder="e.g. 26" maxlength="4" :disabled="!isAdmin" />
            </div>
            <div>
              <label class="ifl">Ctrl No. - Term</label>
              <select v-model="feedbackDoc.ctrl_no_term" class="ifse" :disabled="!isAdmin">
                <option value="1">1 (First Sem)</option>
                <option value="2">2 (Second Sem)</option>
                <option value="S">S (Summer / Mid-Year)</option>
              </select>
            </div>
          </div>
          <div style="font-size:11px;color:var(--fog);margin-top:10px">Applies to all feedback slips, current and future.</div>
          <button v-if="isAdmin" class="ibtn ibtn-p ibtn-sm" style="margin-top:12px" :disabled="savingDoc.feedback" @click="saveDocSettings('QF-OSS-03', feedbackDoc, 'feedback')">
            {{ savingDoc.feedback ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ============ College Modal ============ -->
    <div v-if="showCollegeModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCollegeModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ collegeForm.id ? 'Edit College' : 'Add College' }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="collegeError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ collegeError }}</div>
          <div>
            <label class="ifl">Name <span style="color:var(--red)">*</span></label>
            <input v-model="collegeForm.name" class="ifi" placeholder="e.g. College of Engineering (CE)" />
          </div>
          <div>
            <label class="ifl">Abbreviation</label>
            <input v-model="collegeForm.abbrev" class="ifi" placeholder="e.g. CE" />
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!collegeForm.name.trim() || savingCollege" @click="saveCollege">
              {{ savingCollege ? 'Saving...' : (collegeForm.id ? 'Save Changes' : 'Add College') }}
            </button>
            <button class="ibtn ibtn-o" @click="showCollegeModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Program Modal ============ -->
    <div v-if="showProgramModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showProgramModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ programForm.id ? 'Edit Program' : 'Add Program' }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="programError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ programError }}</div>
          <div>
            <label class="ifl">College <span style="color:var(--red)">*</span></label>
            <select v-model="programForm.college_id" class="ifse">
              <option value="" disabled hidden>Select college...</option>
              <option v-for="c in colleges" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="ifl">Program Name <span style="color:var(--red)">*</span></label>
            <input v-model="programForm.name" class="ifi" placeholder="e.g. Bachelor of Science in Information Technology" />
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!programForm.college_id || !programForm.name.trim() || savingProgram" @click="saveProgram">
              {{ savingProgram ? 'Saving...' : (programForm.id ? 'Save Changes' : 'Add Program') }}
            </button>
            <button class="ibtn ibtn-o" @click="showProgramModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Department Modal ============ -->
    <div v-if="showDepartmentModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showDepartmentModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ departmentForm.id ? 'Edit Department' : 'Add Department' }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="departmentError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ departmentError }}</div>
          <div>
            <label class="ifl">College <span style="color:var(--red)">*</span></label>
            <select v-model="departmentForm.college_id" class="ifse">
              <option value="" disabled hidden>Select college...</option>
              <option v-for="c in colleges" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="ifl">Department Name <span style="color:var(--red)">*</span></label>
            <input v-model="departmentForm.name" class="ifi" placeholder="e.g. Information Technology" />
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!departmentForm.college_id || !departmentForm.name.trim() || savingDepartment" @click="saveDepartment">
              {{ savingDepartment ? 'Saving...' : (departmentForm.id ? 'Save Changes' : 'Add Department') }}
            </button>
            <button class="ibtn ibtn-o" @click="showDepartmentModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Form Option Modal (shared by Wellness / Disciplinary / Sources) ============ -->
    <div v-if="showFormOptionModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showFormOptionModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ formOptionForm.id ? 'Edit' : 'Add' }} {{ formOptionModalNoun }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="formOptionError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ formOptionError }}</div>
          <div>
            <label class="ifl">Label <span style="color:var(--red)">*</span></label>
            <input v-model="formOptionForm.label" class="ifi" placeholder="What staff will see in the dropdown" />
          </div>
          <div v-if="activeTab === 'wellness'">
            <label class="ifl">Unit <span style="color:var(--red)">*</span></label>
            <select v-model="formOptionForm.unit" class="ifse">
              <option value="" disabled hidden>Select unit...</option>
              <option value="GCU">GCU</option>
              <option value="TMDU">TMDU</option>
            </select>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Which unit handles this service. Referrals to TMDU still go through GCU first - this tag only controls where the service shows up in filters and queues.</div>
          </div>
          <div v-if="!formOptionForm.id">
            <label class="ifl">Stored Value <span style="color:var(--red)">*</span></label>
            <input
              v-model="formOptionForm.value"
              class="ifi"
              placeholder="e.g. counseling"
              @input="formOptionForm.value = formOptionForm.value.replace(/[^a-zA-Z0-9_]/g, '')"
            />
            <div style="font-size:11px;color:var(--stone);margin-top:4px">A short internal code (letters, numbers, underscores only). This gets stored on records and can't be changed later.</div>
          </div>
          <div v-else>
            <label class="ifl">Stored Value</label>
            <input class="ifi" :value="formOptionForm.value" disabled style="background:var(--snow);color:var(--stone)" />
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Not editable - existing records reference this value.</div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!formOptionForm.label.trim() || (!formOptionForm.id && !formOptionForm.value.trim()) || (activeTab === 'wellness' && !formOptionForm.unit) || savingFormOption" @click="saveFormOption">
              {{ savingFormOption ? 'Saving...' : (formOptionForm.id ? 'Save Changes' : 'Add') }}
            </button>
            <button class="ibtn ibtn-o" @click="showFormOptionModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, inject, computed, onMounted, h } from 'vue';
import axios from 'axios';
import { useAuthStore } from '../../stores/auth';

const toast = inject('toast');
const auth  = useAuthStore();
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

// Document Headers are admin-only to edit (route:role:admin on the backend);
// system_admin can view Management but not save these.
const isAdmin = computed(() => auth.user?.role === 'admin');

const tabs = [
  { key: 'colleges',     label: 'Colleges' },
  { key: 'programs',     label: 'Programs' },
  { key: 'departments',  label: 'Departments' },
  { key: 'wellness',     label: 'Wellness Services' },
  { key: 'disciplinary', label: 'Disciplinary Services' },
  { key: 'sources',      label: 'Referral Sources' },
  { key: 'documents',    label: 'Document Headers' },
];
const activeTab = ref('colleges');

// Maps a tab key to its referral_form_options category.
const TAB_CATEGORY = {
  wellness:     'referral_type',
  disciplinary: 'act_of_misconduct',
  sources:      'referral_source',
};
const FORM_OPTION_NOUN = {
  wellness:     'Wellness Service',
  disciplinary: 'Act of Misconduct',
  sources:      'Referral Source',
};
const formOptionModalNoun = computed(() => FORM_OPTION_NOUN[activeTab.value] || 'Option');

// ---------- Colleges ----------
const colleges = ref([]);
const loadingColleges = ref(false);
const showCollegeModal = ref(false);
const collegeForm = ref({ id: null, name: '', abbrev: '' });
const collegeError = ref('');
const savingCollege = ref(false);

async function fetchColleges() {
  loadingColleges.value = true;
  try {
    const res = await axios.get(`${API_BASE}/management/colleges`, authHeaders());
    colleges.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingColleges.value = false;
  }
}

function openCollegeModal(c) {
  collegeForm.value = c ? { id: c.id, name: c.name, abbrev: c.abbrev || '' } : { id: null, name: '', abbrev: '' };
  collegeError.value = '';
  showCollegeModal.value = true;
}

async function saveCollege() {
  collegeError.value = '';
  savingCollege.value = true;
  try {
    if (collegeForm.value.id) {
      await axios.put(`${API_BASE}/management/colleges/${collegeForm.value.id}`, collegeForm.value, authHeaders());
    } else {
      await axios.post(`${API_BASE}/management/colleges`, collegeForm.value, authHeaders());
    }
    toast?.success('College saved.');
    showCollegeModal.value = false;
    fetchColleges();
  } catch (e) {
    collegeError.value = e.response?.data?.message || 'Failed to save college.';
  } finally {
    savingCollege.value = false;
  }
}

async function deleteCollege(c) {
  if (!confirm(`Delete "${c.name}"? This can't be undone.`)) return;
  try {
    await axios.delete(`${API_BASE}/management/colleges/${c.id}`, authHeaders());
    toast?.success('College deleted.');
    fetchColleges();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to delete college.');
  }
}

// ---------- Programs ----------
const programs = ref([]);
const loadingPrograms = ref(false);
const programCollegeFilter = ref('');
const showProgramModal = ref(false);
const programForm = ref({ id: null, college_id: '', name: '' });
const programError = ref('');
const savingProgram = ref(false);

async function fetchPrograms() {
  loadingPrograms.value = true;
  try {
    const params = programCollegeFilter.value ? { college_id: programCollegeFilter.value } : {};
    const res = await axios.get(`${API_BASE}/management/programs`, { ...authHeaders(), params });
    programs.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingPrograms.value = false;
  }
}

function openProgramModal(p) {
  programForm.value = p ? { id: p.id, college_id: p.college_id, name: p.name } : { id: null, college_id: '', name: '' };
  programError.value = '';
  showProgramModal.value = true;
}

async function saveProgram() {
  programError.value = '';
  savingProgram.value = true;
  try {
    if (programForm.value.id) {
      await axios.put(`${API_BASE}/management/programs/${programForm.value.id}`, programForm.value, authHeaders());
    } else {
      await axios.post(`${API_BASE}/management/programs`, programForm.value, authHeaders());
    }
    toast?.success('Program saved.');
    showProgramModal.value = false;
    fetchPrograms();
  } catch (e) {
    programError.value = e.response?.data?.message || 'Failed to save program.';
  } finally {
    savingProgram.value = false;
  }
}

async function deleteProgram(p) {
  if (!confirm(`Delete "${p.name}"? This can't be undone.`)) return;
  try {
    await axios.delete(`${API_BASE}/management/programs/${p.id}`, authHeaders());
    toast?.success('Program deleted.');
    fetchPrograms();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to delete program.');
  }
}

// ---------- Departments ----------
const departments = ref([]);
const loadingDepartments = ref(false);
const departmentCollegeFilter = ref('');
const showDepartmentModal = ref(false);
const departmentForm = ref({ id: null, college_id: '', name: '' });
const departmentError = ref('');
const savingDepartment = ref(false);

async function fetchDepartments() {
  loadingDepartments.value = true;
  try {
    const params = departmentCollegeFilter.value ? { college_id: departmentCollegeFilter.value } : {};
    const res = await axios.get(`${API_BASE}/management/departments`, { ...authHeaders(), params });
    departments.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingDepartments.value = false;
  }
}

function openDepartmentModal(d) {
  departmentForm.value = d ? { id: d.id, college_id: d.college_id, name: d.name } : { id: null, college_id: '', name: '' };
  departmentError.value = '';
  showDepartmentModal.value = true;
}

async function saveDepartment() {
  departmentError.value = '';
  savingDepartment.value = true;
  try {
    if (departmentForm.value.id) {
      await axios.put(`${API_BASE}/management/departments/${departmentForm.value.id}`, departmentForm.value, authHeaders());
    } else {
      await axios.post(`${API_BASE}/management/departments`, departmentForm.value, authHeaders());
    }
    toast?.success('Department saved.');
    showDepartmentModal.value = false;
    fetchDepartments();
  } catch (e) {
    departmentError.value = e.response?.data?.message || 'Failed to save department.';
  } finally {
    savingDepartment.value = false;
  }
}

async function deleteDepartment(d) {
  if (!confirm(`Delete "${d.name}"? This can't be undone.`)) return;
  try {
    await axios.delete(`${API_BASE}/management/departments/${d.id}`, authHeaders());
    toast?.success('Department deleted.');
    fetchDepartments();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to delete department.');
  }
}

// ---------- Wellness / Disciplinary / Referral Sources (all referral_form_options, split by category) ----------
const formOptions = ref([]);
const loadingFormOptions = ref(false);
const showFormOptionModal = ref(false);
const formOptionForm = ref({ id: null, category: 'referral_type', value: '', label: '', unit: '' });
const formOptionError = ref('');
const savingFormOption = ref(false);

async function fetchFormOptions() {
  const category = TAB_CATEGORY[activeTab.value];
  if (!category) return;
  loadingFormOptions.value = true;
  try {
    const res = await axios.get(`${API_BASE}/management/form-options`, { ...authHeaders(), params: { category } });
    formOptions.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingFormOptions.value = false;
  }
}

function openFormOptionModal(o) {
  const category = TAB_CATEGORY[activeTab.value];
  formOptionForm.value = o
    ? { id: o.id, category: o.category, value: o.value, label: o.label, unit: o.unit || '' }
    : { id: null, category, value: '', label: '', unit: '' };
  formOptionError.value = '';
  showFormOptionModal.value = true;
}

async function saveFormOption() {
  formOptionError.value = '';
  savingFormOption.value = true;
  try {
    if (formOptionForm.value.id) {
      const payload = { label: formOptionForm.value.label };
      if (activeTab.value === 'wellness') payload.unit = formOptionForm.value.unit;
      await axios.put(`${API_BASE}/management/form-options/${formOptionForm.value.id}`, payload, authHeaders());
    } else {
      await axios.post(`${API_BASE}/management/form-options`, formOptionForm.value, authHeaders());
    }
    toast?.success('Saved.');
    showFormOptionModal.value = false;
    fetchFormOptions();
  } catch (e) {
    formOptionError.value = e.response?.data?.message || 'Failed to save.';
  } finally {
    savingFormOption.value = false;
  }
}

async function deleteFormOption(o) {
  if (!confirm(`Delete "${o.label}"? This can't be undone.`)) return;
  try {
    await axios.delete(`${API_BASE}/management/form-options/${o.id}`, authHeaders());
    toast?.success('Deleted.');
    fetchFormOptions();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to delete.');
  }
}

// ---------- Document Headers (QF-OSS-01 Referral Slip, QF-OSS-03 Feedback Slip) ----------
const referralDoc = ref({ revision_no: '', effectivity_date: '', ctrl_no_year: '', ctrl_no_term: '1' });
const feedbackDoc = ref({ revision_no: '', effectivity_date: '', ctrl_no_year: '', ctrl_no_term: '1' });
const savingDoc = ref({ referral: false, feedback: false });
const docError  = ref({ referral: '', feedback: '' });

function toDateInput(date) {
  return date ? new Date(date).toISOString().slice(0, 10) : '';
}

async function fetchDocSettings(code, target) {
  try {
    const res = await axios.get(`${API_BASE}/document-settings/${code}`, authHeaders());
    target.value = {
      revision_no:      res.data.revision_no || '01',
      effectivity_date: toDateInput(res.data.effectivity_date),
      ctrl_no_year:     res.data.ctrl_no_year || '',
      ctrl_no_term:     res.data.ctrl_no_term || '1',
    };
  } catch (e) {
    console.error(e);
  }
}

async function saveDocSettings(code, form, key) {
  docError.value[key] = '';
  savingDoc.value[key] = true;
  try {
    await axios.put(`${API_BASE}/document-settings/${code}`, form.value, authHeaders());
    toast?.success('Document header updated.');
  } catch (e) {
    docError.value[key] = e.response?.data?.message || 'Failed to update document header.';
  } finally {
    savingDoc.value[key] = false;
  }
}

// FormOptionTable - small local render-function component to avoid repeating
// the same table markup three times (Wellness / Disciplinary / Sources).
const FormOptionTable = {
  props: ['loading', 'options', 'showUnit'],
  emits: ['edit', 'delete'],
  setup(props, { emit }) {
    return () => {
      if (props.loading) {
        return h('div', { style: 'text-align:center;padding:44px' }, [
          h('div', { style: 'width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto' }),
        ]);
      }
      if (!props.options.length) {
        return h('div', { class: 'empty-state' }, [
          h('h3', 'No options yet'),
          h('p', 'Add one to get started.'),
        ]);
      }
      const headerCells = [h('th', 'ID'), h('th', 'Label')];
      if (props.showUnit) headerCells.push(h('th', 'Unit'));
      headerCells.push(h('th', 'Stored Value'), h('th'));

      return h('div', { class: 'ts' }, [
        h('table', { class: 'itable' }, [
          h('thead', h('tr', headerCells)),
          h('tbody', props.options.map(o => {
            const cells = [
              h('td', { style: 'font-family:var(--mono);font-size:12px' }, o.id),
              h('td', o.label),
            ];
            if (props.showUnit) {
              cells.push(h('td', o.unit
                ? h('span', { class: 'ibadge', style: o.unit === 'GCU' ? 'background:var(--mist);color:var(--moss)' : 'background:var(--blue-lt);color:var(--blue)' }, o.unit)
                : h('span', { style: 'color:var(--fog);font-size:12px' }, '-')));
            }
            cells.push(
              h('td', { style: 'font-family:var(--mono);font-size:12px;color:var(--fog)' }, o.value),
              h('td', h('div', { style: 'display:flex;gap:6px;justify-content:flex-end' }, [
                h('button', { class: 'ibtn ibtn-o ibtn-sm', onClick: () => emit('edit', o) }, 'Edit'),
                h('button', { class: 'ibtn ibtn-sm', style: 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0', onClick: () => emit('delete', o) }, 'Delete'),
              ])),
            );
            return h('tr', { key: o.id }, cells);
          })),
        ]),
      ]);
    };
  },
};

// Switch tabs and refetch whichever section's data the newly active tab needs.
function selectTab(key) {
  activeTab.value = key;
  if (TAB_CATEGORY[key]) fetchFormOptions();
}

onMounted(async () => {
  await fetchColleges();
  fetchPrograms();
  fetchDepartments();
  fetchDocSettings('QF-OSS-01', referralDoc);
  fetchDocSettings('QF-OSS-03', feedbackDoc);
});
</script>