<template>
  <div
    id="toast"
    :class="{ show: visible }"
    :style="{ background: type === 'error' ? 'var(--red)' : type === 'warning' ? 'var(--amber)' : 'var(--moss)' }"
  >
    {{ message }}
  </div>
</template>

<script setup>
import { ref } from 'vue';

const visible = ref(false);
const message = ref('');
const type    = ref('success');

let timer = null;

function show(msg, t = 'success', duration = 3000) {
  message.value = msg;
  type.value    = t;
  visible.value = true;
  clearTimeout(timer);
  timer = setTimeout(() => visible.value = false, duration);
}

defineExpose({ show });
</script>