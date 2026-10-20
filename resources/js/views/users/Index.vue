<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>{{ isFacultyView ? 'Faculty Profile' : 'User Management' }}</h1>
      <p>{{ isFacultyView ? 'View and manage faculty member accounts.' : pageIntro }}</p>
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
      <select v-model="filters.status" class="fsm" @change="fetchUsers">
        <option value="">Sort by Status</option>
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
      <select v-if="!isFacultyView" v-model="filters.role" class="fsm" @change="fetchUsers">
        <option value="">All Roles</option>
        <option v-for="o in filterRoleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <select v-if="!isDeanActor" v-model="filters.college" class="fsm" @change="fetchUsers">
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
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Reset</button>
      <button v-if="auth.isAdmin || auth.isTMDUHead" class="ibtn ibtn-o ibtn-sm" @click="openImportModal">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload Masterlist
      </button>
      <button
        v-if="canManage"
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
              <th>Full Name</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id" :style="!u.is_active ? 'opacity:0.55;background:var(--snow)' : ''">
              <td style="font-family:var(--mono);font-size:13px;font-weight:600;cursor:pointer" @click="openView(u)">{{ u.employee_id || '-' }}</td>
              <td style="cursor:pointer" @click="openView(u)">{{ u.name || '-' }}</td>
              <td>
                <span class="ibadge" :style="u.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--ink);border:1px solid var(--fog)'">
                  {{ u.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click="openView(u)">View</button>
                  <button
                    v-if="canManage"
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

          <div v-if="canManage && viewedUser.must_change_password" style="background:var(--snow);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px 14px">
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

          <div v-if="statusHistory.length">
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:6px">Account History</div>
            <div v-for="ev in statusHistory" :key="ev.id" style="border-left:3px solid var(--cloud);padding:2px 0 2px 10px;margin-bottom:8px;font-size:12px;color:var(--slate);line-height:1.5">
              <strong>{{ ev.action === 'deactivated' ? 'Deactivated' : 'Reactivated' }}</strong>: {{ ev.reason_label }}<span v-if="ev.note"> - {{ ev.note }}</span>
              <div v-if="ev.previous_reason_label" style="color:var(--stone)">Originally deactivated: {{ ev.previous_reason_label }}<span v-if="ev.previous_at"> on {{ formatDate(ev.previous_at) }}</span></div>
              <div style="color:var(--fog)">{{ ev.actor_name || 'Admin' }} &middot; {{ new Date(ev.created_at).toLocaleString() }}</div>
            </div>
          </div>

          <div v-if="resetPasswordResult" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
            ✓ New password: <strong style="font-family:var(--mono)">{{ resetPasswordResult }}</strong>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Share this with the employee.</div>
          </div>

          <div style="display:flex;gap:8px;margin-top:8px">
            <button
              v-if="canManage"
              class="ibtn ibtn-p"
              :disabled="!viewedUser.is_active"
              :style="{ flex:1, justifyContent:'center', opacity: viewedUser.is_active ? 1 : .5, cursor: viewedUser.is_active ? 'pointer' : 'not-allowed' }"
              @click="openEditFromView"
            >Edit</button>
            <button class="ibtn ibtn-g" style="flex:1;justify-content:center" @click="showViewModal = false">Close</button>
          </div>
          <button
            v-if="canManage"
            class="ibtn ibtn-sm"
            :disabled="!viewedUser.is_active"
            :style="{ width:'100%', justifyContent:'center', background:'var(--amber-lt)', color:'var(--amber)', border:'1.5px solid var(--amber)', opacity: viewedUser.is_active ? 1 : .5, cursor: viewedUser.is_active ? 'pointer' : 'not-allowed' }"
            @click="resetPassword(viewedUser)"
          >Reset Password</button>
        </div>
      </div>
    </div>

    <!-- Add / Edit Employee Modal - admin only -->
    <div v-if="showModal && canManage" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showModal = false">
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
                <option v-if="userForm.suffix && !SUFFIX_OPTIONS.includes(userForm.suffix)" :value="userForm.suffix">{{ userForm.suffix }}</option>
                <option v-for="x in SUFFIX_OPTIONS" :key="x" :value="x">{{ x }}</option>
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
            <div v-if="userErrors.email" style="font-size:11px;color:var(--red);margin-top:4px">
              {{ userErrors.email }}
            </div>
          </div>

          <div>
            <label class="ifl">Role <span style="color:var(--red)">*</span></label>
            <select
              v-model="userForm.role"
              class="ifse"
              :disabled="isFacultyView"
              :style="errorStyle('role')"
              @change="clearFieldError('role'); onRolePicked()"
            >
              <option value="" disabled hidden>Select role...</option>
              <option v-for="o in addRoleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              <!-- An older account's role stays visible while editing it. -->
              <option v-if="isEditing && userForm.role && !addRoleOptions.some(o => o.value === userForm.role)" :value="userForm.role" disabled>{{ ROLE_LABELS[userForm.role] || userForm.role }}</option>
            </select>
          </div>

          <div v-if="['faculty','dean_secretary','dean','dept_chair'].includes(userForm.role)">
            <label class="ifl">College <span style="color:var(--red)">*</span></label>
            <!-- A Dean adds accounts to their own college only: shown, not chosen. -->
            <input v-if="isDeanActor" class="ifi" :value="auth.user?.college" disabled />
            <select
              v-else
              v-model="userForm.college"
              class="ifse"
              :style="errorStyle('college')"
              @change="userForm.department = ''; clearFieldError('college')"
            >
              <option value="" disabled hidden>Select college...</option>
              <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
            </select>
            <div v-if="isDeanActor && colleges.length && !deanCollege" style="font-size:11.5px;color:var(--amber);background:var(--amber-lt);border-radius:var(--r-sm);padding:8px 10px;margin-top:6px">Your account's college "{{ auth.user?.college }}" is not in the official college list, so its departments can't be shown. Ask the GCU Head to set your college in User Management.</div>
          </div>

          <div v-if="['faculty','dean_secretary','dept_chair'].includes(userForm.role)">
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
            <div v-if="userErrors.contact_number" style="font-size:11px;color:var(--red);margin-top:4px">
              {{ userErrors.contact_number }}
            </div>
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
            <button class="ibtn ibtn-o" @click="cancelUserForm">Cancel</button>
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
    <div v-if="showImportModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeImportModal">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Upload {{ isFacultyView ? 'Faculty' : 'Employee' }} Masterlist</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">

          <template v-if="!previewData.total">
            <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--stone);line-height:1.6">
              Download the template below, fill it in, then upload it here.
            </div>
            <a :href="isFacultyView ? '/templates/faculty_masterlist_template.xlsx' : '/templates/employee_masterlist_template.xlsx'" download class="ibtn ibtn-o" style="width:100%;justify-content:center">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Download Template
            </a>
            <div>
              <label class="ifl">File <span style="font-weight:400;color:var(--stone)">(Accepted file format: .xlsx)</span></label>
              <input type="file" accept=".xlsx" class="ifi" @change="handleFileSelect" />
            </div>
            <div v-if="loadingPreview" style="text-align:center;padding:20px">
              <div style="width:22px;height:22px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
              <div style="font-size:12px;color:var(--stone);margin-top:8px">Reading file...</div>
            </div>
            <div v-if="previewError" style="display:flex;flex-direction:column;gap:8px">
              <div style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
                {{ previewError }}
              </div>
              <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="previewError = ''">Choose Different File</button>
            </div>
            <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="closeImportModal">Cancel</button>
          </template>

          <template v-else>
            <div style="font-size:13px;color:var(--stone)">{{ previewData.total }} record(s) found - {{ previewData.duplicates }} duplicate(s)</div>

            <div v-if="previewData.duplicates > 0" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber)">
              ⚠ Some emails already exist. Choose how to handle all duplicates below.
            </div>

           <div style="max-height:340px;overflow-y:auto;border:1px solid var(--cloud);border-radius:var(--r-sm)">
            <table class="itable">
              <thead>
                <tr>
                  <th style="width:50px">Row</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th style="width:100px">Status</th>
                  <th>Reason</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in previewData.preview" :key="item.row">
                  <td style="font-size:12px">{{ item.row }}</td>
                  <td style="font-size:12px">{{ item.name || '-' }}</td>
                  <td style="font-family:var(--mono);font-size:12px">{{ item.email || '-' }}</td>
                  <td>
                    <span v-if="!item.valid" class="ibadge" style="background:var(--red-lt);color:var(--red)">Invalid</span>
                    <span v-else-if="item.is_duplicate" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">Duplicate</span>
                    <span v-else class="ibadge" style="background:var(--mist);color:var(--moss)">New</span>
                  </td>
                  <td style="font-size:11px;line-height:1.5;vertical-align:top">
                    <template v-if="item.reasons && item.reasons.length">
                      <div v-for="(r, i) in item.reasons" :key="i" style="color:var(--red)">• {{ r }}</div>
                    </template>
                    <span v-else-if="item.is_duplicate" style="color:var(--amber)">Email already exists</span>
                    <span v-else style="color:var(--fog)">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

            <div v-if="previewData.duplicates > 0" style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" style="flex:1;justify-content:center" @click="openImportConfirm('update')" :disabled="importing">
                <span v-if="importing" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
                {{ importing ? 'Uploading...' : 'Update Existing' }}
              </button>
              <button class="ibtn" style="flex:1;justify-content:center;background:var(--cloud);color:var(--stone)" @click="openImportConfirm('skip')" :disabled="importing">
                Keep Existing Information
              </button>
            </div>
            <div v-else style="display:flex;gap:8px">
              <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="openImportConfirm('create')" :disabled="importing">
                <span v-if="importing" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
                {{ importing ? 'Uploading...' : 'Confirm Upload' }}
              </button>
            </div>

            <button class="ibtn ibtn-o" style="width:100%;justify-content:center" @click="resetImportFlow">Choose Different File</button>
          </template>

        </div>
      </div>
    </div>

    <!-- Import Confirmation Modal -->
    <div v-if="showImportConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:65;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showImportConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Masterlist Upload</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone)">Please review the summary below before uploading:</div>

          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div v-if="pendingImportChoice === 'create'">
              <strong>{{ importSummary.newRecords }}</strong> new record{{ importSummary.newRecords === 1 ? '' : 's' }} will be added.
            </div>
            <div v-else-if="pendingImportChoice === 'update'">
              <strong>{{ importSummary.duplicates }}</strong> duplicate{{ importSummary.duplicates === 1 ? '' : 's' }} will be <strong>updated</strong>,
              and <strong>{{ importSummary.newRecords }}</strong> new record{{ importSummary.newRecords === 1 ? '' : 's' }} will be added.
            </div>
            <div v-else-if="pendingImportChoice === 'skip'">
              <strong>{{ importSummary.newRecords }}</strong> new record{{ importSummary.newRecords === 1 ? '' : 's' }} will be added.
              Existing duplicate{{ importSummary.duplicates === 1 ? '' : 's' }}
              (<strong>{{ importSummary.duplicates }}</strong>) will be kept as-is.
            </div>
            <div v-if="importSummary.invalid > 0" style="color:var(--red)">
              <strong>{{ importSummary.invalid }}</strong> invalid row{{ importSummary.invalid === 1 ? '' : 's' }} will be skipped.
            </div>
            <div style="font-size:11px;color:var(--fog);margin-top:4px">
              Total: {{ previewData.total }} row(s) detected.
            </div>
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

    <!-- Reset Password (reason required) -->
    <ResetPasswordDialog
      :open="showResetPwConfirm"
      kind="employee"
      :name="`${userToReset?.first_name || ''} ${userToReset?.last_name || ''}`.trim() || userToReset?.name || ''"
      :fetch-info="() => userAPI.passwordInfo(userToReset.id).then(r => r.data)"
      :busy="resetBusy"
      :error="resetError"
      @confirm="doConfirmedResetPw"
      @cancel="showResetPwConfirm = false"
    />

    <!-- Deactivate / Reactivate (reason required) -->
    <ReasonDialog
      :open="showToggleConfirm"
      :title="toggleAction === 'deactivate' ? 'Deactivate Account?' : 'Activate Account?'"
      :message="toggleMessage"
      :reasons="toggleReasons"
      :reason-label="toggleAction === 'deactivate' ? 'Reason for Deactivation' : 'Reason for Reactivation'"
      :confirm-text="toggleAction === 'deactivate' ? 'Deactivate Account' : 'Activate Account'"
      :other-placeholder="toggleAction === 'deactivate' ? 'Please describe the reason for deactivation briefly. Do not include sensitive personal details.' : 'Please describe the reason for reactivation briefly. Do not include sensitive personal details.'"
      :optional-note="toggleAction === 'activate'"
      :danger="toggleAction === 'deactivate'"
      :busy="toggleBusy"
      :error="toggleError"
      @confirm="doConfirmedToggle"
      @cancel="showToggleConfirm = false"
    >
      <div v-if="toggleAction === 'deactivate' && openWork && (openWork.open_referrals || openWork.open_cases || openWork.upcoming_appointments)" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber);line-height:1.6">
        ⚠ This employee still has {{ openWork.open_referrals }} open referral(s), {{ openWork.open_cases }} open case(s) and {{ openWork.upcoming_appointments }} upcoming appointment(s).
        <div style="margin-top:6px;color:var(--ink)">
          <label class="ifl">Reassign them to</label>
          <select v-model="reassignTo" class="ifse">
            <option value="">Do not reassign</option>
            <option v-for="st in openWork.staff" :key="st.id" :value="st.id">{{ st.name }} ({{ st.unit || st.role }})</option>
          </select>
        </div>
      </div>
    </ReasonDialog>

  </div>
</template>

<script setup>
import { useDiscardGuard } from '../../utils/discardGuard';
import { ref, onMounted, watch, inject, computed } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { userAPI } from '../../api/index';
import ReasonDialog from '../../components/ReasonDialog.vue';
import ResetPasswordDialog from '../../components/ResetPasswordDialog.vue';
import { EMPLOYEE_DEACTIVATE_REASONS, reactivationReasonsFor } from '../../constants/accountReasons';
import { useAuthStore } from '../../stores/auth';
import { ROLE_ORDER, ROLE_LABELS, addableRoles } from '../../utils/roles';
import { matchCollege } from '../../utils/colleges';
import { useRouter } from 'vue-router';
import { onlyLetters, onlyLettersStrict, onlyDigits, contactNumberInput, isValidEmail, isValidPHContact, safeSearchInput, blockSpecialKeypress , titleCaseName } from '../../utils/validators';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;
function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('token')}` } };
}

function titleCase(str) {
  return titleCaseName(str);
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
const router     = useRouter();
// GCU Head manages every account; TMDU Head adds TMDU Staff; the Dean adds
// faculty and chairs for their own college (the server enforces the same).
const canManage   = computed(() => addableRoles(auth.user?.role).length > 0);
const isDeanActor = computed(() => ['dean', 'dean_secretary'].includes(auth.user?.role));
const addRoleOptions = computed(() => addableRoles(auth.user?.role).map(r => ({ value: r, label: r === 'student' ? 'Student (opens Add Student)' : ROLE_LABELS[r] })));
const filterRoleOptions = computed(() => (auth.isAdmin ? ROLE_ORDER : addableRoles(auth.user?.role)).map(r => ({ value: r, label: ROLE_LABELS[r] })));
const pageIntro = computed(() => auth.isAdmin ? 'Manage system accounts and role-based access for all iCARE users.'
  : isDeanActor.value ? `Manage the faculty and department chairs of ${auth.user?.college || 'your college'}.`
  : 'Manage the TMDU Staff accounts.');
const loading    = ref(true);
const showModal  = ref(false);
const showViewModal = ref(false);
const statusHistory = ref([]);
const isEditing  = ref(false);
const users      = ref([]);
const pagination = ref({});
const filters    = ref({ search: '', status: '', role: '', college: '', sort_by: 'created_at', sort_dir: 'desc' });
const sortOption = ref('created_at:desc');

const showClearConfirm = ref(false);
const showUserPreview = ref(false);

const showResetPwConfirm = ref(false);
const userToReset = ref(null);

function openResetPwConfirm(u) {
  resetError.value = '';
  userToReset.value = u;
  showResetPwConfirm.value = true;
}

const resetBusy = ref(false);
const resetError = ref('');
async function doConfirmedResetPw({ reason, note }) {
  const u = userToReset.value;
  if (!u || resetBusy.value) return;
  resetBusy.value = true; resetError.value = '';
  try {
    const res = await userAPI.resetPassword(u.id, { reason, note });
    showResetPwConfirm.value = false;
    resetPasswordResult.value = res.data?.temp_password || '';
    toast?.success('Password reset successfully. The employee was signed out and notified by email.');
    fetchUsers?.();
  } catch (e) {
    const errs = e.response?.data?.errors;
    resetError.value = (errs && Object.values(errs)[0]?.[0]) || e.response?.data?.message || 'Failed to reset password.';
  } finally {
    resetBusy.value = false;
  }
}

const showToggleConfirm = ref(false);
const userToToggle = ref(null);
const toggleAction = ref('');

const toggleBusy = ref(false);
const toggleError = ref('');
const openWork = ref(null);
const reassignTo = ref('');
const escHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const toggleReasons = computed(() =>
  toggleAction.value === 'deactivate'
    ? EMPLOYEE_DEACTIVATE_REASONS
    : reactivationReasonsFor(userToToggle.value?.deactivation_reason)
);
const toggleMessage = computed(() => {
  const u = userToToggle.value;
  if (!u) return '';
  const name = `<strong>${escHtml(u.first_name)} ${escHtml(u.last_name)}</strong>`;
  if (toggleAction.value === 'deactivate') {
    return `Deactivate ${name}'s account? Their case histories, appointments and activity records are kept and stay attributed to them.`;
  }
  const was = u.deactivation_reason
    ? `<br><span style="color:var(--stone)">Deactivated: ${escHtml(u.deactivation_reason)}${u.deactivated_at ? ' on ' + escHtml(new Date(u.deactivated_at).toLocaleDateString()) : ''}.</span>`
    : '';
  return `Reactivate ${name}'s account? They will be able to log in again.${was}`;
});

async function openToggleConfirm(u, action) {
  userToToggle.value = u;
  toggleAction.value = action;
  toggleError.value = '';
  openWork.value = null;
  reassignTo.value = '';
  if (action === 'deactivate') {
    try { openWork.value = (await userAPI.openWork(u.id)).data; } catch (e) { /* the warning is optional */ }
  }
  showToggleConfirm.value = true;
}

async function doConfirmedToggle({ reason, note }) {
  const u = userToToggle.value;
  if (!u || toggleBusy.value) return;
  toggleBusy.value = true;
  toggleError.value = '';
  try {
    await userAPI.toggleActive(u.id, {
      reason,
      note: note || null,
      reassign_to: toggleAction.value === 'deactivate' && reassignTo.value ? reassignTo.value : null,
    });
    showToggleConfirm.value = false;
    toast?.success(`Account ${toggleAction.value}d successfully.`);
    fetchUsers();
  } catch (e) {
    toggleError.value = e.response?.data?.message || 'Please try again.';
  } finally {
    toggleBusy.value = false;
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

// Colleges/Departments now come live from the Management API instead of
// the old static constants files. Stored values stay the same NAME strings.
const colleges = ref([]);
const departmentsByCollege = ref({});

async function fetchManagementData() {
  try {
    const [collegeRes, departmentRes] = await Promise.all([
      axios.get(`${API_BASE}/management/colleges`, authHeaders()),
      axios.get(`${API_BASE}/management/programs`, authHeaders()),   // the choices are the college's programs (the leader's list), named Department
    ]);
    colleges.value = collegeRes.data.map(c => c.name);

    const collegeNameById = {};
    collegeRes.data.forEach(c => { collegeNameById[c.id] = c.name; });

    const grouped = {};
    departmentRes.data.forEach(d => {
      const collegeName = collegeNameById[d.college_id];
      if (!collegeName) return;
      if (!grouped[collegeName]) grouped[collegeName] = [];
      grouped[collegeName].push(d.name);
    });
    departmentsByCollege.value = grouped;
  } catch (e) {
    console.error(e);
  }
}

const viewedUser = ref({});
const formError  = ref('');
const resetPasswordResult = ref('');
const showTempPassword = ref(false);
const tempPasswordValue = ref('');
const userFormSnapshot = ref('');

const userForm = ref({
  first_name: '', middle_name: '', last_name: '', suffix: '', email: '', employee_id: '', role: '',
  college: '', department: '', contact_number: '',
  password: '', password_confirmation: '',
});

const isFacultyView = computed(() => route.name === 'faculty-directory');
// The Dean's college as it appears in the official list (older accounts were
// saved with other spellings).
const deanCollege = computed(() => isDeanActor.value ? matchCollege(auth.user?.college, colleges.value) : '');
const availableDepartments = computed(() => departmentsByCollege.value[isDeanActor.value ? deanCollege.value : userForm.value.college] || []);

const userErrors = computed(() => {
  const f = userForm.value;
  const errs = {};

  if (f.email && !isValidEmail(f.email)) {
    errs.email = 'Please enter a valid email address.';
  }

  if (f.contact_number) {
    if (!f.contact_number.startsWith('0')) {
      errs.contact_number = 'Contact number must start with 0.';
    } else if (!isValidPHContact(f.contact_number)) {
      errs.contact_number = 'Must be 11 digits starting with 09.';
    }
  }

  return errs;
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

// Import flow (preview -> confirm), matching the Students masterlist import.
const showImportModal   = ref(false);
const loadingPreview    = ref(false);
const previewError      = ref('');
const previewData       = ref({ preview: [], total: 0, duplicates: 0, token: '' });
const importing         = ref(false);
const showImportConfirm = ref(false);
const pendingImportChoice = ref('');

const importSummary = computed(() => {
  const items = previewData.value.preview || [];
  return {
    newRecords: items.filter(i => i.valid && !i.is_duplicate).length,
    duplicates: items.filter(i => i.valid && i.is_duplicate).length,
    invalid:    items.filter(i => !i.valid).length,
  };
});

function onSearchLetters() {
  filters.value.search = safeSearchInput(filters.value.search);
  fetchUsers();
}

async function fetchUsers(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters.value, page };
    if (isFacultyView.value) params.role = 'faculty';
    if (params.status === '') delete params.status;
    else params.is_active = params.status;
    delete params.status;
    const res = await userAPI.index(params);
    // One unified list instead of separate Active/Inactive tabs, matching
    // Student Profiling: inactive accounts sink to the bottom (B210).
    users.value      = [...res.data.data].sort((a, b) => (b.is_active ? 1 : 0) - (a.is_active ? 1 : 0));
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
  statusHistory.value = [];
  showViewModal.value = true;
  try {
    const res = await userAPI.show(u.id);
    viewedUser.value = res.data;
  } catch (e) {
    // Non-fatal - falls back to the row data already shown.
  }
  try {
    statusHistory.value = (await userAPI.statusHistory(u.id)).data;
  } catch (e) {
    statusHistory.value = [];
  }
}

const SUFFIX_OPTIONS = ['Jr.', 'Sr.', 'I', 'II', 'III', 'IV', 'V'];

async function openEditFromView() {
  showViewModal.value = false;
  // B260: always edit from a guaranteed-complete record. viewedUser can
  // briefly hold the row-listing data (set synchronously in openView())
  // before its own userAPI.show() call resolves - if Edit is clicked in
  // that window, it would previously open with whatever that partial
  // object had, risking college/department (or anything else) looking
  // empty and then getting wiped on save. Re-fetch fresh here instead of
  // trusting whatever is currently in viewedUser.
  try {
    const res = await userAPI.show(viewedUser.value.id);
    openEdit(res.data);
  } catch (e) {
    openEdit(viewedUser.value);
  }
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
    college: isDeanActor.value ? (auth.user?.college || '') : '', department: '', contact_number: '',
    password: '', password_confirmation: '',
  };
  if (addRoleOptions.value.length === 1) userForm.value.role = addRoleOptions.value[0].value;
  fieldErrors.value = {};
  generatePassword();
}

function openCreate() {
  if (!canManage.value) return;
  isEditing.value = false;
  formError.value = '';
  fieldErrors.value = {};
  showUserPreview.value = false;
  resetUserForm();
  showModal.value = true;
}

function onRolePicked() {
  if (userForm.value.role === 'student') {
    showModal.value = false;
    router.push({ name: 'students', query: { add: 1 } });
    return;
  }
  if (isDeanActor.value) userForm.value.college = auth.user?.college || '';
}

function handleClearForm() {
  openClearConfirm();
}

function openEdit(u) {
  if (!canManage.value) return;
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
  req('college', 'College', ['faculty', 'dean_secretary', 'dean', 'dept_chair'].includes(userForm.value.role));
  req('department', 'Department', ['faculty', 'dean_secretary', 'dept_chair'].includes(userForm.value.role));

  if (userForm.value.email && !isValidEmail(userForm.value.email)) errs.email = true;
  if (userForm.value.contact_number && !isValidPHContact(userForm.value.contact_number)) errs.contact_number = true;

  fieldErrors.value = errs;
  return missing;
}

function goToUserPreview() {
  formError.value = '';
  if (!canManage.value) {
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

function openImportModal() {
  resetImportFlow();
  showImportModal.value = true;
}

function closeImportModal() {
  showImportModal.value = false;
  resetImportFlow();
}

function resetImportFlow() {
  previewData.value = { preview: [], total: 0, duplicates: 0, token: '' };
  previewError.value = '';
}

async function handleFileSelect(e) {
  const file = e.target.files[0];
  if (!file) return;

  loadingPreview.value = true;
  previewError.value = '';
  try {
    const formData = new FormData();
    formData.append('file', file);
    const res = await userAPI.importPreview(formData);

    if (res.data.total === 0) {
      previewError.value = 'The file appears to be empty. Please choose a different file.';
      loadingPreview.value = false;
      return;
    }

    previewData.value = res.data;
  } catch (err) {
    previewError.value = err.response?.data?.message || 'Failed to read file. Please check the format.';
  } finally {
    loadingPreview.value = false;
  }
}

function openImportConfirm(choice) {
  pendingImportChoice.value = choice;
  showImportConfirm.value = true;
}

async function doConfirmedImport() {
  const choice = pendingImportChoice.value;
  showImportConfirm.value = false;
  await confirmImport(choice);
}

async function confirmImport(globalChoice) {
  importing.value = true;
  try {
    const finalDecisions = {};
    previewData.value.preview.forEach((item, idx) => {
      if (item.is_duplicate && item.valid) {
        finalDecisions[idx] = globalChoice === 'update' ? 'update' : 'skip';
      } else if (item.valid) {
        finalDecisions[idx] = 'create';
      } else {
        finalDecisions[idx] = 'skip';
      }
    });

    const res = await userAPI.importConfirm({
      token: previewData.value.token,
      decisions: finalDecisions,
    });
    toast?.success(`${res.data.created} added, ${res.data.updated} updated, ${res.data.skipped} skipped.`);
    closeImportModal();
    fetchUsers();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to complete import.');
  } finally {
    importing.value = false;
  }
}

function changePage(page) { fetchUsers(page); }

function resetFilters() {
  filters.value = { search: '', status: '', role: '', college: '', sort_by: 'created_at', sort_dir: 'desc' };
  sortOption.value = 'created_at:desc';
  fetchUsers();
}

// Same names everywhere (utils/roles.js): TMDU Head included, and the older
// Dean's Secretary accounts shown as Dean.
function roleLabel(role) {
  return ROLE_LABELS[role] || role;
}

function roleStyle(role) {
  const styles = {
    admin:          'background:#1a1a2e;color:#fff',
    gcu_staff:      'background:var(--mist);color:var(--moss)',
    sdu_head:       'background:var(--amber-lt);color:var(--amber)',
    tmdu_head:      'background:var(--purple);color:#fff',
    tmdu_staff:     'background:var(--purple-lt);color:var(--purple)',
    faculty:        'background:var(--blue-lt);color:var(--blue)',
    dean:           'background:var(--forest);color:#fff',
    dept_chair:     'background:var(--amber-lt);color:var(--amber)',
    dean_secretary: 'background:var(--forest);color:#fff',
  };
  return styles[role] || '';
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function formatDate(date) {
  return date ? new Date(date).toLocaleDateString() : '-';
}

onMounted(() => {
  fetchUsers();
  fetchManagementData();
});

watch(() => route.name, () => {
  filters.value = { search: '', status: '', role: '', college: '', sort_by: 'created_at', sort_dir: 'desc' };
  sortOption.value = 'created_at:desc';
  fetchUsers();
});

// Cancel asks before throwing away a changed form (B401).
const cancelUserForm = useDiscardGuard(showModal, userForm, () => { showModal.value = false; });
</script>