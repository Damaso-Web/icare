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
        <div v-if="isGCU" style="margin-left:auto;display:flex;gap:8px">
          <button class="ibtn ibtn-o ibtn-sm" @click="openStatusModal">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Status
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

          <!-- Referral Info -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Referral Info</span>
            </div>

            <!-- Document Code Header -->
            <div style="padding:10px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
              <div style="font-size:11px;color:var(--stone)">
                <div><strong>Document Code:</strong> QF-OSS-01</div>
                <div><strong>Revision No.:</strong> 01</div>
              </div>
              <div style="font-size:11px;color:var(--stone);text-align:right">
                <div><strong>Effectivity:</strong> 07/04/23</div>
                <div><strong>Ctrl No.:</strong> 25-2</div>
              </div>
            </div>

            <div class="icard-body">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referred By</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.referrer_name || '-' }} <span style="color:var(--fog)">({{ toTitleCase(referral.referrer_role) }})</span></div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Client Status</div>
                  <span class="ibadge" :style="referral.client_status === 'existing' ? 'background:var(--blue-lt);color:var(--blue)' : 'background:var(--mist);color:var(--moss)'">
                    {{ referral.client_status === 'existing' ? 'Existing Client' : 'New Client' }}
                  </span>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date Submitted</div>
                  <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.created_at) }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service Requested</div>
                  <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(referral.referral_type) || '-' }}</div>
                </div>
                <div v-if="referral.acknowledged_at">
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Acknowledged</div>
                  <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.acknowledged_at) }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral Code</div>
                  <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ referral.referral_code }}</div>
                </div>
              </div>
              <div style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Concern / Reason for Referral</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ referral.nature_of_concern }}</div>
              </div>
              <div v-if="referral.intake_notes" style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Intake Notes</div>
                <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ referral.intake_notes }}</div>
              </div>
              <div v-if="referral.violation_type" style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Violation Type</div>
                <div style="font-size:13px;color:var(--ink)">{{ referral.violation_type }}</div>
              </div>
              <div v-if="referral.incident_date" style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Date of Incident</div>
                <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.incident_date) }}</div>
              </div>
              <div v-if="referral.sanction" style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Sanction / Outcome</div>
                <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(referral.sanction) }}</div>
                <div v-if="referral.sanction_notes" style="font-size:12px;color:var(--slate);margin-top:4px;line-height:1.6">{{ referral.sanction_notes }}</div>
              </div>
            </div>
          </div>

          <!-- Feedback Slip - copy sent to the referrer for transparency -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Feedback Slip</span></div>
            <div class="icard-body">
              <template v-if="isGCU">
                <textarea v-model="feedbackForm.feedback_notes" class="ifta" placeholder="Progress / outcome summary to send to the referrer..."></textarea>
                <button class="ibtn ibtn-p ibtn-sm" style="margin-top:10px" @click="sendFeedback">
                  <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  Send to Referrer
                </button>
                <div v-if="referral.feedback_sent_at" style="font-size:11px;color:var(--fog);margin-top:8px">
                  Last sent {{ formatDate(referral.feedback_sent_at) }} by {{ referral.feedback_sent_by?.name }}
                </div>
              </template>
              <template v-else>
                <div v-if="!referral.feedback_notes" style="font-size:13px;color:var(--stone)">No feedback has been shared yet.</div>
                <div v-else>
                  <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ referral.feedback_notes }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:8px">Sent {{ formatDate(referral.feedback_sent_at) }}</div>
                </div>
              </template>
            </div>
          </div>

        </div>

        <!-- Right -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Acknowledge - visible to Admin and GCU Staff -->
          <div class="icard" v-if="referral.status === 'submitted' && isGCU">
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

          <!-- Read-only status for non-GCU roles -->
          <div class="icard" v-else-if="referral.status === 'submitted'">
            <div class="icard-body">
              <div style="font-size:13px;color:var(--stone)">Awaiting acknowledgement from GCU.</div>
            </div>
          </div>

        </div>
      </div>

      <!-- Update Referral Status Modal -->
      <div v-if="showStatusModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showStatusModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Update Referral Status</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showStatusModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:8px">
            <select v-model="newStatus" class="ifse">
              <option
                v-for="step in pipeline"
                :key="step.key"
                :value="step.key"
                :disabled="!isStatusSelectable(step.key)"
              >
                {{ step.label }}{{ statusOrder.indexOf(step.key) < statusOrder.indexOf(referral.status) ? ' (already completed)' : !isStatusSelectable(step.key) ? ' (complete previous step first)' : '' }}
              </option>
            </select>
            <div style="font-size:11px;color:var(--stone)">Steps must be completed in order — you can only move to the next step in the pipeline.</div>
            <button
              class="ibtn ibtn-p"
              style="width:100%;justify-content:center"
              :style="{ opacity: !isStatusSelectable(newStatus) ? .5 : 1, cursor: !isStatusSelectable(newStatus) ? 'not-allowed' : 'pointer' }"
              :disabled="!isStatusSelectable(newStatus)"
              @click="updateStatus"
            >Save Status</button>
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
import { toTitleCase } from '../../utils/validators';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();
const loading = ref(true);
const acknowledging = ref(false);
const referral = ref({});

const showStatusModal = ref(false);
const newStatus        = ref('');

const feedbackForm = ref({ feedback_notes: '' });

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

function isStatusSelectable(key) {
  const current = statusOrder.indexOf(referral.value.status);
  const target  = statusOrder.indexOf(key);
  return target === current || target === current + 1;
}

function openStatusModal() {
  const current = statusOrder.indexOf(referral.value.status);
  const next    = pipeline[current + 1];
  newStatus.value = next ? next.key : referral.value.status;
  showStatusModal.value = true;
}

function isCurrentStep(key) {
  return referral.value.status === key;
}

async function acknowledge() {
  acknowledging.value = true;
  try {
    const res = await referralAPI.acknowledge(referral.value.id);
    referral.value = { ...referral.value, ...res.data.referral };
    toast?.success('Acknowledged referral.');
  } catch (e) {
    toast?.error('Failed to acknowledge referral.');
  } finally {
    acknowledging.value = false;
  }
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

async function sendFeedback() {
  if (!feedbackForm.value.feedback_notes) {
    toast?.error('Please write a feedback summary before sending.');
    return;
  }
  try {
    const res = await referralAPI.sendFeedback(referral.value.id, feedbackForm.value);
    referral.value = { ...referral.value, ...res.data };
    toast?.success('Feedback sent to referrer.');
  } catch (e) {
    toast?.error('Failed to send feedback.');
  }
}

async function updateStatus() {
  try {
    const res = await referralAPI.updateStatus(referral.value.id, { status: newStatus.value });
    referral.value.status = res.data.status;
    showStatusModal.value = false;
    toast?.success('Referral status updated.');
  } catch (e) {
    toast?.error('Failed to update status.');
  }
}

onMounted(async () => {
  try {
    const res = await referralAPI.show(route.params.id);
    referral.value = res.data;
    feedbackForm.value.feedback_notes = res.data.feedback_notes || '';
    newStatus.value = res.data.status || '';
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>