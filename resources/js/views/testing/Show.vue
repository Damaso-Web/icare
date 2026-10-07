<!--
  FILE: resources/js/views/testing/Show.vue
  PAGE: iCARE / Testing Record Details (TMDU)

  Mirrors the SIF-style layout of resources/js/views/referrals/Show.vue: a
  5-step status pipeline updated through action buttons, the original
  GCU-authored referral information (TestingRecord.referral - the same
  shared Referral row GCU filled out), Student Profile Details, and a
  "Reassign" action (adapted from the case-level Reassign Counselor pattern
  in students/Show.vue) that sets this record's Test Administrator instead
  of a case's counselor.

  Status bar: Pending | Scheduled for Testing | Test Administered |
  Awaiting Results | Results Released - these map 1:1 to the five
  testing_records.status values (pending, scheduled, test_administered,
  awaiting_results, test_results_issued). There is no fee-form/OR step:
  TMDU schedules the test-taking date directly once the referral is
  acknowledged. "Test Administered" IS its own resting stage now - saving
  "Psychological Tests Administered" stops there; moving on to "Awaiting
  Results" is a separate action (Schedule PAR Release), and the backend
  only allows that once the student has attended the test-taking
  appointment (marked from the "Manage Queue" appointments page).

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
            <div class="icard-header"><span class="icard-title">Case Referral Slip</span></div>

            <!-- TMDU form header - read only (edited in Management). -->
            <div style="padding:10px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
              <div style="font-size:11px;color:var(--stone)">
                <div><strong>Document Code:</strong> QF-OSS-GCU-05</div>
                <div><strong>Revision No.:</strong> {{ tmduDoc.revision_no || '01' }}</div>
              </div>
              <div style="font-size:11px;color:var(--stone);text-align:right">
                <div><strong>Effectivity:</strong> {{ formatDocDate(tmduDoc.effectivity_date || '2023-07-04') }}</div>
                <div><strong>Ctrl No.:</strong> {{ tmduDoc.ctrl_no || '26-1' }}</div>
              </div>
            </div>
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
               bar from Scheduled for Testing to Test Administered. -->
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
                {{ saving ? 'Saving...' : 'Save Tests Administered' }}
              </button>
            </div>
          </div>

          <!-- Schedule PAR Release - the action that moves the bar from Test
               Administered to Awaiting Results. The backend only allows this
               once the student has attended the test-taking appointment
               (marked "Student Attended" from Manage Queue below), so the
               button surfaces that 422 as a plain error if clicked early. -->
          <div class="icard" v-if="canManage && stage === 'test_administered'">
            <div class="icard-header"><span class="icard-title">Schedule PAR Release</span></div>
            <!-- TMDU Appointment Slip header - read only (edited in Management > Document Headers > TMDU). -->
            <div style="padding:10px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
              <div style="font-size:11px;color:var(--stone)">
                <div><strong>Document Code:</strong> QF-TMDU-02</div>
                <div><strong>Revision No.:</strong> {{ slipDoc.revision_no || '00' }}</div>
              </div>
              <div style="font-size:11px;color:var(--stone);text-align:right">
                <div><strong>Effectivity:</strong> {{ formatDocDate(slipDoc.effectivity_date || '2022-09-02') }}</div>
                <div><strong>Ctrl No.:</strong> {{ slipDoc.ctrl_no || '-' }}</div>
              </div>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <div v-if="!testTakingAttended" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--amber)">
                The student must be marked as attended on the test-taking appointment (Manage Queue) before PAR release can be scheduled.
              </div>
              <div>
                <input v-model="parScheduleForm.appointment_date" type="date" class="ifi" style="width:100%" />
                <div v-if="parDateError" style="color:var(--red);font-size:11px;margin-top:4px">{{ parDateError }}</div>
              </div>
              <div>
                <div style="display:flex;gap:8px">
                  <input v-model="parScheduleForm.start_time" type="time" min="08:00" max="16:00" class="ifi" style="flex:1" />
                  <input v-model="parScheduleForm.end_time" type="time" min="08:00" max="16:00" class="ifi" style="flex:1" />
                </div>
                <div v-if="parTimeError" style="color:var(--red);font-size:11px;margin-top:4px">{{ parTimeError }}</div>
              </div>
              <button class="ibtn ibtn-p ibtn-sm" style="align-self:flex-start" @click="confirmSchedulePar" :disabled="saving || parFormInvalid">
                {{ saving ? 'Scheduling...' : 'Schedule PAR Release' }}
              </button>
            </div>
          </div>

          <!-- Attach Psychological Assessment Records (PAR) - the action
               that moves the bar from Awaiting Results to Results Released. -->
          <div class="icard" v-if="canManage && stage === 'awaiting_results'">
            <div class="icard-header"><span class="icard-title">Attach Psychological Assessment Records (PAR)</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <!-- PAR copy pickup is face-to-face, not handled by the system -
                   this is just an indication of whether the student came for
                   their printed copy, taken from the PAR release appointment. -->
              <div v-if="!parReleased" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--amber)">
                🔒 PAR results can only be attached after the scheduled PAR release is marked <strong>Results Released</strong> (open the PAR Release appointment below).
                <span v-if="parReleaseAppointment?.on_hold_at"> The release is currently on hold.</span>
              </div>
              <div v-if="parReleaseAppointment?.status === 'no_show'" style="background:var(--red-lt);border:1px solid #f0a8a8;border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--red)">
                ⚠ PAR copy held — the student did not come for the scheduled face-to-face release on {{ formatDate(parReleaseAppointment.appointment_date) }}.
              </div>
              <div v-else-if="parReleased" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--moss)">
                ✓ PAR copy released to the student in person on {{ formatDate(parReleaseAppointment.appointment_date) }}.
              </div>
              <div v-else-if="parReleaseAppointment" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:8px 12px;font-size:12px;color:var(--amber)">
                PAR release scheduled for {{ formatDate(parReleaseAppointment.appointment_date) }} — not yet marked attended or no-show.
              </div>
              <div>
                <label class="ifl">PAR / Result File</label>
                <input type="file" class="ifi" accept=".pdf,.doc,.docx,.jpg,.png" :disabled="!parReleased" @change="handleFileUpload" />
                <div v-if="selectedFile" style="font-size:12px;color:var(--moss);margin-top:4px">✓ {{ selectedFile.name }}</div>
              </div>
              <div>
                <label class="ifl">Assessment Summary</label>
                <textarea v-model="parForm.assessment_summary" class="ifta" placeholder="Summarize the assessment results..." maxlength="3000" :disabled="!parReleased"></textarea>
              </div>
              <div>
                <label class="ifl">Recommended Actions</label>
                <textarea v-model="parForm.recommendations" class="ifta" placeholder="Recommended actions based on the assessment..." maxlength="3000" :disabled="!parReleased"></textarea>
              </div>
              <button class="ibtn ibtn-blue ibtn-sm" style="align-self:flex-start" @click="attachPar" :disabled="saving || !parReleased">
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

          <!-- Acknowledge - shown right here on Testing Record Details so
               TMDU never has to go to the Referral Queue first. Handles both
               shapes: a record linked to a real, shared Referral row (the
               normal case, via CaseController::referToTmdu()) calls
               referralAPI.acknowledge() - the same endpoint Referral Queue's
               own Acknowledge button uses - and a legacy record with no
               referral_id falls back to testingAPI.acknowledge(). Neither
               changes the TestingRecord's own status (it stays "pending"
               throughout); the Referral's acknowledged_at is what actually
               unlocks Schedule Test Taking below. -->
          <div class="icard" v-if="canManage && record.referral_id && !record.referral?.acknowledged_at && record.status === 'pending'">
            <div class="icard-body">
              <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber);margin-bottom:12px">
                ⚠ This testing referral has not been acknowledged yet.
              </div>
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="acknowledgeLinkedReferral" :disabled="saving">
                {{ saving ? 'Acknowledging...' : 'Acknowledge Testing Referral' }}
              </button>
            </div>
          </div>

          <!-- Legacy fallback acknowledge for records with no referral_id -->
          <div class="icard" v-if="canManage && !record.referral_id && record.status === 'pending' && !record.acknowledged">
            <div class="icard-body">
              <div style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber);margin-bottom:12px">
                ⚠ This testing referral has not been acknowledged yet.
              </div>
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="acknowledgeReferral" :disabled="saving">
                {{ saving ? 'Acknowledging...' : 'Acknowledge Testing Referral' }}
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
              <router-link :to="{ name: 'testing', query: { student_id: record.student?.id } }" class="ibtn ibtn-g ibtn-sm">Testing Records</router-link>
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

          <!-- Schedule Test Taking - available once the referral is
               acknowledged. Acknowledging no longer changes the
               TestingRecord's own status (it stays "pending" both before
               and after - see TestingRecordController::acknowledge()), so
               for a record linked to a real Referral this also requires
               referral.acknowledged_at to be set; a legacy record with no
               referral_id falls back to status alone. No fee-form/OR step -
               TMDU decides the date/time directly. -->
          <div class="icard" v-if="canManage && record.status === 'pending' && (record.referral ? !!record.referral.acknowledged_at : true)">
            <div class="icard-header"><span class="icard-title">Schedule Test Taking</span></div>
            <!-- TMDU Appointment Slip header - read only (edited in Management > Document Headers > TMDU). -->
            <div style="padding:10px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center;background:var(--snow)">
              <div style="font-size:11px;color:var(--stone)">
                <div><strong>Document Code:</strong> QF-TMDU-02</div>
                <div><strong>Revision No.:</strong> {{ slipDoc.revision_no || '00' }}</div>
              </div>
              <div style="font-size:11px;color:var(--stone);text-align:right">
                <div><strong>Effectivity:</strong> {{ formatDocDate(slipDoc.effectivity_date || '2022-09-02') }}</div>
                <div><strong>Ctrl No.:</strong> {{ slipDoc.ctrl_no || '-' }}</div>
              </div>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <div>
                <input v-model="testingForm.appointment_date" type="date" class="ifi" style="width:100%" />
                <div v-if="testingDateError" style="color:var(--red);font-size:11px;margin-top:4px">{{ testingDateError }}</div>
              </div>
              <div>
                <div style="display:flex;gap:8px">
                  <input v-model="testingForm.start_time" type="time" min="08:00" max="16:00" class="ifi" style="flex:1" />
                  <input v-model="testingForm.end_time" type="time" min="08:00" max="16:00" class="ifi" style="flex:1" />
                </div>
                <div v-if="testingTimeError" style="color:var(--red);font-size:11px;margin-top:4px">{{ testingTimeError }}</div>
              </div>
              <button class="ibtn ibtn-p ibtn-sm" @click="confirmScheduleTesting" :disabled="saving || testingFormInvalid">
                {{ saving ? 'Scheduling...' : 'Confirm Testing Schedule' }}
              </button>
            </div>
          </div>

          <!-- Appointments - TMDU-side appointments tied to this testing
               record's case. Schedule Test Taking / Schedule PAR Release
               above are the only way to create these for a testing case -
               TMDU sets the date/time directly with the student face-to-face,
               so there's nothing for the student to self-schedule or for
               TMDU to "confirm" separately. They're created as "Pending"
               and stay that way until the actual meeting happens, at which
               point TMDU marks it here (No-Show) or moves the record's
               status forward on its own scheduled outcome. This panel just
               lists what those two actions have created; the "Manage Queue"
               link is a read-only overview across all TMDU appointments. -->
          <div class="icard" v-if="record.case_id">
            <div class="icard-header">
              <span class="icard-title">Appointments</span>
              <router-link
                v-if="canManage"
                :to="{ name: 'testing-appointments', query: { return_to: route.fullPath } }"
                class="ibtn ibtn-g ibtn-sm"
              >
                Manage Queue
              </router-link>
            </div>
            <div v-if="!tmduAppointments.length" class="empty-state">
              <h3>No appointments yet</h3>
              <p>No TMDU appointments have been scheduled for this testing case.</p>
            </div>
            <div v-else>
              <div
                v-for="a in tmduAppointments"
                :key="a.id"
                style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;flex-direction:column;gap:6px;cursor:pointer;transition:background .1s"
                @mouseover="$event.currentTarget.style.background='var(--foam)'"
                @mouseleave="$event.currentTarget.style.background='transparent'"
                @click="openApptDetail(a)"
              >
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
                  v-if="canManage && a.status === 'pending'"
                  class="ibtn ibtn-sm"
                  style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber);align-self:flex-start"
                  @click.stop="openNoShowModal(a)"
                >
                  No-Show
                </button>
                <span v-if="a.on_hold_at && a.status !== 'completed'" class="ibadge" style="background:var(--amber-lt);color:var(--amber);align-self:flex-start">On Hold</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Appointment Details floating modal - same pattern as the TMDU
           Appointments page: click a card to see it and act on it here. -->
      <div v-if="apptDetail" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="apptDetail = null">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:460px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ toTitleCase(apptDetail.appointment_type) }}</div>
              <div style="font-size:12px;color:var(--stone)">{{ formatDate(apptDetail.appointment_date) }} · {{ apptDetail.start_time }} - {{ apptDetail.end_time }}</div>
            </div>
            <button class="ibtn ibtn-g ibtn-sm" @click="apptDetail = null">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Staff</div>
                <div style="font-size:13px;color:var(--ink)">{{ apptDetail.staff?.name || 'TBA' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
                <span class="ibadge" :class="'ibadge-' + apptDetail.status">{{ toTitleCase(apptDetail.status) }}</span>
                <span v-if="apptDetail.on_hold_at && apptDetail.status !== 'completed'" class="ibadge" style="background:var(--amber-lt);color:var(--amber);margin-left:4px">On Hold</span>
              </div>
            </div>
            <div v-if="apptDetail.required_documents">
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Required Documents</div>
              <div style="font-size:13px;color:var(--ink);background:var(--snow);padding:8px 10px;border-radius:var(--r-sm)">{{ apptDetail.required_documents }}</div>
            </div>
            <div v-if="canManage && !['cancelled','completed'].includes(apptDetail.status)" style="display:flex;gap:8px;flex-wrap:wrap;border-top:1px solid var(--cloud);padding-top:14px">
              <template v-if="apptDetail.appointment_type === 'par_release'">
                <button class="ibtn ibtn-p ibtn-sm" :disabled="parBusy" @click="doParAction(apptDetail, 'released')">Results Released</button>
                <button class="ibtn ibtn-sm" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" :disabled="parBusy || !!apptDetail.on_hold_at" @click="doParAction(apptDetail, 'on_hold')">On Hold</button>
                <button class="ibtn ibtn-sm" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" :disabled="parBusy" @click="doParAction(apptDetail, 'cancel')">Cancel</button>
              </template>
              <template v-else-if="apptDetail.status === 'pending'">
                <button class="ibtn ibtn-o ibtn-sm" :disabled="parBusy" @click="markAttended(apptDetail)">Student Attended</button>
                <button class="ibtn ibtn-sm" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click="openNoShowModal(apptDetail); apptDetail = null">No-Show</button>
              </template>
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
              <select v-model.number="assignForm.to_user_id" class="ifse" :disabled="assignLoading">
                <option value="" disabled>{{ assignLoading ? 'Loading...' : 'Select TMDU staff member...' }}</option>
                <option v-for="u in assignStaffList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" :disabled="assigning || assignLoading" @click="assignTester">{{ assigning ? 'Assigning...' : 'Assign' }}</button>
              <button class="ibtn ibtn-o" @click="showAssignModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- No-Show Modal - GCU's own call on Call Slip vs. just marking it,
           same as appointments/Index.vue, not an automatic threshold. -->
      <div v-if="showNoShowModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showNoShowModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Mark as No-Show</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="font-size:13px;color:var(--slate);line-height:1.6">
              Marking this appointment as a no-show.
              <span v-if="record.case?.no_show_count">This case has {{ record.case.no_show_count }} prior no-show{{ record.case.no_show_count > 1 ? 's' : '' }}.</span>
              Choose how to handle it:
            </div>
            <div v-if="callSlipConfirming" style="border:1px solid #f0a8a8;background:var(--red-lt);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:10px">
              <div style="font-size:13px;color:var(--ink);line-height:1.5"><strong>Send Call-Slip?</strong> This escalates the appointment to the Dean's Secretary. A Call-Slip can only be sent <strong>once</strong> and cannot be undone.</div>
              <div style="display:flex;gap:8px">
                <button class="ibtn" style="flex:1;justify-content:center;background:#fff;color:var(--red);border:1.5px solid #f0a8a8" :disabled="submittingNoShow" @click="submitNoShow('call_slip')">{{ submittingNoShow ? 'Sending...' : 'Yes, Send Call-Slip' }}</button>
                <button class="ibtn ibtn-o" style="flex:1;justify-content:center" :disabled="submittingNoShow" @click="callSlipConfirming = false">Go Back</button>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px">
              <button v-if="!callSlipConfirming" class="ibtn" style="justify-content:center;background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" :disabled="submittingNoShow" @click="submitNoShow('reschedule')">Mark & Ask Student to Reschedule</button>
              <button v-if="!noShowTarget?.no_show_escalated && !callSlipConfirming" class="ibtn" style="justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f0a8a8" @click="callSlipConfirming = true">Issue Call Slip (Escalate to Dean's Secretary)</button>
              <button class="ibtn ibtn-o" style="justify-content:center" @click="showNoShowModal = false">Never Mind</button>
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
import axios from 'axios';
import { testingAPI, userAPI, appointmentAPI, referralAPI } from '../../api/index';
import { toTitleCase } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();

const loading         = ref(true);
const saving          = ref(false);
const record          = ref({});
const selectedFile    = ref(null);

const testingForm     = ref({ appointment_date: '', start_time: '', end_time: '' });
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

const canManage = computed(() => auth.user?.role === 'tmdu_staff');

// Maps the DB-level testing_records.status values onto the 5 bar stages.
// The current flow is a straight 1:1 mapping; the old collapsed values
// (fee_form_pending, or_submitted, in_progress, par_scheduled) are kept
// here purely for backward compatibility with any pre-existing records,
// since the controller no longer writes them.
function stageOf(status) {
  if (status === 'scheduled' || status === 'in_progress') return 'scheduled_for_testing';
  if (status === 'test_administered') return 'test_administered';
  if (status === 'awaiting_results' || status === 'par_scheduled') return 'awaiting_results';
  if (status === 'test_results_issued') return 'results_released';
  return 'pending'; // pending, fee_form_pending, or_submitted (legacy)
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
  return key === stage.value;
}

// Reassign as Test Administrator - adapted from the case-level Reassign
// Counselor pattern in students/Show.vue, targeting assigned_tester_user_id.
const TMDU_ROLES = ['admin', 'tmdu_staff'];
const showAssignModal = ref(false);
const assignForm      = ref({ to_user_id: '' });
const assignStaffList = ref([]);
const assignLoading   = ref(false);
const assigning       = ref(false);

async function loadAssignStaff() {
  assignStaffList.value = [];
  assignLoading.value = true;
  try {
    const res = await testingAPI.availableTesters();
    assignStaffList.value = (res.data.data || res.data).filter(u => TMDU_ROLES.includes(u.role));
  } catch (e) {
    toast?.error('Could not load staff list.');
  } finally {
    assignLoading.value = false;
  }
}

async function openAssignModal() {
  await loadAssignStaff();
  assignForm.value = { to_user_id: record.value.assigned_tester_user_id || '' };
  showAssignModal.value = true;
}

async function assignTester() {
  if (!assignForm.value.to_user_id) {
    toast?.error('Please select a staff member.');
    return;
  }
  if (assigning.value) return;
  assigning.value = true;
  try {
    const id = Number(assignForm.value.to_user_id);
    const res = await testingAPI.assign(record.value.id, { tester_user_id: id });
    record.value.tester = res.data.tester || assignStaffList.value.find(u => u.id === id);
    record.value.assigned_tester_user_id = id;
    showAssignModal.value = false;
    toast?.success('Test administrator reassigned.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to reassign test administrator.');
  } finally {
    assigning.value = false;
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

// TMDU office hours - both scheduling panels below share these rules.
// Weekday-only, 8:00 AM-4:00 PM, checked live as the fields change (not just
// when the schedule button is clicked).
function isWeekend(dateStr) {
  if (!dateStr) return false;
  const day = new Date(dateStr + 'T00:00:00').getDay();
  return day === 0 || day === 6;
}

function dateErrorFor(dateStr) {
  if (!dateStr) return '';
  return isWeekend(dateStr) ? 'TMDU is closed on weekends - please choose a weekday (Monday-Friday).' : '';
}

function timeErrorFor(startTime, endTime) {
  if (!startTime || !endTime) return '';
  if (startTime < '08:00' || endTime > '16:00') return 'Appointments must be scheduled between 8:00 AM and 4:00 PM.';
  if (startTime >= endTime) return 'End time must be after start time.';
  return '';
}

const testingDateError = computed(() => dateErrorFor(testingForm.value.appointment_date));
const testingTimeError = computed(() => timeErrorFor(testingForm.value.start_time, testingForm.value.end_time));
const testingFormInvalid = computed(() => !!testingDateError.value || !!testingTimeError.value);

const parDateError = computed(() => dateErrorFor(parScheduleForm.value.appointment_date));
const parTimeError = computed(() => timeErrorFor(parScheduleForm.value.start_time, parScheduleForm.value.end_time));
const parFormInvalid = computed(() => !!parDateError.value || !!parTimeError.value);

async function confirmScheduleTesting() {
  if (!testingForm.value.appointment_date || !testingForm.value.start_time || !testingForm.value.end_time) {
    toast?.error('Please fill in the date and time.');
    return;
  }
  if (testingFormInvalid.value) {
    toast?.error(testingDateError.value || testingTimeError.value);
    return;
  }
  saving.value = true;
  try {
    await testingAPI.scheduleTesting(record.value.id, testingForm.value);
    toast?.success('Testing appointment scheduled. Student has been notified to bring 2 pencils and arrive 15 minutes early.');
    await loadRecord();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to schedule testing appointment.');
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
    toast?.success('Tests administered recorded.');
    await loadRecord();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to save tests administered.');
  } finally {
    saving.value = false;
  }
}

async function confirmSchedulePar() {
  if (!parScheduleForm.value.appointment_date || !parScheduleForm.value.start_time || !parScheduleForm.value.end_time) {
    toast?.error('Please fill in the date and time.');
    return;
  }
  if (parFormInvalid.value) {
    toast?.error(parDateError.value || parTimeError.value);
    return;
  }
  saving.value = true;
  try {
    await testingAPI.schedulePar(record.value.id, parScheduleForm.value);
    toast?.success('PAR release appointment scheduled. Student has been notified.');
    await loadRecord();
  } catch (e) {
    // Surfaces the schedulePar() attendance-gate 422 ("The student must
    // have attended the scheduled test-taking appointment...") along with
    // any other backend validation error.
    toast?.error(e.response?.data?.message || 'Failed to schedule PAR release.');
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
    toast?.error(e.response?.data?.message || 'Failed to attach PAR.');
  } finally {
    saving.value = false;
  }
}

async function acknowledgeReferral() {
  saving.value = true;
  try {
    await testingAPI.acknowledge(record.value.id);
    await loadRecord();
    toast?.success('Referral acknowledged. You can now schedule test taking.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to acknowledge referral.');
  } finally {
    saving.value = false;
  }
}

// Same action as Referral Queue's own "Acknowledge" button, just reachable
// from here too - acknowledges the actual shared Referral row (not the
// TestingRecord directly). This sets referral.acknowledged_at, which is
// what unlocks Schedule Test Taking above (the TestingRecord's own status
// stays "pending" throughout).
async function acknowledgeLinkedReferral() {
  saving.value = true;
  try {
    await referralAPI.acknowledge(record.value.referral_id);
    await loadRecord();
    toast?.success('Referral acknowledged. You can now schedule test taking.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to acknowledge referral.');
  } finally {
    saving.value = false;
  }
}

// Testing Records is TMDU's own module - the case's appointments include
// GCU's own (from the original referral), so this scopes the panel to only
// the ones actually booked for TMDU (Schedule Test Taking/Schedule PAR
// Release above always create theirs as unit: 'TMDU').
const tmduAppointments = computed(() => (record.value.appointments || []).filter(a => a.unit === 'TMDU'));

// Whether the test-taking appointment has been marked attended (checked_in)
// from Manage Queue - this is exactly what TestingRecordController::
// schedulePar() checks server-side before allowing Schedule PAR Release, so
// surface it here too instead of only finding out via a 422 after clicking.
const testTakingAttended = computed(() =>
  tmduAppointments.value.some(a => a.appointment_type === 'psychological_testing' && a.checked_in)
);

// The PAR copy release is a face-to-face pickup, not something the system
// hands out itself - this just surfaces whether the student actually came
// for it (released) or didn't (held), based on the par_release
// appointment's own status, same as markAppointmentNoShow()/checkIn() below
// already set from Manage Queue. Latest one wins if it was ever rescheduled.
const parReleaseAppointment = computed(() =>
  tmduAppointments.value
    .filter(a => a.appointment_type === 'par_release')
    .sort((a, b) => new Date(b.appointment_date) - new Date(a.appointment_date))[0]
);

// Appointment Details floating modal + actions.
const apptDetail = ref(null);
const parBusy = ref(false);

// PAR results can only be attached once the PAR release appointment has
// been marked "Results Released" (status completed).
const parReleased = computed(() => parReleaseAppointment.value?.status === 'completed');

function openApptDetail(a) { apptDetail.value = a; }

async function doParAction(a, action) {
  if (parBusy.value) return;
  parBusy.value = true;
  try {
    const res = await testingAPI.parAction(a.id, action);
    toast?.success(res.data?.message || 'Updated.');
    apptDetail.value = null;
    await loadRecord();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to update the PAR release.');
  } finally {
    parBusy.value = false;
  }
}

async function markAttended(a) {
  if (parBusy.value) return;
  parBusy.value = true;
  try {
    await appointmentAPI.checkIn(a.id);
    toast?.success('Student marked as attended.');
    apptDetail.value = null;
    await loadRecord();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark attendance.');
  } finally {
    parBusy.value = false;
  }
}

const showNoShowModal = ref(false);
const noShowTarget = ref(null);

const callSlipConfirming = ref(false);
const submittingNoShow = ref(false);

function openNoShowModal(a) {
  noShowTarget.value = a;
  callSlipConfirming.value = false;
  showNoShowModal.value = true;
}

async function submitNoShow(action) {
  if (!noShowTarget.value || submittingNoShow.value) return;
  if (action === 'call_slip' && noShowTarget.value.no_show_escalated) return;
  submittingNoShow.value = true;
  try {
    const res = await appointmentAPI.escalateNoShow(noShowTarget.value.id, action);
    noShowTarget.value.status = 'no_show';
    noShowTarget.value.no_show_escalated = !!res.data.appointment?.no_show_escalated;
    toast?.success(res.data.message || 'Marked as no-show.');
    showNoShowModal.value = false;
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to mark as no-show.');
    callSlipConfirming.value = false;
  } finally {
    submittingNoShow.value = false;
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

const tmduDoc = ref({});
const slipDoc = ref({});
function formatDocDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: '2-digit' });
}
async function fetchSlipDoc() {
  try {
    const base = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
    const res = await axios.get(`${base}/document-settings/QF-TMDU-02`, { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } });
    slipDoc.value = res.data;
  } catch (e) { /* header falls back to the defaults */ }
}
async function fetchTmduDoc() {
  try {
    const base = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
    const res = await axios.get(`${base}/document-settings/QF-OSS-GCU-05`, { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } });
    tmduDoc.value = res.data;
  } catch (e) { /* header falls back to the defaults */ }
}

onMounted(async () => {
  fetchTmduDoc();
  fetchSlipDoc();
  try {
    await loadRecord();
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>