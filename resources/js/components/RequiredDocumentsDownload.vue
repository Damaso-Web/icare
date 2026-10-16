<script setup>
// Download button shown wherever an appointment lists its "Required Documents".
import { REQUIRED_DOCUMENTS_FILE, REQUIRED_DOCUMENTS_FILENAME } from '../constants/requiredDocuments';
</script>

<template>
  <a :href="REQUIRED_DOCUMENTS_FILE" :download="REQUIRED_DOCUMENTS_FILENAME" class="ibtn ibtn-o ibtn-sm" style="text-decoration:none;display:inline-flex">
    Download Letter of Explanation Template
  </a>
</template>