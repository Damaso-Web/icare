<!--
  The masthead of the sign-in pages: the OSS logo, the iCARE mark and the BSU
  seal in a row, then the system's name. The two logos are public/oss-logo.jpg
  and public/bsu-logo.jpg; if a file is missing it is simply left out.
-->
<template>
  <div class="ab">
    <div class="ab-row">
      <img v-show="ossOk" class="logo-oss ab-seal" :src="'/oss-logo.jpg'" alt="Office of Student Services" @error="ossOk = false" />
      <div class="ab-mark"><img :src="'/icare-logo.png'" alt="iCARE" /></div>
      <img v-show="bsuOk" class="logo-bsu ab-seal" :src="'/bsu-logo.jpg'" alt="Benguet State University" @error="bsuOk = false" />
    </div>
    <div class="ab-name">iCARE</div>
    <div class="ab-org">Benguet State University</div>
    <div class="ab-office">Office of Student Services</div>
    <div v-if="subtitle" class="ab-sub">{{ subtitle }}</div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ subtitle: { type: String, default: '' } });

const bsuOk = ref(true);
const ossOk = ref(true);
</script>

<style scoped>
.ab { text-align: center; margin-bottom: 24px; }
.ab-row { display: flex; align-items: center; justify-content: center; gap: 18px; margin-bottom: 12px; }
/* The same white edge and soft gold ring as the iCARE mark. */
.ab-seal { height: 80px; padding: 4px; box-shadow: 0 6px 18px rgba(14, 45, 30, .14), 0 0 0 3px rgba(232, 180, 34, .28); }
.ab-seal.logo-oss { border-radius: 12px; }
.ab-mark { background: #fff; border-radius: 18px; padding: 9px; border: 1px solid rgba(14, 45, 30, .07); box-shadow: 0 6px 18px rgba(14, 45, 30, .14), 0 0 0 3px rgba(232, 180, 34, .28); }
.ab-mark img { width: 62px; height: 62px; object-fit: contain; display: block; }
.ab-name { font-family: var(--serif); font-style: italic; font-size: 30px; line-height: 1; color: var(--forest); }
.ab-org { font-size: 11px; font-weight: 700; letter-spacing: 1.1px; text-transform: uppercase; color: var(--moss); margin-top: 8px; }
.ab-office { font-size: 12px; color: var(--stone); margin-top: 2px; }
.ab-sub { display: inline-block; margin-top: 9px; padding: 3px 11px; border-radius: 20px; font-size: 11.5px; font-weight: 600; color: var(--gold-dk); background: var(--gold-lt); }
</style>
