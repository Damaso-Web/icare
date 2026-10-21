<template>
  <!-- The main address: visitors who are not signed in see the landing page
       (the address stays "/"); everyone else gets the normal app layout. -->
  <Landing v-if="showLanding" />
  <MainLayout v-else />
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import MainLayout from '../layouts/MainLayout.vue';
import Landing from './Landing.vue';

const route = useRoute();
const showLanding = computed(() => {
  route.fullPath; // re-check whenever the route changes
  return route.name === 'dashboard' && !localStorage.getItem('token');
});
</script>