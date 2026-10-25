<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Account</h1>
      <p>Fill in your Personal, Family, Siblings and Educational information, and update your password.</p>
    </div>

    <!-- Profile header, then one section at a time behind tabs, in a column of
         comfortable width - the full form is far too long to show at once. -->
    <div style="max-width:980px;margin:0 auto;display:flex;flex-direction:column;gap:16px">

      <div v-if="student.must_change_password" style="background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:14px 16px;color:var(--amber);font-size:13px;line-height:1.6">
        <strong>Please set a new password to continue.</strong> You are signed in with a temporary password (a one-time password or one reset by an administrator). The rest of the menu stays locked until you change it in the Password tab below.
      </div>

      <div class="icard" style="padding:18px 20px;display:flex;align-items:center;gap:14px">
        <div style="width:48px;height:48px;border-radius:50%;background:var(--forest);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0">{{ headerInitials }}</div>
        <div style="min-width:0">
          <div style="font-size:16px;font-weight:600;color:var(--ink)">{{ student.first_name }} {{ student.last_name }}</div>
        </div>
      </div>

      <div style="display:flex;gap:6px;overflow-x:auto;padding-bottom:2px">
        <button
          v-for="t in TABS"
          :key="t.value"
          type="button"
          class="ibtn ibtn-sm"
          :class="tab === t.value ? 'ibtn-p' : 'ibtn-o'"
          style="flex-shrink:0"
          @click="tab = t.value"
        >
          {{ t.label }}
        </button>
      </div>

      <div v-show="tab === 'personal'" class="icard">
        <div class="icard-header"><span class="icard-title">Personal Information</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="profileError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ profileError }}</div>
          <div v-if="profileSuccess" style="background:var(--mist);border:1px solid #bfe3c8;color:var(--moss);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ profileSuccess }}</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="profileForm.last_name" class="ifi" maxlength="20" :style="lastNameProblem ? ERR_BORDER : ''" @input="profileForm.last_name = capFirst(profileForm.last_name.normalize('NFC').replace(/[^\p{L}\p{M}\s'-]/gu, ''))" />
              <div v-if="lastNameProblem" class="fe">{{ lastNameProblem }}</div>
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="profileForm.first_name" class="ifi" maxlength="20" :style="firstNameProblem ? ERR_BORDER : ''" @input="profileForm.first_name = capFirst(profileForm.first_name.normalize('NFC').replace(/[^\p{L}\p{M}\s'-]/gu, ''))" />
              <div v-if="firstNameProblem" class="fe">{{ firstNameProblem }}</div>
            </div>
          </div>
          <div>
            <label class="ifl">Middle Name</label>
            <input v-model="profileForm.middle_name" class="ifi" maxlength="20" @input="profileForm.middle_name = capFirst(profileForm.middle_name.normalize('NFC').replace(/[^\p{L}\p{M}\s'-]/gu, ''))" />
          </div>
          <div>
            <label class="ifl">Suffix</label>
            <select v-model="profileForm.suffix" class="ifse">
              <option value="">None</option>
              <option value="Jr.">Jr.</option>
              <option value="Sr.">Sr.</option>
              <option value="I">I</option>
              <option value="II">II</option>
              <option value="III">III</option>
              <option value="IV">IV</option>
              <option value="V">V</option>
            </select>
          </div>
          <div>
            <label class="ifl">Email Address</label>
            <input v-model="profileForm.email" type="email" class="ifi" maxlength="100" placeholder="Your personal email (eg. name@gmail.com)" :style="emailProblem ? ERR_BORDER : ''" />
              <div v-if="emailProblem" class="fe">{{ emailProblem }}</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Student ID</label>
              <input :value="student.student_id || ''" class="ifi" disabled />
            </div>
            <div>
              <!-- B552 (partial): Program and Year Level were already separate
                   fields on the student record - only the display combined
                   them into one "Course and Year" string. Splitting them out
                   here is as far as this fix goes: an academic-year-transition
                   prompt (or an admin batch-advancement workflow) that updates
                   year_level itself is a separate product decision - whether
                   it runs automatically, needs the student's confirmation, or
                   is a manual admin action, and how an irregular/graduating
                   student is excluded - and shouldn't be guessed at here. -->
              <label class="ifl">Course</label>
              <input :value="student.program || ''" class="ifi" disabled />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Year Level</label>
              <input :value="student.year_level || ''" class="ifi" disabled />
            </div>
            <div></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Birthdate (mm/dd/yyyy)</label>
              <input v-model="profileForm.birthdate" type="date" class="ifi" :max="maxBirth" :style="birthError ? ERR_BORDER : ''" />
              <div v-if="birthError" class="fe">{{ birthError }}</div>
              <div v-else-if="birthWarning" class="fw">{{ birthWarning }}</div>
            </div>
            <div>
              <label class="ifl">Sex</label>
              <select v-model="profileForm.sex" class="ifse">
                <option value="">Select...</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
            <div>
              <label class="ifl">Civil Status</label>
              <select v-model="profileForm.civil_status" class="ifse">
                <option value="">Select...</option>
                <option v-for="c in CIVIL_STATUSES" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Nationality</label>
              <input v-model="profileForm.nationality" class="ifi" maxlength="100" @input="profileForm.nationality = capFirst(String(profileForm.nationality ?? '').normalize('NFC').replace(/[^\p{L}\p{M}\s'.-]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Birthplace</label>
              <input v-model="profileForm.birthplace" class="ifi" maxlength="255" @input="profileForm.birthplace = capFirst(String(profileForm.birthplace ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
          </div>
          <div>
            <label class="ifl">Languages that I understand</label>
            <input v-model="profileForm.languages" class="ifi" maxlength="255" placeholder="e.g. Filipino, English, Bisaya" @input="profileForm.languages = capFirst(String(profileForm.languages ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
          </div>
          <div>
            <label class="ifl">Address while studying at BSU</label>
            <input v-model="profileForm.address" class="ifi" maxlength="255" placeholder="Street, Barangay, Town / City" :style="addressProblem ? ERR_BORDER : ''" @input="profileForm.address = capFirst(String(profileForm.address ?? '').normalize('NFC').replace(/[^\p{L}\p{M}0-9À-ɏ'.,\x26()\- ]/gu, ''))" />
            <div v-if="addressProblem" class="fe">{{ addressProblem }}</div>
          </div>
          <div>
            <label class="ifl">Contact Number</label>
            <input
            v-model="profileForm.contact_number"
            class="ifi"
            placeholder="09XXXXXXXXX"
            maxlength="11"
            :style="profileError || contactProblem ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="profileForm.contact_number = contactNumberInput(profileForm.contact_number); profileError = ''"
          />
              <div v-if="contactProblem" style="font-size:11.5px;color:var(--red);margin-top:4px">{{ contactProblem }}</div>
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="isProfileUnchanged || profileHasProblem" @click="requestSave('profile')">Save Changes</button>
        </div>
      </div>

      <div v-show="tab === 'family' || tab === 'education'" class="icard">
        <div class="icard-header"><span class="icard-title">{{ tab === 'education' ? 'Educational Background' : 'Family Background' }}</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">

          <div v-show="tab === 'family'" style="display:flex;flex-direction:column;gap:16px">
          <div v-if="backgroundError && backgroundSection !== 'education'" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundError }}</div>
          <div v-if="backgroundSuccess && backgroundSection !== 'education'" style="background:var(--mist);border:1px solid #bfe3c8;color:var(--moss);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundSuccess }}</div>
          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Family Information</div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Father</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 90px;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.father_last_name" class="ifi" maxlength="50" @input="backgroundForm.father_last_name = capFirst(String(backgroundForm.father_last_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.father_first_name" class="ifi" maxlength="50" @input="backgroundForm.father_first_name = capFirst(String(backgroundForm.father_first_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.father_middle_name" class="ifi" maxlength="50" @input="backgroundForm.father_middle_name = capFirst(String(backgroundForm.father_middle_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <select v-model="backgroundForm.father_suffix" class="ifse">
                <option value="">None</option>
                <option v-for="s in SUFFIXES" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.father_occupation" class="ifi" maxlength="50" @input="backgroundForm.father_occupation = capFirst(String(backgroundForm.father_occupation ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.father_contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" :style="contactIssue('father_contact_number') ? ERR_BORDER : ''" @input="backgroundForm.father_contact_number = backgroundForm.father_contact_number.replace(/[^0-9]/g, '').slice(0, 11)" />
              <div v-if="contactIssue('father_contact_number')" style="font-size:11px;color:var(--red);margin-top:4px">{{ contactIssue('father_contact_number') }}</div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
            <div>
              <label class="ifl">Age</label>
              <input v-model="backgroundForm.father_age" class="ifi" maxlength="3" :style="parentAgeIssue('father_age') ? ERR_BORDER : ''" @input="backgroundForm.father_age = String(backgroundForm.father_age ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="parentAgeIssue('father_age')" style="font-size:11px;color:var(--red);margin-top:4px">{{ parentAgeIssue('father_age') }}</div>
            </div>
            <div>
              <label class="ifl">Highest Educational Attainment</label>
              <input v-model="backgroundForm.father_educational_attainment" class="ifi" maxlength="50" @input="backgroundForm.father_educational_attainment = capFirst(String(backgroundForm.father_educational_attainment ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
          </div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate);margin-top:4px">Mother</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 90px;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.mother_last_name" class="ifi" maxlength="50" @input="backgroundForm.mother_last_name = capFirst(String(backgroundForm.mother_last_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.mother_first_name" class="ifi" maxlength="50" @input="backgroundForm.mother_first_name = capFirst(String(backgroundForm.mother_first_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.mother_middle_name" class="ifi" maxlength="50" @input="backgroundForm.mother_middle_name = capFirst(String(backgroundForm.mother_middle_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <select v-model="backgroundForm.mother_suffix" class="ifse">
                <option value="">None</option>
                <option v-for="s in SUFFIXES" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.mother_occupation" class="ifi" maxlength="50" @input="backgroundForm.mother_occupation = capFirst(String(backgroundForm.mother_occupation ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.mother_contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" :style="contactIssue('mother_contact_number') ? ERR_BORDER : ''" @input="backgroundForm.mother_contact_number = backgroundForm.mother_contact_number.replace(/[^0-9]/g, '').slice(0, 11)" />
              <div v-if="contactIssue('mother_contact_number')" style="font-size:11px;color:var(--red);margin-top:4px">{{ contactIssue('mother_contact_number') }}</div>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
            <div>
              <label class="ifl">Age</label>
              <input v-model="backgroundForm.mother_age" class="ifi" maxlength="3" :style="parentAgeIssue('mother_age') ? ERR_BORDER : ''" @input="backgroundForm.mother_age = String(backgroundForm.mother_age ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="parentAgeIssue('mother_age')" style="font-size:11px;color:var(--red);margin-top:4px">{{ parentAgeIssue('mother_age') }}</div>
            </div>
            <div>
              <label class="ifl">Highest Educational Attainment</label>
              <input v-model="backgroundForm.mother_educational_attainment" class="ifi" maxlength="50" @input="backgroundForm.mother_educational_attainment = capFirst(String(backgroundForm.mother_educational_attainment ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
          </div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate);margin-top:4px">Legal Guardian</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr 90px;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.guardian_last_name" class="ifi" maxlength="50" @input="backgroundForm.guardian_last_name = capFirst(String(backgroundForm.guardian_last_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.guardian_first_name" class="ifi" maxlength="50" @input="backgroundForm.guardian_first_name = capFirst(String(backgroundForm.guardian_first_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.guardian_middle_name" class="ifi" maxlength="50" @input="backgroundForm.guardian_middle_name = capFirst(String(backgroundForm.guardian_middle_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Suffix</label>
              <select v-model="backgroundForm.guardian_suffix" class="ifse">
                <option value="">None</option>
                <option v-for="s in SUFFIXES" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
            <div>
              <label class="ifl">Age</label>
              <input v-model="backgroundForm.guardian_age" class="ifi" maxlength="3" :style="parentAgeIssue('guardian_age') ? ERR_BORDER : ''" @input="backgroundForm.guardian_age = String(backgroundForm.guardian_age ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="parentAgeIssue('guardian_age')" style="font-size:11px;color:var(--red);margin-top:4px">{{ parentAgeIssue('guardian_age') }}</div>
            </div>
            <div>
              <label class="ifl">Highest Educational Attainment</label>
              <input v-model="backgroundForm.guardian_educational_attainment" class="ifi" maxlength="50" @input="backgroundForm.guardian_educational_attainment = capFirst(String(backgroundForm.guardian_educational_attainment ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.guardian_occupation" class="ifi" maxlength="50" @input="backgroundForm.guardian_occupation = capFirst(String(backgroundForm.guardian_occupation ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.guardian_contact" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" :style="contactIssue('guardian_contact') ? ERR_BORDER : ''" @input="backgroundForm.guardian_contact = String(backgroundForm.guardian_contact ?? '').replace(/[^0-9]/g, '').slice(0, 11)" />
              <div v-if="contactIssue('guardian_contact')" style="font-size:11px;color:var(--red);margin-top:4px">{{ contactIssue('guardian_contact') }}</div>
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-p ibtn-sm" :disabled="isFamilyUnchanged || savingFamily" @click="requestSave('family')">{{ savingFamily ? 'Saving...' : 'Save Family Information' }}</button>
          </div>

          <div style="height:1px;background:var(--cloud)"></div>

          <div style="display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Siblings Information</div>
            <button type="button" class="ibtn ibtn-o ibtn-sm" @click="addSibling">+ Add Sibling</button>
          </div>
          <div v-if="!backgroundForm.siblings.length" style="font-size:12.5px;color:var(--fog)">No siblings added yet.</div>
          <div
            v-for="(sib, idx) in backgroundForm.siblings"
            :key="idx"
            style="border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px;display:flex;flex-direction:column;gap:10px"
          >
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 90px;gap:10px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="sib.last_name" class="ifi" :style="sibNamesChecked && !String(sib.last_name ?? '').trim() ? ERR_BORDER : ''" maxlength="50" @input="sib.last_name = capFirst(String(sib.last_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
                <div v-if="sibNamesChecked && !String(sib.last_name ?? '').trim()" class="fe">Please fill in this field.</div>
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="sib.first_name" class="ifi" :style="sibNamesChecked && !String(sib.first_name ?? '').trim() ? ERR_BORDER : ''" maxlength="50" @input="sib.first_name = capFirst(String(sib.first_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
                <div v-if="sibNamesChecked && !String(sib.first_name ?? '').trim()" class="fe">Please fill in this field.</div>
              </div>
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="sib.middle_name" class="ifi" maxlength="50" @input="sib.middle_name = capFirst(String(sib.middle_name ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.\- ]/gu, ''))" />
              </div>
              <div>
                <label class="ifl">Suffix</label>
                <select v-model="sib.suffix" class="ifse">
                  <option value="">None</option>
                  <option v-for="s in SUFFIXES" :key="s" :value="s">{{ s }}</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr 1fr;gap:10px">
              <div>
                <label class="ifl">Age</label>
                <input v-model="sib.age" class="ifi" maxlength="3" @input="sib.age = String(sib.age ?? '').replace(/[^0-9]/g, '')" />
              </div>
              <div>
                <label class="ifl">Highest Educational Attainment</label>
                <input v-model="sib.educational_attainment" class="ifi" maxlength="50" @input="sib.educational_attainment = capFirst(String(sib.educational_attainment ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
              </div>
              <div>
                <label class="ifl">Civil Status</label>
                <select v-model="sib.civil_status" class="ifse">
                  <option value="">Select...</option>
                  <option v-for="c in CIVIL_STATUSES" :key="c" :value="c">{{ c }}</option>
                </select>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end">
              <div>
                <label class="ifl">Occupation (if currently studying, enter "Student")</label>
                <input v-model="sib.occupation" class="ifi" maxlength="50" @input="sib.occupation = capFirst(String(sib.occupation ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
              </div>
              <button type="button" class="ibtn ibtn-o ibtn-sm" style="color:var(--red)" @click="askRemoveSibling(idx)">Remove</button>
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-p ibtn-sm" :disabled="isSiblingsUnchanged || savingSiblings" @click="requestSave('siblings')">{{ savingSiblings ? 'Saving...' : 'Save Siblings Information' }}</button>
          </div>
          </div>


          <div v-show="tab === 'education'" style="display:flex;flex-direction:column;gap:16px">
          <div v-if="backgroundError && backgroundSection === 'education'" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundError }}</div>
          <div v-if="backgroundSuccess && backgroundSection === 'education'" style="background:var(--mist);border:1px solid #bfe3c8;color:var(--moss);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundSuccess }}</div>
          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Educational Attainment</div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Elementary</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">School</label>
              <input v-model="backgroundForm.elementary_school" class="ifi" maxlength="100" :style="wordsIssue(backgroundForm.elementary_school, 'a school name') ? ERR_BORDER : ''" @input="backgroundForm.elementary_school = capFirst(String(backgroundForm.elementary_school ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            <div v-if="wordsIssue(backgroundForm.elementary_school, 'a school name')" class="fe">{{ wordsIssue(backgroundForm.elementary_school, 'a school name') }}</div>
              </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.elementary_year_graduated" class="ifi" placeholder="e.g. 2016" maxlength="4" :style="yearIssue(backgroundForm.elementary_year_graduated) ? ERR_BORDER : ''" @input="backgroundForm.elementary_year_graduated = String(backgroundForm.elementary_year_graduated ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="yearIssue(backgroundForm.elementary_year_graduated)" style="font-size:11px;color:var(--red);margin-top:4px">{{ yearIssue(backgroundForm.elementary_year_graduated) }}</div>
            </div>
          </div>
          <div v-if="'elementary_achievements'">
            <label class="ifl">Achievements</label>
            <textarea v-model="backgroundForm.elementary_achievements" class="ifta" style="min-height:56px" maxlength="1000" :style="wordsIssue(backgroundForm.elementary_achievements, 'your achievements') ? ERR_BORDER : ''" placeholder="Honors, awards, recognitions..."></textarea>
            <div v-if="wordsIssue(backgroundForm.elementary_achievements, 'your achievements')" class="fe">{{ wordsIssue(backgroundForm.elementary_achievements, 'your achievements') }}</div>
          </div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Junior High School</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">School</label>
              <input v-model="backgroundForm.high_school" class="ifi" maxlength="100" :style="wordsIssue(backgroundForm.high_school, 'a school name') ? ERR_BORDER : ''" @input="backgroundForm.high_school = capFirst(String(backgroundForm.high_school ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            <div v-if="wordsIssue(backgroundForm.high_school, 'a school name')" class="fe">{{ wordsIssue(backgroundForm.high_school, 'a school name') }}</div>
              </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.high_school_year_graduated" class="ifi" placeholder="e.g. 2020" maxlength="4" :style="yearIssue(backgroundForm.high_school_year_graduated, minYearForLevel('high_school_year_graduated')) ? ERR_BORDER : ''" @input="backgroundForm.high_school_year_graduated = String(backgroundForm.high_school_year_graduated ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="yearIssue(backgroundForm.high_school_year_graduated, minYearForLevel('high_school_year_graduated'))" style="font-size:11px;color:var(--red);margin-top:4px">{{ yearIssue(backgroundForm.high_school_year_graduated, minYearForLevel('high_school_year_graduated')) }}</div>
            </div>
          </div>
          <div v-if="'high_school_achievements'">
            <label class="ifl">Achievements</label>
            <textarea v-model="backgroundForm.high_school_achievements" class="ifta" style="min-height:56px" maxlength="1000" :style="wordsIssue(backgroundForm.high_school_achievements, 'your achievements') ? ERR_BORDER : ''" placeholder="Honors, awards, recognitions..."></textarea>
            <div v-if="wordsIssue(backgroundForm.high_school_achievements, 'your achievements')" class="fe">{{ wordsIssue(backgroundForm.high_school_achievements, 'your achievements') }}</div>
          </div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Senior High School</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">School</label>
              <input v-model="backgroundForm.senior_high_school" class="ifi" maxlength="100" :style="wordsIssue(backgroundForm.senior_high_school, 'a school name') ? ERR_BORDER : ''" @input="backgroundForm.senior_high_school = capFirst(String(backgroundForm.senior_high_school ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            <div v-if="wordsIssue(backgroundForm.senior_high_school, 'a school name')" class="fe">{{ wordsIssue(backgroundForm.senior_high_school, 'a school name') }}</div>
              </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.senior_high_year_graduated" class="ifi" placeholder="e.g. 2022" maxlength="4" :style="yearIssue(backgroundForm.senior_high_year_graduated, minYearForLevel('senior_high_year_graduated')) ? ERR_BORDER : ''" @input="backgroundForm.senior_high_year_graduated = String(backgroundForm.senior_high_year_graduated ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="yearIssue(backgroundForm.senior_high_year_graduated, minYearForLevel('senior_high_year_graduated'))" style="font-size:11px;color:var(--red);margin-top:4px">{{ yearIssue(backgroundForm.senior_high_year_graduated, minYearForLevel('senior_high_year_graduated')) }}</div>
            </div>
          </div>
          <div v-if="'senior_high_achievements'">
            <label class="ifl">Achievements</label>
            <textarea v-model="backgroundForm.senior_high_achievements" class="ifta" style="min-height:56px" maxlength="1000" :style="wordsIssue(backgroundForm.senior_high_achievements, 'your achievements') ? ERR_BORDER : ''" placeholder="Honors, awards, recognitions..."></textarea>
            <div v-if="wordsIssue(backgroundForm.senior_high_achievements, 'your achievements')" class="fe">{{ wordsIssue(backgroundForm.senior_high_achievements, 'your achievements') }}</div>
          </div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">College (current)</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">College / University</label>
              <input v-model="backgroundForm.college_school" class="ifi" maxlength="100" :style="wordsIssue(backgroundForm.college_school, 'a school name') ? ERR_BORDER : ''" @input="backgroundForm.college_school = capFirst(String(backgroundForm.college_school ?? '').normalize('NFC').replace(/[^\p{L}\p{M}'.,\x26()\- ]/gu, ''))" />
            <div v-if="wordsIssue(backgroundForm.college_school, 'a school name')" class="fe">{{ wordsIssue(backgroundForm.college_school, 'a school name') }}</div>
              </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.college_year_graduated" class="ifi" placeholder="Leave blank if ongoing" maxlength="4" :style="yearIssue(backgroundForm.college_year_graduated, minYearForLevel('college_year_graduated')) ? ERR_BORDER : ''" @input="backgroundForm.college_year_graduated = String(backgroundForm.college_year_graduated ?? '').replace(/[^0-9]/g, '')" />
              <div v-if="yearIssue(backgroundForm.college_year_graduated, minYearForLevel('college_year_graduated'))" style="font-size:11px;color:var(--red);margin-top:4px">{{ yearIssue(backgroundForm.college_year_graduated, minYearForLevel('college_year_graduated')) }}</div>
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-p ibtn-sm" :disabled="isEducationUnchanged || savingEducation || educationProblem" @click="requestSave('education')">{{ savingEducation ? 'Saving...' : 'Save Educational Attainment' }}</button>
          </div>
          </div>
        </div>
      </div>

      <div v-show="tab === 'password'" class="icard">
        <div class="icard-header"><span class="icard-title">Change Password</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="pwError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwError }}</div>
          <div v-if="pwSuccess" style="background:var(--mist);border:1px solid #bfe3c8;color:var(--moss);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwSuccess }}</div>
          <div style="font-size:12px;color:var(--stone);line-height:1.5;background:var(--snow);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:8px 12px">Password must be 8&ndash;64 characters with an uppercase letter, a lowercase letter, a number and a symbol (e.g. ! @ # $).</div>
          <div>
            <label class="ifl">Current Password</label>
            <div style="position:relative">
              <input v-model="pwForm.current_password" :type="showCurrentPw ? 'text' : 'password'" class="ifi" maxlength="64" style="padding-right:36px" />
              <button type="button" :aria-label="showCurrentPw ? 'Hide password' : 'Show password'" :title="showCurrentPw ? 'Hide password' : 'Show password'" @click="showCurrentPw = !showCurrentPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);display:flex;padding:4px"><svg v-if="showCurrentPw" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg><svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
          </div>
          <div>
            <label class="ifl">New Password</label>
            <div style="position:relative">
              <input v-model="pwForm.password" :type="showNewPw ? 'text' : 'password'" class="ifi" maxlength="64" style="padding-right:36px" />
              <button type="button" :aria-label="showNewPw ? 'Hide password' : 'Show password'" :title="showNewPw ? 'Hide password' : 'Show password'" @click="showNewPw = !showNewPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);display:flex;padding:4px"><svg v-if="showNewPw" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg><svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
          </div>
          <div style="font-size:11.5px;color:var(--stone);line-height:1.6">
            <PasswordChecklist :password="pwForm.password" />
            <div v-if="pwSameAsCurrent" style="color:var(--red)">New password must be different from your current password.</div>
          </div>
          <div>
            <label class="ifl">Confirm New Password</label>
            <div style="position:relative">
              <input v-model="pwForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" class="ifi" maxlength="64" style="padding-right:36px" />
              <button type="button" :aria-label="showConfirmPw ? 'Hide password' : 'Show password'" :title="showConfirmPw ? 'Hide password' : 'Show password'" @click="showConfirmPw = !showConfirmPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);display:flex;padding:4px"><svg v-if="showConfirmPw" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg><svg v-else viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
            <div v-if="pwMismatch" style="font-size:11.5px;color:var(--red);margin-top:5px">Passwords do not match.</div>
            <div v-else-if="pwMatch" style="font-size:11.5px;color:var(--moss);margin-top:5px">Passwords match.</div>
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="askChangePassword">Change Password</button>
        </div>
      </div>

      <!-- B569: Active Sessions / Linked Devices - every device currently
           signed in to this account, with the ability to sign any of them
           out individually or all at once. -->
      <div v-show="tab === 'sessions'" class="icard">
        <div class="icard-header">
          <span class="icard-title">Active Sessions</span>
          <button v-if="sessions.length > 1" class="ibtn ibtn-o ibtn-sm" :disabled="revokingOthers" @click="revokeOtherSessions">
            {{ revokingOthers ? 'Signing out...' : 'Sign out all other devices' }}
          </button>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:10px">
          <div v-if="sessionsError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ sessionsError }}</div>
          <div style="font-size:12px;color:var(--stone);line-height:1.5">Devices and browsers currently signed in to your account. If you don't recognize one, sign it out.</div>
          <div v-if="sessionsLoading" style="text-align:center;padding:24px"><div style="width:20px;height:20px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div></div>
          <div v-for="s in sessions" :key="s.id" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--cloud);border-radius:var(--r-sm)">
            <div style="min-width:0">
              <div style="font-size:13px;font-weight:600;color:var(--ink)">
                {{ s.device || 'Unknown device' }}
                <span v-if="s.is_current" class="ibadge" style="background:var(--mist);color:var(--moss);margin-left:6px">This device</span>
              </div>
              <div style="font-size:11.5px;color:var(--stone);margin-top:2px">Last active {{ formatSessionDate(s.last_used_at || s.created_at) }}</div>
            </div>
            <button v-if="!s.is_current" class="ibtn ibtn-o ibtn-sm" :disabled="revokingId === s.id" @click="revokeSession(s)">
              {{ revokingId === s.id ? 'Signing out...' : 'Sign out' }}
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Password change confirmation -->
    <div v-if="showPwConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:65;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="showPwConfirm = false">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Change Password</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">Are you sure you want to change your password?</div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-o" :disabled="changingPw" @click="showPwConfirm = false">Cancel</button>
            <button type="button" class="ibtn ibtn-p" :disabled="changingPw" @click="confirmChangePassword">{{ changingPw ? 'Changing...' : 'Yes, Change Password' }}</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Save confirmation -->
    <div v-if="confirmAction" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="confirmAction = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Review your changes</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px;overflow-y:auto">
          <div style="font-size:13px;color:var(--slate)">Please review what will be saved to your <strong>{{ CONFIRM_LABELS[confirmAction] }}</strong>.</div>
          <div class="chg">
            <div v-if="!pendingChanges.length" style="color:var(--stone);font-size:12.5px">No changes.</div>
            <div v-for="c in pendingChanges" :key="c.key" class="chg-row">
              <div class="chg-l">{{ c.label }}</div>
              <div class="chg-v"><span class="chg-old">{{ c.from }}</span><span class="chg-arrow">&rarr;</span><strong>{{ c.to }}</strong></div>
            </div>
          </div>
          <label v-if="needsAgeConfirm" style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:12px">
            <input type="checkbox" v-model="ageConfirmed" style="margin-top:3px;flex-shrink:0" />
            <span style="font-size:12px;line-height:1.5;color:var(--amber)">{{ birthWarning }} I confirm this birthdate is correct.</span>
          </label>
          <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;background:var(--foam);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px">
            <input type="checkbox" v-model="consentChecked" style="margin-top:3px;flex-shrink:0" />
            <span style="font-size:12px;line-height:1.5;color:var(--slate)">I understand the above mentioned Data Privacy Notice of Benguet State University (BSU) and consent to the collection and official use of my personal information through this medium for all legal intents and purposes. I understand that the OSS-SDS-Guidance and Counseling Unit (GCU) will abide by the policy as mentioned above except for cases not within its control. I give my full consent to OSS-SDS-Guidance and Counseling Unit (GCU) necessary and relevant data pertaining to my personal data. I certify that the information I am saving is true and correct.</span>
          </label>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-o" @click="confirmAction = null">Cancel</button>
            <button type="button" class="ibtn ibtn-p" :disabled="!consentChecked || !pendingChanges.length || (needsAgeConfirm && !ageConfirmed)" @click="confirmSave">Yes, Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Remove sibling confirmation (B525/B529) -->
    <div v-if="removeSiblingIdx !== null" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:66;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="removeSiblingIdx = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Remove Sibling</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">Are you sure you want to remove this sibling's information? This action cannot be undone.</div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-o" :disabled="removingSibling" @click="removeSiblingIdx = null">Cancel</button>
            <button type="button" class="ibtn ibtn-p" style="background:var(--red);border-color:var(--red)" :disabled="removingSibling" @click="confirmRemoveSibling">{{ removingSibling ? 'Removing...' : 'Confirm Removal' }}</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Blank sibling entry: continue editing or discard (B524) -->
    <div v-if="blankSiblingIdx !== null" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:66;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="blankSiblingIdx = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Empty Sibling Entry</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">
          <div style="font-size:13px;color:var(--slate);line-height:1.6">You haven't entered any information for this sibling. Do you want to continue or discard this entry?</div>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-o" style="color:var(--red)" @click="discardBlankSibling">Discard Entry</button>
            <button type="button" class="ibtn ibtn-p" @click="blankSiblingIdx = null">Continue Editing</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue';
import PasswordChecklist from '../../components/PasswordChecklist.vue';
import axios from 'axios';
import { emailProblem as checkEmail } from '../../utils/emailCheck';
import { contactNumberInput } from '../../utils/validators';

const toast = inject('toast', null);
const capFirst = v => (v ? v.charAt(0).toUpperCase() + v.slice(1) : v);

// B311: what is wrong with a field, shown on the field itself ('' when fine).
const ERR_BORDER = 'border-color:var(--red);border-width:1.5px';
const sibNamesChecked = ref(false);
const SUFFIXES = ['Jr.', 'Sr.', 'I', 'II', 'III', 'IV'];
// B522/B528: a parent/guardian age that is not biologically plausible.
const MIN_PARENT_AGE = 15;
const MIN_GUARDIAN_AGE = 25;
function parentAgeIssue(key) {
  const v = String(backgroundForm.value[key] ?? '').trim();
  if (!v) return '';
  const n = Number(v);
  if (key === 'guardian_age') {
    return n < MIN_GUARDIAN_AGE ? `Guardian must be at least ${MIN_GUARDIAN_AGE} years old.` : '';
  }
  const who = key === 'father_age' ? 'Father' : 'Mother';
  return n < MIN_PARENT_AGE ? `${who}'s age seems too low. Please check it.` : '';
}
function contactIssue(key) {
  const f = backgroundForm.value;
  const v = String(f[key] ?? '');
  if (!v) return '';
  if (!/^09\d{9}$/.test(v)) return 'Must start with 09 and be 11 digits long.';
  if (key !== 'guardian_contact' && f.father_contact_number === f.mother_contact_number) return "Same as the other parent's number.";
  return '';
}
function yearIssue(v, min) {
  v = String(v ?? '');
  if (!v) return '';
  if (!/^\d{4}$/.test(v)) return 'Enter a 4-digit year.';
  const floor = min || 1950;
  if (Number(v) < floor || Number(v) > new Date().getFullYear()) return `Enter a year from ${floor} up to this year.`;
  return '';
}

// Bug: Elementary, Junior High, Senior High and College all showed a plain
// 1950-to-present range independently, so a student could save the exact
// same "Year Graduated" for every level (or an earlier level finishing
// after a later one) with no warning. Each level now has to be no earlier
// than the one before it that's actually filled in.
const EDUCATION_YEAR_SEQUENCE = ['elementary_year_graduated', 'high_school_year_graduated', 'senior_high_year_graduated', 'college_year_graduated'];
function minYearForLevel(key) {
  const idx = EDUCATION_YEAR_SEQUENCE.indexOf(key);
  if (idx <= 0) return 1950;
  for (let i = idx - 1; i >= 0; i--) {
    const prev = String(backgroundForm.value[EDUCATION_YEAR_SEQUENCE[i]] ?? '');
    if (/^\d{4}$/.test(prev)) return Number(prev) + 1;
  }
  return 1950;
}

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));

// ---- Tabs ----
const TABS = [
  { value: 'personal',  label: 'Personal' },
  { value: 'family',    label: 'Family' },
  { value: 'education', label: 'Education' },
  { value: 'password',  label: 'Password' },
  { value: 'sessions',  label: 'Sessions' },
];
// A student still on a temporary password lands on the Password tab.
const tab = ref(student.value.must_change_password ? 'password' : 'personal');

const headerInitials = computed(() =>
  `${student.value.first_name?.[0] || ''}${student.value.last_name?.[0] || ''}`.toUpperCase() || '?'
);
const headerDetails = computed(() => {
  const s = student.value;
  const year = s.year_level ? (/year/i.test(s.year_level) ? s.year_level : `Year ${s.year_level}`) : '';
  return [s.student_id, [s.program, year].filter(Boolean).join(' - '), s.college].filter(Boolean).join(' · ');
});
const CIVIL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
const pad = n => String(n).padStart(2, '0');
const localDate = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
// The latest birthdate that can be picked is yesterday (never today or later).
const maxBirth = localDate(new Date(Date.now() - 86400000));
function ageOf(iso) {
  const [y, m, d] = String(iso).split('-').map(Number);
  const now = new Date();
  let age = now.getFullYear() - y;
  if (now.getMonth() + 1 < m || (now.getMonth() + 1 === m && now.getDate() < d)) age--;
  return age;
}
const profileForm = ref({
  first_name: '', last_name: '', middle_name: '', suffix: '', email: '', contact_number: '',
  birthdate: '', sex: '', civil_status: '', nationality: '', birthplace: '', languages: '', address: '',
});
const profileSnapshot = ref('');
const isProfileUnchanged = computed(() => JSON.stringify(profileForm.value) === profileSnapshot.value);
const profileError = ref('');
const profileSuccess = ref('');
const pwForm = ref({ current_password: '', password: '', password_confirmation: '' });
const pwError = ref('');
// Checked as the user types, so a mismatch shows before they submit.

// ---- Checked as the user types (B379, B380) ----
const emailProblem = computed(() => {
  return checkEmail(profileForm.value.email);
});
const contactProblem = computed(() => {
  const v = String(profileForm.value.contact_number || '');
  if (!v) return '';
  if (!v.startsWith('09')) return 'The contact number must start with 09.';
  return v.length === 11 ? '' : `The contact number must be 11 digits (${v.length} so far).`;
});

const hasLetter = v => /\p{L}/u.test(String(v ?? ''));
const nameProblem = (v, label) => (String(v ?? '').trim() ? '' : `${label} is required.`);
const firstNameProblem = computed(() => nameProblem(profileForm.value.first_name, 'First name'));
const lastNameProblem = computed(() => nameProblem(profileForm.value.last_name, 'Last name'));
// B507: an address needs words, not just numbers.
const addressProblem = computed(() => {
  const v = String(profileForm.value.address || '').trim();
  if (!v) return '';
  if (!hasLetter(v)) return 'Enter a complete address with the street, barangay and town, not just numbers.';
  return v.length < 5 ? 'The address is too short. Enter a complete address.' : '';
});
// B505: no today/future birthdate; under 18 is flagged but allowed (accelerated students).
const birthIssue = computed(() => {
  const v = profileForm.value.birthdate;
  if (!v) return null;
  if (v > maxBirth) return { error: true, text: 'The birthdate cannot be today or a future date.' };
  if (v < '1900-01-01') return { error: true, text: 'Enter a valid birthdate.' };
  const age = ageOf(v);
  if (age < 15) return { error: true, text: `This birthdate makes you ${age} years old, which is too young for a college student. Please check it.` };
  if (age < 18) return { error: false, text: `This birthdate makes you ${age} years old, younger than the usual college entry age (18). If you are an accelerated student this is fine, but please confirm it is correct when saving. The office may verify it.` };
  return null;
});
const birthError = computed(() => (birthIssue.value?.error ? birthIssue.value.text : ''));
const birthWarning = computed(() => (birthIssue.value && !birthIssue.value.error ? birthIssue.value.text : ''));
const profileHasProblem = computed(() =>
  !!(firstNameProblem.value || lastNameProblem.value || (profileForm.value.email && emailProblem.value) || !profileForm.value.email
    || contactProblem.value || addressProblem.value || birthError.value));

// B509: school names and achievements need words, not just numbers.
function wordsIssue(v, what) {
  v = String(v ?? '').trim();
  if (!v) return '';
  return hasLetter(v) ? '' : `Enter ${what} with words, not just numbers.`;
}
const SCHOOL_KEYS = ['elementary_school', 'high_school', 'senior_high_school', 'college_school'];
const ACHIEVE_KEYS = ['elementary_achievements', 'high_school_achievements', 'senior_high_achievements'];
const educationProblem = computed(() => {
  const f = backgroundForm.value;
  return SCHOOL_KEYS.some(k => wordsIssue(f[k], 'a school name')) || ACHIEVE_KEYS.some(k => wordsIssue(f[k], 'your achievements'))
    || EDUCATION_YEAR_SEQUENCE.some(k => yearIssue(f[k], minYearForLevel(k)));
});

// B506: leaving a tab drops anything typed there but not saved; the saved data comes back.
// B526/B527: an error or success message belongs to the section it came
// from - it is cleared the moment the user navigates to another section, so
// it never reappears, stale, when they come back.
watch(tab, (now, before) => {
  if (now !== before) {
    if (profileSnapshot.value) {
      try { profileForm.value = JSON.parse(profileSnapshot.value); } catch (e) { /* keep the current values */ }
    }
    if (backgroundSnapshot.value) {
      try { backgroundForm.value = JSON.parse(backgroundSnapshot.value); } catch (e) { /* keep the current values */ }
    }
    confirmAction.value = null;
    profileError.value = '';
    profileSuccess.value = '';
    backgroundError.value = '';
    backgroundSuccess.value = '';
    sibNamesChecked.value = false;
  }
  if (before === 'password' && now !== 'password') {
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
    pwError.value = '';
    pwSuccess.value = '';
    showCurrentPw.value = showNewPw.value = showConfirmPw.value = false;
  }
  // B569: the list is only ever fetched when the tab is actually opened,
  // not on every page load, since most visits never touch it.
  if (now === 'sessions' && !sessionsLoaded.value) {
    fetchSessions();
  }
});

// ---- B569: Active Sessions / Linked Devices ----
const sessions        = ref([]);
const sessionsLoading  = ref(false);
const sessionsLoaded  = ref(false);
const sessionsError   = ref('');
const revokingId       = ref(null);
const revokingOthers    = ref(false);

async function fetchSessions() {
  sessionsLoading.value = true;
  sessionsError.value = '';
  try {
    const res = await axios.get(`${API_BASE}/student/sessions`, authHeaders());
    sessions.value = res.data;
    sessionsLoaded.value = true;
  } catch (e) {
    sessionsError.value = e.response?.data?.message || 'Failed to load active sessions.';
  } finally {
    sessionsLoading.value = false;
  }
}

async function revokeSession(s) {
  revokingId.value = s.id;
  try {
    await axios.delete(`${API_BASE}/student/sessions/${s.id}`, authHeaders());
    sessions.value = sessions.value.filter(x => x.id !== s.id);
  } catch (e) {
    sessionsError.value = e.response?.data?.message || 'Failed to sign out that device.';
  } finally {
    revokingId.value = null;
  }
}

async function revokeOtherSessions() {
  revokingOthers.value = true;
  try {
    await axios.post(`${API_BASE}/student/sessions/revoke-others`, {}, authHeaders());
    sessions.value = sessions.value.filter(s => s.is_current);
  } catch (e) {
    sessionsError.value = e.response?.data?.message || 'Failed to sign out other devices.';
  } finally {
    revokingOthers.value = false;
  }
}

function formatSessionDate(date) {
  if (!date) return 'just now';
  return new Date(date).toLocaleString();
}
const pwMismatch = computed(() => !!pwForm.value.password_confirmation && pwForm.value.password !== pwForm.value.password_confirmation);
const pwMatch    = computed(() => !!pwForm.value.password_confirmation && pwForm.value.password === pwForm.value.password_confirmation);
const pwSuccess = ref('');
const pwSameAsCurrent = computed(() => !!pwForm.value.password && pwForm.value.password === pwForm.value.current_password);
const showCurrentPw = ref(false);
const showNewPw = ref(false);
const showConfirmPw = ref(false);

// B271: the old fallback - e.response?.data?.message - showed Laravel's
// generic "The given data was invalid." on a validation failure (password
// too short, confirmation mismatch, etc.) instead of saying what was
// actually wrong. The real per-field reasons are in e.response.data.errors;
// this surfaces the first one instead, falling back to the current-password
// check's own specific message (which isn't a validation error, just a 422)
// or the generic fallback only when neither is present.
function firstApiError(e, fallback) {
  const errors = e.response?.data?.errors;
  if (errors) {
    const firstField = Object.keys(errors)[0];
    if (firstField && errors[firstField]?.[0]) return errors[firstField][0];
  }
  return e.response?.data?.message || fallback;
}

function emptyBackgroundForm() {
  return {
    father_first_name: '', father_middle_name: '', father_last_name: '', father_suffix: '', father_occupation: '', father_contact_number: '',
    father_age: '', father_educational_attainment: '',
    mother_first_name: '', mother_middle_name: '', mother_last_name: '', mother_suffix: '', mother_occupation: '', mother_contact_number: '',
    mother_age: '', mother_educational_attainment: '',
    guardian_first_name: '', guardian_middle_name: '', guardian_last_name: '', guardian_suffix: '', guardian_age: '',
    guardian_occupation: '', guardian_educational_attainment: '', guardian_contact: '',
    siblings: [],
    senior_high_school: '', senior_high_year_graduated: '', senior_high_achievements: '',
    elementary_school: '', elementary_year_graduated: '', elementary_achievements: '',
    high_school: '', high_school_year_graduated: '', high_school_achievements: '',
    college_school: '', college_year_graduated: '',
  };
}
const backgroundForm = ref(emptyBackgroundForm());
const backgroundSnapshot = ref('');
const isBackgroundUnchanged = computed(() => JSON.stringify(backgroundForm.value) === backgroundSnapshot.value);
const FAMILY_KEYS = [
  'father_first_name', 'father_middle_name', 'father_last_name', 'father_suffix', 'father_occupation', 'father_contact_number',
  'mother_first_name', 'mother_middle_name', 'mother_last_name', 'mother_suffix', 'mother_occupation', 'mother_contact_number',
  'father_age', 'father_educational_attainment', 'mother_age', 'mother_educational_attainment',
  'guardian_first_name', 'guardian_middle_name', 'guardian_last_name', 'guardian_suffix', 'guardian_age',
  'guardian_occupation', 'guardian_educational_attainment', 'guardian_contact',
];
const pickFamily = (src) => Object.fromEntries(FAMILY_KEYS.map(k => [k, src?.[k] ?? '']));
const isFamilyUnchanged = computed(() => {
  try {
    const snap = backgroundSnapshot.value ? JSON.parse(backgroundSnapshot.value) : {};
    return JSON.stringify(pickFamily(backgroundForm.value)) === JSON.stringify(pickFamily(snap));
  } catch (e) { return false; }
});
const savingFamily = ref(false);
const EDUCATION_KEYS = [
  'senior_high_school', 'senior_high_year_graduated', 'senior_high_achievements',
  'high_school', 'high_school_year_graduated', 'high_school_achievements',
  'elementary_school', 'elementary_year_graduated', 'elementary_achievements',
  'college_school', 'college_year_graduated',
];
const pickEducation = (src) => Object.fromEntries(EDUCATION_KEYS.map(k => [k, src?.[k] ?? '']));
const pickSiblings = (src) => (src?.siblings || []).map(x => ({
  first_name: x.first_name ?? '', middle_name: x.middle_name ?? '', last_name: x.last_name ?? '', suffix: x.suffix ?? '',
  age: x.age ?? '', occupation: x.occupation ?? '',
  educational_attainment: x.educational_attainment ?? '', civil_status: x.civil_status ?? '',
}));
const snapOf = () => { try { return backgroundSnapshot.value ? JSON.parse(backgroundSnapshot.value) : {}; } catch (e) { return {}; } };
const isSiblingsUnchanged = computed(() => JSON.stringify(pickSiblings(backgroundForm.value)) === JSON.stringify(pickSiblings(snapOf())));
const isEducationUnchanged = computed(() => JSON.stringify(pickEducation(backgroundForm.value)) === JSON.stringify(pickEducation(snapOf())));
const savingSiblings = ref(false);
const savingEducation = ref(false);
const backgroundError = ref('');
const backgroundSuccess = ref('');
// B530: which sub-tab a background message belongs to, so a Family/Siblings
// message never shows while viewing Education, or the other way around.
const backgroundSection = ref('');

// Every save goes through a confirmation modal first.
const CONFIRM_LABELS = {
  profile: 'Personal Information',
  family: 'Family Information',
  siblings: 'Siblings Information',
  education: 'Educational Attainment',
};
const confirmAction = ref(null);
const consentChecked = ref(false);

// B508: what is about to change, shown in the confirmation so it can be reviewed.
const FIELD_LABELS = {
  first_name: 'First Name', last_name: 'Last Name', middle_name: 'Middle Name', suffix: 'Suffix', email: 'Email Address',
  contact_number: 'Contact Number', birthdate: 'Birthdate', sex: 'Sex', civil_status: 'Civil Status', nationality: 'Nationality',
  birthplace: 'Birthplace', languages: 'Languages', address: 'Address',
  father_first_name: "Father's First Name", father_middle_name: "Father's Middle Name", father_last_name: "Father's Last Name",
  father_suffix: "Father's Suffix",
  father_occupation: "Father's Occupation", father_contact_number: "Father's Contact Number", father_age: "Father's Age",
  father_educational_attainment: "Father's Educational Attainment",
  mother_first_name: "Mother's First Name", mother_middle_name: "Mother's Middle Name", mother_last_name: "Mother's Last Name",
  mother_suffix: "Mother's Suffix",
  mother_occupation: "Mother's Occupation", mother_contact_number: "Mother's Contact Number", mother_age: "Mother's Age",
  mother_educational_attainment: "Mother's Educational Attainment",
  guardian_first_name: "Guardian's First Name", guardian_middle_name: "Guardian's Middle Name", guardian_last_name: "Guardian's Last Name",
  guardian_suffix: "Guardian's Suffix",
  guardian_age: "Guardian's Age", guardian_occupation: "Guardian's Occupation", guardian_educational_attainment: "Guardian's Educational Attainment",
  guardian_contact: "Guardian's Contact Number",
  elementary_school: 'Elementary School', elementary_year_graduated: 'Elementary Year Graduated', elementary_achievements: 'Elementary Achievements',
  high_school: 'Junior High School', high_school_year_graduated: 'Junior High Year Graduated', high_school_achievements: 'Junior High Achievements',
  senior_high_school: 'Senior High School', senior_high_year_graduated: 'Senior High Year Graduated', senior_high_achievements: 'Senior High Achievements',
  college_school: 'College / University', college_year_graduated: 'College Year Graduated',
};
function diffOf(keys, now, before) {
  return keys
    .filter(k => String(now?.[k] ?? '') !== String(before?.[k] ?? ''))
    .map(k => ({ key: k, label: FIELD_LABELS[k] || k, from: String(before?.[k] ?? '') || '(empty)', to: String(now?.[k] ?? '') || '(empty)' }));
}
function changesFor(kind) {
  try {
    if (kind === 'profile') return diffOf(Object.keys(profileForm.value), profileForm.value, JSON.parse(profileSnapshot.value || '{}'));
    const now = backgroundForm.value, before = snapOf();
    if (kind === 'family') return diffOf(FAMILY_KEYS, now, before);
    if (kind === 'education') return diffOf(EDUCATION_KEYS, now, before);
    if (kind === 'siblings') {
      const a = pickSiblings(before), b = pickSiblings(now);
      return [{ key: 'siblings', label: 'Siblings', from: `${a.length} listed`, to: b.map(x => `${x.first_name} ${x.last_name}`.trim()).join(', ') || '(none)' }];
    }
  } catch (e) { /* fall through */ }
  return [];
}
const pendingChanges = computed(() => (confirmAction.value ? changesFor(confirmAction.value) : []));
const needsAgeConfirm = computed(() => confirmAction.value === 'profile' && !!birthWarning.value);
const ageConfirmed = ref(false);

function requestSave(kind) {
  backgroundError.value = '';
  backgroundSuccess.value = '';
  if (kind === 'profile') {
    profileError.value = '';
    profileSuccess.value = '';
    if (profileForm.value.contact_number && !/^09\d{9}$/.test(profileForm.value.contact_number)) {
      profileError.value = 'Contact number must start with 09 and be 11 digits long.';
      return;
    }
    const first = firstNameProblem.value || lastNameProblem.value || emailProblem.value || birthError.value || addressProblem.value;
    if (first) { profileError.value = first; return; }
  }
  if (kind === 'family') {
    const f = backgroundForm.value;
    if (['father_age', 'mother_age', 'guardian_age'].some(k => parentAgeIssue(k))) {
      backgroundError.value = 'Please correct the ages marked in red.';
      return;
    }
    if (f.father_contact_number && f.father_contact_number === f.mother_contact_number) {
      backgroundError.value = "The father's and mother's contact numbers are the same. Please enter a different number for each parent.";
      return;
    }
    for (const k of ['father_contact_number', 'mother_contact_number', 'guardian_contact']) {
      if (f[k] && !/^09\d{9}$/.test(f[k])) {
        backgroundError.value = 'Contact numbers must start with 09 and be 11 digits long.';
        return;
      }
    }
  }
  if (kind === 'siblings') {
    sibNamesChecked.value = true;
    for (let i = 0; i < backgroundForm.value.siblings.length; i++) {
      const sib = backgroundForm.value.siblings[i];
      if (siblingIsBlank(sib)) {
        blankSiblingIdx.value = i;
        return;
      }
      if (!sib.first_name?.trim() || !sib.last_name?.trim()) {
        backgroundError.value = 'Please fill in a first and last name for every sibling, or remove the empty row.';
        return;
      }
    }
  }
  if (kind === 'education') {
    const f = backgroundForm.value;
    if (SCHOOL_KEYS.some(k => wordsIssue(f[k], 'x')) || ACHIEVE_KEYS.some(k => wordsIssue(f[k], 'x'))) {
      backgroundError.value = 'Please correct the school names and achievements marked in red.';
      return;
    }
    if (EDUCATION_YEAR_SEQUENCE.some(k => yearIssue(f[k], minYearForLevel(k)))) {
      backgroundError.value = 'Please correct the year graduated marked in red.';
      return;
    }
  }
  consentChecked.value = false;
  ageConfirmed.value = false;
  confirmAction.value = kind;
}

async function confirmSave() {
  if (!consentChecked.value) return;
  const kind = confirmAction.value;
  confirmAction.value = null;
  if (kind === 'profile') await saveProfile();
  else if (kind === 'family') await saveFamily();
  else if (kind === 'siblings') await saveSiblings();
  else if (kind === 'education') await saveEducation();
}

function addSibling() {
  backgroundForm.value.siblings.push({ first_name: '', middle_name: '', last_name: '', suffix: '', age: '', occupation: '', educational_attainment: '', civil_status: '' });
}

// B524: a sibling row with nothing filled in at all gets a "continue or
// discard" prompt instead of a hard validation error.
function siblingIsBlank(sib) {
  return !['first_name', 'middle_name', 'last_name', 'suffix', 'age', 'occupation', 'educational_attainment', 'civil_status']
    .some(k => String(sib?.[k] ?? '').trim());
}
const blankSiblingIdx = ref(null);
function discardBlankSibling() {
  if (blankSiblingIdx.value !== null) backgroundForm.value.siblings.splice(blankSiblingIdx.value, 1);
  blankSiblingIdx.value = null;
}

// B525/B529: removing a sibling is confirmed, then saved to the server right
// away - it no longer waits for a separate "Save Siblings Information" click,
// which is what let a removed sibling reappear after navigating away and back.
const removeSiblingIdx = ref(null);
const removingSibling = ref(false);
function askRemoveSibling(idx) {
  removeSiblingIdx.value = idx;
}
async function confirmRemoveSibling() {
  if (removeSiblingIdx.value === null) return;
  removingSibling.value = true;
  const idx = removeSiblingIdx.value;
  const removed = backgroundForm.value.siblings[idx];
  backgroundForm.value.siblings.splice(idx, 1);
  try {
    const payload = { siblings: pickSiblings(backgroundForm.value) };
    await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    backgroundSnapshot.value = JSON.stringify({ ...snapOf(), ...payload });
    backgroundSection.value = 'family';
    backgroundSuccess.value = 'Sibling removed successfully.';
    backgroundError.value = '';
    toast?.success('Sibling removed successfully.');
  } catch (e) {
    // Put it back locally - the server didn't confirm the removal.
    backgroundForm.value.siblings.splice(idx, 0, removed);
    backgroundSection.value = 'family';
    backgroundError.value = firstApiError(e, 'Failed to remove the sibling. Please try again.');
  } finally {
    removingSibling.value = false;
    removeSiblingIdx.value = null;
  }
}

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

async function saveProfile() {
  const savedLabels = changesFor('profile').map(c => c.label).join(', ');
  profileError.value = '';
  profileSuccess.value = '';

  if (profileForm.value.contact_number && !/^09\d{9}$/.test(profileForm.value.contact_number)) {
    profileError.value = 'Contact number must start with 09 and be 11 digits long.';
    return;
  }

  try {
    const payload = { ...profileForm.value, birthdate: profileForm.value.birthdate || null, sex: profileForm.value.sex || null, civil_status: profileForm.value.civil_status || null };
    const res = await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    student.value = { ...student.value, ...res.data };
    localStorage.setItem('student', JSON.stringify(student.value));
    profileSnapshot.value = JSON.stringify(profileForm.value);
    profileSuccess.value = 'Profile updated successfully.' + (savedLabels ? ` Updated: ${savedLabels}.` : '');
    toast?.success('Profile updated successfully.');
  } catch (e) {
    profileError.value = firstApiError(e, 'Failed to update profile.');
  }
}

// Saves just the Father/Mother fields, independent of Siblings and
// Educational Attainment.
async function saveFamily() {
  backgroundError.value = '';
  backgroundSuccess.value = '';
  backgroundSection.value = 'family';
  savingFamily.value = true;
  try {
    const payload = pickFamily(backgroundForm.value);
    await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    const snap = backgroundSnapshot.value ? JSON.parse(backgroundSnapshot.value) : {};
    backgroundSnapshot.value = JSON.stringify({ ...snap, ...payload });
    backgroundSuccess.value = 'Family information updated successfully.';
    toast?.success('Family information updated successfully.');
  } catch (e) {
    backgroundError.value = firstApiError(e, 'Failed to update your family information.');
  } finally {
    savingFamily.value = false;
  }
}

// Saves just the Siblings list, independent of Family and Education.
async function saveSiblings() {
  backgroundError.value = '';
  backgroundSuccess.value = '';
  backgroundSection.value = 'family';

  for (const sib of backgroundForm.value.siblings) {
    if (!sib.first_name?.trim() || !sib.last_name?.trim()) {
      backgroundError.value = 'Please fill in a first and last name for every sibling, or remove the empty row.';
      return;
    }
  }

  savingSiblings.value = true;
  try {
    const payload = { siblings: pickSiblings(backgroundForm.value) };
    await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    backgroundSnapshot.value = JSON.stringify({ ...snapOf(), ...payload });
    backgroundSuccess.value = 'Siblings information updated successfully.';
    toast?.success('Siblings information updated successfully.');
  } catch (e) {
    backgroundError.value = firstApiError(e, 'Failed to update your siblings information.');
  } finally {
    savingSiblings.value = false;
  }
}

// Saves just the Educational Attainment fields.
async function saveEducation() {
  backgroundError.value = '';
  backgroundSuccess.value = '';
  backgroundSection.value = 'education';
  savingEducation.value = true;
  try {
    const payload = pickEducation(backgroundForm.value);
    await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    backgroundSnapshot.value = JSON.stringify({ ...snapOf(), ...payload });
    backgroundSuccess.value = 'Educational attainment updated successfully.';
    toast?.success('Educational attainment updated successfully.');
  } catch (e) {
    backgroundError.value = firstApiError(e, 'Failed to update your educational attainment.');
  } finally {
    savingEducation.value = false;
  }
}

// Password changes are confirmed first; the server then leaves a notice in
// the account's notifications.
const showPwConfirm = ref(false);
const changingPw    = ref(false);

// Same rules as the server, with an example for the symbol requirement.
function passwordProblem(pw) {
  if (pw.length < 8) return 'The password must be at least 8 characters.';
  if (pw.length > 64) return 'The password must not be longer than 64 characters.';
  if (!/[a-z]/.test(pw) || !/[A-Z]/.test(pw)) return 'The password must contain both uppercase and lowercase letters.';
  if (!/[0-9]/.test(pw)) return 'The password must contain at least one number.';
  if (!/[^A-Za-z0-9]/.test(pw)) return 'The password must contain at least one symbol (e.g. !, @, #, or $).';
  return '';
}

function askChangePassword() {
  pwError.value = '';
  const f = pwForm.value;
  if (!f.current_password || !f.password || !f.password_confirmation) {
    pwError.value = 'Please fill in your current password and the new password twice.';
    return;
  }
  if (f.password !== f.password_confirmation) {
    pwError.value = 'The new password and its confirmation do not match.';
    return;
  }
  if (f.password === f.current_password) {
    pwError.value = 'The new password must be different from your current password.';
    return;
  }
  const problem = passwordProblem(f.password);
  if (problem) { pwError.value = problem; return; }
  showPwConfirm.value = true;
}

async function confirmChangePassword() {
  changingPw.value = true;
  try {
    await changePassword();
  } finally {
    changingPw.value = false;
    showPwConfirm.value = false;
  }
}

async function changePassword() {
  pwError.value = '';
  pwSuccess.value = '';
  try {
    await axios.put(`${API_BASE}/student/password`, pwForm.value, authHeaders());
    student.value.must_change_password = false;
    localStorage.setItem('student', JSON.stringify(student.value));
    window.dispatchEvent(new Event('student-pw-changed'));
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
    showCurrentPw.value = false;
    showNewPw.value = false;
    showConfirmPw.value = false;
    pwSuccess.value = 'Password changed successfully.';
    toast?.success('Password changed successfully.');
  } catch (e) {
    pwError.value = firstApiError(e, 'Failed to change password.');
  }
}

onMounted(async () => {
  profileForm.value = {
    first_name: student.value.first_name || '',
    last_name: student.value.last_name || '',
    middle_name: student.value.middle_name || '',
    suffix: student.value.suffix || '',
    email: student.value.email || '',
    contact_number: student.value.contact_number || '',
  };
  profileSnapshot.value = JSON.stringify(profileForm.value);

  // The login/profile responses only carry a trimmed set of fields, so the
  // Family/Siblings/Education background has to be fetched separately from
  // the full student record.
  try {
    const res = await axios.get(`${API_BASE}/student/me`, authHeaders());
    const d = res.data || {};
    const siblings = Array.isArray(d.siblings) ? d.siblings : (d.siblings ? JSON.parse(d.siblings) : []);
    student.value = { ...student.value, program: d.program, year_level: d.year_level, student_id: d.student_id };
    profileForm.value = {
      ...profileForm.value,
      birthdate: d.birthdate ? String(d.birthdate).slice(0, 10) : '',
      sex: d.sex || '',
      civil_status: d.civil_status || '',
      nationality: d.nationality || '',
      birthplace: d.birthplace || '',
      languages: d.languages || '',
      address: d.address || '',
    };
    profileSnapshot.value = JSON.stringify(profileForm.value);
    backgroundForm.value = {
      father_first_name: d.father_first_name || '',
      father_middle_name: d.father_middle_name || '',
      father_last_name: d.father_last_name || '',
      father_suffix: d.father_suffix || '',
      father_occupation: d.father_occupation || '',
      father_contact_number: d.father_contact_number || '',
      mother_first_name: d.mother_first_name || '',
      mother_middle_name: d.mother_middle_name || '',
      mother_last_name: d.mother_last_name || '',
      mother_suffix: d.mother_suffix || '',
      mother_occupation: d.mother_occupation || '',
      mother_contact_number: d.mother_contact_number || '',
      father_age: d.father_age || '', father_educational_attainment: d.father_educational_attainment || '',
      mother_age: d.mother_age || '', mother_educational_attainment: d.mother_educational_attainment || '',
      guardian_first_name: d.guardian_first_name || '', guardian_middle_name: d.guardian_middle_name || '',
      guardian_last_name: d.guardian_last_name || '', guardian_suffix: d.guardian_suffix || '', guardian_age: d.guardian_age || '',
      guardian_occupation: d.guardian_occupation || '', guardian_educational_attainment: d.guardian_educational_attainment || '',
      guardian_contact: d.guardian_contact || '',
      siblings,
      senior_high_school: d.senior_high_school || '',
      senior_high_year_graduated: d.senior_high_year_graduated || '',
      senior_high_achievements: d.senior_high_achievements || '',
      high_school_achievements: d.high_school_achievements || '',
      elementary_achievements: d.elementary_achievements || '',
      elementary_school: d.elementary_school || '',
      elementary_year_graduated: d.elementary_year_graduated || '',
      high_school: d.high_school || '',
      high_school_year_graduated: d.high_school_year_graduated || '',
      college_school: d.college_school || '',
      college_year_graduated: d.college_year_graduated || '',
    };
    backgroundSnapshot.value = JSON.stringify(backgroundForm.value);
  } catch (e) {
    // Non-fatal - the form just starts empty.
  }
});
</script>

<style scoped>
.fe{font-size:11.5px;color:var(--red);margin-top:4px}
.fw{font-size:11.5px;color:var(--amber);background:var(--amber-lt);border:1px solid var(--amber);border-radius:var(--r-sm);padding:6px 8px;margin-top:4px;line-height:1.5}
.chg{border:1px solid var(--cloud);border-radius:var(--r-sm);background:var(--snow);padding:4px 12px;max-height:260px;overflow-y:auto}
.chg-row{padding:8px 0;border-bottom:1px solid var(--cloud);font-size:12.5px}
.chg-row:last-child{border-bottom:0}
.chg-l{font-size:11px;font-weight:600;color:var(--stone);text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px}
.chg-v{word-break:break-word;color:var(--ink)}
.chg-old{color:var(--fog);text-decoration:line-through}
.chg-arrow{margin:0 6px;color:var(--stone)}
</style>