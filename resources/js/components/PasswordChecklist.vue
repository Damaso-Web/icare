<!--
  The password rules, ticked off one by one as the new password is typed.
  Same rules the server enforces.
-->
<template>
  <ul class="pc">
    <li v-for="r in rules" :key="r.label" :class="{ ok: r.ok, bad: password && !r.ok }">
      <span class="pc-mark">{{ r.ok ? '✓' : '•' }}</span>{{ r.label }}
    </li>
  </ul>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({ password: { type: String, default: '' } });

const rules = computed(() => {
  const pw = props.password || '';
  return [
    { label: '8 to 64 characters',              ok: pw.length >= 8 && pw.length <= 64 },
    { label: 'An uppercase letter (A-Z)',       ok: /[A-Z]/.test(pw) },
    { label: 'A lowercase letter (a-z)',        ok: /[a-z]/.test(pw) },
    { label: 'A number (0-9)',                  ok: /[0-9]/.test(pw) },
    { label: 'A symbol (e.g. ! @ # $)',         ok: /[^A-Za-z0-9]/.test(pw) },
  ];
});
</script>

<style scoped>
.pc { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 3px 14px; font-size: 11.5px; color: var(--stone); }
.pc li { display: flex; align-items: center; gap: 6px; }
.pc-mark { width: 12px; text-align: center; font-weight: 700; }
.pc li.ok { color: var(--moss); }
.pc li.bad { color: var(--red); }
@media (max-width: 560px) { .pc { grid-template-columns: 1fr; } }
</style>
