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
              <div v-if="referral.intake_notes">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Intake Notes</div>
                <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ referral.intake_notes }}</div>
              </div>
              <div v-if="referral.violation_type" style="margin-top:14px">
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

          <!-- Actions — shown at top, only for GCU/Admin -->
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
          <div class="icard" v-if="referral.status !== 'submitted'">
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
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { useRoute } from 'vue-router';
import { referralAPI } from '../../api/index';

const route        = useRoute();
const toast        = inject('toast');
const loading      = ref(true);
const acknowledging = ref(false);
const referral     = ref({});

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
    referral.value = res.data.referral;
    toast?.success('Referral acknowledged and case file created.');
  } catch (e) {
    toast?.error('Failed to acknowledge referral.');
  } finally {
    acknowledging.value = false;
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
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>