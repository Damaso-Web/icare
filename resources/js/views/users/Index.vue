<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
    <h1>{{ isFacultyView ? 'Faculty Directory' : 'User Management' }}</h1>
    <p>{{ isFacultyView ? 'View and manage faculty member accounts.' : 'Manage system accounts and role-based access for all iCARE users.' }}</p>
  </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input v-model="filters.search" type="text" class="sin" placeholder="Search name or email..." style="width:220px" @input="fetchUsers" />
      </div>
      <select v-model="filters.role" class="fsm" @change="fetchUsers">
        <option value="">All Roles</option>
        <option value="admin">Admin / GCU Head</option>
        <option value="gcu_staff">GCU Staff</option>
        <option value="sdu_head">SDU Head</option>
        <option value="tmdu_staff">TMDU Staff</option>
        <option value="faculty">Faculty</option>
        <option value="dean_secretary">Dean's Secretary</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <button
      v-if="auth.isAdmin"
      class="ibtn ibtn-p ibtn-sm"
      style="margin-left:auto"
      @click="openCreate"
    >
      <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      {{ isFacultyView ? 'Add Faculty' : 'Add Employee' }}
    </button>
    </div>

    <!-- Users Table -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="users.length === 0" class="empty-state">
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
              <th>College / Department</th>
              <th>Last Login</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td style="cursor:pointer" @click="openView(u)">
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
              <td style="font-size:12px">{{ u.college || u.department || '—' }}</td>
              <td style="font-size:12px;color:var(--stone)">{{ u.last_login_at ? formatDate(u.last_login_at) : 'Never' }}</td>
              <td>
                <span class="ibadge" :style="u.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--stone)'">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="ibtn ibtn-g ibtn-sm" @click="openView(u)">View</button>
                  <button v-if="auth.isAdmin" class="ibtn ibtn-o ibtn-sm" @click="openEdit(u)">Edit</button>
                  <button
                    v-if="auth.isAdmin"
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

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" style="padding:12px 18px;border-top:1px solid var(--cloud);display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:var(--stone)">
          Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
        </span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- View Employee Profile Modal -->
    <div v-if="showViewModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showViewModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:22px;border-radius:var(--r-lg) var(--r-lg) 0 0;text-align:center">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 10px;font-family:var(--serif)">
            {{ initials(viewedUser.name) }}
          </div>
          <div style="font-size:15px;font-weight:600;color:#fff">{{ viewedUser.name }}</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px">{{ viewedUser.email }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:12px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Employee ID</div>
            <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ viewedUser.employee_id || '—' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Role</div>
            <span class="ibadge" :style="roleStyle(viewedUser.role)">{{ roleLabel(viewedUser.role) }}</span>
          </div>
          <div v-if="viewedUser.college">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedUser.college }}</div>
          </div>
          <div v-if="viewedUser.department">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Department</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedUser.department }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Status</div>
            <span class="ibadge" :style="viewedUser.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--stone)'">
              {{ viewedUser.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Last Login</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedUser.last_login_at ? formatDate(viewedUser.last_login_at) : 'Never' }}</div>
          </div>
          <button class="ibtn ibtn-o" style="width:100%;justify-content:center;margin-top:8px" @click="showViewModal = false">Close</button>
        </div>
      </div>
    </div>

    <!-- Add / Edit Employee Modal — admin only -->
    <div v-if="showModal && auth.isAdmin" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ isEditing ? 'Edit Employee' : 'Add New Employee' }}</div>
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
            <select v-if="!isFacultyView" v-model="filters.role" class="fsm" @change="fetchUsers">
            <option value="">All Roles</option>
            <option value="admin">Admin / GCU Head</option>
            <option value="gcu_staff">GCU Staff</option>
            <option value="sdu_head">SDU Head</option>
            <option value="tmdu_staff">TMDU Staff</option>
            <option value="faculty">Faculty</option>
            <option value="dean_secretary">Dean's Secretary</option>
          </select>
          </div>
          <div v-if="['faculty','dean_secretary'].includes(userForm.role)">
            <label class="ifl">College</label>
            <select v-model="userForm.college" class="ifse">
              <option value="">Select college...</option>
              <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div v-if="['faculty','dean_secretary'].includes(userForm.role)">
            <label class="ifl">Department</label>
            <input v-model="userForm.department" class="ifi" placeholder="e.g. Information Technology" />
          </div>
          <div>
          <label class="ifl">Contact Number</label>
          <input
            v-model="userForm.contact_number"
            class="ifi"
            placeholder="e.g. 09171234567"
            maxlength="11"
            @input="userForm.contact_number = userForm.contact_number.replace(/[^0-9]/g, '').slice(0, 11)"
          />
        </div>
          <div v-if="!isEditing">
          <label class="ifl">Password <span style="color:var(--red)">*</span></label>
          <input v-model="userForm.password" type="password" class="ifi" placeholder="Min. 8 chars, 1 uppercase, 1 number, 1 symbol" />
          <div style="font-size:11px;color:var(--stone);margin-top:4px">
            Must contain: 8+ characters, 1 uppercase letter, 1 number, 1 special character (!@#$%^&*)
          </div>
          <input v-model="userForm.password_confirmation" type="password" class="ifi" placeholder="Confirm password" style="margin-top:8px" />
          <div v-if="userForm.password && !isPasswordValid" style="font-size:11px;color:var(--red);margin-top:4px">
            Password does not meet requirements
          </div>
        </div>
          <div style="display:flex;gap:8px;padding-top:4px">
            <button class="ibtn ibtn-p" @click="saveUser">
              <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              {{ isEditing ? 'Save Changes' : 'Create Employee' }}
            </button>
            <button class="ibtn ibtn-o" @click="showModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { userAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { COLLEGES } from '../../constants/colleges';
import { ref, onMounted, inject, computed } from 'vue';
import { useRoute } from 'vue-router';

const toast      = inject('toast');
const auth       = useAuthStore();
const loading    = ref(true);
const showModal  = ref(false);
const showViewModal = ref(false);
const isEditing  = ref(false);
const users      = ref([]);
const pagination = ref({});
const filters    = ref({ search: '', role: '' });
const colleges   = COLLEGES;
const viewedUser = ref({});
const route = useRoute();
const isFacultyView = computed(() => route.name === 'faculty-directory');

const userForm = ref({
  name: '', email: '', employee_id: '', role: '',
  college: '', department: '', contact_number: '',
  password: '', password_confirmation: '',
});

const isPasswordValid = computed(() => {
  const p = userForm.value.password;
  return p.length >= 8 && /[A-Z]/.test(p) && /[0-9]/.test(p) && /[!@#$%^&*(),.?":{}|<>]/.test(p);
});

async function fetchUsers(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters.value, page };
    if (isFacultyView.value) {
      params.role = 'faculty';
    }
    const res = await userAPI.index(params);
    users.value      = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function openView(u) {
  viewedUser.value = u;
  showViewModal.value = true;
}

function openCreate() {
  if (!auth.isAdmin) return;
  isEditing.value = false;
  userForm.value  = {
    name: '', email: '', employee_id: '',
    role: isFacultyView.value ? 'faculty' : '',
    college: '', department: '', contact_number: '',
    password: '', password_confirmation: '',
  };
  showModal.value = true;
}

function openEdit(u) {
  if (!auth.isAdmin) return;
  isEditing.value = true;
  userForm.value  = { ...u, password: '', password_confirmation: '' };
  showModal.value = true;
}

async function saveUser() {
  if (!auth.isAdmin) {
    toast?.error('Only administrators can manage users.');
    return;
  }
  if (!userForm.value.name || !userForm.value.email || !userForm.value.role) {
    toast?.error('Please fill in all required fields.');
    return;
  }
  if (!isEditing.value && !isPasswordValid.value) {
    toast?.error('Password must be 8+ characters with an uppercase letter, number, and special character.');
    return;
  }
  if (!isEditing.value && userForm.value.password !== userForm.value.password_confirmation) {
    toast?.error('Passwords do not match.');
    return;
  }
  try {
    if (isEditing.value) {
      await userAPI.update(userForm.value.id, userForm.value);
      toast?.success('Employee updated successfully.');
    } else {
      await userAPI.store(userForm.value);
      toast?.success('Employee created successfully.');
    }
    showModal.value = false;
    fetchUsers();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to save employee.');
  }
}

async function toggleActive(u) {
  if (!auth.isAdmin) {
    toast?.error('Only administrators can activate/deactivate employees.');
    return;
  }
  try {
    await userAPI.toggleActive(u.id);
    u.is_active = !u.is_active;
    toast?.success(`Employee ${u.is_active ? 'activated' : 'deactivated'}.`);
  } catch (e) {
    toast?.error('Failed to update employee status.');
  }
}

function changePage(page) { fetchUsers(page); }

function resetFilters() {
  filters.value = { search: '', role: '' };
  fetchUsers();
}

function roleLabel(role) {
  const labels = {
    admin:          'Admin / GCU Head',
    gcu_staff:      'GCU Staff',
    sdu_head:       'SDU Head',
    tmdu_staff:     'TMDU Staff',
    faculty:        'Faculty',
    dean_secretary: "Dean's Secretary",
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

onMounted(() => fetchUsers());
</script>