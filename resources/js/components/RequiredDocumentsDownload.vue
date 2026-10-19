<script setup>
// Download button shown with an appointment's "Required Documents". The Letter of
// Explanation template belongs to the Class Attendance service only, so the button
// appears only when the listed documents actually ask for that letter.
import { computed } from 'vue';
import { REQUIRED_DOCUMENTS_FILE, REQUIRED_DOCUMENTS_FILENAME } from '../constants/requiredDocuments';

const props = defineProps({ documents: { type: String, default: '' } });
const show = computed(() => /letter of explanation/i.test(props.documents || ''));
</script>

<template>
  <a v-if="show" :href="REQUIRED_DOCUMENTS_FILE" :download="REQUIRED_DOCUMENTS_FILENAME" class="ibtn ibtn-o ibtn-sm" style="text-decoration:none;display:inline-flex">
    Download Letter of Explanation Template
  </a>
</template>