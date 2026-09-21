<template>
  <div class="fade-up">
    <!-- Page Header -->
    <div class="ph" style="margin-bottom:20px">
      <h1>Student Profiles</h1>
      <p>Browse or search student records.</p>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:8px;margin-bottom:16px">
      <button
        class="ibtn ibtn-sm"
        :style="!showArchived ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="switchTab(false)"
      >
        Active Students
      </button>
      <button
        class="ibtn ibtn-sm"
        :style="showArchived ? 'background:var(--moss);color:#fff' : 'background:var(--cloud);color:var(--stone)'"
        @click="switchTab(true)"
      >
        Inactive Students
      </button>
    </div>

    <!-- Search Bar -->
    <div class="filter-bar">
      <div class="sw" style="flex:1;max-width:400px">
        <svg class="sw-icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input
          v-model="filters.search"
          type="text"
          class="sin"
          placeholder="Search name or student ID..."
          style="width:100%"
          @keypress="blockSpecialKeypress"
          @input="onSearchInput"
        />
      </div>
      <select v-model="filters.college" class="fsm" @change="fetchStudents">
        <option value="">All Colleges</option>
        <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="sortOption" class="fsm" @change="applySort">
        <option value="created_at:desc">Newest First</option>
        <option value="created_at:asc">Oldest First</option>
        <option value="student_id:asc">Student ID: Ascending</option>
        <option value="student_id:desc">Student ID: Descending</option>
        <option value="last_name:asc">Name: A-Z</option>
        <option value="last_name:desc">Name: Z-A</option>
      </select>
      <button class="ibtn ibtn-o ibtn-sm" @click="resetFilters">Clear</button>
      <button v-if="!showArchived" class="ibtn ibtn-o ibtn-sm" @click="openImportModal">
        <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Upload Masterlist
      </button>
      <button v-if="!showArchived" class="ibtn ibtn-p ibtn-sm" style="margin-left:auto" type="button" @click="openAddModal">
        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Student
      </button>
    </div>

    <!-- Student List -->
    <div class="icard">
      <div v-if="loading" style="text-align:center;padding:44px">
        <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
      </div>
      <div v-else-if="students.length === 0" class="empty-state">
        <h3>No students found</h3>
        <p>Try adjusting your search.</p>
      </div>
      <div class="ts" v-else>
        <table class="itable">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in students" :key="s.id" :style="!s.is_active ? 'opacity:0.55;background:var(--snow)' : ''">
              <td style="font-family:var(--mono);font-size:13px;font-weight:600;cursor:pointer" @click="openView(s)">
                {{ s.student_id }}
              </td>
              <td>
                <span class="ibadge" :style="s.is_active ? 'background:var(--mist);color:var(--moss)' : 'background:var(--cloud);color:var(--stone)'">
                  {{ s.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td style="text-align:right">
                <div style="display:flex;gap:6px;justify-content:flex-end">
                  <button class="ibtn ibtn-o ibtn-sm" @click.stop="openView(s)">View</button>
                  <button
                    v-if="s.is_active"
                    class="ibtn ibtn-sm"
                    style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0"
                    @click.stop="confirmGraduate(s)"
                  >
                    Deactivate
                  </button>
                  <button
                    v-else
                    class="ibtn ibtn-sm"
                    style="background:var(--mist);color:var(--moss);border:1.5px solid var(--mint)"
                    @click.stop="toggleActive(s)"
                  >
                    Activate
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

    <!-- View Student Profile Modal -->
    <div v-if="showViewModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showViewModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:480px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="background:linear-gradient(135deg,var(--forest),var(--pine));padding:22px;border-radius:var(--r-lg) var(--r-lg) 0 0;text-align:center">
          <div style="width:56px;height:56px;border-radius:50%;background:var(--gold);color:var(--forest);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 10px;font-family:var(--serif)">
            {{ initials(viewedStudent.first_name, viewedStudent.last_name) }}
          </div>
          <div style="font-size:15px;font-weight:600;color:#fff">{{ viewedStudent.last_name }}, {{ viewedStudent.first_name }} {{ viewedStudent.middle_name }} {{ viewedStudent.suffix }}</div>
          <div style="font-size:11px;color:rgba(255,255,255,.6);margin-top:2px;font-family:var(--mono)">{{ viewedStudent.student_id }}</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:12px">
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Sex</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.sex || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Year Level</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.year_level || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">College</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.college || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Program</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.program || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Section</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.section || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Email Address</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.email || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Contact Number</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.contact_number || '-' }}</div>
          </div>

          <div style="height:1px;background:var(--cloud);margin:4px 0"></div>

          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Guardian Information</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Name</div>
            <div style="font-size:13px;color:var(--ink)">
              {{ [viewedStudent.guardian_last_name, viewedStudent.guardian_first_name, viewedStudent.guardian_middle_name].filter(Boolean).length
                  ? `${viewedStudent.guardian_last_name || ''}, ${viewedStudent.guardian_first_name || ''} ${viewedStudent.guardian_middle_name || ''}`.trim()
                  : '-' }}
            </div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Guardian Contact</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.guardian_contact || '-' }}</div>
          </div>
          <div>
            <div style="font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog);margin-bottom:3px">Relationship</div>
            <div style="font-size:13px;color:var(--ink)">{{ viewedStudent.guardian_relationship || '-' }}</div>
          </div>

          <div v-if="viewedStudent.must_change_password" style="background:var(--snow);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px 14px">
            <div style="display:flex;align-items:center;justify-content:space-between">
              <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Temporary Password</div>
              <button type="button" @click="toggleTempPasswordVisible" :disabled="!viewedStudent.is_active" style="background:none;border:none;cursor:pointer;color:var(--fog);padding:2px;display:flex;align-items:center">
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
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Student hasn't logged in and changed their password yet.</div>
          </div>

          <div v-if="resetPasswordResult" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
            ✓ New password: <strong style="font-family:var(--mono)">{{ resetPasswordResult }}</strong>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Share this with the student.</div>
          </div>

          <router-link v-if="viewedStudent.is_active" :to="{ name: 'student-show', params: { id: viewedStudent.id } }" style="font-size:12px;color:var(--moss);text-align:center;text-decoration:underline">
            View Full Profile &amp; Referral History
          </router-link>
          <div v-else style="font-size:12px;color:var(--fog);text-align:center">
            View Full Profile &amp; Referral History (unavailable while inactive)
          </div>

          <div style="display:flex;gap:8px;margin-top:4px">
            <button class="ibtn ibtn-p" style="flex:1;justify-content:center" :style="!viewedStudent.is_active ? 'opacity:.5;cursor:not-allowed' : ''" :disabled="!viewedStudent.is_active" @click="openEditFromView">Edit</button>
            <button class="ibtn ibtn-g" style="flex:1;justify-content:center" @click="showViewModal = false">Close</button>
          </div>
          <button class="ibtn ibtn-sm" style="width:100%;justify-content:center;background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" :style="!viewedStudent.is_active ? 'opacity:.5;cursor:not-allowed' : ''" :disabled="!viewedStudent.is_active" @click="resetStudentPassword">Reset Password</button>
        </div>
      </div>
    </div>

    <!-- Edit Student Profile Modal -->
    <div v-if="showEditModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditModal = false">
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
              <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.student_id"
                class="ifi"
                maxlength="15"
                placeholder="e.g. 2302021"
                :style="editErrorStyle('student_id')"
                @input="editForm.student_id = onlyDigits(editForm.student_id); clearEditFieldError('student_id')"
              />
            </div>
            <div>
              <label class="ifl">Sex <span style="color:var(--red)">*</span></label>
              <select
                v-model="editForm.sex"
                class="ifse"
                :style="editErrorStyle('sex')"
                @change="clearEditFieldError('sex')"
              >
                <option value="" disabled hidden>Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.last_name"
                class="ifi"
                maxlength="20"
                placeholder="Last Name"
                :style="editErrorStyle('last_name')"
                @input="editForm.last_name = titleCase(onlyLetters(editForm.last_name)); clearEditFieldError('last_name')"
              />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.first_name"
                class="ifi"
                maxlength="20"
                placeholder="First Name"
                :style="editErrorStyle('first_name')"
                @input="editForm.first_name = titleCase(onlyLetters(editForm.first_name)); clearEditFieldError('first_name')"
              />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="editForm.middle_name" class="ifi" maxlength="20" placeholder="Middle Name" @input="editForm.middle_name = titleCase(onlyLetters(editForm.middle_name))" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input v-model="editForm.suffix" class="ifi" maxlength="20" placeholder="Jr., Sr., III" @input="editForm.suffix = onlyLettersStrict(editForm.suffix)" />
            </div>
            <div>
              <label class="ifl">Year Level <span style="color:var(--red)">*</span></label>
              <select
                v-model="editForm.year_level"
                class="ifse"
                :style="editErrorStyle('year_level')"
                @change="clearEditFieldError('year_level')"
              >
                <option value="" disabled hidden>Select...</option>
                <option v-if="editForm.year_level && !editYearLevelOptions.includes(editForm.year_level)" :value="editForm.year_level">{{ editForm.year_level }}</option>
                <option v-for="yl in editYearLevelOptions" :key="yl" :value="yl">{{ yl }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">College <span style="color:var(--red)">*</span></label>
              <select
                v-model="editForm.college"
                class="ifse"
                :style="editErrorStyle('college')"
                @change="editForm.program = ''; editForm.year_level = ''; clearEditFieldError('college')"
              >
                <option value="" disabled hidden>Select college...</option>
                <option v-if="editForm.college && !colleges.includes(editForm.college)" :value="editForm.college">{{ editForm.college }} (unrecognized)</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Program <span style="color:var(--red)">*</span></label>
              <select
                v-model="editForm.program"
                class="ifse"
                :disabled="!editForm.college"
                :style="editErrorStyle('program')"
                @change="clearEditFieldError('program')"
              >
                <option value="" disabled hidden>Select program...</option>
                <option v-if="editForm.program && !editAvailablePrograms.includes(editForm.program)" :value="editForm.program">{{ editForm.program }}</option>
                <option v-for="p in editAvailablePrograms" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Section <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.section"
                class="ifi"
                placeholder="e.g. A"
                maxlength="1"
                :style="editErrorStyle('section')"
                @input="editForm.section = editForm.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase(); clearEditFieldError('section')"
              />
            </div>
            <div>
              <label class="ifl">Email Address <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.email"
                class="ifi"
                placeholder="student@bsu.edu.ph"
                :style="editErrorStyle('email')"
                @input="clearEditFieldError('email')"
              />
              <div v-if="editErrors.email" style="font-size:11px;color:var(--red);margin-top:4px">
                {{ editErrors.email }}
              </div>
            </div>
            <div>
              <label class="ifl">Contact Number <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.contact_number"
                class="ifi"
                placeholder="09XXXXXXXXX"
                maxlength="11"
                :style="editErrorStyle('contact_number')"
                @input="editForm.contact_number = contactNumberBlockingNonZero(editForm.contact_number); clearEditFieldError('contact_number')"
              />
              <div v-if="editErrors.contact_number" style="font-size:11px;color:var(--red);margin-top:4px">
                {{ editErrors.contact_number }}
              </div>
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:4px">
            Guardian Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Guardian Last Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.guardian_last_name"
                class="ifi"
                maxlength="20"
                placeholder="Dela Cruz"
                :style="editErrorStyle('guardian_last_name')"
                @input="editForm.guardian_last_name = titleCase(onlyLetters(editForm.guardian_last_name)); clearEditFieldError('guardian_last_name')"
              />
            </div>
            <div>
              <label class="ifl">Guardian First Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.guardian_first_name"
                class="ifi"
                maxlength="20"
                placeholder="Juan"
                :style="editErrorStyle('guardian_first_name')"
                @input="editForm.guardian_first_name = titleCase(onlyLetters(editForm.guardian_first_name)); clearEditFieldError('guardian_first_name')"
              />
            </div>
            <div>
              <label class="ifl">Guardian Middle Name</label>
              <input v-model="editForm.guardian_middle_name" class="ifi" maxlength="20" placeholder="Santos" @input="editForm.guardian_middle_name = titleCase(onlyLetters(editForm.guardian_middle_name))" />
            </div>
            <div>
              <label class="ifl">Guardian Contact <span style="color:var(--red)">*</span></label>
              <input
                v-model="editForm.guardian_contact"
                class="ifi"
                placeholder="09XXXXXXXXX"
                maxlength="11"
                :style="editErrorStyle('guardian_contact')"
                @input="editForm.guardian_contact = contactNumberBlockingNonZero(editForm.guardian_contact); clearEditFieldError('guardian_contact')"
              />
              <div v-if="editErrors.guardian_contact" style="font-size:11px;color:var(--red);margin-top:4px">
                {{ editErrors.guardian_contact }}
              </div>
            </div>
            <div>
              <label class="ifl">Relationship <span style="color:var(--red)">*</span></label>
              <select
                v-model="editForm.guardian_relationship"
                class="ifse"
                :style="editErrorStyle('guardian_relationship')"
                @change="clearEditFieldError('guardian_relationship')"
              >
                <option value="" disabled hidden>Select...</option>
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
            <button class="ibtn ibtn-p" @click="openEditConfirm" :disabled="editSaving || isEditFormUnchanged">
              <svg v-if="!editSaving" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="editSaving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ editSaving ? 'Saving...' : 'Save Changes' }}
            </button>
            <button class="ibtn ibtn-o" @click="showEditModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Student Confirmation Modal -->
    <div v-if="showEditConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showEditConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:600px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Changes</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showEditConfirm = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone)">
            You are about to save <strong>{{ editChanges.length }}</strong> change{{ editChanges.length === 1 ? '' : 's' }} to this student profile:
          </div>

          <div style="border:1px solid var(--cloud);border-radius:var(--r-sm);overflow:hidden">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
              <thead>
                <tr style="background:var(--snow)">
                  <th style="text-align:left;padding:8px 12px;font-weight:600;color:var(--stone);font-size:11px;letter-spacing:.5px;text-transform:uppercase">Field</th>
                  <th style="text-align:left;padding:8px 12px;font-weight:600;color:var(--stone);font-size:11px;letter-spacing:.5px;text-transform:uppercase">Before</th>
                  <th style="text-align:left;padding:8px 12px;font-weight:600;color:var(--stone);font-size:11px;letter-spacing:.5px;text-transform:uppercase">After</th>
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
            <button class="ibtn ibtn-p" @click="doConfirmedEditSave" :disabled="editSaving">
              <span v-if="editSaving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ editSaving ? 'Saving...' : 'Confirm & Save' }}
            </button>
            <button class="ibtn ibtn-o" @click="showEditConfirm = false">Go Back &amp; Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Student Modal -->
    <div v-if="showAddModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showAddModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Add Student</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px">
            Student Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Student ID <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.student_id"
                class="ifi"
                maxlength="15"
                placeholder="e.g. 2302021"
                :style="errorStyle('student_id')"
                @input="addForm.student_id = onlyDigits(addForm.student_id); clearFieldError('student_id')"
              />
            </div>
            <div>
              <label class="ifl">Sex <span style="color:var(--red)">*</span></label>
              <select
                v-model="addForm.sex"
                class="ifse"
                :style="errorStyle('sex')"
                @change="clearFieldError('sex')"
              >
                <option value="" disabled hidden>Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div>
              <label class="ifl">Last Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.last_name"
                class="ifi"
                maxlength="20"
                placeholder="Dela Cruz"
                :style="errorStyle('last_name')"
                @input="addForm.last_name = titleCase(onlyLetters(addForm.last_name)); clearFieldError('last_name')"
              />
            </div>
            <div>
              <label class="ifl">First Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.first_name"
                class="ifi"
                maxlength="20"
                placeholder="Juan"
                :style="errorStyle('first_name')"
                @input="addForm.first_name = titleCase(onlyLetters(addForm.first_name)); clearFieldError('first_name')"
              />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="addForm.middle_name" class="ifi" maxlength="20" placeholder="Santos" @input="addForm.middle_name = titleCase(onlyLetters(addForm.middle_name))" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <input v-model="addForm.suffix" class="ifi" maxlength="20" placeholder="Jr., Sr., III" @input="addForm.suffix = onlyLettersStrict(addForm.suffix)" />
            </div>
            <div>
              <label class="ifl">College <span style="color:var(--red)">*</span></label>
              <select
                v-model="addForm.college"
                class="ifse"
                :style="errorStyle('college')"
                @change="addForm.program = ''; addForm.year_level = ''; clearFieldError('college')"
              >
                <option value="" disabled hidden>Select college...</option>
                <option v-for="c in colleges" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Program <span style="color:var(--red)">*</span></label>
              <select
                v-model="addForm.program"
                class="ifse"
                :disabled="!addForm.college"
                :style="errorStyle('program')"
                @change="clearFieldError('program')"
              >
                <option value="" disabled hidden>Select program...</option>
                <option v-for="p in availablePrograms" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Year Level <span style="color:var(--red)">*</span></label>
              <select
                v-model="addForm.year_level"
                class="ifse"
                :style="errorStyle('year_level')"
                @change="clearFieldError('year_level')"
              >
                <option value="" disabled hidden>Select...</option>
                <option v-for="yl in addYearLevelOptions" :key="yl" :value="yl">{{ yl }}</option>
              </select>
            </div>
            <div>
              <label class="ifl">Section <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.section"
                class="ifi"
                placeholder="e.g. A"
                maxlength="1"
                :style="errorStyle('section')"
                @input="addForm.section = addForm.section.replace(/[^a-zA-Z]/g, '').slice(0, 1).toUpperCase(); clearFieldError('section')"
              />
            </div>
            <div>
              <label class="ifl">Email Address <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.email"
                class="ifi"
                placeholder="student@bsu.edu.ph"
                :style="errorStyle('email')"
                @input="clearFieldError('email')"
              />
              <div v-if="addErrors.email" style="font-size:11px;color:var(--red);margin-top:4px">
                {{ addErrors.email }}
              </div>
            </div>
            <div>
              <label class="ifl">Contact Number <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.contact_number"
                class="ifi"
                placeholder="09XXXXXXXXX"
                maxlength="11"
                :style="errorStyle('contact_number')"
                @input="addForm.contact_number = contactNumberBlockingNonZero(addForm.contact_number); clearFieldError('contact_number')"
              />
              <div v-if="addErrors.contact_number" style="font-size:11px;color:var(--red);margin-top:4px">
                {{ addErrors.contact_number }}
              </div>
            </div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px;margin-top:4px">
            Guardian Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Guardian Last Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.guardian_last_name"
                class="ifi"
                maxlength="20"
                placeholder="Santos"
                :style="errorStyle('guardian_last_name')"
                @input="addForm.guardian_last_name = titleCase(onlyLetters(addForm.guardian_last_name)); clearFieldError('guardian_last_name')"
              />
            </div>
            <div>
              <label class="ifl">Guardian First Name <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.guardian_first_name"
                class="ifi"
                maxlength="20"
                placeholder="Maria"
                :style="errorStyle('guardian_first_name')"
                @input="addForm.guardian_first_name = titleCase(onlyLetters(addForm.guardian_first_name)); clearFieldError('guardian_first_name')"
              />
            </div>
            <div>
              <label class="ifl">Guardian Middle Name</label>
              <input v-model="addForm.guardian_middle_name" class="ifi" maxlength="20" placeholder="Reyes" @input="addForm.guardian_middle_name = titleCase(onlyLetters(addForm.guardian_middle_name))" />
            </div>
            <div>
              <label class="ifl">Guardian Contact <span style="color:var(--red)">*</span></label>
              <input
                v-model="addForm.guardian_contact"
                class="ifi"
                placeholder="09XXXXXXXXX"
                maxlength="11"
                :style="errorStyle('guardian_contact')"
                @input="addForm.guardian_contact = contactNumberBlockingNonZero(addForm.guardian_contact); clearFieldError('guardian_contact')"
              />
              <div v-if="addErrors.guardian_contact" style="font-size:11px;color:var(--red);margin-top:4px">
                {{ addErrors.guardian_contact }}
              </div>
            </div>
            <div>
              <label class="ifl">Guardian Relationship <span style="color:var(--red)">*</span></label>
              <select
                v-model="addForm.guardian_relationship"
                class="ifse"
                :style="errorStyle('guardian_relationship')"
                @change="clearFieldError('guardian_relationship')"
              >
                <option value="" disabled hidden>Select...</option>
                <option>Mother</option>
                <option>Father</option>
                <option>Guardian</option>
                <option>Sibling</option>
                <option>Relative</option>
              </select>
            </div>
          </div>

          <div v-if="addError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">
            {{ addError }}
          </div>
          <div v-if="createdPassword" style="background:var(--mist);border:1px solid var(--mint);border-radius:var(--r-sm);padding:12px 14px;font-size:13px;color:var(--forest)">
            ✓ Student added. Temporary password: <strong style="font-family:var(--mono)">{{ createdPassword }}</strong>
            <div style="font-size:11px;color:var(--stone);margin-top:4px">Share this with the student - they'll be required to change it on first login. This window will close automatically in a few seconds.</div>
          </div>
          <div style="display:flex;gap:8px;padding-top:4px">
            <button class="ibtn ibtn-p" type="button" @click="goToPreview" :disabled="saving">
              <svg v-if="!saving" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-if="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ saving ? 'Saving...' : 'Add Student' }}
            </button>
            <button
              class="ibtn"
              type="button"
              :style="isAddFormEmpty ? 'background:var(--cloud);color:var(--fog);border:1.5px solid var(--cloud);cursor:not-allowed;opacity:.6' : 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0'"
              @click="handleClearAddForm"
              :disabled="isAddFormEmpty"
            >Clear Form</button>
            <button class="ibtn ibtn-o" type="button" @click="showAddModal = false">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Student Confirmation Preview Modal -->
    <div v-if="showAddPreview" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:65;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showAddPreview = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Student Details</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="showAddPreview = false">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--stone)">Please review the information below before adding this student:</div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px">
            Student Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><strong>Student ID:</strong> {{ addForm.student_id }}</div>
            <div><strong>Name:</strong> {{ addForm.last_name }}, {{ addForm.first_name }} {{ addForm.middle_name }} {{ addForm.suffix }}</div>
            <div><strong>Sex:</strong> {{ addForm.sex }}</div>
            <div><strong>College:</strong> {{ addForm.college }}</div>
            <div><strong>Program:</strong> {{ addForm.program }}</div>
            <div><strong>Year Level:</strong> {{ addForm.year_level }}</div>
            <div><strong>Section:</strong> {{ addForm.section }}</div>
            <div><strong>Email:</strong> {{ addForm.email }}</div>
            <div><strong>Contact Number:</strong> {{ addForm.contact_number }}</div>
          </div>

          <div style="font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--fog);display:flex;align-items:center;gap:8px">
            Guardian Information
            <div style="flex:1;height:1px;background:var(--cloud)"></div>
          </div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><strong>Name:</strong> {{ addForm.guardian_last_name }}, {{ addForm.guardian_first_name }} {{ addForm.guardian_middle_name }}</div>
            <div><strong>Contact Number:</strong> {{ addForm.guardian_contact }}</div>
            <div><strong>Relationship:</strong> {{ addForm.guardian_relationship }}</div>
          </div>

          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="confirmAddSubmit" :disabled="saving">
              <span v-if="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block"></span>
              {{ saving ? 'Adding...' : 'Confirm & Add Student' }}
            </button>
            <button class="ibtn ibtn-o" @click="showAddPreview = false">Go Back &amp; Edit</button>
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
            All entered information will be cleared. This cannot be undone.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0" @click="doConfirmedClear">Yes, Clear Form</button>
            <button class="ibtn ibtn-o" @click="showClearConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Activate Confirmation -->
    <div v-if="showActivateConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showActivateConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Activate Student Account?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            Reactivate <strong>{{ studentToActivate?.first_name }} {{ studentToActivate?.last_name }}</strong>'s account? They will be able to log in again.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" @click="doConfirmedActivate">Yes, Activate</button>
            <button class="ibtn ibtn-o" @click="showActivateConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Reset Password Confirmation -->
    <div v-if="showResetPwConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:75;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showResetPwConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Reset Student Password?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            A new temporary password will be generated for <strong>{{ viewedStudent?.first_name }} {{ viewedStudent?.last_name }}</strong>. They'll be required to change it on next login.
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn" style="background:var(--amber-lt);color:var(--amber);border:1.5px solid var(--amber)" @click="doConfirmedResetPw">Yes, Reset Password</button>
            <button class="ibtn ibtn-o" @click="showResetPwConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Import Modal -->
    <div v-if="showImportModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeImportModal">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:560px;overflow:hidden;box-shadow:var(--sh-lg);max-height:90vh;overflow-y:auto">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:#fff;z-index:1">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Upload Student Masterlist</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="closeImportModal">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">

          <template v-if="!previewData.total">
            <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:12px;color:var(--stone);line-height:1.6">
              Download the template below, fill it in, then upload it here.
            </div>
            <a href="/templates/student_masterlist_template.xlsx" download class="ibtn ibtn-o" style="width:100%;justify-content:center">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Download Template
            </a>
            <div>
              <label class="ifl">File</label>
              <input type="file" accept=".csv,.xlsx,.xls" class="ifi" @change="handleFileSelect" />
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
          </template>

          <template v-else>
            <div style="font-size:13px;color:var(--stone)">{{ previewData.total }} record(s) found - {{ previewData.duplicates }} duplicate(s)</div>

            <div v-if="previewData.duplicates > 0" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:10px 12px;font-size:12px;color:var(--amber)">
              ⚠ Some Student IDs already exist. Choose how to handle all duplicates below.
            </div>

            <div style="max-height:280px;overflow-y:auto;border:1px solid var(--cloud);border-radius:var(--r-sm)">
              <table class="itable">
                <thead>
                  <tr>
                    <th>Row</th>
                    <th>Student ID</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in previewData.preview" :key="item.row">
                    <td style="font-size:12px">{{ item.row }}</td>
                    <td style="font-family:var(--mono);font-size:12px">{{ item.student_id || '-' }}</td>
                    <td>
                      <span v-if="!item.valid" class="ibadge" style="background:var(--red-lt);color:var(--red)">Invalid</span>
                      <span v-else-if="item.is_duplicate" class="ibadge" style="background:var(--amber-lt);color:var(--amber)">Duplicate</span>
                      <span v-else class="ibadge" style="background:var(--mist);color:var(--moss)">New</span>
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
          <button class="ibtn ibtn-g ibtn-sm" @click="showImportConfirm = false">✕</button>
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

    <!-- Duplicate Name Warning Modal -->
    <div v-if="showDuplicateNameModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Similar Student Record Found</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            A student named <strong>{{ duplicateStudent?.last_name }}, {{ duplicateStudent?.first_name }} {{ duplicateStudent?.middle_name }}</strong> (ID: {{ duplicateStudent?.student_id }}) already exists. Would you like to update their existing record, or keep it as is?
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" style="flex:1;justify-content:center" @click="updateExistingAndProceed" :disabled="saving">Update Existing</button>
            <button class="ibtn" style="flex:1;justify-content:center;background:var(--cloud);color:var(--stone)" @click="keepExistingAndCancel">Keep Existing Information</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Deactivate Student Modal -->
    <div v-if="showGraduateModal" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showGraduateModal = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Deactivate Student Account?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            Deactivating preserves the student's records, which can be reactivated later if needed.
          </div>
          <div style="position:relative">
            <label class="ifl">Reason for Deactivation <span style="color:var(--red)">*</span></label>
            <input
              v-model="reasonSearchQuery"
              class="ifi"
              placeholder="Search or select a reason..."
              @focus="showReasonDropdown = true"
              @input="showReasonDropdown = true; graduateReason = ''"
              autocomplete="off"
            />
            <div
              v-if="showReasonDropdown && filteredReasons.length > 0"
              style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--cloud);border-radius:var(--r-sm);box-shadow:var(--sh-lg);z-index:50;max-height:180px;overflow-y:auto;margin-top:4px"
            >
              <div
                v-for="r in filteredReasons"
                :key="r.value"
                style="padding:9px 14px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--cloud)"
                @mouseover="$event.currentTarget.style.background='var(--foam)'"
                @mouseleave="$event.currentTarget.style.background='#fff'"
                @click="selectReason(r)"
              >{{ r.label }}</div>
            </div>
          </div>
          <div v-if="graduateReason === 'other'">
            <label class="ifl">Please specify</label>
            <input v-model="graduateNotes" class="ifi" placeholder="Reason details" />
          </div>
          <div style="display:flex;gap:8px">
            <button
              class="ibtn"
              :style="canDeactivate ? 'background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0' : 'background:var(--cloud);color:var(--fog);border:1.5px solid var(--cloud);cursor:not-allowed;opacity:.6'"
              :disabled="!canDeactivate"
              @click="openDeactivateConfirm"
            >Deactivate Account</button>
            <button class="ibtn ibtn-o" @click="showGraduateModal = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Deactivate Confirmation Modal -->
    <div v-if="showDeactivateConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:70;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showDeactivateConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:440px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Deactivation</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">
            You are about to deactivate <strong>{{ studentToGraduate?.first_name }} {{ studentToGraduate?.last_name }}</strong>'s account.
          </div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:12px 14px;font-size:13px">
            <div><strong>Reason:</strong> {{ reasonSearchQuery || '—' }}</div>
            <div v-if="graduateReason === 'other' && graduateNotes" style="margin-top:4px"><strong>Details:</strong> {{ graduateNotes }}</div>
          </div>
          <div style="font-size:12px;color:var(--stone)">
            This can be reversed later by reactivating the account.
          </div>
          <div style="display:flex;gap:8px">
            <button
              class="ibtn"
              style="background:var(--red-lt);color:var(--red);border:1.5px solid #f5c0c0"
              @click="confirmDeactivate"
            >Yes, Deactivate</button>
            <button class="ibtn ibtn-o" @click="showDeactivateConfirm = false">Cancel</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import { studentAPI } from '../../api/index';
import { COLLEGES } from '../../constants/colleges';
import { PROGRAMS_BY_COLLEGE } from '../../constants/programs';
import { onlyLetters, onlyLettersStrict, onlyDigits, contactNumberInput, isValidPHContact, isValidEmail, safeSearchInput, blockSpecialKeypress } from '../../utils/validators';

const toast   = inject('toast');
const colleges = COLLEGES;

const YEAR_LEVEL_LABELS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];

const students   = ref([]);
const loading    = ref(false);
const saving     = ref(false);
const pagination = ref({});
const filters    = ref({ search: '', college: '', sort_by: 'created_at', sort_dir: 'desc' });
const sortOption = ref('created_at:desc');

function applySort() {
  const [sortBy, sortDir] = sortOption.value.split(':');
  filters.value.sort_by = sortBy;
  filters.value.sort_dir = sortDir;
  fetchStudents();
}
const showArchived = ref(false);
const showAddModal    = ref(false);
const showGraduateModal = ref(false);
const studentToGraduate = ref(null);
const graduateReason = ref('');
const graduateNotes = ref('');
const canDeactivate = computed(() =>
  graduateReason.value &&
  (graduateReason.value !== 'other' || graduateNotes.value.trim())
);

const REASON_OPTIONS = [
  { value: 'no_longer_enrolled', label: 'No longer enrolled' },
  { value: 'leave_of_absence', label: 'Leave of Absence (LOA)' },
  { value: 'disciplinary_suspension', label: 'Disciplinary Suspension' },
  { value: 'other', label: 'Other' },
];
const reasonSearchQuery = ref('');
const showReasonDropdown = ref(false);
const filteredReasons = computed(() =>
  REASON_OPTIONS.filter(r => r.label.toLowerCase().includes(reasonSearchQuery.value.toLowerCase()))
);
function selectReason(r) {
  graduateReason.value = r.value;
  reasonSearchQuery.value = r.label;
  showReasonDropdown.value = false;
}

const addError = ref('');
const createdPassword = ref('');

const showViewModal  = ref(false);
const viewedStudent  = ref({});
const showTempPassword  = ref(false);
const tempPasswordValue = ref('');
const resetPasswordResult = ref('');

const showEditModal = ref(false);
const editForm      = ref({});
const editFormSnapshot = ref('{}');
const editError      = ref('');
const editSaving     = ref(false);
const editAvailablePrograms = computed(() => PROGRAMS_BY_COLLEGE[editForm.value.college] || []);
const editYearLevelOptions = computed(() => YEAR_LEVEL_LABELS);
const isEditFormUnchanged = computed(() => JSON.stringify(editForm.value) === editFormSnapshot.value);

// Red border for Edit modal
const editFieldErrors = ref({});
function clearEditFieldError(field) {
  if (editFieldErrors.value[field]) {
    editFieldErrors.value = { ...editFieldErrors.value, [field]: false };
  }
}
function editErrorStyle(field) {
  return editFieldErrors.value[field]
    ? 'border-color:var(--red);border-width:1.5px'
    : '';
}

const editErrors = computed(() => {
  const f = editForm.value;
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

  if (f.guardian_contact) {
    if (!f.guardian_contact.startsWith('0')) {
      errs.guardian_contact = 'Guardian contact must start with 0.';
    } else if (!isValidPHContact(f.guardian_contact)) {
      errs.guardian_contact = 'Must be 11 digits starting with 09.';
    } else if (f.contact_number && f.guardian_contact === f.contact_number) {
      errs.guardian_contact = "Can't be the same as the student's own contact number.";
    }
  }

  return errs;
});

const showEditConfirm = ref(false);

const editChanges = computed(() => {
  try {
    const before = JSON.parse(editFormSnapshot.value || '{}');
    const after  = editForm.value || {};
    const labels = {
      student_id: 'Student ID',
      last_name: 'Last Name',
      first_name: 'First Name',
      middle_name: 'Middle Name',
      suffix: 'Suffix',
      sex: 'Sex',
      college: 'College',
      program: 'Program',
      year_level: 'Year Level',
      section: 'Section',
      email: 'Email Address',
      contact_number: 'Contact Number',
      guardian_last_name: 'Guardian Last Name',
      guardian_first_name: 'Guardian First Name',
      guardian_middle_name: 'Guardian Middle Name',
      guardian_contact: 'Guardian Contact',
      guardian_relationship: 'Guardian Relationship',
    };
    const out = [];
    for (const key of Object.keys(labels)) {
      const a = before[key] ?? '';
      const b = after[key]  ?? '';
      if (String(a) !== String(b)) {
        out.push({ label: labels[key], before: a || '—', after: b || '—' });
      }
    }
    return out;
  } catch {
    return [];
  }
});

function openEditConfirm() {
  if (isEditFormUnchanged.value) return;

  editError.value = '';
  const requiredFields = [
    ['student_id', 'Student ID'], ['last_name', 'Last Name'], ['first_name', 'First Name'],
    ['sex', 'Sex'], ['college', 'College'], ['program', 'Program'], ['year_level', 'Year Level'],
    ['section', 'Section'], ['email', 'Email Address'], ['contact_number', 'Contact Number'],
    ['guardian_first_name', 'Guardian First Name'], ['guardian_last_name', 'Guardian Last Name'],
    ['guardian_contact', 'Guardian Contact'], ['guardian_relationship', 'Guardian Relationship'],
  ];
  const errs = {};
  const missing = [];
  for (const [key, label] of requiredFields) {
    if (!editForm.value[key]) { errs[key] = true; missing.push(label); }
  }
  if (editForm.value.email && !isValidEmail(editForm.value.email)) errs.email = true;
  if (editForm.value.contact_number && !isValidPHContact(editForm.value.contact_number)) errs.contact_number = true;
  if (editForm.value.guardian_contact && !isValidPHContact(editForm.value.guardian_contact)) errs.guardian_contact = true;

  editFieldErrors.value = errs;

  if (missing.length) {
    editError.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }
  if (editForm.value.email && !isValidEmail(editForm.value.email)) {
    editError.value = 'Please enter a valid email address.';
    return;
  }
  if (editForm.value.contact_number && !isValidPHContact(editForm.value.contact_number)) {
    editError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }
  if (!isValidPHContact(editForm.value.guardian_contact)) {
    editError.value = 'Guardian contact number must start with 09 and be 11 digits long.';
    return;
  }

  showEditConfirm.value = true;
}

async function doConfirmedEditSave() {
  showEditConfirm.value = false;
  await saveEditedStudent();
}

const showDuplicateNameModal = ref(false);
const duplicateStudent = ref(null);

const addForm = ref({
  student_id: '', last_name: '', first_name: '', middle_name: '', suffix: '', sex: '',
  college: '', program: '', year_level: '', section: '', email: '', contact_number: '',
  guardian_first_name: '', guardian_middle_name: '', guardian_last_name: '',
  guardian_contact: '', guardian_relationship: '',
});

const availablePrograms = computed(() => PROGRAMS_BY_COLLEGE[addForm.value.college] || []);
const addYearLevelOptions = computed(() => YEAR_LEVEL_LABELS);

function titleCase(str) {
  return (str || '').replace(/\w\S*/g, w => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase());
}

function contactNumberBlockingNonZero(value) {
  if (!value) return '';
  if (!value.startsWith('0')) return '';
  return contactNumberInput(value);
}

const isAddFormEmpty = computed(() => Object.values(addForm.value).every(v => !v));

const addErrors = computed(() => {
  const f = addForm.value;
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

  if (f.guardian_contact) {
    if (!f.guardian_contact.startsWith('0')) {
      errs.guardian_contact = 'Guardian contact must start with 0.';
    } else if (!isValidPHContact(f.guardian_contact)) {
      errs.guardian_contact = 'Must be 11 digits starting with 09.';
    } else if (f.contact_number && f.guardian_contact === f.contact_number) {
      errs.guardian_contact = "Can't be the same as the student's own contact number.";
    }
  }

  return errs;
});

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

function validateAddForm() {
  const requiredFields = [
    ['student_id', 'Student ID'],
    ['last_name', 'Last Name'],
    ['first_name', 'First Name'],
    ['sex', 'Sex'],
    ['college', 'College'],
    ['program', 'Program'],
    ['year_level', 'Year Level'],
    ['section', 'Section'],
    ['email', 'Email Address'],
    ['contact_number', 'Contact Number'],
    ['guardian_first_name', 'Guardian First Name'],
    ['guardian_last_name', 'Guardian Last Name'],
    ['guardian_contact', 'Guardian Contact'],
    ['guardian_relationship', 'Guardian Relationship'],
  ];

  const errs = {};
  for (const [key] of requiredFields) {
    if (!addForm.value[key]) errs[key] = true;
  }
  if (addForm.value.email && !isValidEmail(addForm.value.email)) errs.email = true;
  if (addForm.value.contact_number && !isValidPHContact(addForm.value.contact_number)) errs.contact_number = true;
  if (addForm.value.guardian_contact && !isValidPHContact(addForm.value.guardian_contact)) errs.guardian_contact = true;
  if (addForm.value.contact_number && addForm.value.guardian_contact &&
      addForm.value.contact_number === addForm.value.guardian_contact) {
    errs.guardian_contact = true;
  }

  fieldErrors.value = errs;

  const missing = requiredFields.filter(([key]) => !addForm.value[key]).map(([, label]) => label);
  if (missing.length) return `Please fill in: ${missing.join(', ')}.`;
  if (!isValidEmail(addForm.value.email)) return 'Please enter a valid email address.';
  if (!isValidPHContact(addForm.value.contact_number)) return 'Contact number must start with 09 and be 11 digits long.';
  if (!isValidPHContact(addForm.value.guardian_contact)) return 'Guardian contact number must start with 09 and be 11 digits long.';
  if (addForm.value.guardian_contact === addForm.value.contact_number) {
    return "Guardian contact number can't be the same as the student's own contact number.";
  }
  return null;
}

// Deactivate flow
const showDeactivateConfirm = ref(false);

function openDeactivateConfirm() {
  if (!canDeactivate.value) return;
  showDeactivateConfirm.value = true;
}

async function confirmDeactivate() {
  showDeactivateConfirm.value = false;
  await doGraduate();
  showGraduateModal.value = false;
}

// Activate flow
const showActivateConfirm = ref(false);
const studentToActivate = ref(null);

function openActivateConfirm(s) {
  studentToActivate.value = s;
  showActivateConfirm.value = true;
}

async function doConfirmedActivate() {
  const s = studentToActivate.value;
  showActivateConfirm.value = false;
  if (!s) return;
  try {
    await studentAPI.toggleActive(s.id);
    toast?.success('Student account activated.');
    fetchStudents();
  } catch (e) {
    toast?.error('Please try again.');
  }
}

// Reset password flow
const showResetPwConfirm = ref(false);

const doConfirmedResetPw = async () => {
  showResetPwConfirm.value = false;
  try {
    const res = await studentAPI.resetPassword(viewedStudent.value.id);
    resetPasswordResult.value = res.data.temp_password;
    tempPasswordValue.value = res.data.temp_password;
    showTempPassword.value = true;
    viewedStudent.value.must_change_password = true;
    toast?.success('Password reset successfully.');
  } catch (e) {
    toast?.error('Failed to reset password.');
  }
};

// Clear form flow
const showClearConfirm = ref(false);

function openClearConfirm() {
  if (isAddFormEmpty.value) return;
  showClearConfirm.value = true;
}

function doConfirmedClear() {
  showClearConfirm.value = false;
  fieldErrors.value = {};
  clearAddForm();
}

function handleClearAddForm() {
  openClearConfirm();
}

const showAddPreview = ref(false);

function goToPreview() {
  addError.value = '';
  fieldErrors.value = {};
  const err = validateAddForm();
  if (err) { addError.value = err; return; }
  showAddPreview.value = true;
}

async function confirmAddSubmit() {
  showAddPreview.value = false;
  await saveStudent();
}

// Import flow
const showImportModal  = ref(false);
const loadingPreview   = ref(false);
const previewError     = ref('');
const previewData      = ref({ preview: [], total: 0, duplicates: 0, token: '' });
const importing        = ref(false);

const showImportConfirm = ref(false);
const pendingImportChoice = ref('');

const importSummary = computed(() => {
  const items = previewData.value.preview || [];
  return {
    newRecords:   items.filter(i => i.valid && !i.is_duplicate).length,
    duplicates:   items.filter(i => i.valid && i.is_duplicate).length,
    invalid:      items.filter(i => !i.valid).length,
  };
});

function openImportConfirm(choice) {
  pendingImportChoice.value = choice;
  showImportConfirm.value = true;
}

async function doConfirmedImport() {
  const choice = pendingImportChoice.value;
  showImportConfirm.value = false;
  await confirmImport(choice);
}

let searchTimeout = null;

function switchTab(archived) {
  showArchived.value = archived;
  filters.value.search = '';
  fetchStudents();
}

function onSearchInput() {
  filters.value.search = safeSearchInput(filters.value.search);
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => fetchStudents(), 400);
}

async function fetchStudents(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters.value, page };
    params.is_active = showArchived.value ? 0 : 1;
    const res = await studentAPI.index(params);
    students.value   = res.data.data;
    pagination.value = res.data;
  } catch (e) {
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.value = { search: '', college: '', sort_by: 'created_at', sort_dir: 'desc' };
  sortOption.value = 'created_at:desc';
  fetchStudents();
}

function changePage(page) { fetchStudents(page); }

function openAddModal() {
  clearAddForm();
  createdPassword.value = '';
  showAddModal.value = true;
}

function clearAddForm() {
  addForm.value = {
    student_id: '', last_name: '', first_name: '', middle_name: '', suffix: '', sex: '',
    college: '', program: '', year_level: '', section: '', email: '', contact_number: '',
    guardian_first_name: '', guardian_middle_name: '', guardian_last_name: '',
    guardian_contact: '', guardian_relationship: '',
  };
  fieldErrors.value = {};
  addError.value = '';
}

async function saveStudent() {
  addError.value = '';

  const requiredFields = [
    ['student_id', 'Student ID'],
    ['last_name', 'Last Name'],
    ['first_name', 'First Name'],
    ['sex', 'Sex'],
    ['college', 'College'],
    ['program', 'Program'],
    ['year_level', 'Year Level'],
    ['section', 'Section'],
    ['email', 'Email Address'],
    ['contact_number', 'Contact Number'],
    ['guardian_first_name', 'Guardian First Name'],
    ['guardian_last_name', 'Guardian Last Name'],
    ['guardian_contact', 'Guardian Contact'],
    ['guardian_relationship', 'Guardian Relationship'],
  ];
  const missing = requiredFields.filter(([key]) => !addForm.value[key]).map(([, label]) => label);
  if (missing.length) {
    addError.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }

  if (!isValidEmail(addForm.value.email)) {
    addError.value = 'Please enter a valid email address.';
    return;
  }

  if (!isValidPHContact(addForm.value.contact_number)) {
    addError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }

  if (!isValidPHContact(addForm.value.guardian_contact)) {
    addError.value = 'Guardian contact number must start with 09 and be 11 digits long.';
    return;
  }

  if (addForm.value.guardian_contact === addForm.value.contact_number) {
    addError.value = "Guardian contact number can't be the same as the student's own contact number.";
    return;
  }

  try {
    const dupRes = await studentAPI.checkDuplicateName({
      first_name: addForm.value.first_name,
      last_name: addForm.value.last_name,
      middle_name: addForm.value.middle_name,
    });
    if (dupRes.data.duplicate_found) {
      duplicateStudent.value = dupRes.data.existing_student;
      showDuplicateNameModal.value = true;
      return;
    }
  } catch (e) {
    // continue if check fails
  }

  await doSaveStudent();
}

async function doSaveStudent() {
  saving.value = true;
  try {
    const res = await studentAPI.store(addForm.value);
    createdPassword.value = res.data.temp_password || '';
    toast?.success('Student added successfully.');
    showDuplicateNameModal.value = false;
    fetchStudents();
    setTimeout(() => { showAddModal.value = false; }, 4000);
  } catch (e) {
    if (e.response?.data?.errors?.student_id) {
      addError.value = 'This Student ID is already taken.';
    } else {
      addError.value = 'Please fill in all required fields.';
    }
  } finally {
    saving.value = false;
  }
}

function keepExistingAndCancel() {
  showDuplicateNameModal.value = false;
  showAddModal.value = false;
  toast?.success('Kept existing student record. No new entry created.');
}

async function updateExistingAndProceed() {
  saving.value = true;
  try {
    await studentAPI.update(duplicateStudent.value.id, addForm.value);
    toast?.success('Existing student record updated.');
    showDuplicateNameModal.value = false;
    showAddModal.value = false;
    fetchStudents();
  } catch (e) {
    toast?.error('Failed to update existing record.');
  } finally {
    saving.value = false;
  }
}

async function doGraduate() {
  if (!studentToGraduate.value || !canDeactivate.value) return;
  try {
    await studentAPI.graduate(studentToGraduate.value.id, {
      deactivation_reason: graduateReason.value,
      deactivation_notes: graduateReason.value === 'other' ? graduateNotes.value : null,
    });
    showGraduateModal.value = false;
    toast?.success('Deactivated Student Account.');
    fetchStudents();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Please fill in all required fields.');
  }
}

function confirmGraduate(s) {
  studentToGraduate.value = s;
  graduateReason.value = '';
  reasonSearchQuery.value = '';
  showReasonDropdown.value = false;
  graduateNotes.value = '';
  showGraduateModal.value = true;
}

function toggleActive(s) {
  openActivateConfirm(s);
}

function initials(first, last) {
  return ((first?.[0] || '') + (last?.[0] || '')).toUpperCase() || '?';
}

function openView(s) {
  viewedStudent.value = s;
  resetPasswordResult.value = '';
  showTempPassword.value = false;
  tempPasswordValue.value = '';
  showViewModal.value = true;
}

function openEditFromView() {
  showViewModal.value = false;
  editForm.value = { ...viewedStudent.value };

  // Salvage legacy imported contacts missing the leading 0
  const cn = editForm.value.contact_number || '';
  if (cn && !cn.startsWith('0') && /^\d+$/.test(cn)) {
    editForm.value.contact_number = '0' + cn;
  }
  const gc = editForm.value.guardian_contact || '';
  if (gc && !gc.startsWith('0') && /^\d+$/.test(gc)) {
    editForm.value.guardian_contact = '0' + gc;
  }

  editFormSnapshot.value = JSON.stringify(editForm.value);
  editError.value = '';
  editFieldErrors.value = {};
  showEditModal.value = true;
}

async function saveEditedStudent() {
  editError.value = '';

  const requiredFields = [
    ['student_id', 'Student ID'], ['last_name', 'Last Name'], ['first_name', 'First Name'],
    ['sex', 'Sex'], ['college', 'College'], ['program', 'Program'], ['year_level', 'Year Level'],
    ['section', 'Section'], ['email', 'Email Address'], ['contact_number', 'Contact Number'],
    ['guardian_first_name', 'Guardian First Name'], ['guardian_last_name', 'Guardian Last Name'],
    ['guardian_contact', 'Guardian Contact'], ['guardian_relationship', 'Guardian Relationship'],
  ];

  const errs = {};
  for (const [key] of requiredFields) {
    if (!editForm.value[key]) errs[key] = true;
  }
  if (editForm.value.email && !isValidEmail(editForm.value.email)) errs.email = true;
  if (editForm.value.contact_number && !isValidPHContact(editForm.value.contact_number)) errs.contact_number = true;
  if (editForm.value.guardian_contact && !isValidPHContact(editForm.value.guardian_contact)) errs.guardian_contact = true;
  editFieldErrors.value = errs;

  const missing = requiredFields.filter(([key]) => !editForm.value[key]).map(([, label]) => label);
  if (missing.length) {
    editError.value = `Please fill in: ${missing.join(', ')}.`;
    return;
  }

  if (!isValidEmail(editForm.value.email)) {
    editError.value = 'Please enter a valid email address.';
    return;
  }

  if (editForm.value.contact_number && !isValidPHContact(editForm.value.contact_number)) {
    editError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }

  if (!isValidPHContact(editForm.value.guardian_contact)) {
    editError.value = 'Guardian contact number must start with 09 and be 11 digits long.';
    return;
  }

  editSaving.value = true;
  try {
    const res = await studentAPI.update(editForm.value.id, editForm.value);
    Object.assign(viewedStudent.value, res.data);
    const idx = students.value.findIndex(s => s.id === res.data.id);
    if (idx !== -1) students.value[idx] = res.data;
    showEditModal.value = false;
    toast?.success('Student profile updated successfully.');
  } catch (e) {
    editError.value = e.response?.data?.message || 'Please fill in all required fields.';
  } finally {
    editSaving.value = false;
  }
}

function resetStudentPassword() {
  showResetPwConfirm.value = true;
}

async function toggleTempPasswordVisible() {
  showTempPassword.value = !showTempPassword.value;
  if (showTempPassword.value && !tempPasswordValue.value) {
    try {
      const res = await studentAPI.viewTempPassword(viewedStudent.value.id);
      tempPasswordValue.value = res.data.temp_password;
    } catch (e) {
      tempPasswordValue.value = 'Unable to retrieve.';
    }
  }
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
    const res = await studentAPI.importPreview(formData);

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

    const res = await studentAPI.importConfirm({
      token: previewData.value.token,
      decisions: finalDecisions,
    });
    toast?.success(`${res.data.created} added, ${res.data.updated} updated, ${res.data.skipped} skipped.`);
    closeImportModal();
    fetchStudents();
  } catch (e) {
    toast?.error(e.response?.data?.message || 'Failed to complete import.');
  } finally {
    importing.value = false;
  }
}

onMounted(() => fetchStudents());
</script>