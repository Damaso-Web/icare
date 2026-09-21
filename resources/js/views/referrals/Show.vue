
Show · VUE
<template>
  <div class="fade-up">
 
    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>
 
    <template v-else>
      <!-- Back + Case pill + Edit -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
        <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
          <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </button>
        <span
          v-if="primaryCase"
          class="ibadge"
          style="font-family:var(--mono);background:var(--mist);color:var(--moss)"
        >
          {{ primaryCase.case_number }}
        </span>
        <span
          v-if="primaryCase && ['closed', 'resolved'].includes(primaryCase.status)"
          class="ibadge"
          style="background:var(--cloud);color:var(--stone)"
        >
          {{ primaryCase.status === 'closed' ? 'Closed Case' : 'Resolved Case' }}
        </span>
        <span v-if="isRecurringStudent" class="ibadge ibadge-in_progress">Recurring</span>
        <!-- Editing a student record belongs to the Student/Client module.
             Case Files is a read-only case view, so the action is hidden there. -->
        <div v-if="!fromCases" style="margin-left:auto">
          <button class="ibtn ibtn-o ibtn-sm" @click="showEditModal = true">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Student Profile
          </button>
        </div>
      </div>
 
      <div style="display:grid;grid-template-columns:1fr 300px;gap:16px">
 
        <!-- Left: Referrals -->
        <div style="display:flex;flex-direction:column;gap:16px">
 
          <!-- Referral List -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Referrals</span></div>
            <div style="padding:12px 18px;border-bottom:1px solid var(--cloud)">
              <div style="position:relative">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;position:absolute;left:10px;top:50%;transform:translateY(-50%);stroke:var(--fog);fill:none;stroke-width:2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input v-model="referralSearch" class="ifi" style="padding-left:32px" placeholder="Search referral no." />
              </div>
            </div>
            <div v-if="!history.referrals?.length" class="empty-state">
              <h3>No referrals yet</h3>
              <p>No referrals found for this student.</p>
            </div>
            <div v-else-if="!filteredReferrals.length" class="empty-state">
              <h3>No matches</h3>
              <p>No referrals match "{{ referralSearch }}".</p>
            </div>
            <div class="ts" v-else>
              <table class="itable">
                <thead>
                  <tr>
                    <th>Refer No.</th>
                    <th>Type</th>
                    <th>Unit</th>
                    <th>Urgency</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="r in filteredReferrals"
                    :key="r.id"
                    style="cursor:pointer"
                    @click="$router.push({ name: 'referral-show', params: { id: r.id }, query: referralCtx })"
                  >
                    <td style="font-family:var(--mono);font-size:11px">{{ r.referral_code }}</td>
                    <td>{{ toTitleCase(r.referral_type) }}</td>
                    <td><span class="ibadge" :class="'unit-' + referralUnit(r.referral_type).toLowerCase()">{{ referralUnit(r.referral_type) }}</span></td>
                    <td><span v-if="r.urgency_level" class="ibadge" :class="'ibadge-' + r.urgency_level">{{ toTitleCase(r.urgency_level) }}</span></td>
                    <td><span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span></td>
                    <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                    <td><button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'referral-show', params: { id: r.id }, query: referralCtx })">View</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
 
          <!-- Related Concerns Comparison - group referrals by type to spot recurrence/escalation -->
          <div class="icard" v-if="history.referrals?.length > 1">
            <div class="icard-header"><span class="icard-title">Related Concerns Comparison</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:16px">
              <div v-for="group in concernGroups" :key="group.type">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                  <div style="font-size:12.5px;font-weight:700;color:var(--ink)">{{ toTitleCase(group.type) }}</div>
                  <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ group.referrals.length }}</span>
                  <span v-if="group.referrals.length > 1" class="ibadge" style="background:var(--blue-lt);color:var(--blue)">Recurring</span>
                </div>
                <div class="ts">
                  <table class="itable" style="table-layout:fixed">
                    <colgroup>
                      <col style="width:15%" />
                      <col style="width:50%" />
                      <col style="width:17.5%" />
                      <col style="width:17.5%" />
                    </colgroup>
                    <thead>
                      <tr><th>Date</th><th>Concern</th><th>Urgency</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                      <tr v-for="r in group.referrals" :key="r.id" style="cursor:pointer" @click="$router.push({ name: 'referral-show', params: { id: r.id }, query: referralCtx })">
                        <td style="font-size:12px;white-space:nowrap">{{ formatDate(r.created_at) }}</td>
                        <td style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.nature_of_concern || '-' }}</td>
                        <td><span v-if="r.urgency_level" class="ibadge" :class="'ibadge-' + r.urgency_level">{{ toTitleCase(r.urgency_level) }}</span></td>
                        <td><span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
 
        </div>
 
        <!-- Right: Student Info -->
        <div style="display:flex;flex-direction:column;gap:16px">
 
          <!-- Student Information (+ Guardian, + Login Credentials) -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Student Information</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
                <div class="qav" style="width:40px;height:40px;font-size:15px">
                  {{ initials(student.first_name, student.last_name) }}
                </div>
                <div>
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ student.last_name }}, {{ student.first_name }} {{ student.middle_name }}</div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ student.student_id }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.college || '-' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.program || '-' }}</div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.year_level || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.section || '-' }}</div>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.sex || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.contact_number || '-' }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.email || '-' }}</div>
              </div>
 
              <div style="height:1px;background:var(--cloud);margin:4px 0"></div>
 
              <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Guardian</div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Full Name</div>
                <div style="font-size:13px;color:var(--ink)">
                  {{ [student.guardian_last_name, student.guardian_first_name, student.guardian_middle_name].filter(Boolean).length
                      ? `${student.guardian_last_name || ''}, ${student.guardian_first_name || ''} ${student.guardian_middle_name || ''}`.trim()
                      : '-' }}
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Relationship</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.guardian_relationship || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.guardian_contact || '-' }}</div>
                </div>
              </div>
            </div>
          </div>
 
          <!-- Appointments - Case Files drills down per referral, where the
               referral-scoped appointment list already lives. Hidden here to
               avoid showing the student's whole appointment history twice. -->
          <div class="icard" v-if="!fromCases">
            <div class="icard-header"><span class="icard-title">Appointments</span></div>
            <div v-if="!history.appointments?.length" class="empty-state">
              <h3>No appointments yet</h3>
              <p>No appointments scheduled for this student.</p>
            </div>
            <div v-else>
              <div
                v-for="a in history.appointments"
                :key="a.id"
                style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:flex-start;gap:8px"
              >
                <div>
                  <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ toTitleCase(a.appointment_type) }}</div>
                  <div style="font-size:11px;color:var(--stone);margin-top:2px;font-family:var(--mono)">{{ a.appointment_code }}</div>
                  <div style="font-size:11px;color:var(--stone);margin-top:4px">{{ formatDate(a.appointment_date) }} · {{ a.start_time }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:2px">With {{ a.staff?.name || '-' }}</div>
                </div>
                <span class="ibadge" :class="'ibadge-' + a.status" style="flex-shrink:0">{{ toTitleCase(a.status) }}</span>
              </div>
            </div>
          </div>
 
        </div>
      </div>
 
      <!-- Edit Student Modal -->
      <div v-if="showEditModal && !fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Edit Student Profile</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
 
            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px">
              Student Information
              <div style="flex:1;height:1px;background:var(--cloud)"></div>
            </div>
 
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="editForm.last_name" class="ifi" placeholder="Last Name" @input="editForm.last_name = onlyLetters(editForm.last_name)" />
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="editForm.first_name" class="ifi" placeholder="First Name" @input="editForm.first_name = onlyLetters(editForm.first_name)" />
              </div>
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="editForm.middle_name" class="ifi" placeholder="Middle Name" @input="editForm.middle_name = onlyLetters(editForm.middle_name)" />
              </div>
              <div>
                <label class="ifl">Suffix</label>
                <input v-model="editForm.suffix" class="ifi" placeholder="Jr., Sr., III" @input="editForm.suffix = onlyLettersStrict(editForm.suffix)" />
              </div>
              <div>
                <label class="ifl">Sex</label>
                <select v-model="editForm.sex" class="ifse">
                  <option value="">Select...</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="ifl">Student ID</label>
                <input v-model="editForm.student_id" class="ifi" placeholder="e.g. 2302021" @input="editForm.student_id = onlyDigits(editForm.student_id)" />
              </div>
              <div>
                <label class="ifl">Year Level</label>
                <select v-model="editForm.year_level" class="ifse">
                  <option value="">Select...</option>
                  <option>1st Year</option>
                  <option>2nd Year</option>
                  <option>3rd Year</option>
                  <option>4th Year</option>
                  <option>5th Year</option>
                </select>
              </div>
              <div>
                <label class="ifl">College</label>
                <select v-model="editForm.college" class="ifse" @change="editForm.program = ''">
                  <option value="">Select college...</option>
                  <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">Program</label>
                <select v-model="editForm.program" class="ifse" :disabled="!editForm.college">
                  <option value="">Select program...</option>
                  <option v-if="editForm.program && !editAvailablePrograms.includes(editForm.program)" :value="editForm.program">{{ editForm.program }}</option>
                  <option v-for="p in editAvailablePrograms" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">Section</label>
                <input
                  v-model="editForm.section"
                  class="ifi"
                  placeholder="e.g. A"
                  maxlength="1"
                  @input="editForm.section = editForm.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase()"
                />
              </div>
              <div>
                <label class="ifl">Email</label>
                <input v-model="editForm.email" class="ifi" placeholder="student@bsu.edu.ph" />
              </div>
              <div>
                <label class="ifl">Contact Number</label>
                <input v-model="editForm.contact_number" class="ifi" placeholder="09XXXXXXXXX" @input="editForm.contact_number = contactNumberInput(editForm.contact_number)" />
              </div>
            </div>
 
            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:4px">
              Guardian Information
              <div style="flex:1;height:1px;background:var(--cloud)"></div>
            </div>
 
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Guardian Last Name <span style="color:var(--red)">*</span></label>
                <input v-model="editForm.guardian_last_name" class="ifi" placeholder="Dela Cruz" @input="editForm.guardian_last_name = onlyLetters(editForm.guardian_last_name)" />
              </div>
              <div>
                <label class="ifl">Guardian First Name <span style="color:var(--red)">*</span></label>
                <input v-model="editForm.guardian_first_name" class="ifi" placeholder="Juan" @input="editForm.guardian_first_name = onlyLetters(editForm.guardian_first_name)" />
              </div>
              <div>
                <label class="ifl">Guardian Middle Name</label>
                <input v-model="editForm.guardian_middle_name" class="ifi" placeholder="Santos" @input="editForm.guardian_middle_name = onlyLetters(editForm.guardian_middle_name)" />
              </div>
              <div>
                <label class="ifl">Guardian Contact <span style="color:var(--red)">*</span></label>
                <input v-model="editForm.guardian_contact" class="ifi" placeholder="09XXXXXXXXX" @input="editForm.guardian_contact = contactNumberInput(editForm.guardian_contact)" />
              </div>
              <div>
                <label class="ifl">Relationship <span style="color:var(--red)">*</span></label>
                <select v-model="editForm.guardian_relationship" class="ifse">
                  <option value="">Select...</option>
                  <option>Mother</option>
                  <option>Father</option>
                  <option>Guardian</option>
                  <option>Sibling</option>
                  <option>Relative</option>
                </select>
              </div>
            </div>
 
            <div v-if="editError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ editError }}
            </div>
 
            <div style="display:flex;gap:8px;padding-top:4px">
              <button class="ibtn ibtn-p" @click="saveStudent" :disabled="saving">
                <svg v-if="!saving" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-if="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
              <button class="ibtn ibtn-o" @click="showEditModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
 
    </template>
  </div>
</template>
 
<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { toTitleCase } from '../../utils/validators';
import { useRoute } from 'vue-router';
import { studentAPI } from '../../api/index';
import { COLLEGES } from '../../constants/colleges';
import { PROGRAMS_BY_COLLEGE } from '../../constants/programs';
import { onlyLetters, onlyLettersStrict, onlyDigits, contactNumberInput, isValidPHContact } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';
 
const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();
const loading = ref(true);
const saving  = ref(false);
const showEditModal = ref(false);
const student = ref({});
const history = ref({});
const colleges = COLLEGES;
const editError = ref('');
 
const editForm = ref({});
const referralSearch = ref('');
 
// Which sidebar module opened this profile. Case Files links in with ctx=cases;
// Student/Client links in with no ctx. This page is shared by both, so the
// flag decides which actions are allowed here.
const fromCases = computed(() => route.query.ctx === 'cases');
 
// Carry the module context forward into Referral Details so that page - and the
// sidebar highlight in MainLayout - knows where the user actually came from.
const referralCtx = computed(() => ({ ctx: fromCases.value ? 'cases' : 'students' }));
 
const editAvailablePrograms = computed(() => PROGRAMS_BY_COLLEGE[editForm.value.college] || []);
const primaryCase = computed(() => history.value.cases?.[0] || null);
const isRecurringStudent = computed(() => (history.value.referrals?.length || 0) > 1);
 
const filteredReferrals = computed(() => {
  const refs = history.value.referrals || [];
  const q = referralSearch.value.trim().toLowerCase();
  if (!q) return refs;
  return refs.filter(r => r.referral_code?.toLowerCase().includes(q));
});
 
const concernGroups = computed(() => {
  const refs = history.value.referrals || [];
  const groups = {};
  for (const r of refs) {
    const type = r.referral_type || 'other';
    if (!groups[type]) groups[type] = [];
    groups[type].push(r);
  }
  return Object.entries(groups)
    .map(([type, referrals]) => ({ type, referrals }))
    .filter(g => g.referrals.length > 0)
    .sort((a, b) => b.referrals.length - a.referrals.length);
});
 
const SDU_TYPES = ['disciplinary'];
const TMDU_TYPES = ['psychological_testing'];
 
function referralUnit(referralType) {
  if (SDU_TYPES.includes(referralType)) return 'SDU';
  if (TMDU_TYPES.includes(referralType)) return 'TMDU';
  return 'GCU';
}
 
async function saveStudent() {
  editError.value = '';
 
  if (editForm.value.contact_number && !isValidPHContact(editForm.value.contact_number)) {
    editError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }
 
  if (!editForm.value.guardian_first_name || !editForm.value.guardian_last_name ||
      !editForm.value.guardian_contact || !editForm.value.guardian_relationship) {
    editError.value = 'Please fill in all required guardian fields.';
    return;
  }
 
  if (!isValidPHContact(editForm.value.guardian_contact)) {
    editError.value = 'Guardian contact number must start with 09 and be 11 digits long.';
    return;
  }
 
  saving.value = true;
  try {
    const res = await studentAPI.update(student.value.id, editForm.value);
    student.value = res.data;
    showEditModal.value = false;
    toast?.success('Student profile updated successfully.');
  } catch (e) {
    editError.value = e.response?.data?.message || 'Please fill in all required fields.';
  } finally {
    saving.value = false;
  }
}
 
function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}
 
function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}
 
onMounted(async () => {
  try {
    const [studentRes, historyRes] = await Promise.all([
      studentAPI.show(route.params.id),
      studentAPI.history(route.params.id),
    ]);
    student.value = studentRes.data;
    history.value = historyRes.data;
    editForm.value = { ...studentRes.data };
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>
