<template>
  <AppModal :visible="visible" :title="title" @update:visible="$emit('update:visible', $event)">
    <p style="margin:0; color: var(--color-text-secondary)">{{ message }}</p>
    <template #footer>
      <button class="btn btn--secondary" @click="$emit('update:visible', false)">{{ t('btn.cancel') }}</button>
      <button :class="['btn', danger ? 'btn--danger' : 'btn--primary']" @click="confirm">{{ t('btn.confirm') }}</button>
    </template>
  </AppModal>
</template>
<script setup>
import AppModal from './AppModal.vue'
import { useI18n } from '@/composables/useI18n'

defineProps({
  visible: Boolean,
  title: String,
  message: String,
  danger: Boolean
})

const emit = defineEmits(['update:visible', 'confirm'])
const { t } = useI18n()

function confirm() {
  emit('confirm')
  emit('update:visible', false)
}
</script>
