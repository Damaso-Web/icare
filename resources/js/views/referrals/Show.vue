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
    3. Edit / Archive buttons on the Referral Info panel hidden.
    4. Service Slips panel removed entirely.
    5. Summary strip's first cell now swaps label+value: "Referral Number"
       / REF-code when fromCases, "Case Number" / CASE-number otherwise.
    6. Added "On Observation" to the Update Case Status dropdown.
    7. Session Notes entries now show the session date in the header
       instead of the session type (e.g. "Session #1 - Sep 24, 2026").

  Follow-up Session (SIF only):
    "Flag Follow-up" (the case-level purple flag) has been removed entirely.
    Each scheduled follow-up in the Follow-up Session card is now clickable,
    opening a detail modal where GCU staff can view, add, and edit that
    follow-up's notes (saveFollowUpNotes -> appointmentAPI.update), the
    same way Previous Interventions/Session Notes work as click-to-view
    entries. "Schedule Follow-up" still opens its own in-page modal (not
    a link elsewhere) - it books through appointmentAPI.store with
    appointment_type: 'follow_up_session', so it lands in the student's
    appointments correctly labeled "Follow Up Session", and the modal now
    shows the Referral code (referral.referral_code) in its header so
    staff can see which referral the follow-up is tied to while booking.
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
          <button
            v-if="fromCases && (isGCU || (isSDUHead && referral.case.current_unit === 'SDU'))"
            class="ibtn ibtn-o ibtn-sm"
            @click="openTransferModal"
          >
            <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
            Endorse to Unit
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
          <div v-if="!isIncidentReport">
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

      <!-- Status Pipeline - full width, not confined to the left column -->
      <div class="icard" style="margin-bottom:16px">
        <div class="icard-header"><span class="icard-title">{{ isIncidentReport ? 'Incident Report Status' : 'Referral Status' }}</span></div>
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

          <!-- Incident Report - the whole filed Complaint plus its evidence,
               shown in place of Referral Info once a Complaint-originated
               referral (disciplinary, filed via ComplaintController::store())
               has been acknowledged. -->
          <div class="icard" v-if="isIncidentReport">
            <div class="icard-header">
              <span class="icard-title">Incident Report</span>
            </div>
            <div class="icard-body">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Complainant</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.complaint?.complainant_name || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Complainant Address</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.complaint?.complainant_address || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Complainee</div>
                  <div style="font-size:13px;color:var(--ink)">
                    {{ referral.complaint?.complainee?.last_name }}, {{ referral.complaint?.complainee?.first_name }}
                    <span style="color:var(--fog);font-family:var(--mono)">({{ referral.complaint?.complainee?.student_id }})</span>
                  </div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Complainee Position</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.complaint?.complainee_position || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College / Department / Office</div>
                  <div style="font-size:13px;color:var(--ink)">{{ [referral.complaint?.complainee_college, referral.complaint?.complainee_department, referral.complaint?.complainee_office].filter(Boolean).join(' / ') || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Complainee Address</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.complaint?.complainee_address || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Violation Type</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.complaint?.violation_type || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date of Incident</div>
                  <div style="font-size:13px;color:var(--ink)">{{ formatDate(referral.complaint?.incident_date) }}</div>
                </div>
              </div>
              <div style="margin-bottom:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Narration of Facts</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver);white-space:pre-line">{{ referral.complaint?.description || '-' }}</div>
              </div>
              <div v-if="referral.complaint?.attachments?.length">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">Evidence / Affidavit</div>
                <div style="display:flex;flex-direction:column;gap:6px">
                  <a
                    v-for="att in referral.complaint.attachments"
                    :key="att.id"
                    :href="attachmentUrl(att)"
                    target="_blank"
                    rel="noopener"
                    style="font-size:12.5px;color:var(--blue);text-decoration:underline;display:flex;align-items:center;gap:6px"
                  >
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    {{ att.original_filename }}
                    <span style="color:var(--fog);text-decoration:none">({{ toTitleCase(att.category) }})</span>
                  </a>
                </div>
              </div>
              <div v-if="referral.sanction" style="margin-top:14px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Sanction / Outcome</div>
                <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(referral.sanction) }}</div>
                <div v-if="referral.sanction_notes" style="font-size:12px;color:var(--slate);margin-top:4px;line-height:1.6">{{ referral.sanction_notes }}</div>
              </div>
            </div>
          </div>

          <!-- Referral Info (merged with the former "Referral Details" card) -->
          <div class="icard" v-if="!isIncidentReport">
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
                  <button
                    v-if="!fromCases"
                    class="ibtn ibtn-o ibtn-sm"
                    style="margin-top:6px"
                    @click="goToStudentInformationFile"
                  >
                    Go to Student Information File
                  </button>
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

              <!-- Handoff/endorsement history - just a record of what moved
                   where and why, no confirm-receipt step needed. Scoped to
                   this referral (see caseHandoffs computed) so a case that
                   carries more than one referral (e.g. a sibling TMDU
                   referral) doesn't leak the other referral's endorsements
                   in here. -->
              <div v-if="caseHandoffs.length">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">Handoff / Endorsement History</div>
                <div v-for="h in [...caseHandoffs].reverse()" :key="h.id" style="padding:8px 0;border-top:1px solid var(--cloud)">
                  <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ h.from_unit }} → {{ h.to_unit }}</div>
                  <div style="font-size:11px;color:var(--stone);margin-top:2px">By {{ h.from_user?.name || '-' }} to {{ h.to_user?.name || '-' }}</div>
                  <div v-if="h.reason" style="font-size:12px;color:var(--slate);margin-top:4px">{{ h.reason }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:2px">{{ formatDate(h.created_at) }}</div>
                </div>
              </div>
            </div>
          </div>


          <!-- Previous Interventions - case-level, append-only log. For Class Attendance referrals, this doubles as the admission slip: an Unexcused mark locks the referral.
               Student Information Files only. Replaced by Sanction/s Given +
               Detailed Report for an Incident Report. -->
          <div class="icard" v-if="referral.case && fromCases && !isIncidentReport">
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

          <!-- Sanction/s Given - Incident Report only. Case-level, append-only
               log reusing the CaseIntervention mechanism (type: 'sanction'),
               same pattern as Previous Interventions but without the
               excused/unexcused remarks or locking logic. -->
          <div class="icard" v-if="isIncidentReport && referral.case && fromCases">
            <div class="icard-header"><span class="icard-title">Sanction/s Given</span></div>
            <div class="icard-body">
              <div v-if="canManageIncidentReport" style="margin-bottom:16px">
                <label class="ifl">Sanction</label>
                <textarea v-model="sanctionForm.text" class="ifta" style="min-height:70px" placeholder="Describe the sanction given..."></textarea>
                <button class="ibtn ibtn-p ibtn-sm" style="margin-top:8px" @click="addSanction">Save Entry</button>
              </div>
              <div v-if="!sanctionsForReferral.length" style="font-size:13px;color:var(--stone)">No sanctions recorded yet.</div>
              <div v-else style="display:flex;flex-direction:column;gap:8px">
                <div
                  v-for="item in sanctionsForReferral"
                  :key="item.id"
                  style="padding:10px 12px;background:var(--snow);border-radius:var(--r-sm);border-left:2px solid var(--silver)"
                >
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                    <div style="font-size:13px;color:var(--ink)">{{ item.description }}</div>
                    <div style="font-size:11px;color:var(--fog);flex-shrink:0">{{ formatDate(item.created_at) }}</div>
                  </div>
                  <div style="font-size:10.5px;color:var(--fog);margin-top:3px">By {{ item.recorded_by?.name || item.person_in_charge?.name || '-' }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Detailed Report - Incident Report only. Same append-only log
               pattern as Session Notes, typed as 'detailed_report'. -->
          <div class="icard" v-if="isIncidentReport && referral.case && fromCases">
            <div class="icard-header"><span class="icard-title">Detailed Report</span></div>
            <div class="icard-body">
              <div v-if="canManageIncidentReport" style="margin-bottom:16px">
                <label class="ifl">Report Entry</label>
                <textarea v-model="detailedReportForm.text" class="ifta" style="min-height:80px" placeholder="Write a detailed report entry..."></textarea>
                <button class="ibtn ibtn-p ibtn-sm" style="margin-top:8px" @click="addDetailedReport">Save Entry</button>
              </div>
              <div v-if="!detailedReportsForReferral.length" style="font-size:13px;color:var(--stone)">No detailed report entries yet.</div>
              <div v-else style="display:flex;flex-direction:column;gap:8px">
                <div
                  v-for="item in detailedReportsForReferral"
                  :key="item.id"
                  style="padding:12px 14px;background:var(--snow);border-radius:var(--r-sm);border-left:2px solid var(--silver)"
                >
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">{{ formatDate(item.created_at) }}</div>
                  <div style="font-size:13px;color:var(--slate);line-height:1.6;white-space:pre-line;margin-top:4px">{{ item.description }}</div>
                  <div style="font-size:10.5px;color:var(--fog);margin-top:6px">By {{ item.recorded_by?.name || item.person_in_charge?.name || '-' }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Feedback Slip - copy sent to the referrer for transparency.
               Removed from the Referral Queue's own page (it only displays
               details); still available from Student Information Files.
               Removed entirely for an Incident Report. -->
          <div class="icard" v-if="fromCases && !isIncidentReport">
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

                <button class="ibtn ibtn-p ibtn-sm" style="margin-top:10px" @click="openFeedbackConfirm">
                  <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  Send to Referrer
                </button>
              </template>
              <template v-else>
                <div v-if="!referral.feedback_slips?.length" style="font-size:13px;color:var(--stone)">No feedback has been shared yet.</div>
              </template>
            </div>

            <!-- Sent history - like Session Notes, every send is kept as its
                 own permanent record here rather than being overwritten by
                 the next one. -->
            <div v-if="referral.feedback_slips?.length">
              <div v-for="slip in referral.feedback_slips" :key="slip.id" style="padding:14px 18px;border-top:1px solid var(--cloud)">
                <div v-if="slip.feedback_checklist?.length" style="font-size:12px;color:var(--stone);margin-bottom:8px">
                  <span v-for="key in slip.feedback_checklist" :key="key" class="ibadge" style="background:var(--mist);color:var(--moss);margin-right:4px;margin-bottom:4px">
                    {{ FEEDBACK_CHECKLIST_ITEMS.find(i => i.key === key)?.label }}
                  </span>
                </div>
                <div style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ slip.feedback_notes }}</div>
                <div style="font-size:11px;color:var(--fog);margin-top:8px">
                  Sent {{ formatDate(slip.sent_at) }} &middot; Recorded By: {{ slip.sent_by?.name || '-' }} &middot; Sent To: {{ slip.sent_to_name || '-' }}<span v-if="slip.sent_to_role">, {{ slip.sent_to_role }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- PAR Results - staff only, Student Information Files only.
               Surfaces the psychological assessment report once TMDU has
               issued it, without GCU needing to open the sibling
               psychological_testing referral's own Testing Record. -->
          <div class="icard" v-if="isGCU && fromCases && tmduTestingRecord && tmduTestingRecord.status === 'test_results_issued'">
            <div class="icard-header"><span class="icard-title">Psychological Assessment Report (PAR)</span></div>
            <div class="icard-body">
              <div v-if="tmduTestingRecord.tests_administered?.length" style="margin-bottom:12px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Psychological Tests Administered</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                  <span v-for="test in tmduTestingRecord.tests_administered" :key="test" class="ibadge" style="background:var(--mist);color:var(--moss)">{{ test }}</span>
                </div>
              </div>
              <div style="margin-bottom:12px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Assessment Summary</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ tmduTestingRecord.assessment_summary || '-' }}</div>
              </div>
              <div style="margin-bottom:12px">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Recommended Actions</div>
                <div style="font-size:13.5px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ tmduTestingRecord.recommendations || '-' }}</div>
              </div>
              <a v-if="parDocument" :href="attachmentUrl(parDocument)" target="_blank" class="ibtn ibtn-o ibtn-sm" style="margin-bottom:8px">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                {{ parDocument.original_filename || 'View PAR File' }}
              </a>
              <div style="font-size:11px;color:var(--fog)">
                Released by TMDU {{ formatDate(tmduTestingRecord.report_sent_at) }}<span v-if="tmduTestingRecord.tester?.name"> &middot; {{ tmduTestingRecord.tester.name }}</span>
              </div>
            </div>
          </div>

          <!-- Session Notes - staff only, Student Information Files only.
               Replaced by Detailed Report for an Incident Report. -->
          <div class="icard" v-if="isGCU && fromCases && !isIncidentReport">
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
                    Session #{{ note.session_number }} - {{ formatDate(note.session_date) }}
                  </div>
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

          <!-- Follow-up Session - Student Information Files only. Removed
               entirely for an Incident Report. -->
          <div class="icard" v-if="fromCases && !isIncidentReport">
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
              <div
                v-for="fu in followUps"
                :key="fu.id"
                style="padding:14px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:flex-start;gap:12px;cursor:pointer;transition:background .1s"
                @mouseover="$event.currentTarget.style.background='var(--foam)'"
                @mouseleave="$event.currentTarget.style.background='transparent'"
                @click="viewFollowUp(fu)"
              >
                <div>
                  <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ formatDate(fu.appointment_date) }} · {{ fu.start_time }}-{{ fu.end_time }}</div>
                  <div style="font-size:12px;color:var(--stone);margin-top:2px">With {{ fu.staff?.name || 'TBA' }}</div>
                  <div v-if="fu.notes" style="font-size:12px;color:var(--slate);margin-top:6px;background:var(--snow);padding:8px 10px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ fu.notes }}</div>
                  <div v-else style="font-size:11px;color:var(--fog);margin-top:6px;font-style:italic">No notes yet - click to add</div>
                  <div v-if="fu.created_by" style="font-size:10.5px;color:var(--fog);margin-top:4px">Recorded by {{ fu.created_by?.name }}</div>
                </div>
                <span class="ibadge" :class="'ibadge-' + fu.status">{{ toTitleCase(fu.status) }}</span>
              </div>
            </div>
          </div>

        </div>

        <!-- Right -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Acknowledge - visible to Admin and GCU Staff for ordinary
               referrals; a psychological_testing referral (the shared
               GCU<->TMDU referral created by "Refer to TMDU") is instead
               acknowledged by TMDU staff, since it's their side of the
               workflow. Acknowledging is a Referral Queue action; Case
               Files/Student Information Files opens this page read-only,
               so the prompt is hidden there. -->
          <div class="icard" v-if="referral.status === 'submitted' && canAcknowledge && !fromCases">
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

          <!-- Read-only status for roles that can't acknowledge this referral -->
          <div class="icard" v-else-if="referral.status === 'submitted' && !fromCases">
            <div class="icard-body">
              <div style="font-size:13px;color:var(--stone)">
                Awaiting acknowledgement from {{ referral.referral_type === 'psychological_testing' ? 'TMDU' : 'GCU' }}.
              </div>
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
              <button class="ibtn ibtn-g ibtn-sm" @click="showProfileModal = true">Profile</button>
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

          <!-- Student Profile Modal - shows the full profile (incl. the new
               Family/Siblings/Educational Attainment fields the student
               fills in on their own account page) without navigating away
               from the SIF. Family/Siblings/Education are collapsible
               dropdown groups, matching the "Related Concerns Comparison"
               pattern on the Student Profile page, since there can be a
               variable (and sometimes empty) amount of each. Read-only here -
               staff edit the base profile via the Student/Client module. -->
          <div v-if="showProfileModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showProfileModal = false">
            <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;display:flex;flex-direction:column">
              <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
                <div style="font-size:15px;font-weight:600;color:var(--ink)">Student Profile</div>
                <button class="ibtn ibtn-g ibtn-sm" @click="showProfileModal = false">✕</button>
              </div>
              <div style="padding:22px;display:flex;flex-direction:column;gap:14px;overflow-y:auto">

                <div style="display:flex;align-items:center;gap:10px">
                  <div class="qav" style="width:44px;height:44px;font-size:16px">
                    {{ initials(referral.student?.first_name, referral.student?.last_name) }}
                  </div>
                  <div>
                    <div style="font-size:14.5px;font-weight:600;color:var(--ink)">
                      {{ referral.student?.last_name }}, {{ referral.student?.first_name }} {{ referral.student?.middle_name }}
                    </div>
                    <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ referral.student?.student_id }}</div>
                  </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.college || '-' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.program || '-' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.year_level || '-' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.section || '-' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.sex || '-' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.contact_number || '-' }}</div>
                  </div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email</div>
                  <div style="font-size:13px;color:var(--ink)">{{ referral.student?.email || '-' }}</div>
                </div>

                <div style="height:1px;background:var(--cloud)"></div>

                <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Guardian</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Full Name</div>
                    <div style="font-size:13px;color:var(--ink)">
                      {{ [referral.student?.guardian_last_name, referral.student?.guardian_first_name].filter(Boolean).length
                          ? `${referral.student?.guardian_last_name || ''}, ${referral.student?.guardian_first_name || ''} ${referral.student?.guardian_middle_name || ''}`.trim()
                          : '-' }}
                    </div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Relationship</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.guardian_relationship || '-' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                    <div style="font-size:13px;color:var(--ink)">{{ referral.student?.guardian_contact || '-' }}</div>
                  </div>
                </div>

                <div style="height:1px;background:var(--cloud)"></div>

                <!-- Collapsible groups, same interaction as Related Concerns
                     Comparison: chevron rotates, count badge always visible. -->
                <div v-for="group in profileDetailGroups" :key="group.key">
                  <div
                    style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 0"
                    @click="toggleProfileGroup(group.key)"
                  >
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:var(--stone);fill:none;stroke-width:2;transition:transform .15s;flex-shrink:0" :style="{ transform: expandedProfileGroups[group.key] ? 'rotate(90deg)' : 'rotate(0deg)' }"><polyline points="9 18 15 12 9 6"/></svg>
                    <div style="font-size:12.5px;font-weight:700;color:var(--ink)">{{ group.label }}</div>
                    <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ group.count }}</span>
                  </div>
                  <div v-if="expandedProfileGroups[group.key]" style="padding:4px 0 10px 22px;display:flex;flex-direction:column;gap:10px">

                    <!-- Family Information -->
                    <template v-if="group.key === 'family'">
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Father's Name</div>
                          <div style="font-size:13px;color:var(--ink)">{{ fullName(referral.student?.father_last_name, referral.student?.father_first_name, referral.student?.father_middle_name) }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Father's Occupation</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.father_occupation || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Father's Contact</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.father_contact_number || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Mother's Name</div>
                          <div style="font-size:13px;color:var(--ink)">{{ fullName(referral.student?.mother_last_name, referral.student?.mother_first_name, referral.student?.mother_middle_name) }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Mother's Occupation</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.mother_occupation || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Mother's Contact</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.mother_contact_number || '-' }}</div>
                        </div>
                      </div>
                    </template>

                    <!-- Siblings Information -->
                    <template v-else-if="group.key === 'siblings'">
                      <div v-if="!profileSiblings.length" style="font-size:12.5px;color:var(--fog)">No siblings information provided.</div>
                      <div class="ts" v-else>
                        <table class="itable">
                          <thead><tr><th>Name</th><th>Age</th><th>Occupation / School</th></tr></thead>
                          <tbody>
                            <tr v-for="(s, i) in profileSiblings" :key="i">
                              <td style="font-size:12px">{{ fullName(s.last_name, s.first_name, s.middle_name) }}</td>
                              <td style="font-size:12px">{{ s.age || '-' }}</td>
                              <td style="font-size:12px">{{ s.occupation || '-' }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </template>

                    <!-- Educational Attainment -->
                    <template v-else-if="group.key === 'education'">
                      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Elementary</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.elementary_school || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Graduated</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.elementary_year_graduated || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">High School</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.high_school || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Graduated</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.high_school_year_graduated || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.college_school || '-' }}</div>
                        </div>
                        <div>
                          <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Graduated</div>
                          <div style="font-size:13px;color:var(--ink)">{{ referral.student?.college_year_graduated || '-' }}</div>
                        </div>
                      </div>
                    </template>

                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Case Action - Student Information Files only. Removed entirely
               for an Incident Report. -->
          <div class="icard" v-if="isGCU && referral.case && fromCases && !isIncidentReport">
            <div class="icard-header"><span class="icard-title">Case Action</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <router-link
                v-if="referral.status !== 'submitted'"
                :to="{ name: 'appointments', query: { return_to: route.fullPath } }"
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
                <!-- "Refer to TMDU" creates a separate, sibling Referral
                     (referral_type psychological_testing) on this same case
                     - see CaseController::referToTmdu(). This referral's own
                     testing_record field is never populated (that lives on
                     the sibling), so this checks tmduTestingRecord (the
                     sibling's testing record, same computed the PAR Results
                     card uses) instead. -->
                <button
                  v-if="!tmduTestingRecord || tmduTestingRecord.status === 'test_results_issued'"
                  class="ibtn ibtn-o"
                  style="width:100%;justify-content:center"
                  @click="openTmduModal"
                >
                  <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                  Refer to TMDU
                </button>
                <div v-else style="padding:8px 12px;background:var(--mist);border-radius:var(--r-sm);font-size:12px;color:var(--moss);text-align:center">
                  ✓ Already referred to TMDU
                </div>
                <button
                  v-if="!['completed', 'closed'].includes(referral.status)"
                  class="ibtn ibtn-p"
                  style="width:100%;justify-content:center"
                  @click="completeReferral"
                >
                  <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                  Complete Referral
                </button>
                <div v-else style="padding:8px 12px;background:var(--mist);border-radius:var(--r-sm);font-size:12px;color:var(--moss);text-align:center">
                  ✓ Referral completed
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


          <!-- Appointments (scoped to this referral only) - Student
               Information Files only. Removed entirely for an Incident
               Report. -->
          <div class="icard" v-if="referral.case && fromCases && !isIncidentReport">
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
              <textarea v-model="sessionForm.interventions" class="ifta" style="min-height:60px" maxlength="1000" placeholder="What interventions were applied?"></textarea>
            </div>
            <div>
              <label class="ifl">Remarks</label>
              <textarea v-model="sessionForm.next_steps" class="ifta" style="min-height:60px" maxlength="1000" placeholder="Remarks..."></textarea>
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
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--ink)">Schedule Follow-up Session</div>
              <div style="font-size:12px;color:var(--stone)">Referral {{ referral.referral_code }}</div>
            </div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showFollowUpModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Date <span style="color:var(--red)">*</span></label>
              <input v-model="followUpForm.appointment_date" type="date" class="ifi" :min="todayStr" />
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
              <textarea v-model="followUpForm.notes" class="ifta" style="min-height:60px" maxlength="1000" placeholder="What should this follow-up cover?"></textarea>
            </div>
            <div v-if="followUpError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ followUpError }}
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="openFollowUpConfirm">Schedule Follow-up</button>
              <button class="ibtn ibtn-o" @click="showFollowUpModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Schedule Follow-up Confirmation Modal -->
      <div v-if="showFollowUpConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:380px;padding:22px;text-align:center">
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Schedule this follow-up?</div>
          <div style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:18px">
            {{ formatDate(followUpForm.appointment_date) }} · {{ followUpForm.start_time }}–{{ followUpForm.end_time }}
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" style="flex:1;justify-content:center" :disabled="schedulingFollowUp" @click="saveFollowUp">
              {{ schedulingFollowUp ? 'Scheduling...' : 'Yes, Schedule' }}
            </button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="showFollowUpConfirm = false" :disabled="schedulingFollowUp">Cancel</button>
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

      <!-- Close Case Modal -->

      <!-- Send Feedback Confirmation Modal -->
      <div v-if="showFeedbackConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showFeedbackConfirm = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;padding:22px;text-align:center">
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Send Feedback Slip?</div>
          <div style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:18px">
            This will send the feedback slip to the referrer. Please review what you've written before confirming.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" style="flex:1;justify-content:center" :disabled="sendingFeedback" @click="sendFeedback">
              {{ sendingFeedback ? 'Sending...' : 'Yes, Send' }}
            </button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="showFeedbackConfirm = false" :disabled="sendingFeedback">Cancel</button>
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
              <button class="ibtn ibtn-o" @click="selectedIntervention = null">Close</button>
            </div>
          </div>
        </div>
      </div>

      <!-- View / Edit Follow-up Notes Modal -->
      <div v-if="selectedFollowUp" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeFollowUpDetail">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--ink)">Follow-up Session</div>
              <div style="font-size:12px;color:var(--stone)">{{ formatDate(selectedFollowUp.appointment_date) }} · {{ selectedFollowUp.start_time }}-{{ selectedFollowUp.end_time }}</div>
            </div>
            <button class="ibtn ibtn-g ibtn-sm" @click="closeFollowUpDetail">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Staff</div>
                <div style="font-size:13px;color:var(--ink)">{{ selectedFollowUp.staff?.name || 'TBA' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
                <span class="ibadge" :class="'ibadge-' + selectedFollowUp.status">{{ toTitleCase(selectedFollowUp.status) }}</span>
              </div>
            </div>
            <!-- This follow-up's own attendance gate - narrower than, and
                 separate from, the referral-wide one: only THIS follow-up's
                 notes are locked, and only until THIS follow-up's own
                 checked_in is true. Scheduling or attending it never
                 affects any other action on the SIF. -->
            <div v-if="isGCU && !selectedFollowUp.checked_in" style="background:var(--red-lt);border:1px solid var(--red);border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--red)">
              The student has not yet attended this follow-up session. Notes can be added once attendance is confirmed.
            </div>
            <div v-if="isGCU">
              <label class="ifl">Notes</label>
              <textarea v-model="followUpNotesForm" class="ifta" style="min-height:90px" maxlength="1000" placeholder="Add or update notes for this follow-up session..." :disabled="!selectedFollowUp.checked_in"></textarea>
            </div>
            <div v-else>
              <!-- B254: same presentation as Session Notes' Observations block -->
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Notes</div>
              <div v-if="selectedFollowUp.notes" style="font-size:13px;color:var(--slate);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ selectedFollowUp.notes }}</div>
              <div v-else style="font-size:13px;color:var(--stone)">No notes recorded yet.</div>
            </div>
            <div v-if="selectedFollowUp.created_by" style="font-size:11px;color:var(--fog)">Recorded by {{ selectedFollowUp.created_by?.name }}</div>
            <div v-if="isGCU" style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" @click="saveFollowUpNotes" :disabled="!selectedFollowUp.checked_in">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Save Notes
              </button>
              <button class="ibtn ibtn-o" @click="closeFollowUpDetail">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Refer to TMDU Modal: now a proper referral-slip form. Confirming
           creates a real, shared Referral (psychological_testing) under the
           same case, linked to a new TestingRecord - see
           CaseController::referToTmdu(). -->
      <div v-if="showTmduModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showTmduModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Referral for Psychological Testing</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showTmduModal = false">✕</button>
          </div>

          <!-- Document Code Header - read only, same pattern as the Referral
               Slip / Feedback Slip headers above. Revision No. / Effectivity /
               Ctrl No. are edited in Management by admin. -->
          <div style="padding:10px 22px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
            <div style="font-size:11px;color:var(--stone)">
              <div><strong>Document Code:</strong> QF-OSS-GCU-05</div>
              <div><strong>Revision No.:</strong> {{ tmduDoc.revision_no || '01' }}</div>
            </div>
            <div style="font-size:11px;color:var(--stone);text-align:right">
              <div><strong>Effectivity:</strong> {{ formatDocDate(tmduDoc.effectivity_date) }}</div>
              <div><strong>Ctrl No.:</strong> {{ tmduDoc.ctrl_no || '-' }}</div>
            </div>
          </div>

          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="background:var(--blue-lt);border:1px solid var(--blue);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--blue)">
              This creates a new referral for <strong>{{ referral.student?.last_name }}, {{ referral.student?.first_name }}</strong> under the same case, and moves it to TMDU for psychological assessment. That referral becomes shared between GCU and TMDU.
            </div>
            <div>
              <label class="ifl">Reason for Referral <span style="color:var(--red)">*</span></label>
              <textarea v-model="tmduForm.reason" class="ifta" style="min-height:90px" maxlength="1000" placeholder="Why is this student being referred for psychological testing?"></textarea>
            </div>
            <div v-if="tmduError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ tmduError }}
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-blue" :disabled="submittingTmdu" @click="referToTmdu">
                {{ submittingTmdu ? 'Referring...' : 'Refer to TMDU' }}
              </button>
              <button class="ibtn ibtn-o" @click="showTmduModal = false" :disabled="submittingTmdu">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Transfer Unit Modal -->
      <div v-if="showTransferModal && fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showTransferModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Endorse Case to Another Unit</div>
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
              <button class="ibtn ibtn-p" @click="transferUnit">Endorse</button>
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
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { referralAPI, sessionNoteAPI, caseAPI, appointmentAPI, userAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { toTitleCase } from '../../utils/validators';

const route   = useRoute();
const router  = useRouter();
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
const schedulingFollowUp = ref(false);

const showStatusModal      = ref(false);
const showTransferModal    = ref(false);
const newStatus             = ref('');
const unitStaffList         = ref([]);
const transferError         = ref('');

const selectedFollowUp  = ref(null);
const followUpNotesForm = ref('');

const transferForm = ref({ to_unit: '', to_user_id: '', reason: '' });
const interventionForm = ref({ text: '', excused: '', type: 'previous_intervention' });
const selectedIntervention = ref(null);

// Incident Report only - "Sanction/s Given" and "Detailed Report" reuse the
// same case-intervention log as Previous Interventions/Session Notes, just
// typed differently and without the excused/unexcused remarks.
const sanctionForm = ref({ text: '' });
const detailedReportForm = ref({ text: '' });


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

const interventionsForReferral = computed(() => {
  const all = referral.value.case?.interventions || [];
  return all.filter(i => i.referral_id === referral.value.id);
});

// A "Complaint" (Incident Report) is filed together with a disciplinary
// referral by ComplaintController::store(). Once that referral has been
// acknowledged, this page swaps its Referral-Info-style panels for the
// Incident Report ones (see the Sanction/s Given and Detailed Report cards
// below, and the Incident Report card replacing Referral Info).
const isIncidentReport = computed(() => !!referral.value.complaint && !!referral.value.acknowledged_at);

// Sanctions/detailed reports are SDU's domain, not GCU's - reuse the SDU
// Head + admin gate rather than isGCU.
const canManageIncidentReport = computed(() => isSDUHead.value || auth.user?.role === 'admin');

const sanctionsForReferral = computed(() =>
  interventionsForReferral.value.filter(i => i.type === 'sanction')
);
const detailedReportsForReferral = computed(() =>
  interventionsForReferral.value.filter(i => i.type === 'detailed_report')
);

function attachmentUrl(att) {
  return `${API_BASE.replace(/\/api$/, '')}/storage/${att.file_path}`;
}

const referralAppointments = computed(() => {
  const all = referral.value.case?.appointments || [];
  return all.filter(a => a.referral_id === referral.value.id);
});

// Handoffs made before the referral_id column existed can't be attributed
// to one referral, so they still show on every referral of the case (the
// old behavior); a handoff made from this point on is tagged with the
// referral it was actually endorsed from and only shows there.
const caseHandoffs = computed(() => {
  const all = referral.value.case?.handoffs || [];
  return all.filter(h => !h.referral_id || h.referral_id === referral.value.id);
});

// "Refer to TMDU" creates a separate, sibling Referral (referral_type
// psychological_testing) on this same case - see CaseController::referToTmdu().
// This referral's own SIF stays GCU's; the actual psychological testing
// workflow runs on the sibling referral's Testing Record. Once TMDU issues
// the PAR (status test_results_issued), GCU should be able to see the
// results here without having to go find that Testing Record themselves.
const tmduTestingRecord = computed(() => {
  const sibling = (referral.value.case?.referrals || [])
    .find(r => r.referral_type === 'psychological_testing' && r.testing_record);
  return sibling?.testing_record || null;
});

const parDocument = computed(() =>
  (tmduTestingRecord.value?.documents || [])
    .find(d => d.document_type === 'psychological_assessment_report') || null
);

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
  next_steps: '', student_showed_up: true,
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
const isTMDUStaff = computed(() => ['admin', 'tmdu_staff'].includes(auth.user?.role));

// Who can acknowledge THIS referral: GCU for ordinary referrals, TMDU staff
// for the shared psychological_testing referral created by "Refer to TMDU".
const canAcknowledge = computed(() =>
  referral.value.referral_type === 'psychological_testing' ? isTMDUStaff.value : isGCU.value
);

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
  { key: 'scheduled',   label: 'Scheduled' },
  { key: 'in_review',   label: 'In Review' },
  { key: 'in_progress', label: 'In Progress' },
  { key: 'completed',   label: 'Completed' },
];

const statusOrder = ['submitted', 'acknowledged', 'scheduled', 'in_review', 'in_progress', 'completed', 'closed'];

function isStepDone(key) {
  const current = statusOrder.indexOf(referral.value.status);
  const step    = statusOrder.indexOf(key);
  return step < current;
}

function isCurrentStep(key) {
  return referral.value.status === key;
}

function goToStudentInformationFile() {
  const studentId = referral.value.student?.id;
  if (!studentId) return;
  router.push({ name: 'student-show', params: { id: studentId }, query: { ctx: 'cases' } });
}

async function acknowledge() {
  acknowledging.value = true;
  try {
    const res = await referralAPI.acknowledge(referral.value.id);
    referral.value = { ...referral.value, ...res.data.referral };
    toast?.success('Acknowledged referral.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to acknowledge referral.');
  } finally {
    acknowledging.value = false;
  }
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

// Student Profile Modal - opens in place of navigating away to the
// Student Profile page (B-: "Profile" button used to redirect out of the
// SIF). Family/Siblings/Education collapse behind a dropdown toggle, same
// pattern as Related Concerns Comparison on the Student Profile page.
const showProfileModal = ref(false);
const expandedProfileGroups = ref({});
function toggleProfileGroup(key) {
  expandedProfileGroups.value = {
    ...expandedProfileGroups.value,
    [key]: !expandedProfileGroups.value[key],
  };
}

// siblings is stored as JSON on the backend; axios/the API may hand it back
// already parsed (array) or as a raw string depending on the DB driver, so
// handle both rather than assuming one shape.
const profileSiblings = computed(() => {
  const raw = referral.value.student?.siblings;
  if (!raw) return [];
  if (Array.isArray(raw)) return raw;
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
});

// Builds "Last, First Middle" the same way the existing Guardian block
// does, returning '-' when none of the name parts are filled in.
function fullName(last, first, middle) {
  if (![last, first, middle].filter(Boolean).length) return '-';
  return `${last || ''}, ${first || ''} ${middle || ''}`.trim();
}

const profileDetailGroups = computed(() => {
  const s = referral.value.student || {};
  const familyCount = [s.father_last_name, s.mother_last_name].filter(Boolean).length;
  const educationCount = [s.elementary_school, s.high_school, s.college_school].filter(Boolean).length;
  return [
    { key: 'family', label: 'Family Information', count: familyCount },
    { key: 'siblings', label: 'Siblings Information', count: profileSiblings.value.length },
    { key: 'education', label: 'Educational Attainment', count: educationCount },
  ];
});

// Document Code Headers - read only here. Revision No. / Effectivity / Ctrl
// No. for both the Referral Slip (QF-OSS-01) and the Feedback Slip
// (QF-OSS-03) are edited in Management by admin, not on individual referrals.
const referralDoc = ref({});
const feedbackDoc = ref({});
const tmduDoc      = ref({});
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
    // Mirrors ReferralController/SessionNoteController::storeByReferral()'s
    // server-side bump to "In Progress" - only move the badge forward here
    // too, so it doesn't visually override a status that's already moved on
    // (referred out, completed, closed) until the next full reload.
    if (['submitted', 'acknowledged', 'in_review', 'scheduled'].includes(referral.value.status)) {
      referral.value.status = 'in_progress';
    }
    showSessionModal.value = false;
    toast?.success('Session notes saved successfully.');
    sessionForm.value = {
      session_type: 'follow_up', observations: '', interventions: '',
      next_steps: '', student_showed_up: true,
    };
  } catch (e) {
    toast?.error('Failed to save session notes.');
  }
}

// B248: confirm before actually sending, since this notifies the referrer
// and can't be un-sent.
const showFeedbackConfirm = ref(false);
const sendingFeedback     = ref(false);

function openFeedbackConfirm() {
  if (!feedbackForm.value.feedback_notes) {
    toast?.error('Please write a feedback summary before sending.');
    return;
  }
  showFeedbackConfirm.value = true;
}

async function sendFeedback() {
  sendingFeedback.value = true;
  try {
    await referralAPI.sendFeedback(referral.value.id, feedbackForm.value);
    // B249: re-fetch the referral from the server instead of merging only the
    // response locally, so the "sent" record is guaranteed to reflect what
    // was actually persisted rather than an optimistic local update.
    const fresh = await referralAPI.show(referral.value.id);
    referral.value = fresh.data;
    // Each send is its own new history entry, not an edit of the last one -
    // clear the compose form so the next entry starts blank.
    feedbackForm.value = {
      feedback_notes: '',
      feedback_checklist: [],
      feedback_referred_other_text: '',
      feedback_others_text: '',
      feedback_ctrl_no: '',
    };
    showFeedbackConfirm.value = false;
    toast?.success('Feedback sent to referrer.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to send feedback.');
  } finally {
    sendingFeedback.value = false;
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

async function addSanction() {
  if (!sanctionForm.value.text) {
    toast?.error('Please describe the sanction given.');
    return;
  }
  try {
    const res = await caseAPI.addIntervention(referral.value.case.id, {
      referral_id: referral.value.id,
      type: 'sanction',
      description: sanctionForm.value.text,
    });
    if (!referral.value.case.interventions) referral.value.case.interventions = [];
    referral.value.case.interventions.unshift(res.data);
    sanctionForm.value = { text: '' };
    toast?.success('Sanction recorded.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to save sanction.');
  }
}

async function addDetailedReport() {
  if (!detailedReportForm.value.text) {
    toast?.error('Please write the report entry.');
    return;
  }
  try {
    const res = await caseAPI.addIntervention(referral.value.case.id, {
      referral_id: referral.value.id,
      type: 'detailed_report',
      description: detailedReportForm.value.text,
    });
    if (!referral.value.case.interventions) referral.value.case.interventions = [];
    referral.value.case.interventions.unshift(res.data);
    detailedReportForm.value = { text: '' };
    toast?.success('Report entry saved.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to save report entry.');
  }
}

async function updateStatus() {
  try {
    const res = await referralAPI.updateStatus(referral.value.id, { status: newStatus.value });
    referral.value.status = res.data.status;
    showStatusModal.value = false;
    toast?.success('Referral status updated.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to update status.');
  }
}

// One-click shortcut for the common case (mark this referral done) instead
// of having to open Update Status and pick "Completed" from the dropdown.
async function completeReferral() {
  if (!confirm('Mark this referral as completed?')) return;
  try {
    const res = await referralAPI.updateStatus(referral.value.id, { status: 'completed' });
    referral.value.status = res.data.status;
    newStatus.value = res.data.status;
    toast?.success('Referral marked as completed.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to complete referral.');
  }
}

// B255: Refer to TMDU now requires an actual filled-out reason instead of
// firing immediately with a hardcoded string.
const showTmduModal  = ref(false);
const submittingTmdu = ref(false);
const tmduError      = ref('');
const tmduForm       = ref({ reason: '' });

function openTmduModal() {
  tmduForm.value = { reason: '' };
  tmduError.value = '';
  showTmduModal.value = true;
}

async function referToTmdu() {
  if (!tmduForm.value.reason.trim()) {
    tmduError.value = 'Please provide a reason for the referral.';
    return;
  }
  submittingTmdu.value = true;
  try {
    const res = await caseAPI.referToTmdu(referral.value.case.id, { reason: tmduForm.value.reason });
    referral.value.case.current_unit     = 'TMDU';
    referral.value.case.status           = 'awaiting_testing';
    referral.value.case.referred_to_tmdu = true;
    // The response returns the sibling referral and its testing record as
    // separate top-level fields, not nested together - stitch them back
    // together and merge the referral into this case's referrals list so
    // tmduTestingRecord (and the "Already referred to TMDU" button state)
    // updates immediately instead of only after a full page reload.
    //
    // referral_was_reused tells us whether the backend reused an existing
    // open psychological_testing referral for this case instead of making a
    // new one (clicking "Refer to TMDU" more than once used to fork off a
    // duplicate referral every time) - replace that entry in place rather
    // than appending, so the list doesn't end up with a client-side
    // duplicate either.
    const newReferral = res.data?.referral;
    const newTestingRecord = res.data?.testing_record;
    const wasReused = !!res.data?.referral_was_reused;
    if (newReferral) {
      const merged = { ...newReferral, testing_record: newTestingRecord || null };
      const existingList = referral.value.case.referrals || [];
      referral.value.case.referrals = wasReused
        ? existingList.map(r => (r.id === newReferral.id ? merged : r))
        : [...existingList, merged];
    }
    showTmduModal.value = false;
    toast?.success(
      !newReferral ? 'Case referred to TMDU.'
      : wasReused ? `Updated the existing TMDU referral ${newReferral.referral_code}.`
      : `Referred to TMDU. New referral ${newReferral.referral_code} created.`
    );
  } catch (e) {
    tmduError.value = e.response?.data?.message || 'Failed to refer to TMDU.';
  } finally {
    submittingTmdu.value = false;
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
    // Tag the handoff with the referral it's actually being made from, so
    // it only shows up in THIS referral's history - not every other
    // referral sharing the same case (e.g. a sibling TMDU referral).
    const res = await caseAPI.handoff(referral.value.case.id, { ...transferForm.value, referral_id: referral.value.id });
    referral.value.case.current_unit = res.data.current_unit;
    referral.value.case.handoffs = [
      ...(referral.value.case.handoffs || []),
      {
        id: Date.now(),
        referral_id: referral.value.id,
        from_unit: previousUnit,
        to_unit: transferForm.value.to_unit,
        reason: transferForm.value.reason,
        from_user: { name: auth.user?.name },
        to_user_id: Number(transferForm.value.to_user_id),
        to_user: unitStaffList.value.find(u => u.id === Number(transferForm.value.to_user_id)),
        created_at: new Date().toISOString(),
      },
    ];
    showTransferModal.value = false;
    toast?.success('Case transferred.');
  } catch (e) {
    transferError.value = e.response?.data?.message || 'Failed to transfer case.';
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

function viewFollowUp(fu) {
  selectedFollowUp.value  = fu;
  followUpNotesForm.value = fu.notes || '';
}

function closeFollowUpDetail() {
  selectedFollowUp.value  = null;
  followUpNotesForm.value = '';
}

async function saveFollowUpNotes() {
  const fu = selectedFollowUp.value;
  if (!fu) return;
  try {
    const res = await appointmentAPI.update(fu.id, { notes: followUpNotesForm.value });
    const updated = res?.data || { ...fu, notes: followUpNotesForm.value };
    Object.assign(fu, updated);
    const idx = followUps.value.findIndex(f => f.id === fu.id);
    if (idx !== -1) followUps.value[idx] = { ...followUps.value[idx], ...updated };
    toast?.success('Follow-up notes saved.');
    closeFollowUpDetail();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to save follow-up notes.');
  }
}

// B252: never allow picking a day that's already gone.
const todayStr = new Date().toISOString().split('T')[0];

const showFollowUpConfirm = ref(false);

function openFollowUpModal() {
  followUpError.value = '';
  followUpForm.value = { appointment_date: '', start_time: '', end_time: '', staff_user_id: '', notes: '' };
  showFollowUpModal.value = true;
}

// B252: require an explicit confirmation step before actually booking.
function openFollowUpConfirm() {
  followUpError.value = '';
  if (!followUpForm.value.appointment_date || !followUpForm.value.start_time || !followUpForm.value.end_time) {
    followUpError.value = 'Please fill in the date and time.';
    return;
  }
  if (followUpForm.value.appointment_date < todayStr) {
    followUpError.value = 'Follow-up date cannot be in the past.';
    return;
  }
  showFollowUpConfirm.value = true;
}

async function saveFollowUp() {
  if (schedulingFollowUp.value) return; // guard against double-click / double-submit
  schedulingFollowUp.value = true;
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
    // Guard against the same follow-up landing in the list twice (e.g. if a
    // slow request resolves after a retry already added it).
    if (!followUps.value.some(f => f.id === res.data.id)) {
      followUps.value.push(res.data);
    }
    showFollowUpConfirm.value = false;
    showFollowUpModal.value = false;
    toast?.success('Follow-up session scheduled.');
  } catch (e) {
    followUpError.value = e.response?.data?.message || 'Failed to schedule follow-up.';
    showFollowUpConfirm.value = false;
  } finally {
    schedulingFollowUp.value = false;
  }
}

onMounted(async () => {
  try {
    const res = await referralAPI.show(route.params.id);
    referral.value = res.data;
    // The compose form always starts blank - each send is its own new
    // history entry (see referral.feedback_slips), never an edit of a
    // previous one, so there is nothing to pre-fill from.
    feedbackForm.value.feedback_ctrl_no = res.data.feedback_ctrl_no || '';

    newStatus.value = res.data.status || '';
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
  fetchDocSettings('QF-OSS-GCU-05', tmduDoc);
});
</script>