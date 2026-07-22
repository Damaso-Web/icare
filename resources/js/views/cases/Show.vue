<template>
  <div class="fade-up">
    <!-- Back + Header -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>{{ caseFile.case_number }}</h1>
        <p>{{ caseFile.student_first }} {{ caseFile.student_last }} · {{ caseFile.student_id }}</p>
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

        <!-- Case Header Card -->
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
              <div style="font-size:13px;color:var(--ink)">{{ caseFile.counselor_name }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Last Session</div>
              <div style="font-size:13px;color:var(--ink)">{{ formatDate(caseFile.last_session_at) }}</div>
            </div>
          </div>
        </div>

        <!-- Presenting Concern -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Presenting Concern</span></div>
          <div class="icard-body">
            <div style="font-size:13.5px;color:var(--ink);line-height:1.6">{{ caseFile.presenting_concern }}</div>
          </div>
        </div>

        <!-- Session Notes -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Session Notes</span>
            <button class="ibtn ibtn-p ibtn-sm" @click="showSessionModal = true">
              <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Log Session
            </button>
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
              <div style="font-size:11px;color:var(--fog);margin-top:6px">Recorded by {{ note.recorded_by }}</div>
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
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in appointments" :key="a.id">
                  <td>{{ a.appointment_type?.replace(/_/g,' ') }}</td>
                  <td style="font-size:12px">{{ formatDate(a.appointment_date) }}</td>
                  <td style="font-size:12px">{{ a.start_time }}</td>
                  <td style="font-size:12px">{{ a.staff_name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + a.status">{{ a.status }}</span></td>
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
            <button class="ibtn ibtn-blue" style="width:100%;justify-content:center" @click="referToTmdu">
              <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              Refer to TMDU
            </button>
            <button class="ibtn" style="width:100%;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="showCloseModal = true">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              Close Case
            </button>
          </div>
        </div>

        <!-- Student Info -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Student</span>
            <router-link :to="{ name: 'student-show', params: { id: caseFile.student_id } }" class="ibtn ibtn-g ibtn-sm">Profile</router-link>
          </div>
          <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
            <div style="display:flex;align-items:center;gap:10px">
              <div class="qav" style="width:40px;height:40px;font-size:15px">
                {{ initials(caseFile.student_first, caseFile.student_last) }}
              </div>
              <div>
                <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ caseFile.student_first }} {{ caseFile.student_last }}</div>
                <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ caseFile.student_id }}</div>
              </div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year & College</div>
              <div style="font-size:13px;color:var(--ink)">{{ caseFile.student_year }} · {{ caseFile.student_college }}</div>
            </div>
          </div>
        </div>

        <!-- Referral Info -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Source Referral</span>
            <router-link :to="{ name: 'referral-show', params: { id: 1 } }" class="ibtn ibtn-g ibtn-sm">View</router-link>
          </div>
          <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral Code</div>
              <div style="font-size:13px;font-family:var(--mono)">REF-2026-0001</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referred By</div>
              <div style="font-size:13px;color:var(--ink)">Prof. Juan Dela Cruz</div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Log Session Modal -->
    <div v-if="showSessionModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:flex-start;justify-content:flex-end" @click.self="showSessionModal = false">
      <div style="width:min(520px,100vw);height:100vh;background:#fff;overflow-y:auto;box-shadow:-6px 0 40px rgba(0,0,0,.18)">
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
            <textarea v-model="sessionForm.next_steps" class="ifta" style="min-height:60px" placeholder="What are the recommended next steps?"></textarea>
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
    <div v-if="showCloseModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px">
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
            <textarea v-model="closeForm.closure_summary" class="ifta" placeholder="Provide a brief closure summary..."></textarea>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="closeCase">Close Case</button>
            <button class="ibtn ibtn-o" @click="showCloseModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';

const showSessionModal = ref(false);
const showCloseModal   = ref(false);

const caseFile = ref({
  id: 1,
  case_number: 'CASE-2026-0001',
  student_first: 'Maria',
  student_last: 'Santos',
  student_id: '2023-0041',
  student_year: '3rd Year',
  student_college: 'CIT',
  case_type: 'counseling',
  current_unit: 'GCU',
  status: 'in_progress',
  counselor_name: 'Dr. Maria Reyes',
  total_sessions: 2,
  last_session_at: '2026-07-05',
  opened_date: '2026-07-01',
  presenting_concern: 'Student has been showing signs of academic stress and anxiety. Missed 3 consecutive classes and appears withdrawn from peers.',
});

const sessionNotes = ref([
  {
    id: 1,
    session_number: 1,
    session_type: 'initial',
    session_date: '2026-07-03',
    observations: 'Student appeared anxious and withdrawn. Expressed concern about failing grades and fear of disappointing parents.',
    next_steps: 'Schedule follow-up session. Recommend stress management exercises.',
    recorded_by: 'Dr. Maria Reyes',
  },
  {
    id: 2,
    session_number: 2,
    session_type: 'follow_up',
    session_date: '2026-07-05',
    observations: 'Student showed slight improvement. More communicative and responsive. Still concerned about academic performance.',
    next_steps: 'Continue counseling. Coordinate with faculty regarding academic support.',
    recorded_by: 'Dr. Maria Reyes',
  },
]);

const appointments = ref([
  {
    id: 1,
    appointment_type: 'initial_counseling',
    appointment_date: '2026-07-03',
    start_time: '09:00',
    staff_name: 'Dr. Maria Reyes',
    status: 'completed',
  },
  {
    id: 2,
    appointment_type: 'follow_up_session',
    appointment_date: '2026-07-10',
    start_time: '10:00',
    staff_name: 'Dr. Maria Reyes',
    status: 'confirmed',
  },
]);

const sessionForm = ref({
  session_date: '',
  session_start_time: '',
  session_end_time: '',
  session_type: 'follow_up',
  observations: '',
  interventions: '',
  student_response: '',
  next_steps: '',
  mood_rating: '',
  student_showed_up: true,
});

const closeForm = ref({
  interventions_applied: '',
  outcomes: '',
  recommendations: '',
  closure_summary: '',
});

function logSession() {
  if (!sessionForm.value.observations) return;
  const newNote = {
    id: sessionNotes.value.length + 1,
    session_number: sessionNotes.value.length + 1,
    session_type: sessionForm.value.session_type,
    session_date: sessionForm.value.session_date,
    observations: sessionForm.value.observations,
    next_steps: sessionForm.value.next_steps,
    recorded_by: 'Dr. Maria Reyes',
  };
  sessionNotes.value.unshift(newNote);
  caseFile.value.total_sessions++;
  showSessionModal.value = false;
  sessionForm.value = { session_date: '', session_start_time: '', session_end_time: '', session_type: 'follow_up', observations: '', interventions: '', student_response: '', next_steps: '', mood_rating: '', student_showed_up: true };
}

function closeCase() {
  caseFile.value.status = 'closed';
  showCloseModal.value  = false;
}

function referToTmdu() {
  caseFile.value.current_unit = 'TMDU';
  caseFile.value.status       = 'awaiting_testing';
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}
</script>