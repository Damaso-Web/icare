<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>{{ isFacultyView ? 'Faculty Profile' : 'User Management' }}</h1>
      <p>{{ isFacultyView ? 'View and manage faculty member accounts.' : 'Manage system accounts and role-based access for all iCARE users.' }}</p>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button
        class="ibtn ibtn-sm"
        :style="!showInactive ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="switchStatusTab(false)"
      >
        {{ isFacultyView ? 'Active Faculty' : 'Active' }}
      </button>
      <button
        class="ibtn ibtn-sm"
        :style="showInactive ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="switchStatusTab(true)"
      >
        {{ isFacultyView ? 'Inactive Faculty' : 'Inactive' }}
      </button>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="sw">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
           maxlength="15"
          placeholder="Search name, email, or employee ID..."
          style="width:220px"
          @keypress="blockSpecialKeypress"
          @input="onSearchLetters"
        />
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
      <select v-if="!isFacultyView" v-model="filters.role" class="fsm" @change="fetchUsers">
        <option value="">All Roles</option>
        <option value="admin">Admin / GCU Head</option>
        <option value="gcu_staff">GCU Staff</option>
        <option value="sdu_head">SDU Head</option>
        <option value="tmdu_staff">TMDU Staff</option>
        <option value="faculty">Faculty</option>
        <option value="dean_secretary">Dean's Secretary</option>
      </select>
      <select v-model="filters.college" class="fsm" @change="fetchUsers">
        <option value="">All Colleges</option>
        <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="sortOption" class="fsm" @change="applySort">
        <option value="created_at:desc">Newest First</option>
        <option value="created_at:asc">Oldest First</option>
        <option value="employee_id:asc">Employee ID: Ascending</option>
        <option value="employee_id:desc">Employee ID: Descending</option>
        <option value="last_name:asc">Name: A-Z</option>
        <option value="last_name:desc">Name: Z-A</option>
      </select>
      <button v-if="auth.isAdmin && !showInactive" class="ibtn ibtn-o ibtn-sm" @click="showImportModal = true">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload Masterlist
      </button>
      <button
        v-if="auth.isAdmin && !showInactive"
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
              <th>Employee ID</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id" :style="!u.is_active ? 'opacity:0.55;background:var(--snow)' : ''">
              <td style="font-family:var(--mono);font-size:13px;font-weight:600;cursor:pointer" @click="openView(u)">{{ u.employee_id || '-' }}</td>
              <td>
                <span class="ibadge" :style="u.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--ink);border:1px solid var(--fog)'">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openView(u)">View</button>
                  <button
                    v-if="auth.isAdmin"
                    class="ibtn ibtn-sm"
                    :style="u.is_active ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : 'background:var(--mint);color:var(--forest);border:1.5px solid var(--moss)'"
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
          Showing {{ pagination.from }}-{{ pagination.to }} of {{ pagination.total }}
        </span>
        <div style="display:flex;gap:6px">
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === 1" @click="changePage(pagination.current_page - 1)">Prev</button>
          <button class="ibtn ibtn-o ibtn-sm" :disabled="pagination.current_page === pagination.last_page" @click="changePage(pagination.current_page + 1)">Next</button>
        </div>
      </div>
    </div>

    <!-- View Employee Profile Modal -->
    <div v-if="showViewModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showViewModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:22px;border-radius:var(--r-lg) var(--r-lg) 0 0;text-align:center">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 10px;font-family:var(--serif)">
            {{ initials(viewedUser.first_name, viewedUser.last_name) }}
          </div>
          <div style="font-size:15px;font-weight:600;color:#fff">{{ viewedUser.last_name }}, {{ viewedUser.first_name }} {{ viewedUser.middle_name }} {{ viewedUser.suffix }}</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px">{{ viewedUser.email }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:12px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Employee ID</div>
            <div style="font-size:13px;color:var(--ink);font-family:var(--mono)">{{ viewedUser.employee_id || '-' }}</div>
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
          <div v-if="viewedUser.contact_number">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact Number</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedUser.contact_number }}</div>
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

          <div v-if="auth.isAdmin && viewedUser.must_change_password" style="background:var(--snow);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px 14px">
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Temporary Password</div>
              <button
                type="button"
                @click="toggleTempPasswordVisible"
                :disabled="!viewedUser.is_active"
                :style="{ background:'none', border:'none', cursor: viewedUser.is_active ? 'pointer' : 'not-allowed', color:'var(--fog)', padding:'2px', display:'flex', alignItems:'center', opacity: viewedUser.is_active ? 1 : .5 }"
              >
                <svg v-if="!showTempPassword" viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" style="width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round">
                  <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                  <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
              </button>
            </div>
            <div style="font-size:14px;color:var(--ink);font-family:var(--mono);margin-top:4px">
              {{ showTempPassword ? (tempPasswordValue || 'Loading...') : '••••••••••' }}
            </div>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">User hasn't changed their password yet.</div>
          </div>

          <div v-if="resetPasswordResult" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
            ✓ New password: <strong style="font-family:var(--mono)">{{ resetPasswordResult }}</strong>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Share this with the employee.</div>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px">
            <button
              v-if="auth.isAdmin"
              class="ibtn ibtn-p"
              :disabled="!viewedUser.is_active"
              :style="{ flex:1, justifyContent:'center', opacity: viewedUser.is_active ? 1 : .5, cursor: viewedUser.is_active ? 'pointer' : 'not-allowed' }"
              @click="openEditFromView"
            >Edit</button>
            <button class="ibtn ibtn-g" style="flex:1;justify-content:center" @click="showViewModal = false">Close</button>
          </div>
          <button
            v-if="auth.isAdmin"
            class="ibtn ibtn-sm"
            :disabled="!viewedUser.is_active"
            :style="{ width:'100%', justifyContent:'center', background:'var(--amber-lt)', color:'var(--amber)', border:'1.5px solid var(--amber)', opacity: viewedUser.is_active ? 1 : .5, cursor: viewedUser.is_active ? 'pointer' : 'not-allowed' }"
            @click="resetPassword(viewedUser)"
          >Reset Password</button>
        </div>
      </div>
    </div>

    <!-- Add / Edit Employee Modal - admin only -->
    <div v-if="showModal && auth.isAdmin" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">{{ isEditing ? ((isFacultyView || userForm.role === 'faculty') ? 'Edit Faculty Profile' : 'Edit Employee Profile') : (isFacultyView ? 'Add Faculty' : 'Add Employee') }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div>
            <label class="ifl">Employee ID <span style="color:var(--red)">*</span></label>
            <input
            v-model="userForm.employee_id"
            class="ifi"
             maxlength="15"
            placeholder="e.g. 12345"
            :readonly="isEditing"
            :style="(isEditing ? 'background:var(--snow);color:var(--stone);' : '') + errorStyle('employee_id')"
            @input="userForm.employee_id = onlyDigits(userForm.employee_id); clearFieldError('employee_id')"
          />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="userForm.last_name"
                class="ifi"
                 maxlength="20"
                placeholder="Reyes"
                :style="errorStyle('last_name')"
                @input="userForm.last_name = titleCase(onlyLetters(userForm.last_name)); clearFieldError('last_name')"
              />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="userForm.first_name"
                class="ifi"
                 maxlength="20"
                placeholder="Maria"
                :style="errorStyle('first_name')"
                @input="userForm.first_name = titleCase(onlyLetters(userForm.first_name)); clearFieldError('first_name')"
              />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
            <div>
              <label class="ifl">Middle Name</label>
              <input
                v-model="userForm.middle_name"
                class="ifi"
                placeholder="Santos"
                maxlength="20"
                @input="userForm.middle_name = titleCase(onlyLetters(userForm.middle_name)).slice(0, 20)"
              />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <select v-model="userForm.suffix" class="ifse">
                <option value="">None</option>
                <option value="Jr.">Jr.</option>
                <option value="Sr.">Sr.</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
                <option value="V">V</option>
              </select>
            </div>
          </div>
          <div>
            <label class="ifl">Email Address <span style="color:var(--red)">*</span></label>
            <input
              v-model="userForm.email"
              type="email"
              class="ifi"
              placeholder="name@bsu.edu.ph"
              :style="errorStyle('email')"
              @input="clearFieldError('email')"
            />
          </div>
          <div>
            <label class="ifl">Role <span style="color:var(--red)">*</span></label>
            <select
              v-model="userForm.role"
              class="ifse"
              :disabled="isFacultyView"
              :style="errorStyle('role')"
              @change="clearFieldError('role')"
            >
              <option value="" disabled hidden>Select role...</option>
              <option value="admin">Admin / GCU Head</option>
              <option value="gcu_staff">GCU Staff</option>
              <option value="sdu_head">SDU Head</option>
              <option value="tmdu_staff">TMDU Staff</option>
              <option value="faculty">Faculty</option>
              <option value="dean_secretary">Dean's Secretary</option>
            </select>
          </div>
          <div v-if="['faculty','dean_secretary'].includes(userForm.role)">
            <label class="ifl">College <span style="color:var(--red)">*</span></label>
            <select
              v-model="userForm.college"
              class="ifse"
              :style="errorStyle('college')"
              @change="userForm.department = ''; clearFieldError('college')"
            >
              <option value="" disabled hidden>Select college...</option>
              <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>
          <div v-if="['faculty','dean_secretary'].includes(userForm.role)">
            <label class="ifl">Department <span style="color:var(--red)">*</span></label>
            <select
              v-model="userForm.department"
              class="ifse"
              :disabled="!userForm.college"
              :style="errorStyle('department')"
              @change="clearFieldError('department')"
            >
              <option value="" disabled hidden>Select department...</option>
              <option v-for="d in availableDepartments" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
          <div>
            <label class="ifl">Contact Number <span style="color:var(--red)">*</span></label>
            <input
              v-model="userForm.contact_number"
              class="ifi"
              placeholder="09171234567"
              maxlength="11"
              :style="errorStyle('contact_number')"
              @input="userForm.contact_number = contactNumberBlockingNonZero(userForm.contact_number); clearFieldError('contact_number')"
            />
          </div>
          <div v-if="!isEditing">
            <label class="ifl">Temporary Password</label>
            <div style="display:flex;gap:8px;align-items:center">
              <input v-model="userForm.password" type="text" class="ifi" readonly style="font-family:var(--mono)" />
              <button type="button" class="ibtn ibtn-o ibtn-sm" @click="generatePassword">Regenerate</button>
            </div>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">
              A temporary password has been generated. The employee should change it after logging in.
            </div>
          </div>
          <div v-if="formError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
            {{ formError }}
          </div>
          <div style="display:flex;gap:8px;padding-top:4px">
            <button class="ibtn ibtn-p" :disabled="isUserFormUnchanged" @click="goToUserPreview">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            {{ isEditing ? 'Save Changes' : (isFacultyView ? 'Add Faculty' : 'Add Employee') }}
          </button>
          <button
            v-if="!isEditing"
            class="ibtn ibtn-o"
            :disabled="isAddFormEmpty"
            :style="isAddFormEmpty ? '' : 'color:var(--red);border-color:#f5c0c0'"
            @click="handleClearForm"
          >Clear Form</button>
          <button class="ibtn ibtn-o" @click="showModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Confirmation Preview Modal -->
<div v-if="showUserPreview" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:65;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showUserPreview = false">
  <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
    <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
      <div style="font-size:15px;font-weight:600;color:var(--ink)">
        {{ isEditing ? 'Confirm Changes' : (isFacultyView ? 'Confirm New Faculty' : 'Confirm New Employee') }}
      </div>
      <button class="ibtn ibtn-g ibtn-sm" @click="showUserPreview = false">✕</button>
    </div>
    <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
      <div style="font-size:13px;color:var(--stone)">
        Please review the information below before {{ isEditing ? 'saving' : 'adding' }}:
      </div>

      <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
        <div><strong>Employee ID:</strong> {{ userForm.employee_id }}</div>
        <div><strong>Name:</strong> {{ userForm.last_name }}, {{ userForm.first_name }} {{ userForm.middle_name }} {{ userForm.suffix }}</div>
        <div><strong>Email:</strong> {{ userForm.email }}</div>
        <div><strong>Role:</strong> {{ roleLabel(userForm.role) }}</div>
        <div v-if="userForm.college"><strong>College:</strong> {{ userForm.college }}</div>
        <div v-if="userForm.department"><strong>Department:</strong> {{ userForm.department }}</div>
        <div><strong>Contact Number:</strong> {{ userForm.contact_number }}</div>
        <div v-if="!isEditing && userForm.password" style="padding-top:4px;border-top:1px dashed var(--cloud);margin-top:4px">
          <strong>Temporary Password:</strong>
          <span style="font-family:var(--mono);color:var(--forest)">{{ userForm.password }}</span>
          <div style="font-size:11px;color:var(--stone);margin-top:2px">Share this with the {{ isFacultyView ? 'faculty member' : 'employee' }}.</div>
        </div>
      </div>

      <div style="display:flex;gap:8px">
        <button class="ibtn ibtn-p" @click="confirmUserSubmit">
          {{ isEditing ? 'Confirm & Save' : (isFacultyView ? 'Confirm & Add Faculty' : 'Confirm & Add Employee') }}
        </button>
        <button class="ibtn ibtn-o" @click="showUserPreview = false">Go Back &amp; Edit</button>
      </div>
    </div>
  </div>
</div>

    <!-- Import Modal -->
    <div v-if="showImportModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showImportModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Upload {{ isFacultyView ? 'Faculty' : 'Employee' }} Masterlist</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showImportModal = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--stone);line-height:1.6">
            Download the template below, fill it in, then upload it here.
          </div>
          <a :href="isFacultyView ? '/templates/faculty_masterlist_template.xlsx' : '/templates/employee_masterlist_template.xlsx'" download class="ibtn ibtn-o" style="width:100%;justify-content:center">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Template
          </a>
          <div>
            <label class="ifl">File</label>
            <input type="file" accept=".csv,.xlsx,.xls" class="ifi" @change="handleImportFileSelect" />
          </div>
          <div v-if="importResult" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
            ✓ {{ importResult.created }} employees added, {{ importResult.skipped }} skipped.
            <div v-if="importResult.errors?.length" style="margin-top:6px;font-size:11px;color:var(--red)">
              <div v-for="(err, i) in importResult.errors" :key="i">{{ err }}</div>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="openImportConfirm" :disabled="!importFile || importing">
              <span v-if="importing" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ importing ? 'Uploading...' : 'Upload' }}
            </button>
            <button class="ibtn ibtn-o" @click="showImportModal = false">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Import Confirmation Modal -->
    <div v-if="showImportConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:65;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showImportConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Upload</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showImportConfirm = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone)">Please confirm you want to upload this file:</div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;font-size:13px">
            <div><strong>Type:</strong> {{ isFacultyView ? 'Faculty' : 'Employee' }} Masterlist</div>
            <div style="margin-top:6px"><strong>File:</strong> {{ importFile?.name }}</div>
          </div>
          <div style="font-size:12px;color:var(--stone)">
            Once uploaded, new records will be added and duplicates handled by the backend.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="doConfirmedImport" :disabled="importing">
              <span v-if="importing" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ importing ? 'Uploading...' : 'Confirm & Upload' }}
            </button>
            <button class="ibtn ibtn-o" @click="showImportConfirm = false">Go Back</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Clear Form Confirmation -->
    <div v-if="showClearConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showClearConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Clear Form?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            All fields in this form will be cleared. This cannot be undone.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="doConfirmedClear">Yes, Clear Form</button>
            <button class="ibtn ibtn-o" @click="showClearConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reset Password Confirmation -->
    <div v-if="showResetPwConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showResetPwConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Reset Password?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            Reset password for <strong>{{ userToReset?.first_name }} {{ userToReset?.last_name }}</strong>? A new temporary password will be generated.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click="doConfirmedResetPw">Yes, Reset Password</button>
            <button class="ibtn ibtn-o" @click="showResetPwConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Toggle Active Confirmation -->
    <div v-if="showToggleConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showToggleConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink);text-transform:capitalize">{{ toggleAction }} Account?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            Are you sure you want to {{ toggleAction }} <strong>{{ userToToggle?.first_name }} {{ userToToggle?.last_name }}</strong>'s account?
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="doConfirmedToggle" style="text-transform:capitalize">Yes, {{ toggleAction }}</button>
            <button class="ibtn ibtn-o" @click="showToggleConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, watch, inject, computed } from 'vue';
import { useRoute } from 'vue-router';
import { userAPI } from '../../api/index';
import { useAuthStore } from '../../stores/auth';
import { COLLEGES } from '../../constants/colleges';
import { DEPARTMENTS_BY_COLLEGE } from '../../constants/departments';
import { onlyLetters, onlyDigits, contactNumberInput, isValidEmail, isValidPHContact, safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

function titleCase(str) {
  return (str || '').replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
}

const fieldErrors = ref({});

function clearFieldError(field) {
  if (fieldErrors.value[field]) {
    fieldErrors.value = { ...fieldErrors.value, [field]: false };
  }
}

function errorStyle(field) {
  return fieldErrors.value[field]
    ? 'border-color:var(--red);border-width:1.5px'
    : '';
}

const route      = useRoute();
const toast      = inject('toast');
const auth       = useAuthStore();
const loading    = ref(true);
const showModal  = ref(false);
const showViewModal = ref(false);
const isEditing  = ref(false);
const users      = ref([]);
const pagination = ref({});
const filters    = ref({ search: '', role: '', college: '', sort_by: 'created_at', sort_dir: 'desc' });
const sortOption = ref('created_at:desc');
const showInactive = ref(false);

const showClearConfirm = ref(false);
const showUserPreview = ref(false);

const showResetPwConfirm = ref(false);
const userToReset = ref(null);

function openResetPwConfirm(u) {
  userToReset.value = u;
  showResetPwConfirm.value = true;
}

async function doConfirmedResetPw() {
  const u = userToReset.value;
  showResetPwConfirm.value = false;
  if (!u) return;
  try {
    const res = await userAPI.resetPassword(u.id);
    if (res.data?.temp_password) {
      toast?.success(`Password reset. New temporary password: ${res.data.temp_password}`);
    } else {
      toast?.success('Password reset successfully.');
    }
    fetchUsers?.();
  } catch (e) {
    toast?.error('Failed to reset password.');
  }
}

const showToggleConfirm = ref(false);
const userToToggle = ref(null);
const toggleAction = ref('');

function openToggleConfirm(u, action) {
  userToToggle.value = u;
  toggleAction.value = action;
  showToggleConfirm.value = true;
}

async function doConfirmedToggle() {
  const u = userToToggle.value;
  showToggleConfirm.value = false;
  if (!u) return;
  try {
    await userAPI.toggleActive(u.id);
    toast?.success(`Account ${toggleAction.value}d successfully.`);
    fetchUsers();
  } catch (e) {
    toast?.error('Please try again.');
  }
}

function openClearConfirm() {
  if (isAddFormEmpty.value) return;
  showClearConfirm.value = true;
}

function doConfirmedClear() {
  showClearConfirm.value = false;
  resetUserForm();
}

function applySort() {
  const [sortBy, sortDir] = sortOption.value.split(':');
  filters.value.sort_by = sortBy;
  filters.value.sort_dir = sortDir;
  fetchUsers();
}
const colleges   = COLLEGES;
const viewedUser = ref({});
const formError  = ref('');
const resetPasswordResult = ref('');
const showTempPassword = ref(false);
const tempPasswordValue = ref('');
const userFormSnapshot = ref('');

const isFacultyView = computed(() => route.name === 'faculty-directory');
const availableDepartments = computed(() => DEPARTMENTS_BY_COLLEGE[userForm.value.college] || []);



const userForm = ref({
  first_name: '', middle_name: '', last_name: '', suffix: '', email: '', employee_id: '', role: '',
  college: '', department: '', contact_number: '',
  password: '', password_confirmation: '',
});

const isUserFormUnchanged = computed(() =>
  isEditing.value && JSON.stringify(userForm.value) === userFormSnapshot.value
);

const isAddFormEmpty = computed(() =>
  !userForm.value.first_name &&
  !userForm.value.last_name &&
  !userForm.value.middle_name &&
  !userForm.value.suffix &&
  !userForm.value.email &&
  !userForm.value.employee_id &&
  !userForm.value.contact_number &&
  !userForm.value.college &&
  !userForm.value.department
);

const showImportModal = ref(false);
const importFile      = ref(null);
const importing       = ref(false);
const importResult    = ref(null);
const showImportConfirm = ref(false);

function switchStatusTab(inactive) {
  showInactive.value = inactive;
  fetchUsers();
}

function onSearchLetters() {
  filters.value.search = safeSearchInput(filters.value.search);
  fetchUsers();
}

async function fetchUsers(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters.value, page };
    if (isFacultyView.value) params.role = 'faculty';
    params.is_active = showInactive.value ? 0 : 1;
    const res = await userAPI.index(params);
    users.value      = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function generatePassword() {
  const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  const lower = 'abcdefghijkmnpqrstuvwxyz';
  const nums  = '23456789';
  const symbols = '!@#$%^&*';
  let pwd = upper[Math.floor(Math.random()*upper.length)]
          + lower[Math.floor(Math.random()*lower.length)]
          + nums[Math.floor(Math.random()*nums.length)]
          + symbols[Math.floor(Math.random()*symbols.length)];
  const all = upper + lower + nums + symbols;
  for (let i = 0; i < 8; i++) pwd += all[Math.floor(Math.random()*all.length)];
  userForm.value.password = pwd.split('').sort(() => Math.random() - 0.5).join('');
  userForm.value.password_confirmation = userForm.value.password;
}

async function openView(u) {
  resetPasswordResult.value = '';
  showTempPassword.value = false;
  tempPasswordValue.value = '';
  viewedUser.value = u;
  showViewModal.value = true;
  try {
    const res = await userAPI.show(u.id);
    viewedUser.value = res.data;
  } catch (e) {
    // Non-fatal - falls back to the row data already shown.
  }
}

function openEditFromView() {
  showViewModal.value = false;
  openEdit(viewedUser.value);
}

function resetPassword(u) {
  openResetPwConfirm(u);
}

async function toggleTempPasswordVisible() {
  showTempPassword.value = !showTempPassword.value;
  if (showTempPassword.value && !tempPasswordValue.value) {
    try {
      const res = await userAPI.viewTempPassword(viewedUser.value.id);
      tempPasswordValue.value = res.data.temp_password;
    } catch (e) {
      tempPasswordValue.value = 'Unable to retrieve.';
    }
  }
}

function resetUserForm() {
  userForm.value = {
    first_name: '', middle_name: '', last_name: '', suffix: '', email: '', employee_id: '',
    role: isFacultyView.value ? 'faculty' : '',
    college: '', department: '', contact_number: '',
    password: '', password_confirmation: '',
  };
  fieldErrors.value = {};
  generatePassword();
}

function openCreate() {
  if (!auth.isAdmin) return;
  isEditing.value = false;
  formError.value = '';
  fieldErrors.value = {};
  showUserPreview.value = false;
  resetUserForm();
  showModal.value = true;
}

function handleClearForm() {
  openClearConfirm();
}

function openEdit(u) {
  if (!auth.isAdmin) return;
  isEditing.value = true;
  formError.value = '';
  fieldErrors.value = {};
  showUserPreview.value = false;
  userForm.value  = { ...u, password: '', password_confirmation: '' };
  userFormSnapshot.value = JSON.stringify(userForm.value);
  showModal.value = true;
}

function validateUserForm() {
  const errs = {};
  const missing = [];
  const req = (key, label, condition = true) => {
    if (condition && !userForm.value[key]) { errs[key] = true; missing.push(label); }
  };
  if (!isEditing.value) {
    req('employee_id', 'Employee ID');
    req('last_name', 'Last Name');
    req('first_name', 'First Name');
    req('email', 'Email Address');
    req('role', 'Role');
  } else {
    req('employee_id', 'Employee ID');
    req('email', 'Email Address');
  }
  req('contact_number', 'Contact Number');
  req('college', 'College', ['faculty', 'dean_secretary'].includes(userForm.value.role));
  req('department', 'Department', ['faculty', 'dean_secretary'].includes(userForm.value.role));

  if (userForm.value.email && !isValidEmail(userForm.value.email)) errs.email = true;
  if (userForm.value.contact_number && !isValidPHContact(userForm.value.contact_number)) errs.contact_number = true;

  fieldErrors.value = errs;
  return missing;
}

function goToUserPreview() {
  formError.value = '';
  if (!auth.isAdmin) {
    formError.value = 'Please fill in all required fields.';
    return;
  }
  const missing = validateUserForm();
  if (missing.length) {
    formError.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }
  if (userForm.value.email && !isValidEmail(userForm.value.email)) {
    formError.value = 'Please enter a valid email address.';
    return;
  }
  if (userForm.value.contact_number && !isValidPHContact(userForm.value.contact_number)) {
    formError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }
  showUserPreview.value = true;
}

async function confirmUserSubmit() {
  showUserPreview.value = false;
  try {
    if (isEditing.value) {
      const { password, password_confirmation, ...updateData } = userForm.value;
      await userAPI.update(userForm.value.id, updateData);
      toast?.success('Employee updated successfully.');
    } else {
      await userAPI.store(userForm.value);
      toast?.success('Employee created successfully.');
    }
    showModal.value = false;
    fetchUsers();
  } catch (e) {
    console.error('Save user error:', e.response?.data);
    formError.value = e.response?.data?.message || 'Please fill in all required fields.';
  }
}

function toggleActive(u) {
  const action = u.is_active ? 'deactivate' : 'activate';
  openToggleConfirm(u, action);
}

function contactNumberBlockingNonZero(value) {
  if (!value) return '';
  if (!value.startsWith('0')) return '';
  return contactNumberInput(value);
}

function handleImportFileSelect(e) {
  importFile.value = e.target.files[0];
  importResult.value = null;
}

async function uploadImportFile() {
  if (!importFile.value) return;
  importing.value = true;
  try {
    const formData = new FormData();
    formData.append('file', importFile.value);
    const res = await userAPI.import(formData);
    importResult.value = res.data;
    toast?.success(`${res.data.created} employees imported successfully.`);
    fetchUsers();
  } catch (e) {
    toast?.error('Please fill in all required fields.');
  } finally {
    importing.value = false;
  }
}

function openImportConfirm() {
  if (!importFile.value) return;
  showImportConfirm.value = true;
}

async function doConfirmedImport() {
  showImportConfirm.value = false;
  await uploadImportFile();
}

function changePage(page) { fetchUsers(page); }

function resetFilters() {
  filters.value = { search: '', role: '', college: '', sort_by: 'created_at', sort_dir: 'desc' };
  sortOption.value = 'created_at:desc';
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

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => fetchUsers());

watch(() => route.name, () => {
  filters.value = { search: '', role: '', college: '', sort_by: 'created_at', sort_dir: 'desc' };
  sortOption.value = 'created_at:desc';
  showInactive.value = false;
  fetchUsers();
});
</script>