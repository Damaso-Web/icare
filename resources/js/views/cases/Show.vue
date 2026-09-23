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
          <p>{{ caseFile.student?.last_name }}, {{ caseFile.student?.first_name }} {{ caseFile.student?.middle_name }} · {{ caseFile.student?.student_id }}</p>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px">
          <button class="ibtn ibtn-o ibtn-sm" @click="showStatusModal = true">
            <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
            Update Status
          </button>
          <button
            v-if="isGCU && !caseFile.student_unreachable"
            class="ibtn ibtn-sm"
            style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)"
            @click="showUnreachableModal = true"
          >
            <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Flag Unreachable
          </button>
          <button
            v-if="isGCU && caseFile.status !== 'closed'"
            class="ibtn ibtn-sm"
            style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0"
            @click="showCloseModal = true"
          >
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Close Case
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
                <span>{{ toTitleCase(caseFile.case_type) }}</span>
                <span>Opened {{ formatDate(caseFile.opened_date) }}</span>
                <span>{{ caseFile.total_sessions }} session{{ caseFile.total_sessions !== 1 ? 's' : '' }}</span>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;padding:16px">
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
              <span class="ibadge" :class="'ibadge-' + caseFile.status">{{ toTitleCase(caseFile.status) }}</span>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Current Unit</div>
              <span class="ibadge" :class="'unit-' + caseFile.current_unit?.toLowerCase()">{{ caseFile.current_unit }}</span>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Client Status</div>
              <span class="ibadge" :style="caseFile.client_status === 'existing' ? 'background:var(--blue-lt);color:var(--blue)' : 'background:var(--mist);color:var(--moss)'">
                {{ caseFile.client_status === 'existing' ? 'Existing Client' : 'New Client' }}
              </span>
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
            <div v-if="caseFile.prior_referral_count > 0" style="margin:0 16px 16px;background:var(--blue-lt);border:1px solid var(--blue);border-radius:var(--r-sm);padding:10px 14px;font-size:13px;color:var(--blue)">
            ℹ️ This student has {{ caseFile.prior_referral_count }} prior referral{{ caseFile.prior_referral_count > 1 ? 's' : '' }} on record.
          </div>
            <!-- Unreachable Banner -->
            <div v-if="caseFile.student_unreachable" style="margin:0 16px 16px;background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 14px;font-size:13px;color:var(--amber);display:flex;align-items:center;gap:8px">
              <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              Student flagged as unreachable. Dean's Secretary has been notified.
            </div>
          </div>

          <!-- Full Referral Form View -->
          <div class="icard" v-if="caseFile.latest_referral">
            <div class="icard-header">
              <span class="icard-title">Referral Form</span>
              <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
                <span class="ibadge" :class="'ibadge-' + caseFile.latest_referral?.status">{{ toTitleCase(caseFile.latest_referral?.status) }}</span>
                <button v-if="isGCU" class="ibtn ibtn-o ibtn-sm" @click="openReferralStatusModal">
                  <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                  Update Status
                </button>
              </div>
            </div>

            <!-- Document Code Header -->
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

            <div class="icard-body" style="display:flex;flex-direction:column;gap:16px">

              <!-- Student Information -->
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:12px">
                  Student Information
                  <div style="flex:1;height:1px;background:var(--cloud)"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Student ID</div>
                    <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ caseFile.student?.student_id || '—' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Full Name</div>
                    <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.last_name }}, {{ caseFile.student?.first_name }} {{ caseFile.student?.middle_name }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
                    <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.college || '—' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                    <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.program || '—' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
                    <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.year_level || '—' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
                    <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.section || '—' }}</div>
                  </div>
                  <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
                  <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.sex || '—' }}</div>
                </div>
                </div>
              </div>

              <!-- Referred By -->
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:12px">
                  Referred By
                  <div style="flex:1;height:1px;background:var(--cloud)"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Name</div>
                    <div style="font-size:13px;color:var(--ink)">{{ caseFile.latest_referral?.referrer_name || '—' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Role</div>
                    <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(caseFile.latest_referral?.referrer_role) || '—' }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Date Submitted</div>
                    <div style="font-size:13px;color:var(--ink)">{{ formatDate(caseFile.latest_referral?.created_at) }}</div>
                  </div>
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral Code</div>
                    <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ caseFile.latest_referral?.referral_code }}</div>
                  </div>
                </div>
              </div>

              <!-- Referral Details -->
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:12px">
                  Referral Details
                  <div style="flex:1;height:1px;background:var(--cloud)"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                  <div>
                    <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Service Requested</div>
                    <div style="font-size:13px;color:var(--ink)">{{ toTitleCase(caseFile.latest_referral?.referral_type) || '—' }}</div>
                  </div>
                  <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Referral Source</div>
                  <div style="font-size:13px;color:var(--ink)">{{ formatReferralSource(caseFile.latest_referral?.referrer_source) }}</div>
                </div>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:4px">Concern / Reason for Referral</div>
                  <div style="font-size:13px;color:var(--ink);line-height:1.6;background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)">{{ caseFile.latest_referral?.nature_of_concern || '—' }}</div>
                </div>
              </div>

              <!-- Interventions — dated, authored list (B87, B92) -->
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-bottom:8px">
                  Interventions
                  <div style="flex:1;height:1px;background:var(--cloud)"></div>
                  <button v-if="isGCU" class="ibtn ibtn-p ibtn-sm" @click="openInterventionModal">
                    <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add
                  </button>
                </div>
                <div style="display:flex;gap:8px;margin-bottom:10px">
                  <span class="ibadge" style="background:var(--mist);color:var(--moss)">Follow-Ups: {{ caseFile.follow_up_count ?? 0 }}</span>
                  <span class="ibadge" style="background:var(--mist);color:var(--moss)">Parent Conferences: {{ caseFile.parent_conference_count ?? 0 }}</span>
                </div>
                <div v-if="!caseFile.interventions?.length" style="font-size:12px;color:var(--fog);font-style:italic;padding:8px 0">
                  No interventions recorded yet.
                </div>
                <div v-else style="display:flex;flex-direction:column;gap:10px">
                  <div
                    v-for="iv in caseFile.interventions"
                    :key="iv.id"
                    style="background:var(--snow);padding:10px 12px;border-radius:var(--r-sm);border-left:2px solid var(--silver)"
                  >
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                      <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ interventionTypeLabel(iv.type) }}</span>
                      <span v-if="iv.is_completed" class="ibadge" style="background:var(--mist);color:var(--moss)">✓ Completed</span>
                      <button v-else-if="isGCU" class="ibtn ibtn-o ibtn-sm" @click="markInterventionCompleted(iv)">Mark Completed</button>
                    </div>
                    <div style="font-size:13px;color:var(--slate);line-height:1.6;margin-top:6px">{{ iv.description }}</div>
                    <div v-if="iv.excused !== null" style="font-size:11px;margin-top:4px" :style="iv.excused ? 'color:var(--moss)' : 'color:var(--red)'">
                      {{ iv.excused ? 'Excused' : 'Unexcused' }}
                    </div>
                    <div style="font-size:11px;color:var(--fog);margin-top:6px">
                      Recorded by {{ iv.recorded_by?.name }} · {{ formatDate(iv.created_at) }}
                      <span v-if="iv.is_completed"> · Completed by {{ iv.completed_by?.name }} on {{ formatDate(iv.completed_at) }}</span>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- Referral History (B86, B93) -->
          <div class="icard" v-if="caseFile.referrals?.length">
            <div class="icard-header">
              <span class="icard-title">Related Referrals</span>
              <span class="ibadge" style="background:var(--mist);color:var(--moss)">{{ caseFile.referrals.length }} total</span>
            </div>
            <div>
              <div
                v-for="r in caseFile.referrals"
                :key="r.id"
                style="padding:12px 18px;border-bottom:1px solid var(--cloud);cursor:pointer"
                @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
              >
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                  <div style="font-size:12.5px;font-weight:600;color:var(--ink);font-family:var(--mono)">{{ r.referral_code }}</div>
                  <span class="ibadge" :class="'ibadge-' + r.status">{{ toTitleCase(r.status) }}</span>
                </div>
                <div style="font-size:11px;color:var(--stone);margin-top:3px">
                  {{ toTitleCase(r.referral_type) }} · {{ r.referred_by?.name }} · {{ formatDate(r.created_at) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Appointments -->
          <div class="icard" v-if="isGCU">
            <div class="icard-header"><span class="icard-title">Appointments</span></div>
            <div v-if="appointments.length === 0" class="empty-state">
              <h3>No appointments yet</h3>
            </div>
            <div v-else>
              <div v-for="a in appointments" :key="a.id" style="padding:14px 18px;border-bottom:1px solid var(--cloud)">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap">
                  <div>
                    <div style="font-size:13px;font-weight:600;color:var(--ink)">{{ formatDate(a.appointment_date) }} · {{ a.start_time }} – {{ a.end_time }}</div>
                    <div style="font-size:11px;color:var(--stone);margin-top:2px">{{ toTitleCase(a.appointment_type) }} · {{ a.staff?.name || 'TBA' }}</div>
                  </div>
                  <span class="ibadge" :class="'ibadge-' + a.status">{{ toTitleCase(a.status) }}</span>
                </div>
                <button
                  v-if="a.status === 'confirmed'"
                  class="ibtn ibtn-sm"
                  style="margin-top:8px;background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)"
                  @click="escalateNoShow(a)"
                >
                  Mark as Missed / No-Show
                </button>
              </div>
            </div>
          </div>

          <!-- Session Notes — GCU only -->
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
                    Session #{{ note.session_number }} — {{ toTitleCase(note.session_type) }}
                  </div>
                  <div style="font-size:11px;color:var(--fog)">{{ formatDate(note.session_date) }}</div>
                </div>
                <div v-if="note.session_start_time && note.session_end_time" style="font-size:11px;color:var(--stone);margin-bottom:8px">
                  {{ note.session_start_time }} – {{ note.session_end_time }}
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

        </div>

        <!-- Right -->
        <div style="display:flex;flex-direction:column;gap:16px">

          <!-- Service Slips -->
          <div class="icard">
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

          <!-- GCU Case Actions -->
          <div class="icard" v-if="isGCU">
            <div class="icard-header"><span class="icard-title">Case Actions</span></div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <router-link
                v-if="caseFile.latest_referral?.status !== 'submitted'"
                :to="{ name: 'appointments' }"
                class="ibtn ibtn-o"
                style="width:100%;justify-content:center"
              >
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Schedule Appointment
              </router-link>
              <div v-else style="padding:8px 12px;background:var(--cloud);border-radius:var(--r-sm);font-size:12px;color:var(--stone);text-align:center">
                ⚠ Acknowledge referral first
              </div>
              <button
                v-if="!caseFile.referred_to_tmdu"
                class="ibtn ibtn-blue"
                style="width:100%;justify-content:center"
                @click="referToTmdu"
              >
                <svg viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                Refer to TMDU
              </button>
              <div v-else style="padding:8px 12px;background:var(--mist);border-radius:var(--r-sm);font-size:12px;color:var(--moss);text-align:center">
                ✓ Already referred to TMDU
              </div>
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="openHandoffModal">
                <svg viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Handoff Case
              </button>
            </div>
          </div>

          <!-- Handoff / Endorsement History -->
          <div class="icard" v-if="caseFile.handoffs?.length">
            <div class="icard-header"><span class="icard-title">Endorsement History</span></div>
            <div>
              <div v-for="h in caseFile.handoffs" :key="h.id" style="padding:12px 16px;border-bottom:1px solid var(--cloud)">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                  <div style="font-size:12.5px;font-weight:600;color:var(--ink)">{{ h.from_unit }} → {{ h.to_unit }}</div>
                  <span class="ibadge" :style="h.acknowledged ? 'background:var(--mist);color:var(--moss)' : 'background:var(--amber-lt);color:var(--amber)'">
                    {{ h.acknowledged ? 'Received' : 'Pending' }}
                  </span>
                </div>
                <div style="font-size:11px;color:var(--stone);margin-top:3px">
                  {{ h.from_user?.name }} → {{ h.to_user?.name }} · {{ formatDate(h.created_at) }}
                </div>
                <div style="font-size:12px;color:var(--slate);margin-top:6px">{{ h.reason }}</div>
                <div v-if="h.notes" style="font-size:11px;color:var(--stone);margin-top:3px;font-style:italic">{{ h.notes }}</div>
                <button
                  v-if="!h.acknowledged && h.to_user_id === auth.user?.id"
                  class="ibtn ibtn-p ibtn-sm"
                  style="margin-top:8px"
                  @click="confirmHandoffReceipt(h)"
                >
                  Confirm
                </button>
              </div>
            </div>
          </div>

          <!-- Add Intervention Modal -->
          <div v-if="showInterventionModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showInterventionModal = false">
            <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
              <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
                <div style="font-size:15px;font-weight:600;color:var(--ink)">Add Intervention</div>
                <button class="ibtn ibtn-g ibtn-sm" @click="showInterventionModal = false">✕</button>
              </div>
              <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
                <div>
                  <label class="ifl">Type</label>
                  <select v-model="interventionForm.type" class="ifse">
                    <option value="previous_intervention">Previous Intervention</option>
                    <option value="follow_up">Follow-Up</option>
                    <option value="parent_conference">Parent Conference</option>
                    <option value="home_visit">Home Visit</option>
                    <option value="referral_external">External Referral</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div v-if="caseFile.referrals?.length">
                  <label class="ifl">Related Referral (optional)</label>
                  <select v-model="interventionForm.referral_id" class="ifse">
                    <option value="">Not linked to a specific referral</option>
                    <option v-for="r in caseFile.referrals" :key="r.id" :value="r.id">{{ r.referral_code }} — {{ toTitleCase(r.referral_type) }}</option>
                  </select>
                </div>
                <div v-if="interventionShowsExcused">
                  <label class="ifl">Excused?</label>
                  <select v-model="interventionForm.excused" class="ifse">
                    <option :value="null">Not applicable</option>
                    <option :value="true">Excused</option>
                    <option :value="false">Unexcused</option>
                  </select>
                </div>
                <div>
                  <label class="ifl">Description</label>
                  <textarea v-model="interventionForm.description" class="ifta" style="min-height:80px" placeholder="Describe what was done..."></textarea>
                </div>
                <div v-if="interventionError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ interventionError }}</div>
                <div style="display:flex;gap:8px">
                  <button class="ibtn ibtn-p" @click="submitIntervention">Save</button>
                  <button class="ibtn ibtn-o" @click="showInterventionModal = false">Cancel</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Handoff Modal -->
          <div v-if="showHandoffModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showHandoffModal = false">
            <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
              <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
                <div style="font-size:15px;font-weight:600;color:var(--ink)">Handoff Case</div>
                <button class="ibtn ibtn-g ibtn-sm" @click="showHandoffModal = false">✕</button>
              </div>
              <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
                <div>
                  <label class="ifl">To Unit</label>
                  <select v-model="handoffForm.to_unit" class="ifse">
                    <option value="GCU">GCU</option>
                    <option value="SDU">SDU</option>
                    <option value="TMDU">TMDU</option>
                  </select>
                </div>
                <div>
                  <label class="ifl">Receiving Staff</label>
                  <select v-model="handoffForm.to_user_id" class="ifse">
                    <option value="">Select staff...</option>
                    <option v-for="u in handoffStaffOptions" :key="u.id" :value="u.id">{{ u.name }}</option>
                  </select>
                </div>
                <div>
                  <label class="ifl">Reason</label>
                  <textarea v-model="handoffForm.reason" class="ifta" style="min-height:70px"></textarea>
                </div>
                <div>
                  <label class="ifl">Notes</label>
                  <textarea v-model="handoffForm.notes" class="ifta" style="min-height:60px"></textarea>
                </div>
                <div style="display:flex;gap:8px">
                  <button class="ibtn ibtn-p" @click="submitHandoff">Send Handoff</button>
                  <button class="ibtn ibtn-o" @click="showHandoffModal = false">Cancel</button>
                </div>
              </div>
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

          <!-- Update Referral Status (moved here from the Referral Queue) -->
          <div class="icard" v-if="showReferralStatusModal">
            <div class="icard-header">
              <span class="icard-title">Update Referral Status</span>
              <button class="ibtn ibtn-g ibtn-sm" @click="showReferralStatusModal = false">✕</button>
            </div>
            <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
              <select v-model="newReferralStatus" class="ifse">
                <option
                  v-for="step in referralPipeline"
                  :key="step.key"
                  :value="step.key"
                  :disabled="!isReferralStatusSelectable(step.key)"
                >
                  {{ step.label }}{{ referralStatusOrder.indexOf(step.key) < referralStatusOrder.indexOf(caseFile.latest_referral?.status) ? ' (already completed)' : !isReferralStatusSelectable(step.key) ? ' (complete previous step first)' : '' }}
                </option>
              </select>
              <div style="font-size:11px;color:var(--stone)">Steps must be completed in order — you can only move to the next step in the pipeline.</div>
              <button
                class="ibtn ibtn-p"
                style="width:100%;justify-content:center"
                :style="{ opacity: !isReferralStatusSelectable(newReferralStatus) ? .5 : 1, cursor: !isReferralStatusSelectable(newReferralStatus) ? 'not-allowed' : 'pointer' }"
                :disabled="!isReferralStatusSelectable(newReferralStatus)"
                @click="updateReferralStatus"
              >Save Status</button>
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
                  <div style="font-size:13.5px;font-weight:600;color:var(--ink)">
                    {{ caseFile.student?.last_name }}, {{ caseFile.student?.first_name }} {{ caseFile.student?.middle_name }}
                  </div>
                  <div style="font-size:11px;color:var(--fog);font-family:var(--mono)">{{ caseFile.student?.student_id }}</div>
                </div>
              </div>
              <div>
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year & College</div>
                <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.year_level }} · {{ caseFile.student?.college }}</div>
              </div>
              <div v-if="caseFile.student?.program">
                <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
                <div style="font-size:13px;color:var(--ink)">{{ caseFile.student?.program }}</div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Add Notes Modal -->
      <div v-if="showSessionModal && isGCU" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showSessionModal = false">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;max-height:90vh;overflow-y:auto;box-shadow:var(--sh-lg)">
          <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
            <div>
              <div style="font-size:15px;font-weight:600;color:var(--ink)">Add Session Notes</div>
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
              <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="confirmCloseCase">Close Case</button>
              <button class="ibtn ibtn-o" @click="showCloseModal = false">Cancel</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Secondary Confirmation for Closing Case -->
      <div v-if="showCloseConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px">
        <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:380px;padding:22px;text-align:center">
          <div style="font-size:15px;font-weight:600;color:var(--ink);margin-bottom:10px">Are you sure?</div>
          <div style="font-size:13px;color:var(--stone);line-height:1.6;margin-bottom:18px">
            This will permanently close the case. This action cannot be undone.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="flex:1;justify-content:center;background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="closeCase">Yes, Close Case</button>
            <button class="ibtn ibtn-o" style="flex:1;justify-content:center" @click="showCloseConfirm = false">Cancel</button>
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
import { ref, computed, onMounted, inject } from 'vue';
import { toTitleCase } from '../../utils/validators';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { caseAPI, sessionNoteAPI, appointmentAPI, caseHandoffAPI, userAPI, caseInterventionAPI, referralAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';

const route  = useRoute();
const toast  = inject('toast');
const auth   = useAuthStore();

const isGCU = computed(() => ['admin', 'gcu_staff'].includes(auth.user?.role));

const loading              = ref(true);
const showSessionModal     = ref(false);
const showCloseModal       = ref(false);
const showCloseConfirm     = ref(false);
const showStatusModal      = ref(false);
const showUnreachableModal = ref(false);
const newStatus            = ref('');
const unreachableNotes     = ref('');

const showInterventionModal = ref(false);
const interventionForm = ref({ type: 'previous_intervention', referral_id: '', excused: null, description: '' });
const interventionError = ref('');

const interventionShowsExcused = computed(() => {
  if (!interventionForm.value.referral_id) return false;
  const r = caseFile.value.referrals?.find(r => r.id === interventionForm.value.referral_id);
  return r?.referral_type === 'class_attendance';
});

function interventionTypeLabel(type) {
  const labels = {
    previous_intervention: 'Previous Intervention',
    follow_up: 'Follow-Up',
    parent_conference: 'Parent Conference',
    home_visit: 'Home Visit',
    referral_external: 'External Referral',
    other: 'Other',
  };
  return labels[type] || type;
}

function openInterventionModal() {
  interventionForm.value = { type: 'previous_intervention', referral_id: '', excused: null, description: '' };
  interventionError.value = '';
  showInterventionModal.value = true;
}

async function submitIntervention() {
  interventionError.value = '';
  if (!interventionForm.value.description) {
    interventionError.value = 'Please provide a description.';
    return;
  }
  try {
    const payload = { ...interventionForm.value, referral_id: interventionForm.value.referral_id || null };
    const res = await caseInterventionAPI.store(caseFile.value.id, payload);
    if (!caseFile.value.interventions) caseFile.value.interventions = [];
    caseFile.value.interventions.unshift(res.data);
    if (res.data.type === 'follow_up') caseFile.value.follow_up_count = (caseFile.value.follow_up_count || 0) + 1;
    if (res.data.type === 'parent_conference') caseFile.value.parent_conference_count = (caseFile.value.parent_conference_count || 0) + 1;
    showInterventionModal.value = false;
    toast?.success('Intervention recorded.');
  } catch (e) {
    interventionError.value = e.response?.data?.message || 'Failed to save intervention.';
  }
}

async function markInterventionCompleted(iv) {
  try {
    const res = await caseInterventionAPI.markCompleted(iv.id);
    Object.assign(iv, res.data);
    toast?.success('Marked as completed.');
  } catch (e) {
    toast?.error('Failed to mark as completed.');
  }
}

const caseFile     = ref({});
const sessionNotes = ref([]);
const appointments = ref([]);

const sessionForm = ref({
  session_date: '', session_start_time: '', session_end_time: '',
  session_type: 'follow_up', observations: '', interventions: '',
  student_response: '', next_steps: '', student_showed_up: true,
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
    toast?.success('Session notes saved successfully.');
    sessionForm.value = {
      session_date: '', session_start_time: '', session_end_time: '',
      session_type: 'follow_up', observations: '', interventions: '',
      student_response: '', next_steps: '', student_showed_up: true,
    };
  } catch (e) {
    toast?.error('Failed to save session notes.');
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

// Update Referral Status — moved here from the Referral Queue page, since
// this is where day-to-day case work happens. Kept separate from the case's
// own status (above) so the two don't collide.
const showReferralStatusModal = ref(false);
const newReferralStatus       = ref('');

const referralPipeline = [
  { key: 'submitted',    label: 'Submitted' },
  { key: 'acknowledged', label: 'Acknowledged' },
  { key: 'in_review',    label: 'In Review' },
  { key: 'in_progress',  label: 'In Progress' },
  { key: 'completed',    label: 'Completed' },
];

const referralStatusOrder = ['submitted', 'acknowledged', 'in_review', 'in_progress', 'completed', 'closed'];

function isReferralStatusSelectable(key) {
  const current = referralStatusOrder.indexOf(caseFile.value.latest_referral?.status);
  const target  = referralStatusOrder.indexOf(key);
  return target === current || target === current + 1;
}

function openReferralStatusModal() {
  const current = referralStatusOrder.indexOf(caseFile.value.latest_referral?.status);
  const next    = referralPipeline[current + 1];
  newReferralStatus.value = next ? next.key : caseFile.value.latest_referral?.status;
  showReferralStatusModal.value = true;
}

async function updateReferralStatus() {
  try {
    const res = await referralAPI.updateStatus(caseFile.value.latest_referral.id, { status: newReferralStatus.value });
    caseFile.value.latest_referral.status = res.data.status;
    showReferralStatusModal.value = false;
    toast?.success('Referral status updated.');
  } catch (e) {
    toast?.error('Failed to update referral status.');
  }
}

// Document Code Header for the Referral Form card (QF-OSS-01) — read only
// here. Revision No. / Effectivity / Ctrl No. are edited in Management by
// admin, same as on the Referral Queue's detail page.
const referralDoc = ref({});
const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}
async function fetchReferralDoc() {
  try {
    const res = await axios.get(`${API_BASE}/document-settings/QF-OSS-01`, authHeaders());
    referralDoc.value = res.data;
  } catch (e) {
    console.error(e);
  }
}
function formatDocDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: '2-digit' });
}

function confirmCloseCase() {
  if (!closeForm.value.interventions_applied || !closeForm.value.outcomes || !closeForm.value.closure_summary) {
    toast?.error('Please fill in all required fields.');
    return;
  }
  showCloseConfirm.value = true;
}

async function closeCase() {
  try {
    const res = await caseAPI.close(caseFile.value.id, closeForm.value);
    caseFile.value = { ...caseFile.value, ...res.data };
    showCloseModal.value = false;
    showCloseConfirm.value = false;
    toast?.success('Case closed successfully.');
  } catch (e) {
    toast?.error('Failed to close case.');
    showCloseConfirm.value = false;
  }
}

async function referToTmdu() {
  try {
    await caseAPI.referToTmdu(caseFile.value.id, { reason: 'Referred for psychological assessment.' });
    caseFile.value.current_unit     = 'TMDU';
    caseFile.value.status           = 'awaiting_testing';
    caseFile.value.referred_to_tmdu = true;
    toast?.success('Case referred to TMDU.');
  } catch (e) {
    toast?.error('Failed to refer to TMDU.');
  }
}

const showHandoffModal = ref(false);
const handoffForm = ref({ to_unit: 'GCU', to_user_id: '', reason: '', notes: '' });
const staffOptions = ref([]);

const handoffStaffOptions = computed(() => staffOptions.value);

async function fetchStaffOptions() {
  try {
    const res = await userAPI.index({ is_active: 1 });
    staffOptions.value = res.data.data || [];
  } catch (e) {
    console.error(e);
  }
}

function openHandoffModal() {
  handoffForm.value = { to_unit: 'GCU', to_user_id: '', reason: '', notes: '' };
  showHandoffModal.value = true;
  if (staffOptions.value.length === 0) fetchStaffOptions();
}

async function submitHandoff() {
  if (!handoffForm.value.to_user_id || !handoffForm.value.reason) {
    toast?.error('Please select receiving staff and provide a reason.');
    return;
  }
  try {
    await caseAPI.handoff(caseFile.value.id, handoffForm.value);
    toast?.success('Case handed off successfully.');
    showHandoffModal.value = false;
    fetchCase();
  } catch (e) {
    toast?.error('Failed to hand off case.');
  }
}

async function confirmHandoffReceipt(handoff) {
  try {
    await caseHandoffAPI.confirm(handoff.id);
    handoff.acknowledged = true;
    toast?.success('Receipt confirmed.');
  } catch (e) {
    toast?.error('Failed to confirm receipt.');
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

function printSlip(type) {
  toast?.success(`Generating ${type} slip... (coming soon)`);
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}

function formatReferralSource(source) {
  const labels = {
    faculty: 'Faculty Referral',
    sdu:     'SDU Referral',
    self:    'Self-Referral',
    dean:    "Dean's Office",
    parent:  'Parent / Guardian',
  };
  return labels[source] || source || '—';
}

async function fetchCase() {
  try {
    const res = await caseAPI.show(route.params.id);
    caseFile.value          = res.data;
    sessionNotes.value      = res.data.session_notes || [];
    appointments.value      = res.data.appointments  || [];
    newStatus.value         = res.data.status;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchCase();
  fetchReferralDoc();
});
</script>