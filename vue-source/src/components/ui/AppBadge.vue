<template>
  <span :class="['badge', `badge--${color}`, { 'badge--dot': dot }]">{{ label }}</span>
</template>
<script setup>
defineProps({
  label: String,
  color: { type: String, default: 'neutral' },
  dot: Boolean
})
</script>
