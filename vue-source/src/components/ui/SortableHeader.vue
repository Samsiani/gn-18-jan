<template>
  <th :class="['sortable', { sorted: isActive }, extraClass]" @click="$emit('sort', field)" style="cursor:pointer">
    <span style="display:inline-flex; align-items:center; gap:4px; white-space:nowrap">
      {{ label }}
      <span class="sort-icon" v-html="dirIcon"></span>
    </span>
  </th>
</template>
<script setup>
import { computed } from 'vue'
import { icon } from '@/composables/useIcons'
const props = defineProps({ label: String, field: String, sortField: String, sortDir: String, extraClass: { type: String, default: '' } })
defineEmits(['sort'])
const isActive = computed(() => props.sortField === props.field)
const dirIcon = computed(() => isActive.value ? (props.sortDir === 'asc' ? icon('chevron-up', 12) : icon('chevron-down', 12)) : icon('arrow-up-down', 12))
</script>
