<template>
  <div class="fade-up">

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <!-- Back + Header -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
        <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
          <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </button>
        <div class="ph" style="margin:0">
          <h1>{{ student.last_name }}, {{ student.first_name }} {{ student.middle_name }}</h1>
          <p>{{ student.student_id }} · {{ student.year_level }}, {{ student.college }}</p>
        </div>
        <div style="margin-left:auto">
          <button class="ibtn ibtn-o ibtn-sm" @click="showEditModal = true">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Profile
          </button>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">

        <!-- Left -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Case History -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Case History</span>
              <span class="ibadge ibadge-in_progress" v-if="student.is_recurring">Recurring</span>
            </div>
            <div v-if="!history.cases?.length" class="empty-state">
              <h3>No cases yet</h3>
              <p>No case files found for this student.</p>
            </div>
            <div class="ts" v-else>
              <table class="itable">
                <thead>
                  <tr>
                    <th>Case No.</th>
                    <th>Type</th>
                    <th>Unit</th>
                    <th>Sessions</th>
                    <th>Status</th>
                    <th>Opened</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="c in history.cases"
                    :key="c.id"
                    style="cursor:pointer"
                    @click="$router.push({ name: 'case-show', params: { id: c.id } })"
                  >
                    <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
                    <td>{{ c.case_type?.replace(/_/g,' ') }}</td>
                    <td><span class="ibadge" :class="'unit-' + c.current_unit?.toLowerCase()">{{ c.current_unit }}</span></td>
                    <td>{{ c.total_sessions }}</td>
                    <td><span class="ibadge" :class="'ibadge-' + c.status">{{ c.status }}</span></td>
                    <td style="font-size:12px">{{ formatDate(c.opened_date) }}</td>
                    <td><button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'case-show', params: { id: c.id } })">View</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Referral History -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Referral History</span></div>
            <div v-if="!history.referrals?.length" class="empty-state">
              <h3>No referrals yet</h3>
              <p>No referrals found for this student.</p>
            </div>
            <div class="ts" v-else>
              <table class="itable">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Referred By</th>
                    <th>Status</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="r in history.referrals"
                    :key="r.id"
                    style="cursor:pointer"
                    @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
                  >
                    <td style="font-family:var(--mono);font-size:11px">{{ r.referral_code }}</td>
                    <td>{{ r.referral_type?.replace(/_/g,' ') }}</td>
                    <td>{{ r.referrer_name }}</td>
                    <td><span class="ibadge" :class="'ibadge-' + r.status">{{ r.status?.replace(/_/g,' ') }}</span></td>
                    <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Appointments -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Appointments</span></div>
            <div v-if="!history.appointments?.length" class="empty-state">
              <h3>No appointments yet</h3>
              <p>No appointments scheduled for this student.</p>
            </div>
            <div class="ts" v-else>
              <table class="itable">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Staff</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in history.appointments" :key="a.id">
                    <td style="font-family:var(--mono);font-size:11px">{{ a.appointment_code }}</td>
                    <td>{{ a.appointment_type?.replace(/_/g,' ') }}</td>
                    <td>{{ a.staff?.name }}</td>
                    <td style="font-size:12px">{{ formatDate(a.appointment_date) }}</td>
                    <td style="font-size:12px">{{ a.start_time }}</td>
                    <td><span class="ibadge" :class="'ibadge-' + a.status">{{ a.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>

        <!-- Right: Student Info -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Profile Card -->
          <div class="icard">
            <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:20px;border-radius:var(--r-lg) var(--r-lg) 0 0;text-align:center">
              <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 10px;font-family:var(--serif)">
                {{ initials(student.first_name, student.last_name) }}
              </div>
              <div style="font-size:15px;font-weight:600;color:#fff">{{ student.last_name }}, {{ student.first_name }} {{ student.middle_name }}</div>
              <div style="font-size:11px;color:rgba(255,255,255,.5);font-family:var(--mono);margin-top:2px">{{ student.student_id }}</div>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.year_level || '—' }}</div>
              </div>
              <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.sex || '—' }}</div>
            </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.college || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.program || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.section || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.email || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.contact_number || '—' }}</div>
              </div>
            </div>
          </div>

          <!-- Guardian Info -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Guardian</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Name</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.guardian_name || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.guardian_contact || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Relationship</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.guardian_relationship || '—' }}</div>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Actions</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <router-link :to="{ name: 'referral-create' }" class="ibtn ibtn-p" style="width:100%;justify-content:center">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Submit Referral
              </router-link>
              <router-link :to="{ name: 'appointments' }" class="ibtn ibtn-o" style="width:100%;justify-content:center">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule Appointment
              </router-link>
            </div>
          </div>

        </div>
      </div>

      <!-- Edit Student Modal -->
      <div v-if="showEditModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Edit Student Profile</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showEditModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">

            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px">
              Student Information
              <div style="flex:1;height:1px;background:var(--cloud)"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="editForm.last_name" class="ifi" placeholder="Last Name" />
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="editForm.first_name" class="ifi" placeholder="First Name" />
              </div>
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="editForm.middle_name" class="ifi" placeholder="Middle Name" />
              </div>
              <div>
                <label class="ifl">Suffix</label>
                <input v-model="editForm.suffix" class="ifi" placeholder="Jr., Sr., III" />
              </div>
              <div>
                <label class="ifl">Student ID</label>
                <input v-model="editForm.student_id" class="ifi" placeholder="e.g. 2302021" />
              </div>
              <div>
                <label class="ifl">Year Level</label>
                <select v-model="editForm.year_level" class="ifse">
                  <option>1st Year</option>
                  <option>2nd Year</option>
                  <option>3rd Year</option>
                  <option>4th Year</option>
                  <option>5th Year</option>
                </select>
              </div>
              <div>
                <label class="ifl">College</label>
                <select v-model="editForm.college" class="ifse">
                  <option value="">Select college...</option>
                  <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">Program</label>
                <input v-model="editForm.program" class="ifi" placeholder="e.g. Bachelor of Science in IT" />
              </div>
              <div>
                <label class="ifl">Section</label>
                <input v-model="editForm.section" class="ifi" placeholder="e.g. A" />
              </div>
              <div>
                <label class="ifl">Email</label>
                <input v-model="editForm.email" class="ifi" placeholder="student@bsu.edu.ph" />
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
                <label class="ifl">Contact Number</label>
                <input v-model="editForm.contact_number" class="ifi" placeholder="09XXXXXXXXX" />
              </div>
            </div>

            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:4px">
              Guardian Information
              <div style="flex:1;height:1px;background:var(--cloud)"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Guardian Name</label>
                <input v-model="editForm.guardian_name" class="ifi" placeholder="Guardian full name" />
              </div>
              <div>
                <label class="ifl">Guardian Contact</label>
                <input v-model="editForm.guardian_contact" class="ifi" placeholder="09XXXXXXXXX" />
              </div>
              <div>
                <label class="ifl">Relationship</label>
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
import { ref, onMounted, inject } from 'vue';
import { useRoute } from 'vue-router';
import { studentAPI } from '../../api/index';
import { COLLEGES } from '../../constants/colleges';

const route   = useRoute();
const toast   = inject('toast');
const loading = ref(true);
const saving  = ref(false);
const showEditModal = ref(false);
const student = ref({});
const history = ref({});
const colleges = COLLEGES;

const editForm = ref({});

function openEdit() {
  editForm.value = { ...student.value };
  showEditModal.value = true;
}

async function saveStudent() {
  saving.value = true;
  try {
    const res = await studentAPI.update(student.value.id, editForm.value);
    student.value = res.data;
    showEditModal.value = false;
    toast?.success('Student profile updated successfully.');
  } catch (e) {
    toast?.error('Failed to update student profile.');
  } finally {
    saving.value = false;
  }
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
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