<!-- Same help line on the student and personnel sign-in screens. -->
<template>
  <div class="lh">
    Having trouble logging in? Contact the Office of Student Services.
    <div class="lh-links">
      <template v-for="(l, i) in links" :key="l.href">
        <span v-if="i > 0"> · </span>
        <a :href="l.href" :target="l.external ? '_blank' : null" rel="noopener">{{ l.text }}</a>
      </template>
    </div>
  </div>
</template>

<script setup>
import { SUPPORT_EMAIL, SUPPORT_PHONE, SUPPORT_WEBSITE } from '../constants/supportContact';

const links = [
  SUPPORT_EMAIL && { href: 'mailto:' + SUPPORT_EMAIL, text: SUPPORT_EMAIL },
  SUPPORT_PHONE && { href: 'tel:' + SUPPORT_PHONE.replace(/[^+\d]/g, ''), text: SUPPORT_PHONE },
  SUPPORT_WEBSITE && { href: SUPPORT_WEBSITE, text: 'OSS website', external: true },
].filter(Boolean);
</script>

<style scoped>
.lh { text-align: center; margin-top: 16px; font-size: 12px; color: var(--stone); line-height: 1.6; }
.lh-links { margin-top: 2px; font-weight: 600; }
.lh-links a { color: var(--moss); text-decoration: underline; }
</style>