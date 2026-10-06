<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Account</h1>
      <p>Fill in your Personal, Family, Siblings and Educational information, and update your password.</p>
    </div>

    <!-- Profile header, then one section at a time behind tabs, in a column of
         comfortable width - the full form is far too long to show at once. -->
    <div style="max-width:980px;margin:0 auto;display:flex;flex-direction:column;gap:16px">

      <div class="icard" style="padding:18px 20px;display:flex;align-items:center;gap:14px">
        <div style="width:48px;height:48px;border-radius:50%;background:var(--forest);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0">{{ headerInitials }}</div>
        <div style="min-width:0">
          <div style="font-size:16px;font-weight:600;color:var(--ink)">{{ student.first_name }} {{ student.last_name }}</div>
          <div style="font-size:12.5px;color:var(--stone);margin-top:2px">{{ headerDetails }}</div>
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
              <input v-model="profileForm.last_name" class="ifi" maxlength="20" @input="profileForm.last_name = profileForm.last_name.replace(/[^a-zA-Z\s'-]/g, '')" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="profileForm.first_name" class="ifi" maxlength="20" @input="profileForm.first_name = profileForm.first_name.replace(/[^a-zA-Z\s'-]/g, '')" />
            </div>
          </div>
          <div>
            <label class="ifl">Middle Name</label>
            <input v-model="profileForm.middle_name" class="ifi" maxlength="20" @input="profileForm.middle_name = profileForm.middle_name.replace(/[^a-zA-Z\s'-]/g, '')" />
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
            <input v-model="profileForm.email" type="email" class="ifi" maxlength="100" />
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Student ID</label>
              <input :value="student.student_id || ''" class="ifi" disabled />
            </div>
            <div>
              <label class="ifl">Course and Year</label>
              <input :value="[student.program, student.year_level].filter(Boolean).join(' - ')" class="ifi" disabled />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Birthdate (mm/dd/yyyy)</label>
              <input v-model="profileForm.birthdate" type="date" class="ifi" :max="todayStr" />
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
              <input v-model="profileForm.nationality" class="ifi" maxlength="100" @input="profileForm.nationality = String(profileForm.nationality ?? '').replace(/[^a-zA-Z\s'.-]/g, '')" />
            </div>
            <div>
              <label class="ifl">Birthplace</label>
              <input v-model="profileForm.birthplace" class="ifi" maxlength="255" @input="profileForm.birthplace = String(profileForm.birthplace ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
          </div>
          <div>
            <label class="ifl">Languages that I understand</label>
            <input v-model="profileForm.languages" class="ifi" maxlength="255" placeholder="e.g. Filipino, English, Bisaya" @input="profileForm.languages = String(profileForm.languages ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
          </div>
          <div>
            <label class="ifl">Address while studying at BSU</label>
            <input v-model="profileForm.address" class="ifi" maxlength="255" @input="profileForm.address = String(profileForm.address ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
          </div>
          <div>
            <label class="ifl">Contact Number</label>
            <input
            v-model="profileForm.contact_number"
            class="ifi"
            placeholder="09XXXXXXXXX"
            maxlength="11"
            :style="profileError ? 'border-color:var(--red);border-width:1.5px' : ''"
            @input="profileForm.contact_number = profileForm.contact_number.replace(/[^0-9]/g, '').slice(0, 11); profileError = ''"
          />
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="isProfileUnchanged" @click="requestSave('profile')">Save Changes</button>
        </div>
      </div>

      <div v-show="tab === 'family' || tab === 'education'" class="icard">
        <div class="icard-header"><span class="icard-title">{{ tab === 'education' ? 'Educational Background' : 'Family Background' }}</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:16px">
          <div v-if="backgroundError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundError }}</div>
          <div v-if="backgroundSuccess" style="background:var(--mist);border:1px solid #bfe3c8;color:var(--moss);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ backgroundSuccess }}</div>

          <div v-show="tab === 'family'" style="display:flex;flex-direction:column;gap:16px">
          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Family Information</div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Father</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.father_last_name" class="ifi" maxlength="255" @input="backgroundForm.father_last_name = String(backgroundForm.father_last_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.father_first_name" class="ifi" maxlength="255" @input="backgroundForm.father_first_name = String(backgroundForm.father_first_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.father_middle_name" class="ifi" maxlength="255" @input="backgroundForm.father_middle_name = String(backgroundForm.father_middle_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.father_occupation" class="ifi" maxlength="255" @input="backgroundForm.father_occupation = String(backgroundForm.father_occupation ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.father_contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" @input="backgroundForm.father_contact_number = backgroundForm.father_contact_number.replace(/[^0-9]/g, '').slice(0, 11)" />
            </div>
          </div>

          <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
            <div>
              <label class="ifl">Age</label>
              <input v-model="backgroundForm.father_age" class="ifi" maxlength="3" @input="backgroundForm.father_age = String(backgroundForm.father_age ?? '').replace(/[^0-9]/g, '')" />
            </div>
            <div>
              <label class="ifl">Highest Educational Attainment</label>
              <input v-model="backgroundForm.father_educational_attainment" class="ifi" maxlength="255" @input="backgroundForm.father_educational_attainment = String(backgroundForm.father_educational_attainment ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
          </div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate);margin-top:4px">Mother</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.mother_last_name" class="ifi" maxlength="255" @input="backgroundForm.mother_last_name = String(backgroundForm.mother_last_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.mother_first_name" class="ifi" maxlength="255" @input="backgroundForm.mother_first_name = String(backgroundForm.mother_first_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.mother_middle_name" class="ifi" maxlength="255" @input="backgroundForm.mother_middle_name = String(backgroundForm.mother_middle_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.mother_occupation" class="ifi" maxlength="255" @input="backgroundForm.mother_occupation = String(backgroundForm.mother_occupation ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.mother_contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" @input="backgroundForm.mother_contact_number = backgroundForm.mother_contact_number.replace(/[^0-9]/g, '').slice(0, 11)" />
            </div>
          </div>

          <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
            <div>
              <label class="ifl">Age</label>
              <input v-model="backgroundForm.mother_age" class="ifi" maxlength="3" @input="backgroundForm.mother_age = String(backgroundForm.mother_age ?? '').replace(/[^0-9]/g, '')" />
            </div>
            <div>
              <label class="ifl">Highest Educational Attainment</label>
              <input v-model="backgroundForm.mother_educational_attainment" class="ifi" maxlength="255" @input="backgroundForm.mother_educational_attainment = String(backgroundForm.mother_educational_attainment ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
          </div>

          <div style="font-size:11.5px;font-weight:600;color:var(--slate);margin-top:4px">Legal Guardian</div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Last Name</label>
              <input v-model="backgroundForm.guardian_last_name" class="ifi" maxlength="255" @input="backgroundForm.guardian_last_name = String(backgroundForm.guardian_last_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">First Name</label>
              <input v-model="backgroundForm.guardian_first_name" class="ifi" maxlength="255" @input="backgroundForm.guardian_first_name = String(backgroundForm.guardian_first_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Middle Name</label>
              <input v-model="backgroundForm.guardian_middle_name" class="ifi" maxlength="255" @input="backgroundForm.guardian_middle_name = String(backgroundForm.guardian_middle_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:80px 1fr;gap:12px">
            <div>
              <label class="ifl">Age</label>
              <input v-model="backgroundForm.guardian_age" class="ifi" maxlength="3" @input="backgroundForm.guardian_age = String(backgroundForm.guardian_age ?? '').replace(/[^0-9]/g, '')" />
            </div>
            <div>
              <label class="ifl">Highest Educational Attainment</label>
              <input v-model="backgroundForm.guardian_educational_attainment" class="ifi" maxlength="255" @input="backgroundForm.guardian_educational_attainment = String(backgroundForm.guardian_educational_attainment ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label class="ifl">Occupation</label>
              <input v-model="backgroundForm.guardian_occupation" class="ifi" maxlength="255" @input="backgroundForm.guardian_occupation = String(backgroundForm.guardian_occupation ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="backgroundForm.guardian_contact" class="ifi" placeholder="09XXXXXXXXX" maxlength="11" @input="backgroundForm.guardian_contact = String(backgroundForm.guardian_contact ?? '').replace(/[^0-9]/g, '').slice(0, 11)" />
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
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="sib.last_name" class="ifi" maxlength="255" @input="sib.last_name = String(sib.last_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="sib.first_name" class="ifi" maxlength="255" @input="sib.first_name = String(sib.first_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
              </div>
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="sib.middle_name" class="ifi" maxlength="255" @input="sib.middle_name = String(sib.middle_name ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
              </div>
            </div>
            <div style="display:grid;grid-template-columns:80px 1fr 1fr;gap:10px">
              <div>
                <label class="ifl">Age</label>
                <input v-model="sib.age" class="ifi" maxlength="3" @input="sib.age = String(sib.age ?? '').replace(/[^0-9]/g, '')" />
              </div>
              <div>
                <label class="ifl">Highest Educational Attainment</label>
                <input v-model="sib.educational_attainment" class="ifi" maxlength="255" @input="sib.educational_attainment = String(sib.educational_attainment ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
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
                <label class="ifl">Occupation (write student if still studying)</label>
                <input v-model="sib.occupation" class="ifi" maxlength="255" @input="sib.occupation = String(sib.occupation ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
              </div>
              <button type="button" class="ibtn ibtn-o ibtn-sm" style="color:var(--red)" @click="removeSibling(idx)">Remove</button>
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-p ibtn-sm" :disabled="isSiblingsUnchanged || savingSiblings" @click="requestSave('siblings')">{{ savingSiblings ? 'Saving...' : 'Save Siblings Information' }}</button>
          </div>
          </div>


          <div v-show="tab === 'education'" style="display:flex;flex-direction:column;gap:16px">
          <div style="font-size:11px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:var(--fog)">Educational Attainment</div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Senior High School</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">School</label>
              <input v-model="backgroundForm.senior_high_school" class="ifi" maxlength="255" @input="backgroundForm.senior_high_school = String(backgroundForm.senior_high_school ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.senior_high_year_graduated" class="ifi" placeholder="e.g. 2022" maxlength="4" @input="backgroundForm.senior_high_year_graduated = String(backgroundForm.senior_high_year_graduated ?? '').replace(/[^0-9]/g, '')" />
            </div>
          </div>
          <div v-if="'senior_high_achievements'">
            <label class="ifl">Achievements</label>
            <textarea v-model="backgroundForm.senior_high_achievements" class="ifta" style="min-height:56px" maxlength="1000" placeholder="Honors, awards, recognitions..."></textarea>
          </div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Junior High School</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">School</label>
              <input v-model="backgroundForm.high_school" class="ifi" maxlength="255" @input="backgroundForm.high_school = String(backgroundForm.high_school ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.high_school_year_graduated" class="ifi" placeholder="e.g. 2020" maxlength="4" @input="backgroundForm.high_school_year_graduated = String(backgroundForm.high_school_year_graduated ?? '').replace(/[^0-9]/g, '')" />
            </div>
          </div>
          <div v-if="'high_school_achievements'">
            <label class="ifl">Achievements</label>
            <textarea v-model="backgroundForm.high_school_achievements" class="ifta" style="min-height:56px" maxlength="1000" placeholder="Honors, awards, recognitions..."></textarea>
          </div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">Elementary</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">School</label>
              <input v-model="backgroundForm.elementary_school" class="ifi" maxlength="255" @input="backgroundForm.elementary_school = String(backgroundForm.elementary_school ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.elementary_year_graduated" class="ifi" placeholder="e.g. 2016" maxlength="4" @input="backgroundForm.elementary_year_graduated = String(backgroundForm.elementary_year_graduated ?? '').replace(/[^0-9]/g, '')" />
            </div>
          </div>
          <div v-if="'elementary_achievements'">
            <label class="ifl">Achievements</label>
            <textarea v-model="backgroundForm.elementary_achievements" class="ifta" style="min-height:56px" maxlength="1000" placeholder="Honors, awards, recognitions..."></textarea>
          </div>
          <div style="font-size:11.5px;font-weight:600;color:var(--slate)">College (current)</div>
          <div style="display:grid;grid-template-columns:1fr 120px;gap:12px">
            <div>
              <label class="ifl">College / University</label>
              <input v-model="backgroundForm.college_school" class="ifi" maxlength="255" @input="backgroundForm.college_school = String(backgroundForm.college_school ?? '').replace(/[^a-zA-Z0-9À-ɏ'.,\x26()\- ]/g, '')" />
            </div>
            <div>
              <label class="ifl">Year Graduated</label>
              <input v-model="backgroundForm.college_year_graduated" class="ifi" placeholder="Leave blank if ongoing" maxlength="4" @input="backgroundForm.college_year_graduated = String(backgroundForm.college_year_graduated ?? '').replace(/[^0-9]/g, '')" />
            </div>
          </div>

          <div style="display:flex;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-p ibtn-sm" :disabled="isEducationUnchanged || savingEducation" @click="requestSave('education')">{{ savingEducation ? 'Saving...' : 'Save Educational Attainment' }}</button>
          </div>
          </div>
        </div>
      </div>

      <div v-show="tab === 'password'" class="icard">
        <div class="icard-header"><span class="icard-title">Change Password</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="pwError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwError }}</div>
          <div v-if="pwSuccess" style="background:var(--mist);border:1px solid #bfe3c8;color:var(--moss);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwSuccess }}</div>
          <div>
            <label class="ifl">Current Password</label>
            <div style="position:relative">
              <input v-model="pwForm.current_password" :type="showCurrentPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:36px" />
              <button type="button" @click="showCurrentPw = !showCurrentPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showCurrentPw ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <div>
            <label class="ifl">New Password</label>
            <div style="position:relative">
              <input v-model="pwForm.password" :type="showNewPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:36px" />
              <button type="button" @click="showNewPw = !showNewPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showNewPw ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <div>
            <label class="ifl">Confirm New Password</label>
            <div style="position:relative">
              <input v-model="pwForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:36px" />
              <button type="button" @click="showConfirmPw = !showConfirmPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showConfirmPw ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="changePassword">Change Password</button>
        </div>
      </div>

    </div>

    <!-- Save confirmation -->
    <div v-if="confirmAction" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:60;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="confirmAction = null">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:420px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud)">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Save changes?</div>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:16px">
          <div style="font-size:13px;color:var(--slate)">Are you sure you want to save your changes to <strong>{{ CONFIRM_LABELS[confirmAction] }}</strong>?</div>
          <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;background:var(--foam);border:1px solid var(--cloud);border-radius:var(--r-sm);padding:12px">
            <input type="checkbox" v-model="consentChecked" style="margin-top:3px;flex-shrink:0" />
            <span style="font-size:12px;line-height:1.5;color:var(--slate)">I understand the above mentioned Data Privacy Notice of Benguet State University (BSU) and consent to the collection and official use of my personal information through this medium for all legal intents and purposes. I understand that the OSS-SDS-Guidance and Counseling Unit (GCU) will abide by the policy as mentioned above except for cases not within its control. I give my full consent to OSS-SDS-Guidance and Counseling Unit (GCU) necessary and relevant data pertaining to my personal data. I certify that the information I am saving is true and correct.</span>
          </label>
          <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="ibtn ibtn-o" @click="confirmAction = null">Cancel</button>
            <button type="button" class="ibtn ibtn-p" :disabled="!consentChecked" @click="confirmSave">Yes, Save</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const API_BASE = `${import.meta.env.VITE_API_URL || 'https://icare-backend-5jwe.onrender.com'}/api`;

const student = ref(JSON.parse(localStorage.getItem('student') || '{}'));

// ---- Tabs ----
const TABS = [
  { value: 'personal',  label: 'Personal' },
  { value: 'family',    label: 'Family' },
  { value: 'education', label: 'Education' },
  { value: 'password',  label: 'Password' },
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
const todayStr = new Date().toISOString().split('T')[0];
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
const pwSuccess = ref('');
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
    father_first_name: '', father_middle_name: '', father_last_name: '', father_occupation: '', father_contact_number: '',
    father_age: '', father_educational_attainment: '',
    mother_first_name: '', mother_middle_name: '', mother_last_name: '', mother_occupation: '', mother_contact_number: '',
    mother_age: '', mother_educational_attainment: '',
    guardian_first_name: '', guardian_middle_name: '', guardian_last_name: '', guardian_age: '',
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
  'father_first_name', 'father_middle_name', 'father_last_name', 'father_occupation', 'father_contact_number',
  'mother_first_name', 'mother_middle_name', 'mother_last_name', 'mother_occupation', 'mother_contact_number',
  'father_age', 'father_educational_attainment', 'mother_age', 'mother_educational_attainment',
  'guardian_first_name', 'guardian_middle_name', 'guardian_last_name', 'guardian_age',
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
  first_name: x.first_name ?? '', middle_name: x.middle_name ?? '', last_name: x.last_name ?? '',
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

// Every save goes through a confirmation modal first.
const CONFIRM_LABELS = {
  profile: 'Personal Information',
  family: 'Family Information',
  siblings: 'Siblings Information',
  education: 'Educational Attainment',
};
const confirmAction = ref(null);
const consentChecked = ref(false);

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
  }
  if (kind === 'siblings') {
    for (const sib of backgroundForm.value.siblings) {
      if (!sib.first_name?.trim() || !sib.last_name?.trim()) {
        backgroundError.value = 'Please fill in a first and last name for every sibling, or remove the empty row.';
        return;
      }
    }
  }
  consentChecked.value = false;
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
  backgroundForm.value.siblings.push({ first_name: '', middle_name: '', last_name: '', age: '', occupation: '', educational_attainment: '', civil_status: '' });
}
function removeSibling(idx) {
  backgroundForm.value.siblings.splice(idx, 1);
}

function authHeaders() {
  return { headers: { Authorization: `Bearer ${localStorage.getItem('student_token')}` } };
}

async function saveProfile() {
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
    profileSuccess.value = 'Profile updated successfully.';
  } catch (e) {
    profileError.value = firstApiError(e, 'Failed to update profile.');
  }
}

// Saves just the Father/Mother fields, independent of Siblings and
// Educational Attainment.
async function saveFamily() {
  backgroundError.value = '';
  backgroundSuccess.value = '';
  savingFamily.value = true;
  try {
    const payload = pickFamily(backgroundForm.value);
    await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    const snap = backgroundSnapshot.value ? JSON.parse(backgroundSnapshot.value) : {};
    backgroundSnapshot.value = JSON.stringify({ ...snap, ...payload });
    backgroundSuccess.value = 'Family information updated successfully.';
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
  savingEducation.value = true;
  try {
    const payload = pickEducation(backgroundForm.value);
    await axios.put(`${API_BASE}/student/profile`, payload, authHeaders());
    backgroundSnapshot.value = JSON.stringify({ ...snapOf(), ...payload });
    backgroundSuccess.value = 'Educational attainment updated successfully.';
  } catch (e) {
    backgroundError.value = firstApiError(e, 'Failed to update your educational attainment.');
  } finally {
    savingEducation.value = false;
  }
}

async function changePassword() {
  pwError.value = '';
  pwSuccess.value = '';
  try {
    await axios.put(`${API_BASE}/student/password`, pwForm.value, authHeaders());
    student.value.must_change_password = false;
    localStorage.setItem('student', JSON.stringify(student.value));
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
    showCurrentPw.value = false;
    showNewPw.value = false;
    showConfirmPw.value = false;
    pwSuccess.value = 'Password changed successfully.';
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
      father_occupation: d.father_occupation || '',
      father_contact_number: d.father_contact_number || '',
      mother_first_name: d.mother_first_name || '',
      mother_middle_name: d.mother_middle_name || '',
      mother_last_name: d.mother_last_name || '',
      mother_occupation: d.mother_occupation || '',
      mother_contact_number: d.mother_contact_number || '',
      father_age: d.father_age || '', father_educational_attainment: d.father_educational_attainment || '',
      mother_age: d.mother_age || '', mother_educational_attainment: d.mother_educational_attainment || '',
      guardian_first_name: d.guardian_first_name || '', guardian_middle_name: d.guardian_middle_name || '',
      guardian_last_name: d.guardian_last_name || '', guardian_age: d.guardian_age || '',
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