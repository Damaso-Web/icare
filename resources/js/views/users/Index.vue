<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>User Management</h1>
      <p>Manage system accounts and role-based access for all iCARE users.</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="search" type="text" class="sin" placeholder="Search name or email..." style="width:220px" />
      </div>
      <select v-model="filterRole" class="fsm">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="gcu_staff">GCU Staff</option>
        <option value="sdu_head">SDU Head</option>
        <option value="tmdu_staff">TMDU Staff</option>
        <option value="faculty">Faculty</option>
        <option value="dean_secretary">Dean's Secretary</option>
      </select>
      <select v-model="filterStatus" class="fsm">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <button class="ibtn ibtn-p ibtn-sm" style="margin-left:auto" @click="openCreate">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add User
      </button>
    </div>

    <!-- Users Table -->
    <div class="icard">
      <div v-if="filtered.length === 0" class="empty-state">
        <h3>No users found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>User</th>
              <th>Employee ID</th>
              <th>Role</th>
              <th>Unit / College</th>
              <th>Last Login</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in filtered" :key="u.id">
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="iav">{{ initials(u.name) }}</div>
                  <div>
                    <div style="font-weight:600;color:var(--ink)">{{ u.name }}</div>
                    <div style="font-size:11px;color:var(--fog)">{{ u.email }}</div>
                  </div>
                </div>
              </td>
              <td style="font-family:var(--mono);font-size:12px">{{ u.employee_id || '—' }}</td>
              <td><span class="ibadge" :style="roleStyle(u.role)">{{ roleLabel(u.role) }}</span></td>
              <td style="font-size:12px">{{ u.unit || u.college || '—' }}</td>
              <td style="font-size:12px;color:var(--stone)">{{ u.last_login ? formatDate(u.last_login) : 'Never' }}</td>
              <td>
                <span class="ibadge" :style="u.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--stone)'">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openEdit(u)">Edit</button>
                  <button
                    class="ibtn ibtn-sm"
                    :style="u.is_active ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : 'background:var(--mist);color:var(--moss);border:1.5px solid var(--mint)'"
                    @click="toggleActive(u)"
                  >
                    {{ u.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- User Modal -->
    <div v-if="showModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ isEditing ? 'Edit User' : 'Add New User' }}</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label class="ifl">Full Name <span style="color:var(--red)">*</span></label>
            <input v-model="userForm.name" class="ifi" placeholder="e.g. Dr. Maria Reyes" />
          </div>
          <div>
            <label class="ifl">Email <span style="color:var(--red)">*</span></label>
            <input v-model="userForm.email" type="email" class="ifi" placeholder="name@bsu.edu.ph" />
          </div>
          <div>
            <label class="ifl">Employee ID</label>
            <input v-model="userForm.employee_id" class="ifi" placeholder="e.g. BSU-GCU-001" />
          </div>
          <div>
            <label class="ifl">Role <span style="color:var(--red)">*</span></label>
            <select v-model="userForm.role" class="ifse">
              <option value="">Select role...</option>
              <option value="admin">Admin</option>
              <option value="gcu_staff">GCU Staff</option>
              <option value="sdu_head">SDU Head</option>
              <option value="tmdu_staff">TMDU Staff</option>
              <option value="faculty">Faculty</option>
              <option value="dean_secretary">Dean's Secretary</option>
            </select>
          </div>
          <div v-if="['gcu_staff','sdu_head','tmdu_staff','admin'].includes(userForm.role)">
            <label class="ifl">Unit</label>
            <select v-model="userForm.unit" class="ifse">
              <option value="GCU">GCU</option>
              <option value="SDU">SDU</option>
              <option value="TMDU">TMDU</option>
              <option value="OSS">OSS</option>
            </select>
          </div>
          <div v-if="['faculty','dean_secretary'].includes(userForm.role)">
            <label class="ifl">College</label>
            <select v-model="userForm.college" class="ifse">
              <option value="">Select college...</option>
              <option>CIT</option>
              <option>CAS</option>
              <option>CEA</option>
              <option>CB</option>
              <option>CED</option>
              <option>CA</option>
            </select>
          </div>
          <div v-if="!isEditing">
            <label class="ifl">Password <span style="color:var(--red)">*</span></label>
            <input v-model="userForm.password" type="password" class="ifi" placeholder="Min. 8 characters" />
          </div>
          <div style="display:flex;gap:8px;padding-top:4px">
            <button class="ibtn ibtn-p" @click="saveUser">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              {{ isEditing ? 'Save Changes' : 'Create User' }}
            </button>
            <button class="ibtn ibtn-o" @click="showModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const search       = ref('');
const filterRole   = ref('');
const filterStatus = ref('');
const showModal    = ref(false);
const isEditing    = ref(false);

const users = ref([
  { id: 1, name: 'System Administrator', email: 'admin@bsu.edu.ph',    employee_id: 'BSU-ADMIN-001', role: 'admin',          unit: 'OSS',  college: null, is_active: true,  last_login: '2026-07-12' },
  { id: 2, name: 'Dr. Maria Reyes',      email: 'mreyes@bsu.edu.ph',   employee_id: 'BSU-GCU-001',  role: 'gcu_staff',      unit: 'GCU',  college: null, is_active: true,  last_login: '2026-07-12' },
  { id: 3, name: 'Ms. Ana Cruz',         email: 'acruz@bsu.edu.ph',    employee_id: 'BSU-GCU-002',  role: 'gcu_staff',      unit: 'GCU',  college: null, is_active: true,  last_login: '2026-07-11' },
  { id: 4, name: 'Mr. Ramon Valdez',     email: 'rvaldez@bsu.edu.ph',  employee_id: 'BSU-SDU-001',  role: 'sdu_head',       unit: 'SDU',  college: null, is_active: true,  last_login: '2026-07-10' },
  { id: 5, name: 'Ms. Grace Tamayo',     email: 'gtamayo@bsu.edu.ph',  employee_id: 'BSU-TMDU-001', role: 'tmdu_staff',     unit: 'TMDU', college: null, is_active: true,  last_login: '2026-07-09' },
  { id: 6, name: 'Prof. Juan Dela Cruz', email: 'jdelacruz@bsu.edu.ph',employee_id: 'BSU-FAC-001',  role: 'faculty',        unit: null,   college: 'CIT',is_active: true,  last_login: '2026-07-08' },
  { id: 7, name: 'Ms. Liza Santos',      email: 'lsantos@bsu.edu.ph',  employee_id: 'BSU-DS-001',   role: 'dean_secretary', unit: null,   college: 'CIT',is_active: false, last_login: '2026-06-30' },
]);

const userForm = ref({
  name: '', email: '', employee_id: '', role: '', unit: '', college: '', password: '',
});

const filtered = computed(() => {
  return users.value.filter(u => {
    const matchSearch = !search.value ||
      u.name.toLowerCase().includes(search.value.toLowerCase()) ||
      u.email.toLowerCase().includes(search.value.toLowerCase());
    const matchRole   = !filterRole.value   || u.role === filterRole.value;
    const matchStatus = !filterStatus.value ||
      (filterStatus.value === 'active' ? u.is_active : !u.is_active);
    return matchSearch && matchRole && matchStatus;
  });
});

function openCreate() {
  isEditing.value = false;
  userForm.value  = { name: '', email: '', employee_id: '', role: '', unit: '', college: '', password: '' };
  showModal.value = true;
}

function openEdit(u) {
  isEditing.value = true;
  userForm.value  = { ...u };
  showModal.value = true;
}

function saveUser() {
  if (!userForm.value.name || !userForm.value.email || !userForm.value.role) return;
  if (isEditing.value) {
    const idx = users.value.findIndex(u => u.id === userForm.value.id);
    if (idx !== -1) users.value[idx] = { ...userForm.value };
  } else {
    users.value.push({ ...userForm.value, id: users.value.length + 1, is_active: true, last_login: null });
  }
  showModal.value = false;
}

function toggleActive(u) {
  u.is_active = !u.is_active;
}

function resetFilters() {
  search.value       = '';
  filterRole.value   = '';
  filterStatus.value = '';
}

function roleLabel(role) {
  const labels = {
    admin: 'Admin', gcu_staff: 'GCU Staff', sdu_head: 'SDU Head',
    tmdu_staff: 'TMDU Staff', faculty: 'Faculty', dean_secretary: "Dean's Secretary",
  };
  return labels[role] || role;
}

function roleStyle(role) {
  const styles = {
    admin:          'background:#1a1a2e;color:#fff',
    gcu_staff:      'background:var(--mist);color:var(--moss)',
    sdu_head:       'background:var(--amber-lt);color:var(--amber)',
    tmdu_staff:     'background:var(--purple-lt);color:var(--purple)',
    faculty:        'background:var(--blue-lt);color:var(--blue)',
    dean_secretary: 'background:var(--cloud);color:var(--stone)',
  };
  return styles[role] || '';
}

function initials(name) {
  return name?.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '—';
}
</script>