<template>
  <svg v-if="data && data.length >= 2" class="sparkline" :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" fill="none" xmlns="http://www.w3.org/2000/svg">
    <polyline :points="points" :stroke="color" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    <circle :cx="lastX" :cy="lastY" r="2" :fill="color"/>
  </svg>
</template>
<script setup>
import { computed } from 'vue'
const props = defineProps({ data: Array, color: { type: String, default: '#4f46e5' }, width: { type: Number, default: 80 }, height: { type: Number, default: 28 } })
const padding = 2
const points = computed(() => {
  if (!props.data || props.data.length < 2) return ''
  const max = Math.max(...props.data); const min = Math.min(...props.data); const range = max - min || 1
  const w = props.width - padding * 2; const h = props.height - padding * 2
  return props.data.map((val, i) => {
    const x = padding + (i / (props.data.length - 1)) * w
    const y = padding + h - ((val - min) / range) * h
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
})
const lastX = computed(() => (padding + props.width - padding * 2).toFixed(1))
const lastY = computed(() => {
  if (!props.data || props.data.length < 2) return '0'
  const max = Math.max(...props.data); const min = Math.min(...props.data); const range = max - min || 1
  const h = props.height - padding * 2
  return (padding + h - ((props.data[props.data.length - 1] - min) / range) * h).toFixed(1)
})
</script>
