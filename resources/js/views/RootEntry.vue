<template>
  <!-- The main address: visitors who are not signed in see the landing page
       (the address stays "/"); everyone else gets the normal app layout. -->
  <Landing v-if="showLanding" />
  <MainLayout v-else />
</template>

<script setup>
import { computed, defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';
import Landing from './Landing.vue';
// Loaded only once someone is signed in, so a landing visitor never downloads
// the whole authenticated app shell.
const MainLayout = defineAsyncComponent(() => import('../layouts/MainLayout.vue'));

const route = useRoute();
const showLanding = computed(() => {
  route.fullPath; // re-check whenever the route changes
  return route.name === 'dashboard' && !localStorage.getItem('token');
});
</script>