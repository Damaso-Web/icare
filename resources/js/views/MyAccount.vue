<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px">
      <h1>My Account</h1>
      <p>Update your personal information and password.</p>
    </div>

    <div style="max-width:1080px;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start">

      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="icard">
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
                <input v-model="profileForm.suffix" class="ifi" placeholder="e.g. Jr., III" maxlength="20" />
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

        <div class="icard">
          <div class="icard-header"><span class="icard-title">Account Details</span></div>
          <div style="padding:16px 20px;display:flex;flex-direction:column;gap:8px;font-size:13px">
            <div><span style="color:var(--stone)">Employee ID:</span> {{ auth.user?.employee_id || '-' }}</div>
            <div><span style="color:var(--stone)">Role:</span> {{ roleLabel }}</div>
            <div v-if="auth.user?.unit"><span style="color:var(--stone)">Unit:</span> {{ auth.user.unit }}</div>
            <div v-if="auth.user?.college"><span style="color:var(--stone)">College:</span> {{ auth.user.college }}</div>
            <div v-if="auth.user?.department"><span style="color:var(--stone)">Department:</span> {{ auth.user.department }}</div>
            <div style="font-size:11px;color:var(--fog);margin-top:4px">These details can only be changed by the Administrator.</div>
          </div>
        </div>
      </div>

      <div class="icard">
        <div class="icard-header"><span class="icard-title">Change Password</span></div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px">
          <div v-if="pwError" style="background:var(--red-lt);border:1px solid #f5c0c0;color:var(--red);padding:8px 12px;border-radius:var(--r-sm);font-size:12px">{{ pwError }}</div>
          <div>
            <label class="ifl">Current Password</label>
            <input v-model="pwForm.current_password" type="password" class="ifi" />
          </div>
          <div>
            <label class="ifl">New Password</label>
            <input v-model="pwForm.password" type="password" class="ifi" />
          </div>
          <div>
            <label class="ifl">Confirm New Password</label>
            <input v-model="pwForm.password_confirmation" type="password" class="ifi" />
          </div>
          <button class="ibtn ibtn-p" style="width:100%;justify-content:center" @click="changePassword">Change Password</button>
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
            <input v-model="confirmPassword" type="password" class="ifi" @keyup.enter="submitProfile" />
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

async function changePassword() {
  pwError.value = '';
  try {
    await api.put('/me/password', pwForm.value);
    toast?.success('Password changed successfully.');
    pwForm.value = { current_password: '', password: '', password_confirmation: '' };
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
