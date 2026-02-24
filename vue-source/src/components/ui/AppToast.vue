<template>
  <Teleport to="body">
    <div class="toast-container">
      <div v-for="toast in toasts" :key="toast.id" :class="['toast', `toast--${toast.type}`]">
        <div class="toast__icon" v-html="toastIcon(toast.type)"></div>
        <div class="toast__content">
          <div class="toast__title">{{ toast.title }}</div>
          <div v-if="toast.message" class="toast__message">{{ toast.message }}</div>
        </div>
        <button class="toast__close" @click="removeToast(toast.id)" v-html="icon('x', 14)"></button>
      </div>
    </div>
  </Teleport>
</template>
<script setup>
import { icon } from '@/composables/useIcons'
import { useToast } from '@/composables/useToast'
const { toasts, removeToast } = useToast()
function toastIcon(type) {
  const map = { success: 'check-circle', error: 'alert-circle', warning: 'alert-triangle', info: 'info' }
  return icon(map[type] || 'info')
}
</script>
