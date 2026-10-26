<template>
  <div class="fade-up">
    <div class="ph" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div>
        <h1>Case Referral</h1>
        <p v-if="data.referral_code" style="font-family:var(--mono)">{{ data.referral_code }}</p>
      </div>
      <button class="ibtn ibtn-o ibtn-sm" @click="goBack">{{ returnTo ? 'Back to Referral' : 'Back to Case Referrals' }}</button>
    </div>

    <div v-if="loading" style="text-align:center;padding:44px">
      <div style="width:24px;height:24px;border:2px solid var(--mint);border-top-color:var(--moss);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto"></div>
    </div>
    <div v-else-if="error" class="empty-state"><h3>{{ error }}</h3></div>
    <div v-else class="icard" style="max-width:680px">
      <div class="icard-header"><span class="icard-title">Referral for Psychological Testing</span></div>
      <CaseReferralView :data="data" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { caseReferralAPI } from '../../api/index';
import CaseReferralView from '../../components/CaseReferralView.vue';

const route = useRoute();
const router = useRouter();

// Set when opened from a referral's SIF ("View Case Referral"), so Back goes
// to that same SIF instead of the Case Referrals list.
const returnTo = computed(() => (typeof route.query.return_to === 'string' && route.query.return_to.startsWith('/') ? route.query.return_to : ''));

function goBack() {
  if (returnTo.value) {
    // back() undoes the single push that got here; push only if there is no history (reloaded URL).
    if (window.history.state && window.history.state.back) router.back();
    else router.push(returnTo.value);
  } else {
    router.push({ name: 'case-referrals' });
  }
}
const loading = ref(true);
const error = ref('');
const data = ref({});

onMounted(async () => {
  try {
    const res = await caseReferralAPI.show(route.params.id);
    data.value = res.data;
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load this case referral.';
  } finally {
    loading.value = false;
  }
});
</script>