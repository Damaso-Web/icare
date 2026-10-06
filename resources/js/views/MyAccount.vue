<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Account</h1>
      <p>Update your personal information and password.</p>
    </div>

    <!-- Profile header, then one section at a time behind tabs - the same
         layout as the student portal's My Account. -->
    <div style="max-width:980px;margin:0 auto;display:flex;flex-direction:column;gap:16px">

      <div class="icard" style="padding:18px 20px;display:flex;align-items:flex-start;gap:14px">
        <div style="width:48px;height:48px;border-radius:50%;background:var(--forest);color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;flex-shrink:0">{{ headerInitials }}</div>
        <div style="min-width:0">
          <div style="font-size:16px;font-weight:600;color:var(--ink)">{{ auth.user?.name }}</div>
          <div style="font-size:12.5px;color:var(--stone);margin-top:2px">{{ auth.user?.email }}</div>
          <div style="display:flex;flex-wrap:wrap;gap:4px 18px;font-size:12.5px;margin-top:8px">
            <span v-for="d in accountDetails" :key="d.label"><span style="color:var(--stone)">{{ d.label }}:</span> {{ d.value }}</span>
          </div>
          <div style="font-size:11px;color:var(--fog);margin-top:6px">These details can only be changed by the Administrator.</div>
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

        <div v-show="tab === 'profile'" class="icard">
          <div class="icard-header"><span class="icard-title">Profile Information</span></div>
          <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
            <div v-if="profileError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ profileError }}</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div>
                <label class="ifl">Last Name</label>
                <input v-model="profileForm.last_name" class="ifi"
         @input="profileForm.last_name = profileForm.last_name.replace(/[^a-zA-Z\s'.-]/g, '')" />
              </div>
              <div>
                <label class="ifl">First Name</label>
                <input v-model="profileForm.first_name" class="ifi"
         @input="profileForm.first_name = profileForm.first_name.replace(/[^a-zA-Z\s'.-]/g, '')" />
              </div>
            </div>
            <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px">
              <div>
                <label class="ifl">Middle Name</label>
                <input v-model="profileForm.middle_name" class="ifi"
         @input="profileForm.middle_name = profileForm.middle_name.replace(/[^a-zA-Z\s'.-]/g, '')" />
              </div>
              <div>
                <label class="ifl">Suffix</label>
                <input v-model="profileForm.suffix" class="ifi" placeholder="e.g. Jr., III" maxlength="20" @input="profileForm.suffix = String(profileForm.suffix ?? '').replace(/[^a-zA-ZÀ-ɏ'.\- ]/g, '')" />
              </div>
            </div>
            <div>
              <label class="ifl">Email</label>
              <input v-model="profileForm.email" type="email" class="ifi" />
              <div v-if="emailChanged" style="font-size:11px;color:var(--amber);margin-top:4px">
                This is your login email. You'll be asked for your current password to confirm the change.
              </div>
            </div>
            <div>
              <label class="ifl">Contact Number</label>
              <input v-model="profileForm.contact_number" class="ifi" placeholder="09XXXXXXXXX" maxlength="11"
         @input="profileForm.contact_number = profileForm.contact_number.replace(/\D/g, '')" />
            </div>
            <button class="ibtn ibtn-p" style="width:100%;justify-content:center" :disabled="!isDirty || saving" @click="saveProfile">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>

      <div v-show="tab === 'password'" class="icard">
        <div class="icard-header"><span class="icard-title">Change Password</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="pwError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwError }}</div>
          <div>
            <label class="ifl">Current Password</label>
            <div style="position:relative">
              <input v-model="pwForm.current_password" :type="showCurrentPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:52px" />
              <button type="button" @click="showCurrentPw = !showCurrentPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showCurrentPw ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <div>
            <label class="ifl">New Password</label>
            <div style="position:relative">
              <input v-model="pwForm.password" :type="showNewPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:52px" />
              <button type="button" @click="showNewPw = !showNewPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showNewPw ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <div>
            <label class="ifl">Confirm New Password</label>
            <div style="position:relative">
              <input v-model="pwForm.password_confirmation" :type="showConfirmPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:52px" />
              <button type="button" @click="showConfirmPw = !showConfirmPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showConfirmPw ? 'Hide' : 'Show' }}</button>
            </div>
            <div v-if="pwMismatch" style="font-size:11.5px;color:var(--red);margin-top:5px">Passwords do not match.</div>
            <div v-else-if="pwMatch" style="font-size:11.5px;color:var(--moss);margin-top:5px">Passwords match.</div>
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="askChangePassword">Change Password</button>
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
    <!-- Email change confirmation -->
    <div v-if="showEmailConfirm" style="position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:65;display:flex;align-items:center;justify-content:center;padding:20px" @click.self="closeEmailConfirm">
      <div style="background:#fff;border-radius:var(--r-lg);width:100%;max-width:460px;overflow:hidden;box-shadow:var(--sh-lg)">
        <div style="padding:20px 22px;border-bottom:1px solid var(--cloud);display:flex;align-items:center;justify-content:space-between">
          <div style="font-size:15px;font-weight:600;color:var(--ink)">Confirm Email Change</div>
          <button class="ibtn ibtn-g ibtn-sm" @click="closeEmailConfirm">✕</button>
        </div>
        <div style="padding:22px;display:flex;flex-direction:column;gap:14px">
          <div v-if="confirmError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ confirmError }}</div>
          <div style="background:var(--snow);border-radius:var(--r-sm);padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><strong>Current email:</strong> {{ auth.user?.email }}</div>
            <div><strong>New email:</strong> <span style="color:var(--forest)">{{ profileForm.email }}</span></div>
          </div>
          <div style="font-size:12.5px;color:var(--stone)">
            Your email is your login username. After this change, you must sign in with <strong>{{ profileForm.email }}</strong>.
          </div>
          <div>
            <label class="ifl">Current Password</label>
            <div style="position:relative">
              <input v-model="confirmPassword" :type="showEmailPw ? 'text' : 'password'" class="ifi" maxlength="100" style="padding-right:52px" @keyup.enter="submitProfile" />
              <button type="button" @click="showEmailPw = !showEmailPw" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--fog);font-size:11px">{{ showEmailPw ? 'Hide' : 'Show' }}</button>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="ibtn ibtn-p" :disabled="!confirmPassword || saving" @click="submitProfile">
              {{ saving ? 'Saving...' : 'Confirm & Save' }}
            </button>
            <button class="ibtn ibtn-o" @click="closeEmailConfirm">Go Back &amp; Edit</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, inject } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../api/index';

const toast = inject('toast');
const auth  = useAuthStore();

// ---- Tabs ----
const TABS = [
  { value: 'profile',  label: 'Profile' },
  { value: 'password', label: 'Password' },
];
// An account still on a temporary password lands on the Password tab.
const tab = ref(auth.user?.must_change_password ? 'password' : 'profile');

const headerInitials = computed(() =>
  (auth.user?.name || '').split(' ').filter(Boolean).map(n => n[0]).slice(0, 2).join('').toUpperCase() || '?'
);
// Read-only account details, shown in the header. Only the ones that are set.
const accountDetails = computed(() => {
  const u = auth.user || {};
  return [
    { label: 'Employee ID', value: u.employee_id || '-' },
    { label: 'Role',        value: roleLabel.value },
    { label: 'Unit',        value: u.unit },
    { label: 'College',     value: u.college },
    { label: 'Department',  value: u.department },
  ].filter(d => d.value);
});

const PROFILE_FIELDS = ['first_name', 'last_name', 'middle_name', 'suffix', 'email', 'contact_number'];

const profileForm = ref(Object.fromEntries(PROFILE_FIELDS.map(f => [f, ''])));
const snapshot     = ref('');
const profileError = ref('');
const saving       = ref(false);

const showEmailConfirm = ref(false);
const confirmPassword  = ref('');
const confirmError     = ref('');

const pwForm = ref({ current_password: '', password: '', password_confirmation: '' });
const pwError = ref('');
// Checked as the user types, so a mismatch shows before they submit.
const pwMismatch = computed(() => !!pwForm.value.password_confirmation && pwForm.value.password !== pwForm.value.password_confirmation);
const pwMatch    = computed(() => !!pwForm.value.password_confirmation && pwForm.value.password === pwForm.value.password_confirmation);
const showCurrentPw = ref(false);
const showNewPw     = ref(false);
const showConfirmPw = ref(false);
const showEmailPw   = ref(false);

const isDirty      = computed(() => JSON.stringify(profileForm.value) !== snapshot.value);
const emailChanged = computed(() =>
  profileForm.value.email.trim().toLowerCase() !== (auth.user?.email || '').toLowerCase()
);

const roleLabel = computed(() => ({
  admin:          'Admin / GCU Head',
  gcu_staff:      'GCU Staff',
  sdu_head:       'SDU Head',
  tmdu_staff:     'TMDU Staff',
  faculty:        'Faculty',
  dean_secretary: "Dean's Secretary",
  system_admin:   'System Admin',
}[auth.user?.role] || auth.user?.role || '-'));

function fillForm(user) {
  profileForm.value = Object.fromEntries(PROFILE_FIELDS.map(f => [f, user?.[f] || '']));
  snapshot.value = JSON.stringify(profileForm.value);
}

function validateProfile() {
  const f = profileForm.value;
  if (f.last_name.trim().length < 2 || f.first_name.trim().length < 2) return 'First and last name must be at least 2 characters.';
  if (f.middle_name && f.middle_name.trim().length < 2) return 'Middle name must be at least 2 characters, or leave it blank.';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email.trim())) return 'Please enter a valid email address.';
  if (f.contact_number && !/^09\d{9}$/.test(f.contact_number)) return 'Contact number must be 11 digits starting with 09.';
  return '';
}

function saveProfile() {
  profileError.value = validateProfile();
  if (profileError.value) return;
  if (emailChanged.value) {
    confirmPassword.value = '';
    confirmError.value = '';
    showEmailConfirm.value = true;
    return;
  }
  submitProfile();
}

function closeEmailConfirm() {
  showEmailConfirm.value = false;
  confirmPassword.value = '';
  confirmError.value = '';
  showEmailPw.value = false;
}

async function submitProfile() {
  if (saving.value) return;
  saving.value = true;
  confirmError.value = '';
  try {
    const payload = { ...profileForm.value, email: profileForm.value.email.trim() };
    if (emailChanged.value) payload.current_password = confirmPassword.value;
    const res = await api.put('/me/profile', payload);
    auth.user = { ...auth.user, ...res.data };
    localStorage.setItem('user', JSON.stringify(auth.user));
    fillForm(auth.user);
    closeEmailConfirm();
    profileError.value = '';
    toast?.success('Profile updated successfully.');
  } catch (e) {
    const data = e.response?.data;
    const msg = (data?.errors && Object.values(data.errors)[0]?.[0]) || data?.message || 'Failed to update profile.';
    if (showEmailConfirm.value) confirmError.value = msg;
    else profileError.value = msg;
  } finally {
    saving.value = false;
  }
}

// Password changes are confirmed first; the server then leaves a notice in
// the account's notifications.
const showPwConfirm = ref(false);
const changingPw    = ref(false);

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
  try {
    await api.put('/me/password', pwForm.value);
    toast?.success('Password changed successfully.');
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
    showCurrentPw.value = showNewPw.value = showConfirmPw.value = false;
  } catch (e) {
    pwError.value = e.response?.data?.message || 'Failed to change password.';
  }
}

onMounted(async () => {
  fillForm(auth.user);
  // Refresh from the server so the form never shows stale data cached at login.
  try {
    const res = await api.get('/me');
    auth.user = { ...auth.user, ...res.data };
    localStorage.setItem('user', JSON.stringify(auth.user));
    fillForm(auth.user);
  } catch { /* keep the cached values */ }
});
</script>