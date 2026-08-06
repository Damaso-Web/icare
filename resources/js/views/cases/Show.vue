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
          <h1>{{ caseFile.case_number }}</h1>
          <p>{{ caseFile.student?.first_name }} {{ caseFile.student?.last_name }} · {{ caseFile.student?.student_id }}</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
          <button class="ibtn ibtn-o ibtn-sm" @click="showStatusModal = true">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Status
          </button>
          <button class="ibtn ibtn-amber ibtn-sm" @click="showSessionModal = true">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Log Session
          </button>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">

        <!-- Left -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Case Header -->
          <div class="icard">
            <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:20px 22px;border-radius:var(--r-lg) var(--r-lg) 0 0">
              <div style="font-family:var(--serif);font-style:italic;font-size:20px;color:#fff;margin-bottom:4px">{{ caseFile.case_number }}</div>
              <div style="font-size:12px;color:rgba(255,255,255,.55);display:flex;gap:12px;flex-wrap:wrap">
                <span>{{ caseFile.case_type?.replace(/_/g,' ') }}</span>
                <span>Opened {{ formatDate(caseFile.opened_date) }}</span>
                <span>{{ caseFile.total_sessions }} session{{ caseFile.total_sessions !== 1 ? 's' : '' }}</span>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;padding:16px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
                <span class="ibadge" :class="'ibadge-' + caseFile.status">{{ caseFile.status?.replace(/_/g,' ') }}</span>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Current Unit</div>
                <span class="ibadge" :class="'unit-' + caseFile.current_unit?.toLowerCase()">{{ caseFile.current_unit }}</span>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Counselor</div>
                <div style="font-size:13px;color:var(--ink)">{{ caseFile.counselor?.name || '—' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Last Session</div>
                <div style="font-size:13px;color:var(--ink)">{{ formatDate(caseFile.last_session_at) }}</div>
              </div>
            </div>
            <!-- Unreachable Banner -->
            <div v-if="caseFile.student_unreachable" style="margin:0 16px 16px;background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 14px;font-size:13px;color:var(--amber);display:flex;align-items:center;gap:8px">
              <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Student flagged as unreachable. Dean's Secretary has been notified.
            </div>
          </div>

          <!-- Presenting Concern -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Presenting Concern</span></div>
            <div class="icard-body">
              <div style="font-size:13.5px;color:var(--ink);line-height:1.6">{{ caseFile.presenting_concern || '—' }}</div>
            </div>
          </div>

          <!-- Session Notes -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Session Notes</span>
            </div>
            <div v-if="sessionNotes.length === 0" class="empty-state">
              <h3>No sessions yet</h3>
              <p>Log the first session to start tracking progress.</p>
            </div>
            <div v-else>
              <div v-for="note in sessionNotes" :key="note.id" style="padding:16px 18px;border-bottom:1px solid var(--cloud)">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                  <div style="font-size:13px;font-weight:600;color:var(--ink)">
                    Session #{{ note.session_number }} — {{ note.session_type?.replace(/_/g,' ') }}
                  </div>
                  <div style="font-size:11px;color:var(--fog)">{{ formatDate(note.session_date) }}</div>
                </div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Observations</div>
                <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver);margin-bottom:8px">{{ note.observations }}</div>
                <div v-if="note.next_steps">
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Next Steps</div>
                  <div style="font-size:13px;color:var(--slate)">{{ note.next_steps }}</div>
                </div>
                <div style="font-size:11px;color:var(--fog);margin-top:6px">
                Recorded by {{ note.recorded_by?.name }}
                <span v-if="note.session_start_time"> · {{ note.session_start_time }} – {{ note.session_end_time }}</span>
              </div>
              </div>
            </div>
          </div>

          <!-- Appointments -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Appointments</span>
              <router-link :to="{ name: 'appointments' }" class="ibtn ibtn-o ibtn-sm">Schedule</router-link>
            </div>
            <div v-if="appointments.length === 0" class="empty-state">
              <h3>No appointments yet</h3>
              <p>Schedule an appointment for this case.</p>
            </div>
            <div class="ts" v-else>
              <table class="itable">
                <thead>
                  <tr>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Staff</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="a in appointments" :key="a.id">
                    <td>{{ a.appointment_type?.replace(/_/g,' ') }}</td>
                    <td style="font-size:12px">{{ formatDate(a.appointment_date) }}</td>
                    <td style="font-size:12px">{{ a.start_time }}</td>
                    <td style="font-size:12px">{{ a.staff?.name }}</td>
                    <td><span class="ibadge" :class="'ibadge-' + a.status">{{ a.status }}</span></td>
                    <td>
                      <button
                        v-if="a.status === 'no_show' && !a.no_show_escalated"
                        class="ibtn ibtn-sm"
                        style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber);font-size:11px"
                        @click="escalateNoShow(a)"
                      >
                        Escalate
                      </button>
                      <span v-if="a.no_show_escalated" style="font-size:11px;color:var(--fog)">Escalated</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div>

        <!-- Right -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Quick Actions -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Actions</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="showSessionModal = true">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Log Session
              </button>
              <router-link :to="{ name: 'appointments' }" class="ibtn ibtn-o" style="width:100%;justify-content:center">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule Appointment
              </router-link>
              <!-- Refer to TMDU — only show if not yet referred -->
              <button
                v-if="!caseFile.referred_to_tmdu"
                class="ibtn ibtn-blue"
                style="width:100%;justify-content:center"
                @click="referToTmdu"
              >
                <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Refer to TMDU
              </button>
              <div
                v-if="caseFile.referred_to_tmdu"
                style="padding:8px 12px;background:var(--mist);border-radius:var(--r-sm);font-size:12px;color:var(--moss);text-align:center"
              >
                ✓ Already referred to TMDU
              </div>
              <button
                class="ibtn"
                style="width:100%;justify-content:center;background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)"
                @click="showUnreachableModal = true"
                :disabled="caseFile.student_unreachable"
              >
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ caseFile.student_unreachable ? 'Student Flagged Unreachable' : 'Flag as Unreachable' }}
              </button>
              <button
                v-if="caseFile.status !== 'closed'"
                class="ibtn"
                style="width:100%;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0"
                @click="showCloseModal = true"
              >
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Close Case
              </button>
            </div>
          </div>

          <!-- Update Status -->
          <div class="icard" v-if="showStatusModal">
            <div class="icard-header">
              <span class="icard-title">Update Status</span>
              <button class="ibtn ibtn-g ibtn-sm" @click="showStatusModal = false">✕</button>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <select v-model="newStatus" class="ifse">
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="awaiting_testing">Awaiting Testing</option>
                <option value="on_hold">On Hold</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="updateStatus">
                Save Status
              </button>
            </div>
          </div>

          <!-- Student Info -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Student</span>
              <router-link :to="{ name: 'student-show', params: { id: caseFile.student?.id } }" class="ibtn ibtn-g ibtn-sm">Profile</router-link>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div style="display:flex;align-items:center;gap:10px">
                <div class="qav" style="width:40px;height:40px;font-size:15px">
                  {{ initials(caseFile.student?.first_name, caseFile.student?.last_name) }}
                </div>
                <div>
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ caseFile.student?.first_name }} {{ caseFile.student?.last_name }}</div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ caseFile.student?.student_id }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year & College</div>
                <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.year_level }} · {{ caseFile.student?.college }}</div>
              </div>
            </div>
          </div>

          <!-- Referral Info -->
          <div class="icard" v-if="caseFile.referral">
            <div class="icard-header">
              <span class="icard-title">Source Referral</span>
              <router-link :to="{ name: 'referral-show', params: { id: caseFile.referral?.id } }" class="ibtn ibtn-g ibtn-sm">View</router-link>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral Code</div>
                <div style="font-size:13px;font-family:var(--mono)">{{ caseFile.referral?.referral_code }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referred By</div>
                <div style="font-size:13px;color:var(--ink)">{{ caseFile.referral?.referrer_name }}</div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Log Session Drawer -->
      <div v-if="showSessionModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60" @click.self="showSessionModal = false">
        <div style="position:fixed;top:0;right:0;width:min(520px,100vw);height:100vh;background:#fff;overflow-y:auto;box-shadow:-6px 0 40px rgba(0,0,0,.18)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--ink)">Log Session</div>
              <div style="font-size:12px;color:var(--stone)">{{ caseFile.case_number }}</div>
            </div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showSessionModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Session Date <span style="color:var(--red)">*</span></label>
              <input v-model="sessionForm.session_date" type="date" class="ifi" />
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Start Time</label>
                <input v-model="sessionForm.session_start_time" type="time" class="ifi" />
              </div>
              <div>
                <label class="ifl">End Time</label>
                <input v-model="sessionForm.session_end_time" type="time" class="ifi" />
              </div>
            </div>
            <div>
              <label class="ifl">Session Type <span style="color:var(--red)">*</span></label>
              <select v-model="sessionForm.session_type" class="ifse">
                <option value="initial">Initial</option>
                <option value="follow_up">Follow Up</option>
                <option value="assessment">Assessment</option>
                <option value="conference">Conference</option>
                <option value="final">Final</option>
              </select>
            </div>
            <div>
              <label class="ifl">Student Showed Up</label>
              <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                <input v-model="sessionForm.student_showed_up" type="checkbox" id="showed" style="width:15px;height:15px;accent-color:var(--moss)" />
                <label for="showed" style="font-size:13px;color:var(--slate);cursor:pointer">Yes, student attended</label>
              </div>
            </div>
            <div>
              <label class="ifl">Observations <span style="color:var(--red)">*</span></label>
              <textarea v-model="sessionForm.observations" class="ifta" placeholder="What did you observe during this session?"></textarea>
            </div>
            <div>
              <label class="ifl">Interventions Applied</label>
              <textarea v-model="sessionForm.interventions" class="ifta" style="min-height:60px" placeholder="What interventions were applied?"></textarea>
            </div>
            <div>
              <label class="ifl">Student Response</label>
              <textarea v-model="sessionForm.student_response" class="ifta" style="min-height:60px" placeholder="How did the student respond?"></textarea>
            </div>
            <div>
              <label class="ifl">Next Steps</label>
              <textarea v-model="sessionForm.next_steps" class="ifta" style="min-height:60px" placeholder="Recommended next steps?"></textarea>
            </div>
            <div>
              <label class="ifl">Mood Rating (1=Distressed, 5=Stable)</label>
              <select v-model="sessionForm.mood_rating" class="ifse">
                <option value="">Select...</option>
                <option value="1">1 — Very Distressed</option>
                <option value="2">2 — Distressed</option>
                <option value="3">3 — Neutral</option>
                <option value="4">4 — Stable</option>
                <option value="5">5 — Very Stable</option>
              </select>
            </div>
            <div style="display:flex;gap:8px;padding-top:8px">
              <button class="ibtn ibtn-p" @click="logSession">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Save Session
              </button>
              <button class="ibtn ibtn-o" @click="showSessionModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Close Case Modal -->
      <div v-if="showCloseModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCloseModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Close Case</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showCloseModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Interventions Applied <span style="color:var(--red)">*</span></label>
              <textarea v-model="closeForm.interventions_applied" class="ifta" placeholder="Summarize interventions applied..."></textarea>
            </div>
            <div>
              <label class="ifl">Outcomes <span style="color:var(--red)">*</span></label>
              <textarea v-model="closeForm.outcomes" class="ifta" placeholder="What were the outcomes?"></textarea>
            </div>
            <div>
              <label class="ifl">Recommendations</label>
              <textarea v-model="closeForm.recommendations" class="ifta" style="min-height:60px" placeholder="Any recommendations for follow-up?"></textarea>
            </div>
            <div>
              <label class="ifl">Closure Summary <span style="color:var(--red)">*</span></label>
              <textarea v-model="closeForm.closure_summary" class="ifta" placeholder="Brief closure summary..."></textarea>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="closeCase">Close Case</button>
              <button class="ibtn ibtn-o" @click="showCloseModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Flag Unreachable Modal -->
      <div v-if="showUnreachableModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showUnreachableModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Flag Student as Unreachable</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showUnreachableModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--amber)">
              ⚠ This will notify the Dean's Secretary of {{ caseFile.student?.college }} that the student is unreachable.
            </div>
            <div>
              <label class="ifl">Notes / Reason</label>
              <textarea v-model="unreachableNotes" class="ifta" placeholder="Describe attempts made to contact the student..."></textarea>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click="flagUnreachable">
                <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Flag as Unreachable
              </button>
              <button class="ibtn ibtn-o" @click="showUnreachableModal = false">Cancel</button>
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
import { caseAPI, sessionNoteAPI, appointmentAPI } from '../../api/index';

const route  = useRoute();
const toast  = inject('toast');

const loading              = ref(true);
const showSessionModal     = ref(false);
const showCloseModal       = ref(false);
const showStatusModal      = ref(false);
const showUnreachableModal = ref(false);
const newStatus            = ref('');
const unreachableNotes     = ref('');

const caseFile     = ref({});
const sessionNotes = ref([]);
const appointments = ref([]);

const sessionForm = ref({
  session_date: '', session_start_time: '', session_end_time: '',
  session_type: 'follow_up', observations: '', interventions: '',
  student_response: '', next_steps: '', mood_rating: '', student_showed_up: true,
});

const closeForm = ref({
  interventions_applied: '', outcomes: '', recommendations: '', closure_summary: '',
});

async function logSession() {
  if (!sessionForm.value.observations || !sessionForm.value.session_date) {
    toast?.error('Please fill in the date and observations.');
    return;
  }
  try {
    const res = await sessionNoteAPI.store(caseFile.value.id, sessionForm.value);
    sessionNotes.value.unshift(res.data);
    caseFile.value.total_sessions++;
    showSessionModal.value = false;
    toast?.success('Session logged successfully.');
    sessionForm.value = {
      session_date: '', session_start_time: '', session_end_time: '',
      session_type: 'follow_up', observations: '', interventions: '',
      student_response: '', next_steps: '', mood_rating: '', student_showed_up: true,
    };
  } catch (e) {
    toast?.error('Failed to log session.');
  }
}

async function updateStatus() {
  try {
    const res = await caseAPI.updateStatus(caseFile.value.id, { status: newStatus.value });
    caseFile.value.status = res.data.status;
    showStatusModal.value = false;
    toast?.success('Status updated.');
  } catch (e) {
    toast?.error('Failed to update status.');
  }
}

async function closeCase() {
  if (!closeForm.value.interventions_applied || !closeForm.value.outcomes || !closeForm.value.closure_summary) {
    toast?.error('Please fill in all required fields.');
    return;
  }
  try {
    const res = await caseAPI.close(caseFile.value.id, closeForm.value);
    caseFile.value = { ...caseFile.value, ...res.data };
    showCloseModal.value = false;
    toast?.success('Case closed successfully.');
  } catch (e) {
    toast?.error('Failed to close case.');
  }
}

async function referToTmdu() {
  try {
    await caseAPI.referToTmdu(caseFile.value.id, { reason: 'Referred for psychological assessment.' });
    caseFile.value.current_unit = 'TMDU';
    caseFile.value.status       = 'awaiting_testing';
    toast?.success('Case referred to TMDU.');
  } catch (e) {
    toast?.error('Failed to refer to TMDU.');
  }
}

async function flagUnreachable() {
  try {
    await caseAPI.flagUnreachable(caseFile.value.id, { notes: unreachableNotes.value });
    caseFile.value.student_unreachable = true;
    showUnreachableModal.value         = false;
    toast?.success("Student flagged as unreachable. Dean's Secretary has been notified.");
  } catch (e) {
    toast?.error('Failed to flag student as unreachable.');
  }
}

async function escalateNoShow(appointment) {
  try {
    await appointmentAPI.escalateNoShow(appointment.id);
    appointment.no_show_escalated = true;
    toast?.success("No-show escalated to Dean's Secretary.");
  } catch (e) {
    toast?.error('Failed to escalate no-show.');
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
    const res = await caseAPI.show(route.params.id);
    caseFile.value     = res.data;
    sessionNotes.value = res.data.session_notes || [];
    appointments.value = res.data.appointments  || [];
    newStatus.value    = res.data.status;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>