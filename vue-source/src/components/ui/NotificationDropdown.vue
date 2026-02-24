<template>
  <div v-if="visible" class="notification-dropdown open">
    <div class="notification-dropdown__header">
      <span class="font-semibold" style="font-size:var(--text-sm)">{{ t('notif.header') }}</span>
      <button class="btn btn--ghost btn--sm" @click="markAllRead">{{ t('notif.markAllRead') }}</button>
    </div>
    <div class="notification-dropdown__items">
      <div v-if="notifStore.items.length === 0" style="padding:var(--space-6); text-align:center; color:var(--color-text-tertiary); font-size:var(--text-sm)">
        {{ t('notif.empty') }}
      </div>
      <div v-else v-for="n in notifStore.items" :key="n.id"
        :class="['notification-dropdown__item', { unread: !n.read }]"
        @click="clickNotification(n)">
        <div :class="['notification-dropdown__icon', `notification-dropdown__icon--${n.type || 'system'}`]" v-html="icon(n.icon, 18)"></div>
        <div class="notification-dropdown__content">
          <div class="notification-dropdown__title">{{ n.titleKey ? t(n.titleKey) : n.title }}</div>
          <div class="notification-dropdown__message">{{ resolveMessage(n) }}</div>
          <div class="notification-dropdown__time">{{ getTimeAgo(n.time, t) }}</div>
        </div>
        <div v-if="!n.read" class="notification-dropdown__unread-dot"></div>
      </div>
    </div>
  </div>
</template>
<script setup>
import { useRouter } from 'vue-router'
import { icon } from '@/composables/useIcons'
import { getTimeAgo } from '@/composables/useFormatters'
import { useNotificationStore } from '@/stores/notifications'
import { useI18n } from '@/composables/useI18n'

defineProps({ visible: Boolean })
const emit = defineEmits(['close'])

const router = useRouter()
const notifStore = useNotificationStore()
const { t } = useI18n()

function markAllRead() { notifStore.markAllRead() }

function resolveMessage(n) {
  if (!n.messageKey) return n.message || ''
  const params = { ...(n.messageParams || {}) }
  if (params.methodKey) { params.method = t(params.methodKey); delete params.methodKey }
  return t(n.messageKey, params)
}

function clickNotification(n) {
  notifStore.markRead(n.id)
  if (n.invoiceId) {
    router.push({ name: 'invoice-view', params: { id: n.invoiceId } })
    emit('close')
  }
}
</script>
