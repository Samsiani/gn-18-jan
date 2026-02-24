<template>
  <div class="empty-state">
    <div class="empty-state__icon" v-html="icon('alert-circle', 64)"></div>
    <h3 class="empty-state__title">Page Not Found</h3>
    <p class="empty-state__message">The page you're looking for doesn't exist.</p>
    <router-link to="/dashboard" class="btn btn--primary">Go to Dashboard</router-link>
  </div>
</template>

<script setup>
import { icon } from '@/composables/useIcons'
</script>
