<!--
  Sent Referrals: every referral this account has sent (staff or student).
  Read-only summary; the unit handling it keeps the full record.
-->
<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>Sent Referrals</h1>
      <p>Referrals you have sent and where they stand.</p>
    </div>

    <div class="icard">
      <ListFilters :model="filters" :selects="filterSelects" placeholder="Search referral no. or student..." @change="load(1)" />
      <div v-if="loading" style="padding:30px;text-align:center">
        <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="!rows.length" class="empty-state">
        <h3>{{ filtered ? 'No matching referrals' : 'No referrals sent yet' }}</h3>
        <p>{{ filtered ? 'Try a different search or reset the filters.' : 'Referrals you send will be kept here.' }}</p>
      </div>
      <div v-else class="ts">
        <table class="itable">
          <thead>
            <tr><th>Referral No.</th><th>Student</th><th>Type</th><th>Status</th><th>Date Sent</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" style="cursor:pointer" @click="selected = r; editing = false">
              <td style="font-family:var(--mono);font-size:12px">{{ r.referral_code }}</td>
              <td>{{ r.student ? `${r.student.last_name}, ${r.student.first_name}` : '-' }}</td>
              <td>{{ title(r.referral_type) }}</td>
              <td><span class="ibadge" :class="'ibadge-' + r.status">{{ title(r.status) }}</span></td>
              <td style="font-size:12px;white-space:nowrap">{{ fmt(r.created_at) }}</td>
              <td style="text-align:right"><button class="ibtn ibtn-o ibtn-sm" @click.stop="selected = r; editing = false">View</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="last > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:flex-end;gap:6px">
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === 1" @click="load(page - 1)">Prev</button>
        <button class="ibtn ibtn-o ibtn-sm" :disabled="page === last" @click="load(page + 1)">Next</button>
      </div>
    </div>

    <div v-if="selected" class="sr-overlay" @click.self="close">
      <div class="sr-box">
        <div style="padding:18px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Referral {{ selected.referral_code }}</div>
          <div style="font-size:12px;color:var(--stone)">{{ title(selected.referral_type) }} · {{ fmt(selected.created_at) }}</div>
        </div>
        <div style="padding:18px 22px;overflow-y:auto;display:flex;flex-direction:column;gap:12px">
          <div>
            <div class="sr-k">Student</div>
            <div class="sr-v">{{ selected.student ? `${selected.student.first_name} ${selected.student.last_name}` : '-' }}</div>
          </div>
          <div>
            <div class="sr-k">Status</div>
            <span class="ibadge" :class="'ibadge-' + selected.status">{{ title(selected.status) }}</span>
          </div>

          <template v-if="!editing">
            <div>
              <div class="sr-k">Reason for Referral</div>
              <div class="sr-v" style="white-space:pre-line;line-height:1.6">{{ selected.nature_of_concern }}</div>
            </div>
            <div v-if="canEdit(selected)" style="font-size:12px;color:var(--stone);background:var(--snow);border-radius:var(--r-sm);padding:8px 12px">
              GCU has not acknowledged this referral yet, so you can still edit it.
            </div>
          </template>

          <template v-else>
            <div>
              <label class="ifl">Service Requested</label>
              <select v-model="form.referral_type" class="ifse">
                <option v-for="t in typeChoices" :key="t" :value="t">{{ title(t) }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Reason for Referral <span style="color:var(--red)">*</span> <span style="color:var(--fog);font-weight:400">(at least 10 characters)</span></label>
              <textarea v-model="form.nature_of_concern" class="ifta" style="min-height:110px" maxlength="1000"></textarea>
            </div>
            <template v-if="selected.referral_type === 'disciplinary'">
              <div>
                <label class="ifl">Violation</label>
                <input v-model="form.violation_type" class="ifi" maxlength="255" />
              </div>
              <div>
                <label class="ifl">Incident Date</label>
                <input v-model="form.incident_date" type="date" class="ifi" />
              </div>
              <div>
                <label class="ifl">Incident Description</label>
                <textarea v-model="form.incident_description" class="ifta" style="min-height:80px" maxlength="2000"></textarea>
              </div>
            </template>
            <div v-if="error" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ error }}</div>
          </template>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--cloud);display:flex;gap:8px">
          <template v-if="!editing">
            <button v-if="canEdit(selected)" class="ibtn ibtn-p" @click="startEdit">Edit Referral</button>
            <button class="ibtn ibtn-o" @click="close">Close</button>
          </template>
          <template v-else>
            <button class="ibtn ibtn-p" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
            <button class="ibtn ibtn-o" :disabled="saving" @click="editing = false">Cancel</button>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import ListFilters from '../../components/ListFilters.vue';
import { useRoute } from 'vue-router';
import api, { studentApi } from '../../api/index';

const route = useRoute();
const isStudent = !!route.meta.student;
const http = isStudent ? studentApi : api;
const base = isStudent ? '/student/outreach/referrals' : '/my/referrals';

const rows = ref([]);
const loading = ref(true);
const page = ref(1);
const last = ref(1);
const selected = ref(null);
const editing = ref(false);
const saving = ref(false);
const error = ref('');
const form = ref({});

const ALL_TYPES = ['class_attendance', 'counseling', 'academic_deficiency', 'leave_of_absence', 'withdrawal', 'readmission', 'shifting'];
// A referral keeps to its own unit, so a GCU referral can only change to another GCU service.
const typeChoices = computed(() => {
  const t = selected.value?.referral_type;
  return t === 'disciplinary' || t === 'psychological_testing' ? [t] : ALL_TYPES;
});
// The sender may correct it until GCU acknowledges it.
const canEdit = r => r && r.status === 'submitted' && !r.acknowledged_at && !r.is_archived;

function close() { selected.value = null; editing.value = false; }
function startEdit() {
  const r = selected.value;
  form.value = {
    referral_type: r.referral_type,
    nature_of_concern: r.nature_of_concern || '',
    violation_type: r.violation_type || '',
    incident_date: r.incident_date ? String(r.incident_date).slice(0, 10) : '',
    incident_description: r.incident_description || '',
  };
  error.value = '';
  editing.value = true;
}
async function save() {
  error.value = '';
  if ((form.value.nature_of_concern || '').trim().length < 10) { error.value = 'Please write the reason in at least 10 characters.'; return; }
  saving.value = true;
  try {
    const payload = { referral_type: form.value.referral_type, nature_of_concern: form.value.nature_of_concern.trim() };
    if (selected.value.referral_type === 'disciplinary') {
      payload.violation_type = form.value.violation_type || null;
      payload.incident_date = form.value.incident_date || null;
      payload.incident_description = form.value.incident_description || null;
    }
    const res = await http.put(`${base}/${selected.value.id}`, payload);
    const idx = rows.value.findIndex(x => x.id === selected.value.id);
    if (idx >= 0) rows.value[idx] = { ...rows.value[idx], ...res.data };
    selected.value = rows.value[idx] || { ...selected.value, ...res.data };
    editing.value = false;
  } catch (e) {
    const errs = e.response?.data?.errors;
    error.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Could not save the changes.';
  } finally {
    saving.value = false;
  }
}

const filters = reactive({ search: '', sort: 'newest', status: '', referral_type: '' });
const options = ref({ types: [], statuses: [] });
const filtered = computed(() => !!(filters.search || filters.status || filters.referral_type || filters.sort !== 'newest'));
const filterSelects = computed(() => [
  { key: 'status', all: 'All Status', options: options.value.statuses.map(v => ({ value: v, label: title(v) })) },
  { key: 'referral_type', all: 'All Types', options: options.value.types.map(v => ({ value: v, label: title(v) })) },
]);

const fmt = d => (d ? new Date(d).toLocaleDateString() : '-');
const title = s => (s ? s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()).replace(/\b(Sdu|Gcu|Tmdu)\b/g, m => m.toUpperCase()) : '');

async function load(p = 1) {
  loading.value = true;
  try {
    const params = { page: p, sort: filters.sort };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    if (filters.referral_type) params.referral_type = filters.referral_type;
    const res = await http.get(base, { params });
    options.value = { types: res.data.filter_options?.types || [], statuses: res.data.filter_options?.statuses || [] };
    rows.value = res.data.data || [];
    page.value = res.data.current_page || 1;
    last.value = res.data.last_page || 1;
  } catch (e) {
    rows.value = [];
  } finally {
    loading.value = false;
  }
}
onMounted(() => load());
</script>

<style scoped>
.sr-overlay{position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px}
.sr-box{background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--sh-lg)}
.sr-k{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);margin-bottom:3px}
.sr-v{font-size:13.5px;color:var(--ink)}
</style>