<!--
  FILE: resources/js/views/testing/Show.vue
  PAGE: iCARE / Testing Record Details (TMDU)

  New page (Testing module overhaul). Mirrors the SIF-style layout of
  resources/js/views/referrals/Show.vue: a 5-step status pipeline updated
  through action buttons, the original GCU-authored referral information
  (TestingRecord.referral - the same shared Referral row GCU filled out),
  Student Profile Details, and a "Reassign" action (adapted from the
  case-level Reassign Counselor pattern in students/Show.vue) that sets
  this record's Test Administrator instead of a case's counselor.

  Status bar: Pending | Scheduled for Testing | Test Administered |
  Awaiting Results | Results Released. DB statuses collapse into these 5
  stages (see stageOf()) - "Test Administered" has no resting state of its
  own going forward: saving "Psychological Tests Administered" moves the
  record straight to Awaiting Results, and the bar still lights up
  "Test Administered" as passed at that point.

  This is a shared case: GCU staff who referred the student (and admin) can
  open this page read-only. Only TMDU staff/admin (canManage) see the
  action buttons/forms.
-->
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
          <h1>{{ record.referral?.referral_code ? record.referral.referral_code : `Testing Record #${record.id}` }}</h1>
          <p>{{ record.student?.last_name }}, {{ record.student?.first_name }} {{ record.student?.middle_name }} · {{ record.student?.student_id }}</p>
        </div>
        <div v-if="canManage" style="margin-left:auto;display:flex;gap:8px">
          <button class="ibtn ibtn-o ibtn-sm" @click="openAssignModal">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Reassign
          </button>
        </div>
      </div>

      <!-- Status Pipeline -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">Testing Status</span></div>
        <div style="padding:16px 18px">
          <div style="display:flex;gap:0;overflow-x:auto">
            <div
              v-for="(step, i) in pipeline"
              :key="step.key"
              style="flex:1;min-width:110px;padding:10px 14px;text-align:center;font-size:11px;font-weight:600;border:1px solid var(--cloud)"
              :style="{
                background: isStepDone(step.key) ? 'var(--mist)' : isCurrentStep(step.key) ? 'var(--moss)' : '#fff',
                color: isStepDone(step.key) ? 'var(--moss)' : isCurrentStep(step.key) ? '#fff' : 'var(--stone)',
                borderColor: isStepDone(step.key) ? 'var(--mint)' : isCurrentStep(step.key) ? 'var(--moss)' : 'var(--cloud)',
                borderRadius: i === 0 ? 'var(--r-sm) 0 0 var(--r-sm)' : i === pipeline.length - 1 ? '0 var(--r-sm) var(--r-sm) 0' : '0',
              }"
            >
              {{ step.label }}
            </div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 340px;gap:16px">

        <!-- Left -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Referral Information - the GCU-authored referral this record
               was created from. Same shared Referral row shown on
               referrals/Show.vue; only rendered here if this record has one
               (older records created before the referral link existed won't). -->
          <div class="icard" v-if="record.referral">
            <div class="icard-header"><span class="icard-title">Referral Information</span></div>
            <div class="icard-body">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referred By</div>
                  <div style="font-size:13px;color:var(--ink)">{{ record.referral.referrer_name || '-' }} <span style="color:var(--fog)">({{ toTitleCase(record.referral.referrer_role) }})</span></div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Client Status</div>
                  <span class="ibadge" :style="record.referral.client_status === 'existing' ? 'background:var(--blue-lt);color:var(--blue)' : 'background:var(--mist);color:var(--moss)'">
                    {{ record.referral.client_status === 'existing' ? 'Existing Client' : 'New Client' }}
                  </span>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date Submitted</div>
                  <div style="font-size:13px;color:var(--ink)">{{ formatDate(record.referral.created_at) }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral Code</div>
                  <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ record.referral.referral_code }}</div>
                </div>
                <div v-if="record.referral.acknowledged_at">
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Acknowledged</div>
                  <div style="font-size:13px;color:var(--ink)">{{ formatDate(record.referral.acknowledged_at) }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Concern / Reason for Referral</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ record.referral.nature_of_concern || record.reason || '-' }}</div>
              </div>
            </div>
          </div>

          <!-- No linked referral (older record) - show the free-text reason instead -->
          <div class="icard" v-else-if="record.reason">
            <div class="icard-header"><span class="icard-title">Reason for Testing</span></div>
            <div class="icard-body">
              <div style="font-size:13.5px;color:var(--ink);line-height:1.6">{{ record.reason }}</div>
            </div>
          </div>

          <!-- Psychological Tests Administered - the action that moves the
               bar from Scheduled for Testing straight to Awaiting Results. -->
          <div class="icard" v-if="canManage && stage === 'scheduled_for_testing'">
            <div class="icard-header"><span class="icard-title">Psychological Tests Administered</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div>
                <label class="ifl">Testing Date</label>
                <input v-model="administerForm.testing_date" type="date" class="ifi" />
              </div>
              <div>
                <label class="ifl">Tests Administered</label>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                  <span
                    v-for="test in availableTests"
                    :key="test"
                    style="padding:4px 10px;border-radius:20px;font-size:11px;cursor:pointer;border:1.5px solid var(--silver);transition:all .1s"
                    :style="{
                      background: administerForm.tests_administered.includes(test) ? 'var(--moss)' : '#fff',
                      color: administerForm.tests_administered.includes(test) ? '#fff' : 'var(--slate)',
                      borderColor: administerForm.tests_administered.includes(test) ? 'var(--moss)' : 'var(--silver)',
                    }"
                    @click="toggleTest(test)"
                  >
                    {{ test }}
                  </span>
                </div>
              </div>
              <button class="ibtn ibtn-p ibtn-sm" style="align-self:flex-start" @click="saveTestsAdministered" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save & Move to Awaiting Results' }}
              </button>
            </div>
          </div>

          <!-- Attach Psychological Assessment Records (PAR) - the action
               that moves the bar from Awaiting Results to Results Released. -->
          <div class="icard" v-if="canManage && stage === 'awaiting_results'">
            <div class="icard-header"><span class="icard-title">Attach Psychological Assessment Records (PAR)</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div>
                <label class="ifl">PAR / Result File</label>
                <input type="file" class="ifi" accept=".pdf,.doc,.docx,.jpg,.png" @change="handleFileUpload" />
                <div v-if="selectedFile" style="font-size:12px;color:var(--moss);margin-top:4px">✓ {{ selectedFile.name }}</div>
              </div>
              <div>
                <label class="ifl">Assessment Summary</label>
                <textarea v-model="parForm.assessment_summary" class="ifta" placeholder="Summarize the assessment results..."></textarea>
              </div>
              <div>
                <label class="ifl">Recommended Actions</label>
                <textarea v-model="parForm.recommendations" class="ifta" placeholder="Recommended actions based on the assessment..."></textarea>
              </div>
              <button class="ibtn ibtn-blue ibtn-sm" style="align-self:flex-start" @click="attachPar" :disabled="saving">
                {{ saving ? 'Sending...' : 'Attach PAR & Release Results to GCU' }}
              </button>
            </div>
          </div>

          <!-- Results Released - read-only summary -->
          <div class="icard" v-if="stage === 'results_released'">
            <div class="icard-header"><span class="icard-title">Psychological Assessment Report</span></div>
            <div class="icard-body">
              <div style="margin-bottom:12px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Assessment Summary</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ record.assessment_summary || '-' }}</div>
              </div>
              <div style="margin-bottom:12px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Recommended Actions</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ record.recommendations || '-' }}</div>
              </div>
              <div style="font-size:11px;color:var(--fog)">
                Sent to GCU {{ formatDate(record.report_sent_at) }}
              </div>
            </div>
          </div>

        </div>

        <!-- Right -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Legacy fallback acknowledge for records with no referral_id -->
          <div class="icard" v-if="canManage && !record.referral_id && record.status === 'pending' && !record.acknowledged">
            <div class="icard-body">
              <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber);margin-bottom:12px">
                ⚠ This referral has not been acknowledged yet.
              </div>
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="acknowledgeReferral" :disabled="saving">
                {{ saving ? 'Acknowledging...' : 'Acknowledge & Notify Student' }}
              </button>
            </div>
          </div>

          <!-- Test Administrator -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Test Administrator</span></div>
            <div class="icard-body">
              <div style="font-size:13px;color:var(--ink)">{{ record.tester?.name || 'Unassigned' }}</div>
            </div>
          </div>

          <!-- Student Profile Details -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Student Profile Details</span>
              <router-link :to="{ name: 'student-show', params: { id: record.student?.id } }" class="ibtn ibtn-g ibtn-sm">Profile</router-link>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="qav" style="width:40px;height:40px;font-size:15px">
                  {{ initials(record.student?.first_name, record.student?.last_name) }}
                </div>
                <div>
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">
                    {{ record.student?.last_name }}, {{ record.student?.first_name }} {{ record.student?.middle_name }}
                  </div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ record.student?.student_id }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College / Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ record.student?.college || '-' }}<span v-if="record.student?.program"> · {{ record.student.program }}</span></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year & Section</div>
                  <div style="font-size:13px;color:var(--ink)">{{ record.student?.year_level || '-' }} <span v-if="record.student?.section">- {{ record.student.section }}</span></div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
                  <div style="font-size:13px;color:var(--ink)">{{ record.student?.sex || '-' }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                <div style="font-size:13px;color:var(--ink)">{{ record.student?.contact_number || '-' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email</div>
                <div style="font-size:13px;color:var(--ink)">{{ record.student?.email || '-' }}</div>
              </div>
            </div>
          </div>

          <!-- OR Photo (uploaded by the student when requesting their testing schedule) -->
          <div class="icard" v-if="record.or_photo_path">
            <div class="icard-header"><span class="icard-title">OR Submitted by Student</span></div>
            <div class="icard-body">
              <div style="font-size:12px;color:var(--stone);margin-bottom:8px">{{ record.or_photo_original_name || 'or-photo' }} · {{ formatDate(record.or_uploaded_at) }}</div>
              <button class="ibtn ibtn-o ibtn-sm" @click="viewOrPhoto" :disabled="loadingOrPhoto">
                {{ loadingOrPhoto ? 'Loading...' : 'View OR Photo' }}
              </button>
              <div v-if="record.or_stamped_at" style="font-size:12px;color:var(--moss);margin-top:8px">
                ✓ OR confirmed stamped by {{ record.or_stamped_by?.name || 'TMDU staff' }} on {{ formatDate(record.or_stamped_at) }}
              </div>
            </div>
          </div>

          <!-- Schedule Test Taking - kept as it was on Testing Records -->
          <div class="icard" v-if="canManage && record.status === 'or_submitted'">
            <div class="icard-header"><span class="icard-title">Schedule Test Taking</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <input v-model="testingForm.appointment_date" type="date" class="ifi" />
              <div style="display:flex;gap:8px">
                <input v-model="testingForm.start_time" type="time" class="ifi" style="flex:1" />
                <input v-model="testingForm.end_time" type="time" class="ifi" style="flex:1" />
              </div>
              <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:12px;color:var(--slate)">
                <input type="checkbox" v-model="testingForm.or_stamped_confirmed" style="width:15px;height:15px;accent-color:var(--moss);margin-top:1px" />
                I confirm the student's Official Receipt has been received and stamped, face-to-face.
              </label>
              <button class="ibtn ibtn-p ibtn-sm" @click="confirmScheduleTesting" :disabled="saving || !testingForm.or_stamped_confirmed">
                {{ saving ? 'Scheduling...' : 'Confirm Testing Schedule' }}
              </button>
            </div>
          </div>

          <!-- Schedule PAR Release - same style as Schedule Test Taking, only
               reachable while the record is in the Awaiting Results stage. -->
          <div class="icard" v-if="canManage && stage === 'awaiting_results'">
            <div class="icard-header"><span class="icard-title">Schedule PAR Release</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <input v-model="parScheduleForm.appointment_date" type="date" class="ifi" />
              <div style="display:flex;gap:8px">
                <input v-model="parScheduleForm.start_time" type="time" class="ifi" style="flex:1" />
                <input v-model="parScheduleForm.end_time" type="time" class="ifi" style="flex:1" />
              </div>
              <button class="ibtn ibtn-p ibtn-sm" @click="confirmSchedulePar" :disabled="saving">
                {{ saving ? 'Scheduling...' : 'Schedule PAR Release' }}
              </button>
              <div v-if="record.status === 'par_scheduled'" style="font-size:12px;color:var(--moss)">✓ PAR release appointment scheduled.</div>
            </div>
          </div>

          <!-- Testing Action - mirrors the Case Action panel on the Student
               Information File. Schedule Test Taking / Schedule PAR Release
               above already create their own appointments automatically;
               this gives TMDU a way to book anything else tied to this case
               (e.g. an intake meeting) through the general Appointments module. -->
          <div class="icard" v-if="canManage && record.case_id">
            <div class="icard-header"><span class="icard-title">Testing Action</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <router-link
                :to="{ name: 'appointments', query: { return_to: route.fullPath } }"
                class="ibtn ibtn-blue"
                style="width:100%;justify-content:center"
              >
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule Appointment
              </router-link>
            </div>
          </div>

          <!-- Appointments - every appointment tied to this testing record's
               case, including the ones Schedule Test Taking / Schedule PAR
               Release create automatically. -->
          <div class="icard" v-if="record.case_id">
            <div class="icard-header"><span class="icard-title">Appointments</span></div>
            <div v-if="!record.appointments?.length" class="empty-state">
              <h3>No appointments yet</h3>
              <p>No appointments have been scheduled for this testing case.</p>
            </div>
            <div v-else>
              <div v-for="a in record.appointments" :key="a.id" style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                  <div>
                    <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ toTitleCase(a.appointment_type) }}</div>
                    <div style="font-size:11px;color:var(--stone);margin-top:2px">{{ formatDate(a.appointment_date) }} · {{ a.start_time }}</div>
                    <span class="ibadge" :class="'unit-' + a.unit?.toLowerCase()" style="margin-top:4px;display:inline-block">{{ a.unit }}</span>
                  </div>
                  <span class="ibadge" :class="'ibadge-' + a.status">{{ toTitleCase(a.status) }}</span>
                </div>
                <div style="font-size:11px;color:var(--fog)">With {{ a.staff?.name || '-' }}</div>
                <button
                  v-if="canManage && a.status === 'confirmed'"
                  class="ibtn ibtn-sm"
                  style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber);align-self:flex-start"
                  @click="markAppointmentNoShow(a)"
                >
                  No-Show
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Reassign as Test Administrator Modal -->
      <div v-if="showAssignModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showAssignModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Reassign as Test Administrator</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Assign To <span style="color:var(--red)">*</span></label>
              <select v-model="assignForm.to_user_id" class="ifse">
                <option value="" disabled>Select TMDU staff member...</option>
                <option v-for="u in assignStaffList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="assignTester">Assign</button>
              <button class="ibtn ibtn-o" @click="showAssignModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { useRoute } from 'vue-router';
import { testingAPI, userAPI, appointmentAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();

const loading         = ref(true);
const saving          = ref(false);
const loadingOrPhoto  = ref(false);
const record          = ref({});
const selectedFile    = ref(null);

const testingForm     = ref({ appointment_date: '', start_time: '', end_time: '', or_stamped_confirmed: false });
const parScheduleForm = ref({ appointment_date: '', start_time: '', end_time: '' });
const administerForm  = ref({ tests_administered: [], testing_date: '' });
const parForm         = ref({ assessment_summary: '', recommendations: '' });

// Psychological tests only - same list as the former Testing Records drawer.
const availableTests = [
  'MMPI-2',
  'SCL-90',
  'Beck Depression Inventory (BDI)',
  'Hamilton Anxiety Scale (HAM-A)',
  "Raven's Progressive Matrices",
  'WAIS-IV',
  'Draw-A-Person Test',
  'Sentence Completion Test',
];

const canManage = computed(() => ['admin', 'tmdu_staff'].includes(auth.user?.role));

// Collapses the DB-level testing_records.status values into the 5 bar
// stages. "test_administered" (legacy) and "par_scheduled" both fall under
// "Awaiting Results" - see the migration/controller notes for why there's
// no separate resting state for "Test Administered" going forward.
function stageOf(status) {
  if (['pending', 'fee_form_pending', 'or_submitted'].includes(status)) return 'pending';
  if (['scheduled', 'in_progress'].includes(status)) return 'scheduled_for_testing';
  if (['test_administered', 'awaiting_results', 'par_scheduled'].includes(status)) return 'awaiting_results';
  if (status === 'test_results_issued') return 'results_released';
  return 'pending';
}

const stage = computed(() => stageOf(record.value.status));

const pipeline = [
  { key: 'pending',               label: 'Pending' },
  { key: 'scheduled_for_testing', label: 'Scheduled for Testing' },
  { key: 'test_administered',     label: 'Test Administered' },
  { key: 'awaiting_results',      label: 'Awaiting Results' },
  { key: 'results_released',      label: 'Results Released' },
];
const stageOrder = pipeline.map(s => s.key);

function isStepDone(key) {
  return stageOrder.indexOf(key) < stageOrder.indexOf(stage.value);
}
function isCurrentStep(key) {
  // "test_administered" never becomes the current stage on its own (saving
  // Psychological Tests Administered jumps straight to Awaiting Results) -
  // it only ever shows as done or upcoming, never highlighted as current.
  return key === stage.value;
}

// Reassign as Test Administrator - adapted from the case-level Reassign
// Counselor pattern in students/Show.vue, targeting assigned_tester_user_id.
const TMDU_ROLES = ['admin', 'tmdu_staff'];
const showAssignModal = ref(false);
const assignForm      = ref({ to_user_id: '' });
const assignStaffList = ref([]);

async function loadAssignStaff() {
  assignStaffList.value = [];
  try {
    const res = await userAPI.index({ is_active: 1 });
    assignStaffList.value = (res.data.data || res.data).filter(u => TMDU_ROLES.includes(u.role));
  } catch (e) {
    // Non-fatal - dropdown just stays empty.
  }
}

function openAssignModal() {
  assignForm.value = { to_user_id: record.value.assigned_tester_user_id || '' };
  showAssignModal.value = true;
  loadAssignStaff();
}

async function assignTester() {
  if (!assignForm.value.to_user_id) {
    toast?.error('Please select a staff member.');
    return;
  }
  try {
    const res = await testingAPI.update(record.value.id, { assigned_tester_user_id: assignForm.value.to_user_id });
    record.value.tester = res.data.tester || assignStaffList.value.find(u => u.id === Number(assignForm.value.to_user_id));
    record.value.assigned_tester_user_id = assignForm.value.to_user_id;
    showAssignModal.value = false;
    toast?.success('Test administrator reassigned.');
  } catch (e) {
    toast?.error('Failed to reassign test administrator.');
  }
}

function toggleTest(test) {
  const idx = administerForm.value.tests_administered.indexOf(test);
  if (idx === -1) administerForm.value.tests_administered.push(test);
  else administerForm.value.tests_administered.splice(idx, 1);
}

function handleFileUpload(event) {
  selectedFile.value = event.target.files[0] || null;
}

async function viewOrPhoto() {
  loadingOrPhoto.value = true;
  try {
    const res = await testingAPI.orPhoto(record.value.id);
    const url = URL.createObjectURL(res.data);
    window.open(url, '_blank');
  } catch (e) {
    toast?.error('Failed to load OR photo.');
  } finally {
    loadingOrPhoto.value = false;
  }
}

async function confirmScheduleTesting() {
  if (!testingForm.value.appointment_date || !testingForm.value.start_time || !testingForm.value.end_time) {
    toast?.error('Please fill in the date and time.');
    return;
  }
  if (!testingForm.value.or_stamped_confirmed) {
    toast?.error("Please confirm the student's OR has been received and stamped.");
    return;
  }
  saving.value = true;
  try {
    await testingAPI.scheduleTesting(record.value.id, testingForm.value);
    toast?.success('Testing appointment scheduled. Student has been notified.');
    await loadRecord();
  } catch (e) {
    toast?.error('Failed to schedule testing appointment.');
  } finally {
    saving.value = false;
  }
}

async function saveTestsAdministered() {
  if (!administerForm.value.testing_date) {
    toast?.error('Please set the testing date.');
    return;
  }
  if (!administerForm.value.tests_administered.length) {
    toast?.error('Please select at least one test administered.');
    return;
  }
  saving.value = true;
  try {
    await testingAPI.administerTests(record.value.id, administerForm.value);
    toast?.success('Tests administered recorded. Now awaiting results.');
    await loadRecord();
  } catch (e) {
    toast?.error('Failed to save tests administered.');
  } finally {
    saving.value = false;
  }
}

async function confirmSchedulePar() {
  if (!parScheduleForm.value.appointment_date || !parScheduleForm.value.start_time || !parScheduleForm.value.end_time) {
    toast?.error('Please fill in the date and time.');
    return;
  }
  saving.value = true;
  try {
    await testingAPI.schedulePar(record.value.id, parScheduleForm.value);
    toast?.success('PAR release appointment scheduled. Student has been notified.');
    await loadRecord();
  } catch (e) {
    toast?.error('Failed to schedule PAR release.');
  } finally {
    saving.value = false;
  }
}

async function attachPar() {
  if (!parForm.value.assessment_summary || !parForm.value.recommendations) {
    toast?.error('Please fill in the Assessment Summary and Recommended Actions.');
    return;
  }
  saving.value = true;
  try {
    const formData = new FormData();
    formData.append('assessment_summary', parForm.value.assessment_summary);
    formData.append('recommendations', parForm.value.recommendations);
    if (selectedFile.value) formData.append('report_file', selectedFile.value);

    await testingAPI.sendToGcu(record.value.id, formData);
    toast?.success('PAR attached. Results released to GCU.');
    await loadRecord();
  } catch (e) {
    toast?.error('Failed to attach PAR.');
  } finally {
    saving.value = false;
  }
}

async function acknowledgeReferral() {
  saving.value = true;
  try {
    await testingAPI.acknowledge(record.value.id);
    await loadRecord();
    toast?.success('Referral acknowledged. Student notified to set their appointment.');
  } catch (e) {
    toast?.error('Failed to acknowledge referral.');
  } finally {
    saving.value = false;
  }
}

async function markAppointmentNoShow(a) {
  try {
    await appointmentAPI.escalateNoShow(a.id);
    a.status = 'no_show';
    toast?.success("Marked as no-show and escalated to Dean's Secretary.");
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark as no-show.');
  }
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

async function loadRecord() {
  const res = await testingAPI.show(route.params.id);
  record.value = res.data;
  administerForm.value = {
    tests_administered: [...(res.data.tests_administered || [])],
    testing_date: res.data.testing_date ? res.data.testing_date.slice(0, 10) : '',
  };
  parForm.value = {
    assessment_summary: res.data.assessment_summary || '',
    recommendations: res.data.recommendations || '',
  };
  selectedFile.value = null;
}

onMounted(async () => {
  try {
    await loadRecord();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>