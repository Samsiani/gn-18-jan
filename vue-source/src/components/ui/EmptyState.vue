<template>
  <div class="empty-state">
    <div class="empty-state__icon" v-html="icon('inbox', 64)"></div>
    <h3 class="empty-state__title">{{ title }}</h3>
    <p class="empty-state__message">{{ message }}</p>
    <slot></slot>
  </div>
</template>
<script setup>
import { icon } from '@/composables/useIcons'

defineProps({
  title: String,
  message: String
})
</script>
