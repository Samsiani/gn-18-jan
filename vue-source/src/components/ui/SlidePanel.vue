<template>
  <Teleport to="body">
    <div :class="['panel-overlay', { visible: visible }]" @click="close"></div>
    <div :class="['slide-panel', { open: visible }, size ? `slide-panel--${size}` : '']">
      <div class="slide-panel__header">
        <h3 style="margin:0; font-size:var(--text-lg)"><slot name="header">{{ title }}</slot></h3>
        <button class="btn btn--ghost btn--icon" @click="close" v-html="icon('x', 18)"></button>
      </div>
      <div class="slide-panel__body">
        <slot></slot>
      </div>
    </div>
  </Teleport>
</template>
<script setup>
import { icon } from '@/composables/useIcons'
defineProps({ visible: Boolean, title: { type: String, default: '' }, size: { type: String, default: '' } })
const emit = defineEmits(['update:visible', 'close'])
function close() {
  emit('update:visible', false)
  emit('close')
}
</script>
