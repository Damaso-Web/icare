<!--
  FILE: resources/js/views/students/Show.vue
  PAGE: iCARE / Student Profile
  (Not to be confused with resources/js/views/referrals/Show.vue,
   which is the Referral Details page - different file, same filename.)

  Changed in this update:
    1. "Edit Student Profile" button now hidden when opened from Case Files
       (v-if="!fromCases"), since editing belongs to Student/Client only.
    2. "Appointments" panel now hidden when opened from Case Files
       (v-if="!fromCases").
    3. Referral links now carry the real navigation context (referralCtx)
       instead of a hardcoded ctx=cases, so Student/Client -> Referral
       Details keeps working correctly.
-->
<template>
  <div class="fade-up">

    <!-- Loading -->
    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>

    <template v-else>
      <!-- Back + Case pill + Edit -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
        <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
          <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        </button>
        <span
          v-if="primaryCase"
          style="font-family:var(--serif);font-style:italic;font-size:26px;color:var(--forest);font-weight:400"
        >
          {{ caseName }}
        </span>
        <!-- Until a client no. is entered the case still goes by its old number -->
        <span v-if="primaryCase && !hasCtrlNo && fromCases && sifDoc.ctrl_no" style="font-size:11.5px;color:var(--fog)">No Ctrl No. yet</span>
        <span
          v-if="primaryCase && ['closed', 'resolved'].includes(primaryCase.status)"
          class="ibadge"
          style="background:var(--cloud);color:var(--stone)"
        >
          {{ primaryCase.status === 'closed' ? 'Closed Case' : 'Resolved Case' }}
        </span>
        <span v-if="isRecurringStudent" class="ibadge ibadge-in_progress">Recurring</span>
        <!-- B244: show which staff member is assigned to this case -->
        <span v-if="primaryCase" style="font-size:12px;color:var(--stone)">
          Assigned to: <strong style="color:var(--ink)">{{ primaryCase.counselor?.name || 'Unassigned' }}</strong>
        </span>
        <div style="margin-left:auto;display:flex;gap:8px">
          <!-- Update Case Status - moved here from Referral Details (B242),
               since this is the actual Case Details view for the SIF. -->
          <button v-if="isGCU && fromCases && primaryCase" class="ibtn ibtn-o ibtn-sm" @click="newCaseStatus = primaryCase.status; showCaseStatusModal = true">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Case Status
          </button>
          <!-- Reassigning a case is for the GCU Head only -->
          <button v-if="auth.user?.role === 'admin' && primaryCase" class="ibtn ibtn-o ibtn-sm" @click="openAssignModal">
            <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Reassign
          </button>
          <!-- Editing a student record belongs to the Student/Client module.
               Case Files is a read-only case view, so the action is hidden there. -->
          <button v-if="!fromCases" class="ibtn ibtn-o ibtn-sm" @click="openEdit">
            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit Student Profile
          </button>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1fr 300px;gap:16px">

        <!-- Left: Referrals -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Referral List -->
          <div class="icard">
            <div class="icard-header">
              <span class="icard-title">Referrals</span>
              <!-- View Case Study Report - moved here from each individual
                   Referral Details page's Case Action card, since the
                   report is case-level (it only ever takes the case id),
                   not referral-level - it belongs on the main SIF page,
                   next to the linked referrals it covers, not repeated on
                   every one of them. -->
              <router-link
                v-if="primaryCase && auth.user?.role !== 'tmdu_staff'"
                :to="{ name: 'case-study-report', params: { id: primaryCase.id } }"
                target="_blank"
                class="ibtn ibtn-o ibtn-sm"
                style="margin-left:auto"
              >
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                View Case Study Report
              </router-link>
            </div>
            <div style="padding:12px 18px;border-bottom:1px solid var(--cloud)">
              <div style="position:relative">
                <svg viewBox="0 0 24 24" style="width:15px;height:15px;position:absolute;left:10px;top:50%;transform:translateY(-50%);stroke:var(--fog);fill:none;stroke-width:2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input v-model="referralSearch" class="ifi" style="padding-left:32px" maxlength="50" placeholder="Search referral no." @input="referralSearch = String(referralSearch ?? '').replace(/[^a-zA-Z0-9\- ]/g, '')" />
              </div>
            </div>
            <div v-if="!visibleReferrals.length" class="empty-state">
              <h3>No referrals yet</h3>
              <p>No referrals found for this student.</p>
            </div>
            <div v-else-if="!filteredReferrals.length" class="empty-state">
              <h3>No matches</h3>
              <p>No referrals match "{{ referralSearch }}".</p>
            </div>
            <div class="ts" v-else>
              <table class="itable">
                <thead>
                  <tr>
                    <th>Refer No.</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date Submitted</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="r in filteredReferrals"
                    :key="r.id"
                    style="cursor:pointer"
                    @click="$router.push({ name: 'referral-show', params: { id: r.id }, query: referralCtx })"
                  >
                    <td style="font-family:var(--mono);font-size:11px">{{ r.referral_code }}</td>
                    <td>{{ toTitleCase(r.referral_type) }}</td>
                    <td><span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span></td>
                    <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                    <td><button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'referral-show', params: { id: r.id }, query: referralCtx })">View</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Related Concerns Comparison - group referrals by type to spot recurrence/escalation.
               Each group is now a collapsible dropdown (B245) so multiple groups
               don't crowd the screen; the count badge stays visible either way. -->
          <div class="icard" v-if="visibleReferrals.length > 1">
            <div class="icard-header"><span class="icard-title">Related Concerns Comparison</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div v-for="group in concernGroups" :key="group.type">
                <div
                  style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 0"
                  @click="toggleConcernGroup(group.type)"
                >
                  <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:var(--stone);fill:none;stroke-width:2;transition:transform .15s" :style="{ transform: expandedConcernGroups[group.type] ? 'rotate(90deg)' : 'rotate(0deg)' }"><polyline points="9 18 15 12 9 6"/></svg>
                  <div style="font-size:12.5px;font-weight:700;color:var(--ink)">{{ toTitleCase(group.type) }}</div>
                  <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ group.referrals.length }}</span>
                  <span v-if="group.referrals.length > 1" class="ibadge" style="background:var(--blue-lt);color:var(--blue)">Recurring</span>
                </div>
                <div class="ts" v-if="expandedConcernGroups[group.type]">
                  <table class="itable" style="table-layout:fixed">
                    <colgroup>
                      <col style="width:20%" />
                      <col style="width:55%" />
                      <col style="width:25%" />
                    </colgroup>
                    <thead>
                      <tr><th>Date Submitted</th><th>Concern</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                      <tr v-for="r in group.referrals" :key="r.id" style="cursor:pointer" @click="$router.push({ name: 'referral-show', params: { id: r.id }, query: referralCtx })">
                        <td style="font-size:12px;white-space:nowrap">{{ formatDate(r.created_at) }}</td>
                        <td style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ r.nature_of_concern || '-' }}</td>
                        <td><span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- Right: Student Info -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Student Information (+ Guardian, + Login Credentials) -->
          <div class="icard">
            <div class="icard-header"><span class="icard-title">Student Information</span></div>

            <!-- SIF document header - Case Files view only. Revision No. /
                 Effectivity / Year-Term come from Management (QF-OSS-GCU-01).
                 The client no. at the end of the Ctrl No. is per student and
                 editable here by GCU. -->
            <div v-if="fromCases" style="padding:10px 18px;border-bottom:1px solid var(--cloud);background:var(--snow);font-size:11px;color:var(--stone);display:grid;grid-template-columns:1fr 1fr;gap:2px 10px">
              <div><strong>Document Code:</strong> QF-OSS-GCU-01</div>
              <div style="text-align:right"><strong>Effectivity:</strong> {{ formatDocDate(sifDoc.effectivity_date) }}</div>
              <div><strong>Revision No.:</strong> {{ sifDoc.revision_no || '01' }}</div>
              <div></div>
              <div class="ctrl-row">
                <span class="ctrl-label">Ctrl No.</span>
                <template v-if="!editingClientNo">
                  <span class="ctrl-value" :class="{ unset: !hasCtrlNo }">{{ sifCtrlNo }}</span>
                  <button v-if="isGCU" type="button" class="ibtn ibtn-g ibtn-sm" style="padding:2px 8px" @click="startClientNoEdit">{{ hasCtrlNo ? 'Edit' : 'Set' }}</button>
                </template>
                <template v-else>
                  <span class="ctrl-value">{{ sifDoc.ctrl_no || '-' }}-</span>
                  <input v-model="clientNoDraft" class="ifi" style="width:70px;padding:3px 8px;font-family:var(--mono)" maxlength="4" inputmode="numeric" placeholder="____" @input="clientNoDraft = String(clientNoDraft ?? '').replace(/\D/g, '')" @keyup.enter="saveClientNo" />
                  <button type="button" class="ibtn ibtn-p ibtn-sm" style="padding:2px 8px" :disabled="savingClientNo" @click="saveClientNo">{{ savingClientNo ? '...' : 'Save' }}</button>
                  <button type="button" class="ibtn ibtn-g ibtn-sm" style="padding:2px 8px" :disabled="savingClientNo" @click="editingClientNo = false">Cancel</button>
                </template>
              </div>
            </div>

            <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
                <div class="qav" style="width:40px;height:40px;font-size:15px">
                  {{ initials(student.first_name, student.last_name) }}
                </div>
                <div>
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">{{ student.last_name }}, {{ student.first_name }} {{ student.middle_name }}</div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ student.student_id }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.college || '-' }}</div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.program || '-' }}</div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.year_level || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.section || '-' }}</div>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.sex || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.contact_number || '-' }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email</div>
                <div style="font-size:13px;color:var(--ink)">{{ student.email || '-' }}</div>
              </div>

              <div style="height:1px;background:var(--cloud);margin:4px 0"></div>

              <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Guardian</div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Full Name</div>
                <div style="font-size:13px;color:var(--ink)">
                  {{ [student.guardian_last_name, student.guardian_first_name, student.guardian_middle_name].filter(Boolean).length
                      ? `${student.guardian_last_name || ''}, ${student.guardian_first_name || ''} ${student.guardian_middle_name || ''}`.trim()
                      : '-' }}
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Relationship</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.guardian_relationship || '-' }}</div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
                  <div style="font-size:13px;color:var(--ink)">{{ student.guardian_contact || '-' }}</div>
                </div>
              </div>

              <!-- Family Information / Siblings Information / Educational
                   Attainment - collapsible, shown here inside the Student
                   Information File only (not on Referral Details). -->
              <template v-if="fromCases">
              <div style="height:1px;background:var(--cloud);margin:4px 0"></div>
              <div v-for="group in profileDetailGroups" :key="group.key">
                <div
                  style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 0"
                  @click="toggleProfileGroup(group.key)"
                >
                  <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:var(--stone);fill:none;stroke-width:2;transition:transform .15s;flex-shrink:0" :style="{ transform: expandedProfileGroups[group.key] ? 'rotate(90deg)' : 'rotate(0deg)' }"><polyline points="9 18 15 12 9 6"/></svg>
                  <div style="font-size:11.5px;font-weight:700;color:var(--ink)">{{ group.label }}</div>
                  <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ group.count }}</span>
                </div>
                <div v-if="expandedProfileGroups[group.key]" style="padding:4px 0 10px 22px;display:flex;flex-direction:column;gap:10px">

                  <!-- Family Information -->
                  <template v-if="group.key === 'family'">
                    <div style="display:flex;flex-direction:column;gap:10px">
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Father's Name</div>
                        <div style="font-size:13px;color:var(--ink)">{{ fullName(student.father_last_name, student.father_first_name, student.father_middle_name) }}</div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Father's Occupation</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.father_occupation || '-' }}</div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Father's Contact</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.father_contact_number || '-' }}</div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Mother's Name</div>
                        <div style="font-size:13px;color:var(--ink)">{{ fullName(student.mother_last_name, student.mother_first_name, student.mother_middle_name) }}</div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Mother's Occupation</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.mother_occupation || '-' }}</div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Mother's Contact</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.mother_contact_number || '-' }}</div>
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
                    <div style="display:flex;flex-direction:column;gap:10px">
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Elementary</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.elementary_school || '-' }} <span v-if="student.elementary_year_graduated" style="color:var(--stone)">({{ student.elementary_year_graduated }})</span></div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">High School</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.high_school || '-' }} <span v-if="student.high_school_year_graduated" style="color:var(--stone)">({{ student.high_school_year_graduated }})</span></div>
                      </div>
                      <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                        <div style="font-size:13px;color:var(--ink)">{{ student.college_school || '-' }} <span v-if="student.college_year_graduated" style="color:var(--stone)">({{ student.college_year_graduated }})</span></div>
                      </div>
                    </div>
                  </template>

                </div>
              </div>
              </template>

            </div>
          </div>

          <!-- Appointments - Case Files drills down per referral, where the
               referral-scoped appointment list already lives. Hidden here to
               avoid showing the student's whole appointment history twice. -->
          <div class="icard" v-if="!fromCases">
            <div class="icard-header"><span class="icard-title">Appointments</span></div>
            <div v-if="!history.appointments?.length" class="empty-state">
              <h3>No appointments yet</h3>
              <p>No appointments scheduled for this student.</p>
            </div>
            <div v-else>
              <div
                v-for="a in history.appointments"
                :key="a.id"
                style="padding:12px 18px;border-bottom:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:flex-start;gap:8px"
              >
                <div>
                  <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ toTitleCase(a.appointment_type) }}</div>
                  <div style="font-size:11px;color:var(--stone);margin-top:2px;font-family:var(--mono)">{{ a.appointment_code }}</div>
                  <div style="font-size:11px;color:var(--stone);margin-top:4px">{{ formatDate(a.appointment_date) }} · {{ a.start_time }}</div>
                  <div style="font-size:11px;color:var(--fog);margin-top:2px">With {{ a.staff?.name || '-' }}</div>
                </div>
                <span class="ibadge" :class="'ibadge-' + a.status" style="flex-shrink:0">{{ toTitleCase(a.status) }}</span>
              </div>
            </div>
          </div>

                    <!-- Testing / PAR Results - psychological testing records for this
               student. Hidden from Case Files since the case-scoped Study
               Report already shows that case's current testing record. -->
          <div class="icard" v-if="!fromCases && history.testing_records?.length">
            <div class="icard-header"><span class="icard-title">Testing / PAR Results</span></div>
            <div
              v-for="t in history.testing_records"
              :key="t.id"
              style="padding:12px 18px;border-bottom:1px solid var(--cloud)"
            >
              <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
                <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ toTitleCase(t.status) }}</div>
                <span v-if="t.report_date" style="font-size:11px;color:var(--stone)">{{ formatDate(t.report_date) }}</span>
              </div>
              <div v-if="t.assessment_summary" style="font-size:12px;color:var(--slate);margin-top:6px;white-space:pre-wrap">
                {{ t.assessment_summary }}
              </div>
              <div v-if="t.recommendations" style="font-size:11px;color:var(--stone);margin-top:6px">
                <strong>Recommendations:</strong> {{ t.recommendations }}
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Edit Student Modal -->
      <div v-if="showEditModal && !fromCases" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeEdit">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Edit Student Profile</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">

            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px">
              Student Information
              <div style="flex:1;height:1px;background:var(--cloud)"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="editForm.last_name" class="ifi" maxlength="20" placeholder="Last Name" @input="editForm.last_name = onlyLetters(editForm.last_name)" />
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="editForm.first_name" class="ifi" maxlength="20" placeholder="First Name" @input="editForm.first_name = onlyLetters(editForm.first_name)" />
              </div>
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="editForm.middle_name" class="ifi" maxlength="20" placeholder="Middle Name" @input="editForm.middle_name = onlyLetters(editForm.middle_name)" />
              </div>
              <div>
                <label class="ifl">Suffix</label>
                <select v-model="editForm.suffix" class="ifse">
                  <option value="">None</option>
                  <option v-if="editForm.suffix && !SUFFIX_OPTIONS.includes(editForm.suffix)" :value="editForm.suffix">{{ editForm.suffix }}</option>
                  <option v-for="x in SUFFIX_OPTIONS" :key="x" :value="x">{{ x }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">Sex</label>
                <select v-model="editForm.sex" class="ifse">
                  <option value="">Select...</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
              <div>
                <label class="ifl">Student ID</label>
                <input v-model="editForm.student_id" class="ifi" maxlength="15" placeholder="e.g. 2302021" @input="editForm.student_id = onlyDigits(editForm.student_id)" />
              </div>
              <div>
                <label class="ifl">Year Level</label>
                <select v-model="editForm.year_level" class="ifse">
                  <option value="">Select...</option>
                  <option v-if="editForm.year_level && !YEAR_LEVEL_OPTIONS.includes(String(editForm.year_level))" :value="editForm.year_level">{{ editForm.year_level }}</option>
                  <option v-for="yl in YEAR_LEVEL_OPTIONS" :key="yl" :value="yl">{{ yl }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">College</label>
                <select v-model="editForm.college" class="ifse" @change="editForm.program = ''">
                  <option value="">Select college...</option>
                  <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">Program</label>
                <select v-model="editForm.program" class="ifse" :disabled="!editForm.college">
                  <option value="">Select program...</option>
                  <option v-if="editForm.program && !editAvailablePrograms.includes(editForm.program)" :value="editForm.program">{{ editForm.program }}</option>
                  <option v-for="p in editAvailablePrograms" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>
              <div>
                <label class="ifl">Section</label>
                <input
                  v-model="editForm.section"
                  class="ifi"
                  placeholder="e.g. A"
                  maxlength="1"
                  @input="editForm.section = editForm.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase()"
                />
              </div>
              <div>
                <label class="ifl">Email Address</label>
                <input v-model="editForm.email" class="ifi" maxlength="100" placeholder="student@bsu.edu.ph" />
              </div>
              <div>
                <label class="ifl">Contact Number</label>
                <input v-model="editForm.contact_number" class="ifi" maxlength="11" placeholder="09XXXXXXXXX" @input="editForm.contact_number = contactNumberInput(editForm.contact_number); editForm.contact_number = String(editForm.contact_number ?? '').replace(/[^0-9+\- ]/g, '')" />
              </div>
            </div>

            <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:4px">
              Guardian Information
              <div style="flex:1;height:1px;background:var(--cloud)"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Guardian Last Name <span style="color:var(--red)">*</span></label>
                <input v-model="editForm.guardian_last_name" class="ifi" maxlength="20" placeholder="Dela Cruz" @input="editForm.guardian_last_name = onlyLetters(editForm.guardian_last_name)" />
              </div>
              <div>
                <label class="ifl">Guardian First Name <span style="color:var(--red)">*</span></label>
                <input v-model="editForm.guardian_first_name" class="ifi" maxlength="20" placeholder="Juan" @input="editForm.guardian_first_name = onlyLetters(editForm.guardian_first_name)" />
              </div>
              <div>
                <label class="ifl">Guardian Middle Name</label>
                <input v-model="editForm.guardian_middle_name" class="ifi" maxlength="20" placeholder="Santos" @input="editForm.guardian_middle_name = onlyLetters(editForm.guardian_middle_name)" />
              </div>
              <div>
                <label class="ifl">Guardian Contact <span style="color:var(--red)">*</span></label>
                <input v-model="editForm.guardian_contact" class="ifi" maxlength="11" placeholder="09XXXXXXXXX" @input="editForm.guardian_contact = contactNumberInput(editForm.guardian_contact); editForm.guardian_contact = String(editForm.guardian_contact ?? '').replace(/[^0-9+\- ]/g, '')" />
              </div>
              <div>
                <label class="ifl">Relationship <span style="color:var(--red)">*</span></label>
                <select v-model="editForm.guardian_relationship" class="ifse">
                  <option value="">Select...</option>
                  <option>Mother</option>
                  <option>Father</option>
                  <option>Guardian</option>
                  <option>Sibling</option>
                  <option>Relative</option>
                </select>
              </div>
            </div>

            <div v-if="editError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
              {{ editError }}
            </div>

            <div style="display:flex;gap:8px;padding-top:4px">
              <button class="ibtn ibtn-p" @click="askSaveStudent" :disabled="saving || isEditUnchanged">
                <svg v-if="!saving" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-if="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
                {{ saving ? 'Saving...' : 'Save Changes' }}
              </button>
              <button class="ibtn ibtn-o" @click="closeEdit">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Confirm changes (B331) -->
      <div v-if="showEditConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditConfirm = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Changes</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div style="font-size:13px;color:var(--stone)">
              You are about to save <strong>{{ editChanges.length }}</strong> change{{ editChanges.length === 1 ? '' : 's' }} to this student profile:
            </div>
            <div style="border:1px solid var(--cloud);border-radius:var(--r-sm);overflow:hidden">
              <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                  <tr style="background:var(--snow)">
                    <th style="text-align:left;padding:8px 12px;font-weight:600;color:var(--stone);font-size:11px;text-transform:uppercase">Field</th>
                    <th style="text-align:left;padding:8px 12px;font-weight:600;color:var(--stone);font-size:11px;text-transform:uppercase">Before</th>
                    <th style="text-align:left;padding:8px 12px;font-weight:600;color:var(--stone);font-size:11px;text-transform:uppercase">After</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="c in editChanges" :key="c.label" style="border-top:1px solid var(--cloud)">
                    <td style="padding:8px 12px;color:var(--slate)">{{ c.label }}</td>
                    <td style="padding:8px 12px;color:var(--red);text-decoration:line-through">{{ c.before }}</td>
                    <td style="padding:8px 12px;color:var(--moss);font-weight:600">{{ c.after }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" :disabled="saving" @click="saveStudent">{{ saving ? 'Saving...' : 'Confirm & Save' }}</button>
              <button class="ibtn ibtn-o" :disabled="saving" @click="showEditConfirm = false">Go Back &amp; Edit</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Update Case Status Modal - moved here from Referral Details (B242) -->
      <div v-if="showCaseStatusModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showCaseStatusModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Update Case Status</div>
            <button class="ibtn ibtn-g ibtn-sm" @click="showCaseStatusModal = false">✕</button>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:8px">
            <select v-model="newCaseStatus" class="ifse">
              <!-- Only these 4 are meant to be picked manually. Other statuses
                   (awaiting_testing, on_hold, resolved...) are still set by
                   other flows (referring to TMDU, resolving a referral), so
                   if the case is currently on one of them it is shown here
                   as a greyed-out, unselectable current value instead of
                   leaving the dropdown blank. -->
              <option v-if="!['open','on_observation','closed'].includes(newCaseStatus) && newCaseStatus" :value="newCaseStatus" disabled>
                {{ String(newCaseStatus).replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }} (current)
              </option>
              <option value="open">Open</option>
              <option value="on_observation">On Observation</option>
              <option value="closed">Closed</option>
            </select>
            <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="updateCaseStatus">Save Status</button>
          </div>
        </div>
      </div>

      <!-- Reassign Counselor Modal -->
      <div v-if="showAssignModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showAssignModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
            <div style="font-size:15px;font-weight:600;color:var(--ink)">Reassign Counselor</div>
          </div>
          <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
            <div>
              <label class="ifl">Assign To <span style="color:var(--red)">*</span></label>
              <select v-model.number="assignForm.to_user_id" class="ifse" :disabled="assignLoading">
                <option value="" disabled>{{ assignLoading ? 'Loading...' : 'Select staff member...' }}</option>
                <option v-for="u in assignStaffList" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" :disabled="assigning || assignLoading" @click="assignCounselor">{{ assigning ? 'Assigning...' : 'Assign' }}</button>
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
import { toTitleCase } from '../../utils/validators';
import { useRoute } from 'vue-router';
import { studentAPI, caseAPI, userAPI } from '../../api/index';
import { COLLEGES } from '../../constants/colleges';
import { PROGRAMS_BY_COLLEGE } from '../../constants/programs';
import { onlyLetters, onlyLettersStrict, onlyDigits, contactNumberInput, isValidPHContact } from '../../utils/validators';
import { useAuthStore } from '../../stores/auth';

const route   = useRoute();
const toast   = inject('toast');
const auth    = useAuthStore();
const loading = ref(true);
const saving  = ref(false);
const showEditModal = ref(false);
const student = ref({});
const history = ref({});
const colleges = COLLEGES;
const editError = ref('');

const editForm = ref({});
const editSnapshot = ref('');
const showEditConfirm = ref(false);
const SUFFIX_OPTIONS = ['Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V'];
// Same year-level values the student list / add-student forms save.
const YEAR_LEVEL_OPTIONS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

const isEditUnchanged = computed(() => JSON.stringify(editForm.value) === editSnapshot.value);

const EDIT_LABELS = {
  student_id: 'Student ID', last_name: 'Last Name', first_name: 'First Name', middle_name: 'Middle Name',
  suffix: 'Suffix', sex: 'Sex', college: 'College', program: 'Program', year_level: 'Year Level',
  section: 'Section', email: 'Email Address', contact_number: 'Contact Number',
  guardian_last_name: 'Guardian Last Name', guardian_first_name: 'Guardian First Name',
  guardian_middle_name: 'Guardian Middle Name', guardian_contact: 'Guardian Contact',
  guardian_relationship: 'Guardian Relationship',
};
const editChanges = computed(() => {
  let before = {};
  try { before = JSON.parse(editSnapshot.value || '{}'); } catch { before = {}; }
  const after = editForm.value || {};
  return Object.keys(EDIT_LABELS)
    .filter(k => String(before[k] ?? '') !== String(after[k] ?? ''))
    .map(k => ({ label: EDIT_LABELS[k], before: before[k] || '—', after: after[k] || '—' }));
});

function openEdit() {
  editError.value = '';
  editForm.value = { ...student.value };
  editSnapshot.value = JSON.stringify(editForm.value);
  showEditConfirm.value = false;
  showEditModal.value = true;
}

// Closing (Cancel or clicking outside) throws away anything typed.
function closeEdit() {
  editForm.value = { ...student.value };
  editSnapshot.value = JSON.stringify(editForm.value);
  editError.value = '';
  showEditConfirm.value = false;
  showEditModal.value = false;
}
const referralSearch = ref('');

// Update Case Status - moved here from Referral Details (B242)
const showCaseStatusModal = ref(false);
const newCaseStatus       = ref('');

async function updateCaseStatus() {
  try {
    const res = await caseAPI.updateStatus(primaryCase.value.id, { status: newCaseStatus.value });
    primaryCase.value.status = res.data.status;
    showCaseStatusModal.value = false;
    toast?.success('Case status updated.');
  } catch (e) {
    toast?.error('Failed to update case status.');
  }
}

// Related Concerns Comparison - each concern group collapses behind a
// dropdown toggle (B245) so several groups don't crowd the screen; the
// count badge on the header stays visible whether expanded or not.
const expandedConcernGroups = ref({});
function toggleConcernGroup(type) {
  expandedConcernGroups.value = {
    ...expandedConcernGroups.value,
    [type]: !expandedConcernGroups.value[type],
  };
}

// Which sidebar module opened this profile. Case Files links in with ctx=cases;
// Student/Client links in with no ctx. This page is shared by both, so the
// flag decides which actions are allowed here.
const fromCases = computed(() => route.query.ctx === 'cases');

// Student Information Sheet (QF-OSS-GCU-01) document header. Ctrl No. is
// "<year>-<term>-<client no.>", e.g. 26-1-0012. Year-term is set in
// Management; the client no. (which number client this student is) is stored
// on the student and edited here.
const sifDoc = ref({});
const editingClientNo = ref(false);
const clientNoDraft = ref('');
const savingClientNo = ref(false);

function formatDocDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: '2-digit' });
}

const sifCtrlNo = computed(() => {
  const n = student.value?.sif_client_no;
  const tail = n ? String(n).padStart(4, '0') : '____';
  return `${sifDoc.value?.ctrl_no || '-'}-${tail}`;
});

// The Ctrl No. is the case's name once the student has a client no.; until
// then the case keeps its old "CASE-<student id>" number.
const hasCtrlNo = computed(() => Boolean(student.value?.sif_client_no && sifDoc.value?.ctrl_no));
const caseName  = computed(() => hasCtrlNo.value ? sifCtrlNo.value : (primaryCase.value?.case_number || ''));

function startClientNoEdit() {
  clientNoDraft.value = student.value?.sif_client_no ? String(student.value.sif_client_no) : '';
  editingClientNo.value = true;
}

async function saveClientNo() {
  if (savingClientNo.value) return;
  savingClientNo.value = true;
  try {
    const value = clientNoDraft.value === '' ? null : Number(clientNoDraft.value);
    const res = await studentAPI.updateClientNo(student.value.id, { sif_client_no: value });
    student.value = { ...student.value, sif_client_no: res.data.sif_client_no };
    editingClientNo.value = false;
    toast?.success('Client number updated.');
  } catch (e) {
    toast?.error(e.response?.data?.errors?.sif_client_no?.[0] || e.response?.data?.message || 'Could not update the client number.');
  } finally {
    savingClientNo.value = false;
  }
}

// Family / Siblings / Educational Attainment dropdown groups for the
// Student Information card (SIF only, see the fromCases gate in the template).
const expandedProfileGroups = ref({});
function toggleProfileGroup(key) {
  expandedProfileGroups.value = {
    ...expandedProfileGroups.value,
    [key]: !expandedProfileGroups.value[key],
  };
}

// siblings is stored as JSON; the API may hand it back already parsed
// (array) or as a raw string, so handle both.
const profileSiblings = computed(() => {
  const raw = student.value?.siblings;
  if (!raw) return [];
  if (Array.isArray(raw)) return raw;
  try {
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch (e) {
    return [];
  }
});

function fullName(last, first, middle) {
  if (![last, first, middle].filter(Boolean).length) return '-';
  return `${last || ''}, ${first || ''} ${middle || ''}`.trim();
}

const profileDetailGroups = computed(() => {
  const s = student.value || {};
  const familyCount = [s.father_last_name, s.mother_last_name].filter(Boolean).length;
  const educationCount = [s.elementary_school, s.high_school, s.college_school].filter(Boolean).length;
  return [
    { key: 'family', label: 'Family Information', count: familyCount },
    { key: 'siblings', label: 'Siblings Information', count: profileSiblings.value.length },
    { key: 'education', label: 'Educational Attainment', count: educationCount },
  ];
});


// Carry the module context forward into Referral Details so that page - and the
// sidebar highlight in MainLayout - knows where the user actually came from.
const referralCtx = computed(() => ({ ctx: fromCases.value ? 'cases' : (route.query.ctx === 'reports' ? 'reports' : 'students') }));

const editAvailablePrograms = computed(() => PROGRAMS_BY_COLLEGE[editForm.value.college] || []);
const primaryCase = computed(() => history.value.cases?.[0] || null);
const isRecurringStudent = computed(() => visibleReferrals.value.length > 1);
const isGCU = computed(() => ['admin', 'gcu_staff'].includes(auth.user?.role));

// Reassign Counselor - moved here from Referral Details
const COUNSELING_ROLES = { GCU: ['admin', 'gcu_staff'], SDU: ['admin', 'sdu_head'], TMDU: ['admin', 'tmdu_staff'] };
const showAssignModal = ref(false);
const assignForm       = ref({ to_user_id: '' });
const assignStaffList  = ref([]);
const assignLoading    = ref(false);
const assigning        = ref(false);

async function loadAssignStaff(unit) {
  assignStaffList.value = [];
  assignLoading.value = true;
  try {
    const res = await userAPI.roster();
    const allowed = COUNSELING_ROLES[unit] || ['admin', 'gcu_staff', 'sdu_head', 'tmdu_staff'];
    assignStaffList.value = (res.data.data || res.data).filter(u => allowed.includes(u.role));
  } catch (e) {
    toast?.error('Could not load staff list.');
  } finally {
    assignLoading.value = false;
  }
}

// Load the list first, then open - the dropdown never re-renders under the
// user's cursor while options stream in.
async function openAssignModal() {
  assignForm.value = { to_user_id: '' };
  await loadAssignStaff(primaryCase.value?.current_unit);
  assignForm.value.to_user_id = primaryCase.value?.primary_counselor_id || '';
  showAssignModal.value = true;
}

async function assignCounselor() {
  if (!assignForm.value.to_user_id) {
    toast?.error('Please select a staff member.');
    return;
  }
  if (assigning.value) return;
  assigning.value = true;
  try {
    const id = Number(assignForm.value.to_user_id);
    const res = await caseAPI.update(primaryCase.value.id, { primary_counselor_id: id });
    primaryCase.value.counselor = res.data.counselor || assignStaffList.value.find(u => u.id === id);
    primaryCase.value.primary_counselor_id = id;
    showAssignModal.value = false;
    toast?.success('Case reassigned.');
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to reassign case.');
  } finally {
    assigning.value = false;
  }
}

// Incident Reports (Complaint-originated referrals) are SDU's own module,
// tracked through the dedicated Complaints page rather than a student's
// case history here - same exclusion as the Referral Queue/Monitoring.
const visibleReferrals = computed(() => (history.value.referrals || []).filter(r => !r.complaint_id));

const filteredReferrals = computed(() => {
  const refs = visibleReferrals.value;
  const q = referralSearch.value.trim().toLowerCase();
  if (!q) return refs;
  return refs.filter(r => r.referral_code?.toLowerCase().includes(q));
});

const concernGroups = computed(() => {
  const refs = visibleReferrals.value;
  const groups = {};
  for (const r of refs) {
    const type = r.referral_type || 'other';
    if (!groups[type]) groups[type] = [];
    groups[type].push(r);
  }
  return Object.entries(groups)
    .map(([type, referrals]) => ({ type, referrals }))
    .filter(g => g.referrals.length > 0)
    .sort((a, b) => b.referrals.length - a.referrals.length);
});

const SDU_TYPES = ['disciplinary'];
const TMDU_TYPES = ['psychological_testing'];

function referralUnit(referralType) {
  if (SDU_TYPES.includes(referralType)) return 'SDU';
  if (TMDU_TYPES.includes(referralType)) return 'TMDU';
  return 'GCU';
}

function askSaveStudent() {
  editError.value = '';

  if (editForm.value.contact_number && !isValidPHContact(editForm.value.contact_number)) {
    editError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }

  if (!editForm.value.guardian_first_name || !editForm.value.guardian_last_name ||
      !editForm.value.guardian_contact || !editForm.value.guardian_relationship) {
    editError.value = 'Please fill in all required guardian fields.';
    return;
  }

  if (!isValidPHContact(editForm.value.guardian_contact)) {
    editError.value = 'Guardian contact number must start with 09 and be 11 digits long.';
    return;
  }

  const f = editForm.value;
  if (f.contact_number && f.guardian_contact && f.contact_number === f.guardian_contact) {
    editError.value = "The student's contact number and the guardian's contact number are the same. Please enter a different number for the guardian.";
    return;
  }
  if (isEditUnchanged.value) return;
  showEditConfirm.value = true;
}

async function saveStudent() {
  editError.value = '';
  saving.value = true;
  try {
    const res = await studentAPI.update(student.value.id, editForm.value);
    student.value = res.data;
    editForm.value = { ...res.data };
    editSnapshot.value = JSON.stringify(editForm.value);
    showEditConfirm.value = false;
    showEditModal.value = false;
    toast?.success('Student profile updated successfully.');
  } catch (e) {
    showEditConfirm.value = false;
    editError.value = e.response?.data?.message || 'Please fill in all required fields.';
  } finally {
    saving.value = false;
  }
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(async () => {
  // Ctrl No. prefix, loaded alongside the page so the case name is right from the start.
  studentAPI.documentSettings('QF-OSS-GCU-01').then(res => { sifDoc.value = res.data || {}; }).catch(() => { /* header falls back to the defaults */ });
  try {
    const [studentRes, historyRes] = await Promise.all([
      studentAPI.show(route.params.id),
      studentAPI.history(route.params.id),
    ]);
    student.value = studentRes.data;
    history.value = historyRes.data;
    editForm.value = { ...studentRes.data };
    editSnapshot.value = JSON.stringify(editForm.value);
    newCaseStatus.value = history.value.cases?.[0]?.status || '';
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
/* SIF document header: the Ctrl No. is the case's name, so it stands out. */
.ctrl-row { grid-column: 1 / -1; display: flex; align-items: center; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed var(--silver); }
.ctrl-label { font-size: 10px; font-weight: 700; letter-spacing: .6px; text-transform: uppercase; color: var(--fog); }
.ctrl-value { font-family: var(--mono); font-size: 17px; font-weight: 700; letter-spacing: .5px; color: var(--forest); background: var(--mist); border: 1px solid var(--mint); border-radius: 6px; padding: 2px 10px; }
.ctrl-value.unset { color: var(--stone); background: #fff; border-style: dashed; border-color: var(--silver); font-weight: 500; }
</style>
