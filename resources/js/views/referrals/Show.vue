<!--
  FILE: resources/js/views/referrals/Show.vue
  PAGE: iCARE / Referral Details
  (Not to be confused with resources/js/views/students/Show.vue,
   which is the Student Profile page - different file, same filename.)

  Changed in this update, all gated on a new "fromCases" flag (true when
  this page was opened from Case Files, via ?ctx=cases):
    1. Header now shows the Case Number when fromCases, the Referral Code
       otherwise (see the "headerTitle" computed).
    2. Update Status / Update Case Status buttons hidden.
    3. Flag Follow-up button hidden.
    4. Edit / Archive buttons on the Referral Info panel hidden.
    5. Service Slips panel hidden.
    6. Summary strip's first cell now swaps label+value: "Referral Number"
       / REF-code when fromCases, "Case Number" / CASE-number otherwise.
    7. Added "On Observation" to the Update Case Status dropdown.
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
          <!-- In Case Files the case is the subject being browsed, so the case
               number is the page title and the referral code moves into the
               summary strip below. Elsewhere the referral stays the subject. -->
          <h1>{{ headerTitle }}</h1>
          <p>{{ referral.student?.last_name }}, {{ referral.student?.first_name }} {{ referral.student?.middle_name }} · {{ referral.student?.student_id }}</p>
        </div>
        <div v-if="(isGCU || isSDUHead) && referral.case" style="margin-left:auto;display:flex;gap:8px">
          <!-- Update Status is now a Student Information Files action - the
               Referral Queue only displays the details. -->
          <button v-if="isGCU && fromCases" class="ibtn ibtn-o ibtn-sm" @click="showStatusModal = true">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Status
          </button>
          <button v-if="isGCU && !fromCases" class="ibtn ibtn-o ibtn-sm" @click="showCaseStatusModal = true">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Case Status
          </button>
          <button
            v-if="fromCases && (isGCU || (isSDUHead && referral.case.current_unit === 'SDU'))"
            class="ibtn ibtn-o ibtn-sm"
            @click="openTransferModal"
          >
            <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            Transfer Unit
          </button>
          <button
            v-if="isGCU && fromCases && !referral.case.requires_follow_up"
            class="ibtn ibtn-sm"
            style="background:var(--purple-lt);color:var(--purple);border:1.5px solid var(--purple)"
            @click="showFollowUpFlagModal = true"
          >
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><path d="M4 22V4a2 2 0 0 1 2-2h9l5 5v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/></svg>
            Flag Follow-up
          </button>
          <button
            v-if="isGCU && fromCases && !referral.case.student_unreachable"
            class="ibtn ibtn-sm"
            style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)"
            @click="showUnreachableModal = true"
          >
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Flag Unreachable
          </button>
        </div>
      </div>

      <!-- Case Summary - Student Information Files only; the Referral Queue
           only displays the referral's own details. -->
      <div class="icard" v-if="referral.case && fromCases" style="margin-bottom:16px">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;padding:16px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">{{ fromCases ? 'Referral Number' : 'Case Number' }}</div>
            <div style="font-family:var(--mono);font-size:13px;color:var(--ink)">{{ fromCases ? referral.referral_code : referral.case.case_number }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Current Unit</div>
            <span class="ibadge" :class="'unit-' + referral.case.current_unit?.toLowerCase()">{{ referral.case.current_unit }}</span>
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

      <!-- Status Pipeline - full width, not confined to the left column -->
      <div class="icard" style="margin-bottom:16px">
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

      <div style="display:grid;grid-template-columns:1fr 340px;gap:16px">

        <!-- Left -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Referral Info (merged with the former "Referral Details" card) -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Referral Info</span>
            </div>

            <!-- Document Code Header - read only. Revision No. / Effectivity /
                 Ctrl No. are edited in Management by admin, not here. -->
            <div style="padding:10px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
              <div style="font-size:11px;color:var(--stone)">
                <div><strong>Document Code:</strong> QF-OSS-01</div>
                <div><strong>Revision No.:</strong> {{ referralDoc.revision_no || '01' }}</div>
              </div>
              <div style="font-size:11px;color:var(--stone);text-align:right">
                <div><strong>Effectivity:</strong> {{ formatDocDate(referralDoc.effectivity_date) }}</div>
                <div><strong>Ctrl No.:</strong> {{ referralDoc.ctrl_no || '-' }}</div>
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


          <!-- Previous Interventions - case-level, append-only log. For Class Attendance referrals, this doubles as the admission slip: an Unexcused mark locks the referral.
               Student Information Files only. -->
          <div class="icard" v-if="referral.case && fromCases">
            <div class="icard-header"><span class="icard-title">Previous Interventions</span></div>
            <div class="icard-body">
              <div style="font-size:11px;color:var(--stone);margin-bottom:10px;font-style:italic">For OSS Personnel</div>

              <!-- Add entry (GCU only) - Person-In-Charge is always the logged-in staff account; excused/unexcused only applies to Class Attendance referrals -->
              <div v-if="isGCU && !interventionLocked">
                <div :style="{ display:'grid', gridTemplateColumns: showExcusedRemarks ? '2fr 1fr' : '1fr', gap:'16px', alignItems:'start', marginBottom:'12px' }">
                  <div>
                    <label class="ifl">Intervention</label>
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

          <!-- Feedback Slip - copy sent to the referrer for transparency.
               Removed from the Referral Queue's own page (it only displays
               details); still available from Student Information Files. -->
          <div class="icard" v-if="fromCases">
            <div class="icard-header"><span class="icard-title">Feedback Slip</span></div>

            <!-- Document Code Header - read only. Revision No. / Effectivity /
                 Ctrl No. are edited in Management by admin. -->
            <div style="padding:10px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
              <div style="font-size:11px;color:var(--stone)">
                <div><strong>Document Code:</strong> QF-OSS-03</div>
                <div><strong>Revision No.:</strong> {{ feedbackDoc.revision_no || '01' }}</div>
              </div>
              <div style="font-size:11px;color:var(--stone);text-align:right">
                <div><strong>Effectivity:</strong> {{ formatDocDate(feedbackDoc.effectivity_date) }}</div>
                <div><strong>Ctrl No.:</strong> {{ feedbackDoc.ctrl_no || '-' }}</div>
              </div>
            </div>

            <div class="icard-body">
              <template v-if="isGCU">
                <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:8px">Intervention/s or Assistance Provided</div>
                <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
                  <label v-for="item in FEEDBACK_CHECKLIST_ITEMS" :key="item.key" style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--slate);cursor:pointer">
                    <input type="checkbox" :value="item.key" v-model="feedbackForm.feedback_checklist" style="width:15px;height:15px;accent-color:var(--moss)"/>
                    {{ item.label }}
                    <input
                      v-if="item.key === 'referred_other' && feedbackForm.feedback_checklist.includes('referred_other')"
                      v-model="feedbackForm.feedback_referred_other_text"
                      type="text"
                      placeholder="for interventions..."
                      style="flex:1;font-size:12px;padding:3px 6px;border:1px solid var(--cloud);border-radius:3px"
                    />
                    <input
                      v-if="item.key === 'others' && feedbackForm.feedback_checklist.includes('others')"
                      v-model="feedbackForm.feedback_others_text"
                      type="text"
                      placeholder="specify..."
                      style="flex:1;font-size:12px;padding:3px 6px;border:1px solid var(--cloud);border-radius:3px"
                    />
                  </label>
                </div>

                <label class="ifl">Remarks</label>
                <textarea v-model="feedbackForm.feedback_notes" class="ifta" placeholder="Progress / outcome summary to send to the referrer..."></textarea>

                <div style="font-size:11px;color:var(--fog);margin-top:10px">
                  Attending OSS Personnel: <strong style="color:var(--ink)">{{ auth.user?.name }}</strong>
                </div>

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
                  <div v-if="referral.feedback_checklist?.length" style="font-size:12px;color:var(--stone);margin-bottom:8px">
                    <span v-for="key in referral.feedback_checklist" :key="key" class="ibadge" style="background:var(--mist);color:var(--moss);margin-right:4px;margin-bottom:4px">
                      {{ FEEDBACK_CHECKLIST_ITEMS.find(i => i.key === key)?.label }}
                    </span>
                  </div>
                  <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ referral.feedback_notes }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:8px">Sent {{ formatDate(referral.feedback_sent_at) }} by {{ referral.feedback_sent_by?.name }}</div>
                </div>
              </template>
            </div>
          </div>

          <!-- Session Notes - staff only, Student Information Files only -->
          <div class="icard" v-if="isGCU && fromCases">
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
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Remarks</div>
                  <div style="font-size:13px;color:var(--slate)">{{ note.next_steps }}</div>
                </div>
                <div style="font-size:11px;color:var(--fog);margin-top:6px">Recorded by {{ note.recorded_by?.name }}</div>
              </div>
            </div>
          </div>

          <!-- Follow-up Session - Student Information Files only -->
          <div class="icard" v-if="fromCases">
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

          <!-- Acknowledge - visible to Admin and GCU Staff. Acknowledging is a Referral
               Queue action; Case Files/Student Information Files opens this page
               read-only, so the prompt is hidden there. -->
          <div class="icard" v-if="referral.status === 'submitted' && isGCU && !fromCases">
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
          <div class="icard" v-else-if="referral.status === 'submitted' && !fromCases">
            <div class="icard-body">
              <div style="font-size:13px;color:var(--stone)">Awaiting acknowledgement from GCU.</div>
            </div>
          </div>

          <!-- Acknowledged - stays visible on the Referral Queue page once the
               referral has moved past "submitted", instead of just vanishing. -->
          <div class="icard" v-else-if="!fromCases">
            <div class="icard-body">
              <div style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--moss);display:flex;align-items:center;gap:8px">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><polyline points="20 6 9 17 4 12"/></svg>
                Acknowledged{{ referral.acknowledged_at ? ' on ' + formatDate(referral.acknowledged_at) : '' }}
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

          <!-- Case Action - Student Information Files only -->
          <div class="icard" v-if="isGCU && referral.case && fromCases">
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
              <template v-if="fromCases">
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
              </template>
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

          <!-- Service Slips - now a Student Information Files action, like the
               other case actions; the Referral Queue only displays details. -->
          <div class="icard" v-if="isGCU && fromCases">
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

          <!-- Appointments (scoped to this referral only) - Student Information Files only -->
          <div class="icard" v-if="referral.case && fromCases">
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
      <div v-if="showSessionModal && isGCU && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showSessionModal = false">
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
              <label class="ifl">Student Showed Up</label>
              <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
                <input v-model="sessionForm.student_showed_up" type="checkbox" id="ref-showed" style="width:15px;height:15px;accent-color:var(--moss)" />
                <label for="ref-showed" style="font-size:13px;color:var(--slate);cursor:pointer">Yes, student attended</label>
              </div>
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
              <label class="ifl">Remarks</label>
              <textarea v-model="sessionForm.next_steps" class="ifta" style="min-height:60px" placeholder="Remarks..."></textarea>
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
      <div v-if="showFollowUpModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showFollowUpModal = false">
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

      <!-- Update Referral Status Modal - Student Information Files only -->
      <div v-if="showStatusModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showStatusModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Update Referral Status</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showStatusModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:8px">
            <select v-model="newStatus" class="ifse">
              <option value="submitted">Submitted</option>
              <option value="acknowledged">Acknowledged</option>
              <option value="in_review">In Review</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
            </select>
            <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="updateStatus">Save Status</button>
          </div>
        </div>
      </div>

      <!-- Update Case Status Modal -->
      <div v-if="showCaseStatusModal && !fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCaseStatusModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Update Case Status</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showCaseStatusModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:8px">
            <select v-model="newCaseStatus" class="ifse">
              <option value="open">Open</option>
              <option value="on_observation">On Observation</option>
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
      <div v-if="showUnreachableModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showUnreachableModal = false">
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
      <div v-if="showFollowUpFlagModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showFollowUpFlagModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Flag Case for Follow-up</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showFollowUpFlagModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="background:var(--purple-lt);border:1px solid var(--purple);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--purple)">
              This marks the case as requiring further attention so it stands out in the case list.
            </div>
            <div>
              <label class="ifl">Notes / Reason</label>
              <textarea v-model="followUpFlagNotes" class="ifta" placeholder="Why does this case need further attention?"></textarea>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn" style="background:var(--purple-lt);color:var(--purple);border:1.5px solid var(--purple)" @click="flagFollowUp">
                Flag for Follow-up
              </button>
              <button class="ibtn ibtn-o" @click="showFollowUpFlagModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Transfer Unit Modal -->
      <div v-if="showTransferModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showTransferModal = false">
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

    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, inject, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { referralAPI, sessionNoteAPI, caseAPI, appointmentAPI, userAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { toTitleCase } from '../../utils/validators';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();
const loading = ref(true);
const acknowledging = ref(false);
const referral = ref({});

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
const showTransferModal    = ref(false);
const newStatus             = ref('');
const newCaseStatus         = ref('');
const unreachableNotes      = ref('');
const followUpFlagNotes     = ref('');
const unitStaffList         = ref([]);
const transferError         = ref('');

const transferForm = ref({ to_unit: '', to_user_id: '', reason: '' });
const interventionForm = ref({ text: '', excused: '', type: 'previous_intervention' });
const selectedIntervention = ref(null);


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

const FEEDBACK_CHECKLIST_ITEMS = [
  { key: 'interview',            label: 'Interview' },
  { key: 'counseling',           label: 'Counseling' },
  { key: 'psychological_testing', label: 'Psychological Testing' },
  { key: 'referred_scholarship', label: 'Referred to Scholarship Sponsor/s' },
  { key: 'referred_other',       label: 'Referred to' },
  { key: 'others',               label: 'Others' },
];
const feedbackForm = ref({
  feedback_notes: '',
  feedback_checklist: [],
  feedback_referred_other_text: '',
  feedback_others_text: '',
  feedback_ctrl_no: '',
});

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

// Set by whichever module linked here (?ctx=cases from Case Files). This page is
// shared, so the flag decides whether it behaves as a live referral worksheet
// (Referral Queue) or as a read-only entry in a case history (Case Files).
const fromCases = computed(() => route.query.ctx === 'cases');

// Case Files leads with the case number; every other entry point leads with the
// referral code. Falls back gracefully while the record is still loading.
const headerTitle = computed(() => {
  if (fromCases.value && referral.value.case?.case_number) {
    return referral.value.case.case_number;
  }
  return referral.value.referral_code || 'Referral Details';
});

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

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

// Document Code Headers - read only here. Revision No. / Effectivity / Ctrl
// No. for both the Referral Slip (QF-OSS-01) and the Feedback Slip
// (QF-OSS-03) are edited in Management by admin, not on individual referrals.
const referralDoc = ref({});
const feedbackDoc = ref({});
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}
async function fetchDocSettings(code, target) {
  try {
    const res = await axios.get(`${API_BASE}/document-settings/${code}`, authHeaders());
    target.value = res.data;
  } catch (e) {
    console.error(e);
  }
}
function formatDocDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: '2-digit' });
}

async function logSession() {
  if (!sessionForm.value.interventions) {
    toast?.error('Please fill in the interventions applied.');
    return;
  }
  try {
    const now = new Date();
    const payload = {
      ...sessionForm.value,
      observations: sessionForm.value.interventions,
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

function openTransferModal() {
  transferError.value = '';
  transferForm.value = { to_unit: '', to_user_id: '', reason: '' };
  unitStaffList.value = [];
  showTransferModal.value = true;
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
  try {
    await caseAPI.flagFollowUp(referral.value.case.id, { notes: followUpFlagNotes.value });
    referral.value.case.requires_follow_up = true;
    referral.value.case.follow_up_notes    = followUpFlagNotes.value;
    showFollowUpFlagModal.value            = false;
    toast?.success('Case flagged for follow-up.');
  } catch (e) {
    toast?.error('Failed to flag case for follow-up.');
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
    feedbackForm.value.feedback_notes               = res.data.feedback_notes || '';
    feedbackForm.value.feedback_checklist            = res.data.feedback_checklist || [];
    feedbackForm.value.feedback_referred_other_text  = res.data.feedback_referred_other_text || '';
    feedbackForm.value.feedback_others_text          = res.data.feedback_others_text || '';
    feedbackForm.value.feedback_ctrl_no              = res.data.feedback_ctrl_no || '';

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
  fetchDocSettings('QF-OSS-01', referralDoc);
  fetchDocSettings('QF-OSS-03', feedbackDoc);
});
</script>