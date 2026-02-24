<template>
  <span v-html="iconHtml" style="display:inline-flex;align-items:center"></span>
</template>
<script setup>
import { computed } from 'vue'
import { icon } from '@/composables/useIcons'

const props = defineProps({
  name: String,
  size: { type: Number, default: 20 }
})

const iconHtml = computed(() => icon(props.name, props.size))
</script>
