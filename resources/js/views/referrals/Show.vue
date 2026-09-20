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
        <div v-if="(isGCU || isSDUHead) && referral.case" style="margin-left:auto;display:flex;gap:8px">
          <button v-if="isGCU && referral.status !== 'submitted'" class="ibtn ibtn-o ibtn-sm" @click="openStatusModal">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Status
          </button>
          <button v-if="isGCU" class="ibtn ibtn-o ibtn-sm" @click="showCaseStatusModal = true">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Case Status
          </button>
          <button v-if="isGCU" class="ibtn ibtn-o ibtn-sm" @click="openAssignModal">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Reassign
          </button>
          <button
            v-if="isGCU || (isSDUHead && referral.case.current_unit === 'SDU')"
            class="ibtn ibtn-o ibtn-sm"
            @click="openTransferModal"
          >
            <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            Transfer Unit
          </button>
          <button
            v-if="isGCU && !referral.case.requires_follow_up"
            class="ibtn ibtn-sm"
            style="background:var(--purple-lt);color:var(--purple);border:1.5px solid var(--purple)"
            @click="showFollowUpFlagModal = true"
          >
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><path d="M4 22V4a2 2 0 0 1 2-2h9l5 5v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/></svg>
            Flag Follow-up
          </button>
          <button
            v-if="isGCU && !referral.case.student_unreachable"
            class="ibtn ibtn-sm"
            style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)"
            @click="showUnreachableModal = true"
          >
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Flag Unreachable
          </button>
        </div>
      </div>

      <!-- Case Summary -->
      <div class="icard" v-if="referral.case" style="margin-bottom:16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;padding:16px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Case Number</div>
            <div style="font-family:var(--mono);font-size:13px;color:var(--ink)">{{ referral.case.case_number }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Current Unit</div>
            <span class="ibadge" :class="'unit-' + referral.case.current_unit?.toLowerCase()">{{ referral.case.current_unit }}</span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Case Status</div>
            <span class="ibadge" :class="'ibadge-' + referral.case.status">{{ toTitleCase(referral.case.status) }}</span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Counselor</div>
            <div style="font-size:13px;color:var(--ink)">{{ referral.case.counselor?.name || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sessions</div>
            <div style="font-size:13px;color:var(--ink)">{{ referral.case.total_sessions }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Last Session</div>
            <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.case.last_session_at) }}</div>
          </div>
        </div>
      </div>

      <div v-if="referral.case?.student_unreachable" class="icard" style="margin-bottom:16px;background:var(--amber-lt);border:1px solid var(--amber);padding:10px 14px;font-size:13px;color:var(--amber);display:flex;align-items:center;gap:8px">
        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Student flagged as unreachable. Dean's Secretary has been notified.
      </div>

      <div v-if="referral.case?.requires_follow_up" class="icard" style="margin-bottom:16px;background:var(--purple-lt);border:1px solid var(--purple);padding:10px 14px;font-size:13px;color:var(--purple);display:flex;align-items:center;justify-content:space-between;gap:8px">
        <div style="display:flex;align-items:center;gap:8px">
          <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><path d="M4 22V4a2 2 0 0 1 2-2h9l5 5v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/></svg>
          <span>Case flagged for further attention<span v-if="referral.case.follow_up_notes"> - {{ referral.case.follow_up_notes }}</span></span>
        </div>
        <button v-if="isGCU" class="ibtn ibtn-o ibtn-sm" @click="resolveFollowUpFlag">Resolve</button>
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

          <!-- Referral Info (merged with the former "Referral Details" card) -->
          <div class="icard">
            <div class="icard-header" style="display:flex;align-items:center;justify-content:space-between">
              <span class="icard-title">Referral Info</span>
              <div style="display:flex;gap:6px">
                <button v-if="!referral.is_archived" class="ibtn ibtn-o ibtn-sm" @click="openEditModal">Edit</button>
                <button
                  class="ibtn ibtn-o ibtn-sm"
                  :disabled="!referral.is_archived && !isArchivable"
                  :title="!referral.is_archived && !isArchivable ? 'Case must be Resolved or Closed before it can be archived.' : ''"
                  :style="referral.is_archived
                    ? 'background:var(--mist);color:var(--moss);border-color:var(--mint)'
                    : (!isArchivable ? 'opacity:.5;cursor:not-allowed' : '')"
                  @click="toggleArchive"
                >
                  {{ referral.is_archived ? 'Unarchive' : 'Archive' }}
                </button>
              </div>
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
                  <span v-if="referral.case?.is_recurring" class="ibadge" style="background:var(--blue-lt);color:var(--blue);margin-left:4px">Recurring</span>
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

              <div v-if="referral.case?.handoffs?.length">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">Handoff History</div>
                <div v-for="h in [...referral.case.handoffs].reverse()" :key="h.id" style="padding:8px 0;border-top:1px solid var(--cloud)">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                    <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ h.from_unit }} → {{ h.to_unit }}</div>
                    <span v-if="h.acknowledged" class="ibadge" style="background:var(--mist);color:var(--moss)">Receipt Confirmed</span>
                  </div>
                  <div style="font-size:11px;color:var(--stone);margin-top:2px">By {{ h.from_user?.name || '-' }} to {{ h.to_user?.name || '-' }}</div>
                  <div v-if="h.reason" style="font-size:12px;color:var(--slate);margin-top:4px">{{ h.reason }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:2px">{{ formatDate(h.created_at) }}</div>
                  <button
                    v-if="!h.acknowledged && h.to_user_id === auth.user?.id"
                    class="ibtn ibtn-o ibtn-sm"
                    style="margin-top:6px"
                    @click="confirmHandoffReceipt(h)"
                  >
                    Confirm Receipt
                  </button>
                </div>
              </div>
            </div>
          </div>


          <!-- Previous Interventions - case-level, append-only log. For Class Attendance referrals, this doubles as the admission slip: an Unexcused mark locks the referral. -->
          <div class="icard" v-if="referral.case">
            <div class="icard-header"><span class="icard-title">Case Intervention Log</span></div>
            <div class="icard-body">
              <div style="font-size:11px;color:var(--stone);margin-bottom:10px;font-style:italic">For OSS Personnel</div>

              <!-- Add entry (GCU only) - Person-In-Charge is always the logged-in staff account; excused/unexcused only applies to Class Attendance referrals -->
              <div v-if="isGCU && !interventionLocked">
                <div style="margin-bottom:12px">
                  <label class="ifl">Type</label>
                  <select v-model="interventionForm.type" class="ifse">
                    <option value="previous_intervention">Counseling Session</option>
                    <option value="follow_up">Follow-up</option>
                    <option value="parent_conference">Parent Conference</option>
                    <option value="home_visit">Home Visit</option>
                    <option value="referral_external">Referral to External Unit</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div :style="{ display:'grid', gridTemplateColumns: showExcusedRemarks ? '2fr 1fr' : '1fr', gap:'16px', alignItems:'start', marginBottom:'12px' }">
                  <div>
                    <label class="ifl">{{ interventionForm.type === 'previous_intervention' ? 'Intervention' : 'Notes' }}</label>
                    <textarea v-model="interventionForm.text" class="ifta" style="min-height:70px" placeholder="Describe any prior support or actions already taken..."></textarea>
                  </div>
                  <div v-if="showExcusedRemarks">
                    <label class="ifl">Remarks</label>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
                      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--slate)">
                        <input
                          type="checkbox"
                          :checked="interventionForm.excused === '1'"
                          @change="interventionForm.excused = interventionForm.excused === '1' ? '' : '1'"
                          style="width:15px;height:15px;accent-color:var(--moss)"
                        />
                        Excused
                      </label>
                      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:var(--slate)">
                        <input
                          type="checkbox"
                          :checked="interventionForm.excused === '0'"
                          @change="interventionForm.excused = interventionForm.excused === '0' ? '' : '0'"
                          style="width:15px;height:15px;accent-color:var(--red)"
                        />
                        Unexcused
                      </label>
                    </div>
                    <button class="ibtn ibtn-p ibtn-sm" style="margin-top:12px" @click="addIntervention">Save Entry</button>
                  </div>
                </div>
                <button v-if="!showExcusedRemarks" class="ibtn ibtn-p ibtn-sm" style="margin-bottom:16px" @click="addIntervention">Save Entry</button>
              </div>
              <div v-else-if="isGCU && interventionLocked" style="background:var(--mist);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--moss);margin-bottom:16px">
                This referral has been marked Unexcused, which serves as its admission slip. No further interventions can be added for it.
              </div>

              <!-- History - each saved entry is permanent; new activity adds another entry, it never overwrites -->
              <div v-if="!referral.case.interventions?.length" style="font-size:13px;color:var(--stone)">No previous interventions recorded yet.</div>
              <div v-else style="display:flex;flex-direction:column;gap:8px">
                <div
                  v-for="item in referral.case.interventions"
                  :key="item.id"
                  style="padding:10px 12px;background:var(--snow);border-radius:var(--r-sm);border-left:2px solid var(--silver);cursor:pointer;transition:background .1s"
                  @mouseover="$event.currentTarget.style.background='var(--foam)'"
                  @mouseleave="$event.currentTarget.style.background='var(--snow)'"
                  @click="viewIntervention(item)"
                >
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                    <div style="font-size:13px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ item.description }}</div>
                    <div style="font-size:11px;color:var(--fog);flex-shrink:0">{{ formatDate(item.created_at) }}</div>
                  </div>
                  <div style="font-size:11px;color:var(--stone);margin-top:3px">
                    {{ toTitleCase(item.type) || 'Previous Intervention' }}
                    <span v-if="item.referral"> · {{ toTitleCase(item.referral.referral_type) }}</span>
                    <span v-if="item.excused !== null"> · {{ item.excused ? 'Excused' : 'Unexcused' }}</span>
                    <span v-if="item.is_completed" class="ibadge" style="background:var(--mist);color:var(--moss);margin-left:4px">Completed</span>
                  </div>
                  <div style="font-size:10.5px;color:var(--fog);margin-top:2px">By {{ item.recorded_by?.name || item.person_in_charge?.name || '-' }}</div>
                </div>
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

          <!-- Session Notes - staff only -->
          <div class="icard" v-if="isGCU">
            <div class="icard-header">
              <span class="icard-title">Session Notes</span>
              <button class="ibtn ibtn-p ibtn-sm" @click="showSessionModal = true">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Notes
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
                    Session #{{ note.session_number }} - {{ toTitleCase(note.session_type) }}
                  </div>
                  <div style="font-size:11px;color:var(--fog)">{{ formatDate(note.session_date) }}</div>
                </div>
                <div v-if="note.session_start_time && note.session_end_time" style="font-size:11px;color:var(--stone);margin-bottom:8px">
                  {{ note.session_start_time }} - {{ note.session_end_time }}
                </div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Observations</div>
                <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver);margin-bottom:8px">{{ note.observations }}</div>
                <div v-if="note.next_steps">
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Next Steps</div>
                  <div style="font-size:13px;color:var(--slate)">{{ note.next_steps }}</div>
                </div>
                <div style="font-size:11px;color:var(--fog);margin-top:6px">Recorded by {{ note.recorded_by?.name }}</div>
              </div>
            </div>
          </div>

          <!-- Follow-up Session -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Follow-up Session</span>
              <button v-if="isGCU" class="ibtn ibtn-p ibtn-sm" @click="openFollowUpModal">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Schedule Follow-up
              </button>
            </div>
            <div v-if="followUps.length === 0" class="empty-state">
              <h3>No follow-up scheduled</h3>
              <p>Schedule a follow-up session to keep tracking this referral.</p>
            </div>
            <div v-else>
              <div v-for="fu in followUps" :key="fu.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
                <div>
                  <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ formatDate(fu.appointment_date) }} · {{ fu.start_time }}-{{ fu.end_time }}</div>
                  <div style="font-size:12px;color:var(--stone);margin-top:2px">With {{ fu.staff?.name || 'TBA' }}</div>
                  <div v-if="fu.notes" style="font-size:12px;color:var(--slate);margin-top:6px;background:var(--snow);padding:8px 10px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ fu.notes }}</div>
                </div>
                <span class="ibadge" :class="'ibadge-' + fu.status">{{ toTitleCase(fu.status) }}</span>
              </div>
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


          <!-- Case Action -->
          <div class="icard" v-if="isGCU && referral.case">
            <div class="icard-header"><span class="icard-title">Case Action</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <router-link
                v-if="referral.status !== 'submitted'"
                :to="{ name: 'appointments' }"
                class="ibtn ibtn-blue"
                style="width:100%;justify-content:center"
              >
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule Appointment
              </router-link>
              <div v-else style="padding:8px 12px;background:var(--cloud);border-radius:var(--r-sm);font-size:12px;color:var(--stone);text-align:center">
                ⚠ Acknowledge referral first
              </div>
              <button
                v-if="!referral.case.referred_to_tmdu"
                class="ibtn ibtn-o"
                style="width:100%;justify-content:center"
                @click="referToTmdu"
              >
                <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Refer to TMDU
              </button>
              <div v-else style="padding:8px 12px;background:var(--mist);border-radius:var(--r-sm);font-size:12px;color:var(--moss);text-align:center">
                ✓ Already referred to TMDU
              </div>
              <router-link
                :to="{ name: 'case-study-report', params: { id: referral.case.id } }"
                target="_blank"
                class="ibtn ibtn-o"
                style="width:100%;justify-content:center"
              >
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                View Case Study Report
              </router-link>
            </div>
          </div>

          <!-- Service Slips -->
          <div class="icard" v-if="isGCU">
            <div class="icard-header"><span class="icard-title">Service Slips</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="printSlip('admission')">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Admission Slip
              </button>
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="printSlip('call')">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Call Slip
              </button>
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="printSlip('feedback')">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Feedback Slip
              </button>
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="printSlip('followup')">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Follow Up Slip
              </button>
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="printSlip('parent')">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Parent Conference Slip
              </button>
            </div>
          </div>

          <!-- Appointments (scoped to this referral only) -->
          <div class="icard" v-if="referral.case">
            <div class="icard-header"><span class="icard-title">Appointments</span></div>
            <div v-if="!referralAppointments.length" class="empty-state">
              <h3>No appointments yet</h3>
              <p>No appointments have been scheduled for this referral.</p>
            </div>
            <div v-else>
              <div v-for="a in referralAppointments" :key="a.id" style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;flex-direction:column;gap:6px">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                  <div>
                    <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ toTitleCase(a.appointment_type) }}</div>
                    <div style="font-size:11px;color:var(--stone);margin-top:2px">{{ formatDate(a.appointment_date) }} · {{ a.start_time }}</div>
                    <span class="ibadge" :class="'unit-' + a.unit?.toLowerCase()" style="margin-top:4px;display:inline-block">{{ a.unit }}</span>
                  </div>
                  <span class="ibadge" :class="'ibadge-' + a.status">{{ toTitleCase(a.status) }}</span>
                </div>
                <div style="font-size:11px;color:var(--fog)">With {{ a.staff?.name || '-' }}</div>
                <button v-if="isGCU && a.status === 'confirmed'" class="ibtn ibtn-sm" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber);align-self:flex-start" @click="markCaseAppointmentNoShow(a)">No-Show</button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Add Session Notes Modal -->
      <div v-if="showSessionModal && isGCU" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showSessionModal = false">
        <div style="width:100%;max-width:520px;max-height:90vh;background:#fff;overflow-y:auto;border-radius:var(--r-lg);box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--ink)">Add Session Notes</div>
              <div style="font-size:12px;color:var(--stone)">{{ referral.referral_code }}</div>
            </div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showSessionModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
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
                <input v-model="sessionForm.student_showed_up" type="checkbox" id="ref-showed" style="width:15px;height:15px;accent-color:var(--moss)" />
                <label for="ref-showed" style="font-size:13px;color:var(--slate);cursor:pointer">Yes, student attended</label>
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
            <div style="display:flex;gap:8px;padding-top:8px">
              <button class="ibtn ibtn-p" @click="logSession">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Save Notes
              </button>
              <button class="ibtn ibtn-o" @click="showSessionModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Schedule Follow-up Modal -->
      <div v-if="showFollowUpModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showFollowUpModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Schedule Follow-up Session</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showFollowUpModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Date <span style="color:var(--red)">*</span></label>
              <input v-model="followUpForm.appointment_date" type="date" class="ifi" />
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Start Time <span style="color:var(--red)">*</span></label>
                <input v-model="followUpForm.start_time" type="time" class="ifi" />
              </div>
              <div>
                <label class="ifl">End Time <span style="color:var(--red)">*</span></label>
                <input v-model="followUpForm.end_time" type="time" class="ifi" />
              </div>
            </div>
            <div>
              <label class="ifl">Assigned Staff</label>
              <select v-model="followUpForm.staff_user_id" class="ifse">
                <option value="">Auto-assign (me)</option>
                <option v-for="u in staffList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div v-if="followUpForm.staff_user_id && followUpForm.appointment_date" style="font-size:12px;color:var(--stone);background:var(--cloud);border-radius:var(--r-sm);padding:10px 12px">
              <div v-if="loadingFollowUpAvailability">Checking schedule...</div>
              <template v-else>
                <div style="font-weight:600;color:var(--ink);margin-bottom:4px">Schedule for {{ followUpForm.appointment_date }}</div>
                <div v-if="followUpAvailability?.booked_slots?.length" style="display:flex;flex-direction:column;gap:2px">
                  <div v-for="(slot, i) in followUpAvailability.booked_slots" :key="i" :style="isFollowUpOverlapping(slot) ? 'color:var(--red);font-weight:600' : ''">
                    {{ slot.start_time }}–{{ slot.end_time }} · {{ toTitleCase(slot.appointment_type) }}
                    <span v-if="isFollowUpOverlapping(slot)">(conflicts with this time)</span>
                  </div>
                </div>
                <div v-else>No other appointments booked for this staff that day.</div>
              </template>
            </div>
            <div>
              <label class="ifl">Notes</label>
              <textarea v-model="followUpForm.notes" class="ifta" style="min-height:60px" placeholder="What should this follow-up cover?"></textarea>
            </div>
            <div v-if="followUpError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ followUpError }}
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="saveFollowUp">Schedule Follow-up</button>
              <button class="ibtn ibtn-o" @click="showFollowUpModal = false">Cancel</button>
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

      <!-- Update Case Status Modal -->
      <div v-if="showCaseStatusModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCaseStatusModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Update Case Status</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showCaseStatusModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:8px">
            <select v-model="newCaseStatus" class="ifse">
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="awaiting_testing">Awaiting Testing</option>
              <option value="on_hold">On Hold</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
            <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="updateCaseStatus">Save Status</button>
          </div>
        </div>
      </div>


      <!-- Close Case Modal -->

      <!-- Flag Unreachable Modal -->
      <div v-if="showUnreachableModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showUnreachableModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Flag Student as Unreachable</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showUnreachableModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--amber)">
              ⚠ This will notify the Dean's Secretary of {{ referral.student?.college }} that the student is unreachable.
            </div>
            <div>
              <label class="ifl">Notes / Reason</label>
              <textarea v-model="unreachableNotes" class="ifta" placeholder="Describe attempts made to contact the student..."></textarea>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click="flagUnreachable">
                Flag as Unreachable
              </button>
              <button class="ibtn ibtn-o" @click="showUnreachableModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Intervention Detail Modal -->
      <div v-if="selectedIntervention" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="selectedIntervention = null">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Intervention Summary</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="selectedIntervention = null">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Type</div>
              <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(selectedIntervention.type) || 'Previous Intervention' }}</div>
            </div>
            <div v-if="selectedIntervention.referral">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service</div>
              <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(selectedIntervention.referral?.referral_type) || '-' }}</div>
            </div>
            <div v-if="selectedIntervention.referral">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Reason of Referral</div>
              <div style="font-size:13px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ selectedIntervention.referral?.nature_of_concern || '-' }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">{{ selectedIntervention.type === 'previous_intervention' ? 'Intervention' : 'Notes' }}</div>
              <div style="font-size:13.5px;color:var(--ink);line-height:1.6;white-space:pre-line;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ selectedIntervention.description }}</div>
            </div>
            <div v-if="selectedIntervention.excused !== null">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Remarks</div>
              <div style="font-size:13px;color:var(--ink)">{{ selectedIntervention.excused ? 'Excused' : 'Unexcused' }}</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Person-In-Charge</div>
                <div style="font-size:13px;color:var(--ink)">{{ selectedIntervention.person_in_charge?.name || '-' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date</div>
                <div style="font-size:13px;color:var(--ink)">{{ formatDate(selectedIntervention.created_at) }}</div>
              </div>
            </div>

            <div v-if="selectedIntervention.is_completed" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--forest)">
              ✓ Completed {{ formatDate(selectedIntervention.completed_at) }}{{ selectedIntervention.completed_by ? ' by ' + selectedIntervention.completed_by.name : '' }}.
            </div>

            <div style="display:flex;gap:8px;justify-content:flex-end">
              <button v-if="isGCU && !selectedIntervention.is_completed" class="ibtn ibtn-p" @click="markInterventionCompleted">Done</button>
              <button class="ibtn ibtn-o" @click="closeIntervention">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Flag Follow-up Modal -->
      <div v-if="showFollowUpFlagModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showFollowUpFlagModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Flag Case for Follow-up</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showFollowUpFlagModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="background:var(--purple-lt);border:1px solid var(--purple);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--purple)">
            This marks the case as requiring further attention and schedules a reminder for the counselor.
          </div>
          <div>
            <label class="ifl">Follow-up Due Date <span style="color:var(--red)">*</span></label>
            <input v-model="followUpDueDate" type="date" class="ifi" />
          </div>
          <div>
            <label class="ifl">Notes / Reason</label>
            <textarea v-model="followUpFlagNotes" class="ifta" placeholder="Why does this case need further attention?"></textarea>
          </div>
          <div v-if="followUpFlagError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ followUpFlagError }}</div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--purple-lt);color:var(--purple);border:1.5px solid var(--purple)" @click="flagFollowUp">
              Flag for Follow-up
            </button>
            <button class="ibtn ibtn-o" @click="showFollowUpFlagModal = false">Cancel</button>
          </div>
        </div>
        </div>
      </div>

      <!-- Reassign Counselor Modal -->
      <div v-if="showAssignModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showAssignModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Reassign Counselor</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showAssignModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Assign To <span style="color:var(--red)">*</span></label>
              <select v-model="assignForm.to_user_id" class="ifse">
                <option value="">Select staff member...</option>
                <option v-for="u in unitStaffList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="assignCounselor">Assign</button>
              <button class="ibtn ibtn-o" @click="showAssignModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Transfer Unit Modal -->
      <div v-if="showTransferModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showTransferModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Transfer Case to Another Unit</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showTransferModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Target Unit <span style="color:var(--red)">*</span></label>
              <select v-model="transferForm.to_unit" class="ifse" @change="loadUnitStaff(transferForm.to_unit)">
                <option value="">Select unit...</option>
                <option v-for="u in ['GCU', 'SDU', 'TMDU'].filter(u => u !== referral.case?.current_unit)" :key="u" :value="u">{{ u }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Assign To <span style="color:var(--red)">*</span></label>
              <select v-model="transferForm.to_user_id" class="ifse" :disabled="!transferForm.to_unit">
                <option value="">Select staff member...</option>
                <option v-for="u in unitStaffList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Reason <span style="color:var(--red)">*</span></label>
              <textarea v-model="transferForm.reason" class="ifta" style="min-height:60px" placeholder="Why is this case being transferred?"></textarea>
            </div>
            <div v-if="transferError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ transferError }}
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="transferUnit">Transfer</button>
              <button class="ibtn ibtn-o" @click="showTransferModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Referral Modal -->
      <div v-if="showEditModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Edit Referral</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Concern / Reason for Referral <span style="color:var(--red)">*</span></label>
              <textarea v-model="editForm.nature_of_concern" class="ifta" style="min-height:80px"></textarea>
            </div>
            <div>
              <label class="ifl">Urgency Level</label>
              <select v-model="editForm.urgency_level" class="ifse">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div>
              <label class="ifl">Intake Notes</label>
              <textarea v-model="editForm.intake_notes" class="ifta" style="min-height:60px"></textarea>
            </div>
            <template v-if="referral.referral_type === 'disciplinary'">
              <div>
                <label class="ifl">Violation Type</label>
                <input v-model="editForm.violation_type" class="ifi" />
              </div>
              <div>
                <label class="ifl">Incident Description</label>
                <textarea v-model="editForm.incident_description" class="ifta" style="min-height:60px"></textarea>
              </div>
              <div>
                <label class="ifl">Date of Incident</label>
                <input v-model="editForm.incident_date" type="date" class="ifi" />
              </div>
              <div>
                <label class="ifl">Sanction / Outcome</label>
                <select v-model="editForm.sanction" class="ifse">
                  <option value="">Not yet determined</option>
                  <option value="verbal_warning">Verbal Warning</option>
                  <option value="written_warning">Written Warning</option>
                  <option value="suspension">Suspension</option>
                  <option value="expulsion">Expulsion</option>
                  <option value="community_service">Community Service</option>
                  <option value="dismissed">Case Dismissed</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="ifl">Sanction Notes</label>
                <textarea v-model="editForm.sanction_notes" class="ifta" style="min-height:60px" placeholder="Details about the sanction/outcome..."></textarea>
              </div>
            </template>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" :disabled="isEditFormUnchanged" @click="submitEdit">Save Changes</button>
              <button class="ibtn ibtn-o" @click="showEditModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, inject, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { referralAPI, sessionNoteAPI, caseAPI, appointmentAPI, userAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { toTitleCase } from '../../utils/validators';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();
const loading = ref(true);
const acknowledging = ref(false);
const referral = ref({});
const followUpDueDate = ref('');
const followUpFlagError = ref('');

const sessionNotes      = ref([]);
const showSessionModal  = ref(false);

const followUps          = ref([]);
const staffList          = ref([]);
const showFollowUpModal  = ref(false);
const followUpError      = ref('');

const showStatusModal      = ref(false);
const showCaseStatusModal  = ref(false);
const showUnreachableModal = ref(false);
const showFollowUpFlagModal = ref(false);
const showAssignModal      = ref(false);
const showTransferModal    = ref(false);
const newStatus             = ref('');
const newCaseStatus         = ref('');
const unreachableNotes      = ref('');
const followUpFlagNotes     = ref('');
const unitStaffList         = ref([]);
const transferError         = ref('');

const assignForm   = ref({ to_user_id: '' });
const transferForm = ref({ to_unit: '', to_user_id: '', reason: '' });
const interventionForm = ref({ text: '', excused: '', type: 'previous_intervention' });
const selectedIntervention = ref(null);

const showEditModal = ref(false);
const editForm = ref({ nature_of_concern: '', urgency_level: '', intake_notes: '', violation_type: '', incident_description: '', incident_date: '', sanction: '', sanction_notes: '' });
const editFormSnapshot = ref('');

const isEditFormUnchanged = computed(() => JSON.stringify(editForm.value) === editFormSnapshot.value);

// Archiving is only allowed once the case itself has wrapped up - archiving
// an in-progress/open case would hide a client that's still being worked.
const isArchivable = computed(() => ['resolved', 'closed'].includes(referral.value.case?.status));

const EDIT_FIELD_LABELS = {
  urgency_level: 'Urgency Level',
  nature_of_concern: 'Concern',
  intake_notes: 'Intake Notes',
};

function openEditModal() {
  editForm.value = {
    nature_of_concern: referral.value.nature_of_concern || '',
    urgency_level: referral.value.urgency_level || 'low',
    intake_notes: referral.value.intake_notes || '',
    violation_type: referral.value.violation_type || '',
    incident_description: referral.value.incident_description || '',
    incident_date: referral.value.incident_date ? referral.value.incident_date.split('T')[0] : '',
    sanction: referral.value.sanction || '',
    sanction_notes: referral.value.sanction_notes || '',
  };
  editFormSnapshot.value = JSON.stringify(editForm.value);
  showEditModal.value = true;
}

async function submitEdit() {
  const before = JSON.parse(editFormSnapshot.value);
  const changeLines = Object.keys(EDIT_FIELD_LABELS)
    .filter(key => before[key] !== editForm.value[key])
    .map(key => `${EDIT_FIELD_LABELS[key]}:\n  Before: ${before[key] || '(blank)'}\n  After: ${editForm.value[key] || '(blank)'}`);

  if (changeLines.length && !confirm(`Confirm the following changes?\n\n${changeLines.join('\n\n')}`)) {
    return;
  }

  try {
    const res = await referralAPI.update(referral.value.id, editForm.value);
    referral.value = { ...referral.value, ...res.data };
    toast?.success('Referral updated.');
    showEditModal.value = false;
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to update referral.');
  }
}

async function toggleArchive() {
  if (!referral.value.is_archived && !isArchivable.value) return;
  const action = referral.value.is_archived ? 'restore this referral from the archive' : 'archive this referral';
  if (!confirm(`Are you sure you want to ${action}?`)) return;
  try {
    const res = referral.value.is_archived
      ? await referralAPI.unarchive(referral.value.id)
      : await referralAPI.archive(referral.value.id);
    referral.value = { ...referral.value, ...res.data };
    toast?.success(referral.value.is_archived ? 'Referral archived.' : 'Referral restored from archive.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to update archive status.');
  }
}

function viewIntervention(item) {
  selectedIntervention.value = item;
}

async function markInterventionCompleted() {
  try {
    const res = await caseAPI.completeIntervention(selectedIntervention.value.id);
    Object.assign(selectedIntervention.value, res.data);
    const idx = (referral.value.case.interventions || []).findIndex(i => i.id === res.data.id);
    if (idx !== -1) referral.value.case.interventions[idx] = res.data;
    toast?.success('Intervention marked as completed.');
  } catch (e) {
    toast?.error('Failed to mark intervention as completed.');
  }
}

// Close on this modal drops the entry rather than just dismissing the view.
async function closeIntervention() {
  const id = selectedIntervention.value.id;
  try {
    await caseAPI.deleteIntervention(id);
    referral.value.case.interventions = (referral.value.case.interventions || []).filter(i => i.id !== id);
    toast?.success('Intervention entry removed.');
  } catch (e) {
    toast?.error('Failed to remove intervention entry.');
  } finally {
    selectedIntervention.value = null;
  }
}

const interventionsForReferral = computed(() => {
  const all = referral.value.case?.interventions || [];
  return all.filter(i => i.referral_id === referral.value.id);
});

const referralAppointments = computed(() => {
  const all = referral.value.case?.appointments || [];
  return all.filter(a => a.referral_id === referral.value.id);
});

const interventionLocked = computed(() =>
  referral.value.referral_type === 'class_attendance'
  && interventionsForReferral.value.some(i => i.excused === false)
);

const showExcusedRemarks = computed(() =>
  interventionForm.value.type === 'previous_intervention'
  && referral.value.referral_type === 'class_attendance'
);

const COUNSELING_ROLES = { GCU: ['admin', 'gcu_staff'], SDU: ['admin', 'sdu_head'], TMDU: ['admin', 'tmdu_staff'] };

const sessionForm = ref({
  session_type: 'follow_up', observations: '', interventions: '',
  student_response: '', next_steps: '', student_showed_up: true,
});

const feedbackForm = ref({ feedback_notes: '' });

const followUpForm = ref({
  appointment_date: '', start_time: '', end_time: '', staff_user_id: '', notes: '',
});

const followUpAvailability = ref(null);
const loadingFollowUpAvailability = ref(false);

function isFollowUpOverlapping(slot) {
  if (!followUpForm.value.start_time || !followUpForm.value.end_time) return false;
  return slot.start_time < followUpForm.value.end_time && slot.end_time > followUpForm.value.start_time;
}

watch([() => followUpForm.value.staff_user_id, () => followUpForm.value.appointment_date], async ([staffId, date]) => {
  followUpAvailability.value = null;
  if (!staffId || !date) return;
  loadingFollowUpAvailability.value = true;
  try {
    const res = await appointmentAPI.availability({ user_id: staffId, date });
    followUpAvailability.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loadingFollowUpAvailability.value = false;
  }
});

const isGCU = computed(() => ['admin', 'gcu_staff'].includes(auth.user?.role));
const isSDUHead = computed(() => auth.user?.role === 'sdu_head');

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

function printSlip(type) {
  toast?.success(`Generating ${type} slip... (coming soon)`);
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

async function logSession() {
  if (!sessionForm.value.observations) {
    toast?.error('Please fill in the observations.');
    return;
  }
  try {
    const now = new Date();
    const payload = {
      ...sessionForm.value,
      session_date: now.toISOString().split('T')[0],
      session_start_time: now.toTimeString().slice(0, 5),
    };
    const res = await sessionNoteAPI.storeByReferral(referral.value.id, payload);
    sessionNotes.value.unshift(res.data);
    showSessionModal.value = false;
    toast?.success('Session notes saved successfully.');
    sessionForm.value = {
      session_type: 'follow_up', observations: '', interventions: '',
      student_response: '', next_steps: '', student_showed_up: true,
    };
  } catch (e) {
    toast?.error('Failed to save session notes.');
  }
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

async function addIntervention() {
  if (!interventionForm.value.text) {
    toast?.error('Please describe the intervention.');
    return;
  }
  try {
    const res = await caseAPI.addIntervention(referral.value.case.id, {
      referral_id: interventionForm.value.type === 'previous_intervention' ? referral.value.id : null,
      type: interventionForm.value.type,
      description: interventionForm.value.text,
      excused: showExcusedRemarks.value && interventionForm.value.excused !== ''
        ? interventionForm.value.excused === '1'
        : null,
    });
    if (!referral.value.case.interventions) referral.value.case.interventions = [];
    referral.value.case.interventions.unshift(res.data);
    interventionForm.value = { text: '', excused: '', type: 'previous_intervention' };
    toast?.success('Entry recorded.');
    viewIntervention(res.data);
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to save entry.');
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

async function updateCaseStatus() {
  try {
    const res = await caseAPI.updateStatus(referral.value.case.id, { status: newCaseStatus.value });
    referral.value.case.status = res.data.status;
    showCaseStatusModal.value = false;
    toast?.success('Case status updated.');
  } catch (e) {
    toast?.error('Failed to update case status.');
  }
}

async function flagUnreachable() {
  try {
    await caseAPI.flagUnreachable(referral.value.case.id, { notes: unreachableNotes.value });
    referral.value.case.student_unreachable = true;
    showUnreachableModal.value = false;
    toast?.success("Student flagged as unreachable. Dean's Secretary has been notified.");
  } catch (e) {
    toast?.error('Failed to flag student as unreachable.');
  }
}

async function referToTmdu() {
  try {
    await caseAPI.referToTmdu(referral.value.case.id, { reason: 'Referred for psychological assessment.' });
    referral.value.case.current_unit     = 'TMDU';
    referral.value.case.status           = 'awaiting_testing';
    referral.value.case.referred_to_tmdu = true;
    toast?.success('Case referred to TMDU.');
  } catch (e) {
    toast?.error('Failed to refer to TMDU.');
  }
}

async function loadUnitStaff(unit) {
  transferForm.value.to_user_id = '';
  unitStaffList.value = [];
  if (!unit) return;
  try {
    const res = await userAPI.index({ is_active: 1, unit });
    let list = (res.data.data || res.data).filter(u => (COUNSELING_ROLES[unit] || []).includes(u.role));
    if (list.length === 0) {
      const fallback = await userAPI.index({ is_active: 1 });
      list = (fallback.data.data || fallback.data).filter(u => (COUNSELING_ROLES[unit] || []).includes(u.role));
    }
    unitStaffList.value = list;
  } catch (e) {
    // Non-fatal - dropdown just stays empty.
  }
}

function openAssignModal() {
  assignForm.value = { to_user_id: '' };
  showAssignModal.value = true;
  loadUnitStaff(referral.value.case?.current_unit);
}

function openTransferModal() {
  transferError.value = '';
  transferForm.value = { to_unit: '', to_user_id: '', reason: '' };
  unitStaffList.value = [];
  showTransferModal.value = true;
}

async function assignCounselor() {
  if (!assignForm.value.to_user_id) {
    toast?.error('Please select a staff member.');
    return;
  }
  try {
    const res = await caseAPI.update(referral.value.case.id, { primary_counselor_id: assignForm.value.to_user_id });
    referral.value.case.counselor = res.data.counselor || unitStaffList.value.find(u => u.id === Number(assignForm.value.to_user_id));
    referral.value.case.primary_counselor_id = assignForm.value.to_user_id;
    showAssignModal.value = false;
    toast?.success('Case reassigned.');
  } catch (e) {
    toast?.error('Failed to reassign case.');
  }
}

async function transferUnit() {
  if (!transferForm.value.to_unit || !transferForm.value.to_user_id || !transferForm.value.reason) {
    transferError.value = 'Please fill in all fields.';
    return;
  }
  transferError.value = '';
  const previousUnit = referral.value.case.current_unit;
  try {
    const res = await caseAPI.handoff(referral.value.case.id, transferForm.value);
    referral.value.case.current_unit = res.data.current_unit;
    referral.value.case.handoffs = [
      ...(referral.value.case.handoffs || []),
      {
        id: Date.now(),
        from_unit: previousUnit,
        to_unit: transferForm.value.to_unit,
        reason: transferForm.value.reason,
        from_user: { name: auth.user?.name },
        to_user_id: Number(transferForm.value.to_user_id),
        to_user: unitStaffList.value.find(u => u.id === Number(transferForm.value.to_user_id)),
        acknowledged: false,
        created_at: new Date().toISOString(),
      },
    ];
    showTransferModal.value = false;
    toast?.success('Case transferred.');
  } catch (e) {
    transferError.value = e.response?.data?.message || 'Failed to transfer case.';
  }
}

async function confirmHandoffReceipt(handoff) {
  try {
    const res = await caseAPI.acknowledgeHandoff(referral.value.case.id, handoff.id);
    handoff.acknowledged = true;
    handoff.acknowledged_at = res.data.acknowledged_at;
    toast?.success('Receipt confirmed.');
  } catch (e) {
    toast?.error('Failed to confirm receipt.');
  }
}

async function markCaseAppointmentNoShow(a) {
  try {
    await appointmentAPI.escalateNoShow(a.id);
    a.status = 'no_show';
    toast?.success("Marked as no-show and escalated to Dean's Secretary.");
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark as no-show.');
  }
}

async function flagFollowUp() {
  followUpFlagError.value = '';
  if (!followUpDueDate.value) {
    followUpFlagError.value = 'Please select a follow-up due date.';
    return;
  }
  try {
    await caseAPI.flagFollowUp(referral.value.case.id, { notes: followUpFlagNotes.value, due_date: followUpDueDate.value });
    referral.value.case.requires_follow_up  = true;
    referral.value.case.follow_up_notes     = followUpFlagNotes.value;
    referral.value.case.follow_up_due_date  = followUpDueDate.value;
    showFollowUpFlagModal.value             = false;
    followUpDueDate.value                   = '';
    toast?.success('Case flagged for follow-up.');
  } catch (e) {
    followUpFlagError.value = e.response?.data?.message || 'Failed to flag case for follow-up.';
  }
}

async function resolveFollowUpFlag() {
  try {
    await caseAPI.resolveFollowUp(referral.value.case.id);
    referral.value.case.requires_follow_up = false;
    toast?.success('Follow-up flag cleared.');
  } catch (e) {
    toast?.error('Failed to clear follow-up flag.');
  }
}

function openFollowUpModal() {
  followUpError.value = '';
  followUpForm.value = { appointment_date: '', start_time: '', end_time: '', staff_user_id: '', notes: '' };
  showFollowUpModal.value = true;
}

async function saveFollowUp() {
  followUpError.value = '';
  if (!followUpForm.value.appointment_date || !followUpForm.value.start_time || !followUpForm.value.end_time) {
    followUpError.value = 'Please fill in the date and time.';
    return;
  }
  try {
    const res = await appointmentAPI.store({
      case_id:           referral.value.case.id,
      referral_id:       referral.value.id,
      student_id:        referral.value.student.id,
      staff_user_id:     followUpForm.value.staff_user_id || null,
      unit:              referral.value.case.current_unit || 'GCU',
      appointment_type:  'follow_up_session',
      appointment_date:  followUpForm.value.appointment_date,
      start_time:        followUpForm.value.start_time,
      end_time:          followUpForm.value.end_time,
      notes:             followUpForm.value.notes,
    });
    followUps.value.push(res.data);
    showFollowUpModal.value = false;
    toast?.success('Follow-up session scheduled.');
  } catch (e) {
    followUpError.value = e.response?.data?.message || 'Failed to schedule follow-up.';
  }
}

onMounted(async () => {
  try {
    const res = await referralAPI.show(route.params.id);
    referral.value = res.data;
    feedbackForm.value.feedback_notes = res.data.feedback_notes || '';

    newStatus.value = res.data.status || '';
    newCaseStatus.value = res.data.case?.status || '';
    if (isGCU.value) {
      const notesRes = await sessionNoteAPI.indexByReferral(route.params.id);
      sessionNotes.value = notesRes.data;

      try {
        const staffRes = await userAPI.index({ is_active: 1 });
        staffList.value = (staffRes.data.data || staffRes.data).filter(u => ['admin', 'gcu_staff'].includes(u.role));
      } catch (e) {
        // Non-fatal - staff dropdowns just fall back to empty.
      }
    }

    if (res.data.case) {
      const apptRes = await appointmentAPI.index({ referral_id: route.params.id, appointment_type: 'follow_up_session' });
      followUps.value = (apptRes.data.data || apptRes.data);
    }
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>