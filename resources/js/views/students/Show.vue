<template>
  <div class="fade-up">
    <!-- Back + Header -->
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
      <button class="ibtn ibtn-o ibtn-sm" @click="$router.back()">
        <svg viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      </button>
      <div class="ph" style="margin:0">
        <h1>{{ student.first_name }} {{ student.last_name }}</h1>
        <p>{{ student.student_id }} · {{ student.year_level }}, {{ student.college }}</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">

      <!-- Left -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Case History -->
        <div class="icard">
          <div class="icard-header">
            <span class="icard-title">Case History</span>
            <span class="ibadge ibadge-in_progress" v-if="student.is_recurring">Recurring</span>
          </div>
          <div v-if="cases.length === 0" class="empty-state">
            <h3>No cases yet</h3>
            <p>No case files found for this student.</p>
          </div>
          <div class="ts" v-else>
            <table class="itable">
              <thead>
                <tr>
                  <th>Case No.</th>
                  <th>Type</th>
                  <th>Unit</th>
                  <th>Sessions</th>
                  <th>Status</th>
                  <th>Opened</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="c in cases"
                  :key="c.id"
                  style="cursor:pointer"
                  @click="$router.push({ name: 'case-show', params: { id: c.id } })"
                >
                  <td style="font-family:var(--mono);font-size:11px">{{ c.case_number }}</td>
                  <td>{{ c.case_type?.replace(/_/g,' ') }}</td>
                  <td><span class="ibadge" :class="'unit-' + c.current_unit.toLowerCase()">{{ c.current_unit }}</span></td>
                  <td>{{ c.total_sessions }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + c.status">{{ c.status }}</span></td>
                  <td style="font-size:12px">{{ formatDate(c.opened_date) }}</td>
                  <td><button class="ibtn ibtn-o ibtn-sm" @click.stop="$router.push({ name: 'case-show', params: { id: c.id } })">View</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Referral History -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Referral History</span></div>
          <div v-if="referrals.length === 0" class="empty-state">
            <h3>No referrals yet</h3>
            <p>No referrals found for this student.</p>
          </div>
          <div class="ts" v-else>
            <table class="itable">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Type</th>
                  <th>Referred By</th>
                  <th>Urgency</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="r in referrals"
                  :key="r.id"
                  style="cursor:pointer"
                  @click="$router.push({ name: 'referral-show', params: { id: r.id } })"
                >
                  <td style="font-family:var(--mono);font-size:11px">{{ r.referral_code }}</td>
                  <td>{{ r.referral_type?.replace(/_/g,' ') }}</td>
                  <td>{{ r.referrer_name }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + r.urgency_level">{{ r.urgency_level }}</span></td>
                  <td><span class="ibadge" :class="'ibadge-' + r.status">{{ r.status?.replace(/_/g,' ') }}</span></td>
                  <td style="font-size:12px">{{ formatDate(r.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Appointments -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Appointments</span></div>
          <div v-if="appointments.length === 0" class="empty-state">
            <h3>No appointments yet</h3>
            <p>No appointments scheduled for this student.</p>
          </div>
          <div class="ts" v-else>
            <table class="itable">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Type</th>
                  <th>Staff</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in appointments" :key="a.id">
                  <td style="font-family:var(--mono);font-size:11px">{{ a.appointment_code }}</td>
                  <td>{{ a.appointment_type?.replace(/_/g,' ') }}</td>
                  <td>{{ a.staff_name }}</td>
                  <td style="font-size:12px">{{ formatDate(a.appointment_date) }}</td>
                  <td style="font-size:12px">{{ a.start_time }}</td>
                  <td><span class="ibadge" :class="'ibadge-' + a.status">{{ a.status }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Right: Student Info -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Profile Card -->
        <div class="icard">
          <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:20px;border-radius:var(--r-lg) var(--r-lg) 0 0;text-align:center">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 10px;font-family:var(--serif)">
              {{ initials(student.first_name, student.last_name) }}
            </div>
            <div style="font-size:15px;font-weight:600;color:#fff">{{ student.first_name }} {{ student.last_name }}</div>
            <div style="font-size:11px;color:rgba(255,255,255,.5);font-family:var(--mono);margin-top:2px">{{ student.student_id }}</div>
          </div>
          <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.year_level }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.college }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.program }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.section || '—' }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.email || '—' }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.contact_number || '—' }}</div>
            </div>
          </div>
        </div>

        <!-- Guardian Info -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Guardian</span></div>
          <div class="icard-body" style="display:flex;flex-direction:column;gap:10px">
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Name</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.guardian_name || '—' }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.guardian_contact || '—' }}</div>
            </div>
            <div>
              <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Relationship</div>
              <div style="font-size:13px;color:var(--ink)">{{ student.guardian_relationship || '—' }}</div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="icard">
          <div class="icard-header"><span class="icard-title">Actions</span></div>
          <div class="icard-body" style="display:flex;flex-direction:column;gap:8px">
            <router-link :to="{ name: 'referral-create' }" class="ibtn ibtn-p" style="width:100%;justify-content:center">
              <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Submit Referral
            </router-link>
            <router-link :to="{ name: 'appointments' }" class="ibtn ibtn-o" style="width:100%;justify-content:center">
              <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              Schedule Appointment
            </router-link>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const student = ref({
  id: 1,
  first_name: 'Maria',
  last_name: 'Santos',
  student_id: '2023-0041',
  email: 'maria.santos@bsu.edu.ph',
  contact_number: '09171234567',
  year_level: '3rd Year',
  college: 'CIT',
  program: 'BSIT',
  section: 'BSIT 3-A',
  guardian_name: 'Rosa Santos',
  guardian_contact: '09181234567',
  guardian_relationship: 'Mother',
  is_recurring: true,
});

const cases = ref([
  {
    id: 1,
    case_number: 'CASE-2026-0001',
    case_type: 'counseling',
    current_unit: 'GCU',
    total_sessions: 2,
    status: 'in_progress',
    opened_date: '2026-07-01',
  },
]);

const referrals = ref([
  {
    id: 1,
    referral_code: 'REF-2026-0001',
    referral_type: 'counseling',
    referrer_name: 'Prof. Juan Dela Cruz',
    urgency_level: 'high',
    status: 'in_review',
    created_at: '2026-07-01T08:00:00',
  },
]);

const appointments = ref([
  {
    id: 1,
    appointment_code: 'APT-2026-0001',
    appointment_type: 'initial_counseling',
    staff_name: 'Dr. Maria Reyes',
    appointment_date: '2026-07-05',
    start_time: '09:00',
    status: 'confirmed',
  },
]);

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}
</script>