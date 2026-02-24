<template>
  <div class="tabs">
    <button v-for="tab in tabs" :key="tab.id"
      :class="['tab', { active: modelValue === tab.id }]"
      @click="$emit('update:modelValue', tab.id)">{{ tab.label }}</button>
  </div>
</template>
<script setup>
defineProps({
  tabs: Array,
  modelValue: String
})

defineEmits(['update:modelValue'])
</script>
