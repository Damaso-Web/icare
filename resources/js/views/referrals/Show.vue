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
          <h1>{{ referral.referral_code || 'Referral Details' }}</h1>
          <p>{{ referral.student?.last_name }}, {{ referral.student?.first_name }} {{ referral.student?.middle_name }} · {{ referral.student?.student_id }}</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
          <button v-if="isGCU" class="ibtn ibtn-o ibtn-sm" @click="openEditForm">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </button>
          <button v-if="isGCU" class="ibtn ibtn-sm" style="background:var(--cloud);color:var(--stone)" @click="archiveReferral">
            <svg viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
            Archive
          </button>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 340px;gap:16px">

        <!-- Left -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Status Pipeline -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Referral Status</span></div>
            <div style="padding:16px 18px">
              <div style="display:flex;gap:0;overflow-x:auto">
                <div
                  v-for="(step, i) in pipeline"
                  :key="step.key"
                  style="flex:1;min-width:80px;padding:10px 14px;text-align:center;font-size:11px;font-weight:600;border:1px solid var(--cloud)"
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

          <!-- Concern Details -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Concern Details</span></div>
            <div class="icard-body">
              <div style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Nature of Concern</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6">{{ referral.nature_of_concern }}</div>
              </div>
              <div v-if="referral.intake_notes" style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Intake Notes</div>
                <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ referral.intake_notes }}</div>
              </div>
              <div v-if="referral.violation_type">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Violation Type</div>
                <div style="font-size:13px;color:var(--ink)">{{ referral.violation_type }}</div>
              </div>
            </div>
          </div>

          <!-- Case File -->
          <div class="icard" v-if="referral.case">
            <div class="icard-header">
              <span class="icard-title">Linked Case File</span>
              <router-link :to="{ name: 'case-show', params: { id: referral.case.id } }" class="ibtn ibtn-o ibtn-sm">Open Case</router-link>
            </div>
            <div class="icard-body">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Case Number</div>
                  <div style="font-family:var(--mono);font-size:13px">{{ referral.case.case_number }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
                  <span class="ibadge" :class="'ibadge-' + referral.case.status">{{ referral.case.status }}</span>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sessions</div>
                  <div style="font-size:13px">{{ referral.case.total_sessions }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Opened</div>
                  <div style="font-size:13px">{{ formatDate(referral.case.opened_date) }}</div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Right -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Actions — shown at top -->
          <div class="icard" v-if="referral.status === 'submitted'">
            <div class="icard-header"><span class="icard-title">Action Required</span></div>
            <div class="icard-body">
              <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber);margin-bottom:12px">
                ⚠ This referral has not been acknowledged yet.
              </div>
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="acknowledge" :disabled="acknowledging">
                <svg v-if="!acknowledging" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-if="acknowledging" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
                {{ acknowledging ? 'Acknowledging...' : 'Acknowledge Referral' }}
              </button>
            </div>
          </div>

          <!-- Referral Info -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Referral Info</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:12px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
                <span class="ibadge" :class="'ibadge-' + referral.status">{{ referral.status?.replace(/_/g,' ') }}</span>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Client Status</div>
                <span class="ibadge" :style="referral.client_status === 'existing' ? 'background:var(--blue-lt);color:var(--blue)' : 'background:var(--mist);color:var(--moss)'">
                  {{ referral.client_status === 'existing' ? 'Existing Client' : 'New Client' }}
                </span>
                <div v-if="referral.prior_referral_count > 0" style="font-size:11px;color:var(--stone);margin-top:4px">
                  {{ referral.prior_referral_count }} prior referral{{ referral.prior_referral_count > 1 ? 's' : '' }}
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Type</div>
                <div style="font-size:13px;color:var(--ink)">{{ referral.referral_type?.replace(/_/g,' ') }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referred By</div>
                <div style="font-size:13px;color:var(--ink)">{{ referral.referrer_name }}</div>
                <div style="font-size:11px;color:var(--stone)">{{ referral.referrer_role?.replace(/_/g,' ') }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date Submitted</div>
                <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.created_at) }}</div>
              </div>
              <div v-if="referral.acknowledged_at">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Acknowledged</div>
                <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.acknowledged_at) }}</div>
              </div>
            </div>
          </div>

          <!-- Student Info -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Student</span>
              <router-link :to="{ name: 'student-show', params: { id: referral.student?.id } }" class="ibtn ibtn-g ibtn-sm">Profile</router-link>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="qav" style="width:40px;height:40px;font-size:15px">
                  {{ initials(referral.student?.first_name, referral.student?.last_name) }}
                </div>
                <div>
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">
                    {{ referral.student?.last_name }}, {{ referral.student?.first_name }} {{ referral.student?.middle_name }}
                  </div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ referral.student?.student_id }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year & College</div>
                <div style="font-size:13px;color:var(--ink)">{{ referral.student?.year_level }} · {{ referral.student?.college }}</div>
              </div>
              <div v-if="referral.student?.program">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ referral.student?.program }}</div>
              </div>
            </div>
          </div>

          <!-- Schedule Appointment (only after acknowledged) -->
          <div class="icard" v-if="referral.status !== 'submitted' && isGCU">
            <div class="icard-header"><span class="icard-title">Next Steps</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <router-link
                v-if="referral.case"
                :to="{ name: 'case-show', params: { id: referral.case.id } }"
                class="ibtn ibtn-o"
                style="width:100%;justify-content:center"
              >
                <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                View Case File
              </router-link>
              <router-link
                :to="{ name: 'appointments' }"
                class="ibtn ibtn-blue"
                style="width:100%;justify-content:center"
              >
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule Appointment
              </router-link>
            </div>
          </div>

        </div>
      </div>

      <!-- Edit Referral Modal -->
      <div v-if="showEditModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Edit Referral</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showEditModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Service Requested</label>
              <select v-model="editForm.referral_type" class="ifse">
                <option value="counseling">Class Attendance / Absent / Tardy</option>
                <option value="academic_coaching">Academic Deficiency</option>
                <option value="psychological_testing">Psychological Testing</option>
                <option value="consultation">Scholarship / Grant Assistance</option>
                <option value="admission_slip">Student Organizations &amp; Activities Concerns</option>
                <option value="disciplinary">Student Housing (Dormitories)</option>
                <option value="others">For Student Employment (SA/SPES)</option>
                <option value="other">Others</option>
              </select>
            </div>
            <div>
              <label class="ifl">Concern / Reason for Referral</label>
              <textarea v-model="editForm.nature_of_concern" class="ifta"></textarea>
            </div>
            <div>
              <label class="ifl">Intake Notes</label>
              <textarea v-model="editForm.intake_notes" class="ifta" style="min-height:60px" placeholder="Additional notes..."></textarea>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="saveEdit">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Save Changes
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
import { ref, onMounted, inject, computed } from 'vue';
import { useRoute } from 'vue-router';
import { referralAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();
const loading = ref(true);
const acknowledging = ref(false);
const showEditModal = ref(false);
const referral = ref({});
const editForm  = ref({});

const isGCU = computed(() => ['admin', 'gcu_staff'].includes(auth.user?.role));

const pipeline = [
  { key: 'submitted',    label: 'Submitted' },
  { key: 'acknowledged', label: 'Acknowledged' },
  { key: 'in_review',   label: 'In Review' },
  { key: 'in_progress', label: 'In Progress' },
  { key: 'completed',   label: 'Completed' },
];

const statusOrder = ['submitted', 'acknowledged', 'in_review', 'in_progress', 'completed', 'closed'];

function isStepDone(key) {
  const current = statusOrder.indexOf(referral.value.status);
  const step    = statusOrder.indexOf(key);
  return step < current;
}

function isCurrentStep(key) {
  return referral.value.status === key;
}

async function acknowledge() {
  acknowledging.value = true;
  try {
    const res = await referralAPI.acknowledge(referral.value.id);
    referral.value = { ...referral.value, ...res.data.referral };
    toast?.success('Referral acknowledged and case file created.');
  } catch (e) {
    toast?.error('Failed to acknowledge referral.');
  } finally {
    acknowledging.value = false;
  }
}

function openEditForm() {
  editForm.value = {
    referral_type:     referral.value.referral_type,
    nature_of_concern: referral.value.nature_of_concern,
    intake_notes:      referral.value.intake_notes,
  };
  showEditModal.value = true;
}

async function saveEdit() {
  try {
    await referralAPI.update(referral.value.id, editForm.value);
    referral.value = { ...referral.value, ...editForm.value };
    showEditModal.value = false;
    toast?.success('Referral updated successfully.');
  } catch (e) {
    toast?.error('Failed to update referral.');
  }
}

async function archiveReferral() {
  try {
    await referralAPI.archive(referral.value.id);
    toast?.success('Referral archived.');
    setTimeout(() => window.history.back(), 800);
  } catch (e) {
    toast?.error('Failed to archive referral.');
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
    const res = await referralAPI.show(route.params.id);
    referral.value = res.data;
    editForm.value = {
      referral_type:     res.data.referral_type,
      nature_of_concern: res.data.nature_of_concern,
      intake_notes:      res.data.intake_notes,
    };
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>