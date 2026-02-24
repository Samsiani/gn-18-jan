<template>
  <header class="topbar">
    <button class="topbar__hamburger" @click="toggleMobile" v-html="icon('menu')"></button>
    <div class="topbar__breadcrumb">
      <template v-for="(item, i) in breadcrumb" :key="i">
        <span :class="['topbar__breadcrumb-item', { current: i === breadcrumb.length - 1 }]">{{ item }}</span>
        <span v-if="i < breadcrumb.length - 1" class="topbar__breadcrumb-separator">/</span>
      </template>
    </div>
    <div class="topbar__spacer"></div>
    <GlobalSearch />
    <div class="topbar__actions">
      <button class="topbar__action-btn" @click="mainStore.toggleTheme()" :data-tooltip="mainStore.theme === 'light' ? t('topbar.darkMode') : t('topbar.lightMode')">
        <span v-html="mainStore.theme === 'light' ? icon('moon', 20) : icon('sun', 20)"></span>
      </button>
      <div class="topbar__lang-switch">
        <button :class="['topbar__lang-opt', { 'topbar__lang-opt--active': locale === 'en' }]"
                @click="switchLocale('en')">ENG</button>
        <button :class="['topbar__lang-opt', { 'topbar__lang-opt--active': locale === 'ka' }]"
                @click="switchLocale('ka')">GEO</button>
      </div>
      <button class="topbar__action-btn" @click.stop="toggleNotifications" :data-tooltip="t('topbar.notifications')" style="position:relative">
        <span v-html="icon('bell', 20)"></span>
        <span v-if="notifStore.unreadCount > 0" class="notification-badge">{{ notifStore.unreadCount }}</span>
      </button>
      <NotificationDropdown :visible="showNotifications" @close="showNotifications = false" />
    </div>
    <button class="topbar__collapse-btn" @click="mainStore.toggleSidebar()" :data-tooltip="mainStore.sidebarCollapsed ? t('topbar.expandSidebar') : t('topbar.collapseSidebar')">
      <span v-html="mainStore.sidebarCollapsed ? icon('panel-left-open', 20) : icon('panel-left-close', 20)"></span>
    </button>
  </header>
</template>
<script setup>
import { ref, computed, inject, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { useI18nStore } from '@/stores/i18n'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import NotificationDropdown from '@/components/ui/NotificationDropdown.vue'
import GlobalSearch from '@/components/ui/GlobalSearch.vue'

const route = useRoute()
const mainStore = useMainStore()
const authStore = useAuthStore()
const notifStore = useNotificationStore()
const i18nStore = useI18nStore()
const { t, locale } = useI18n()
const switchLocale = inject('switchLocale')
const showNotifications = ref(false)

const breadcrumbMap = computed(() => ({
  'dashboard':       [t('breadcrumb.gnInvoice'), t('breadcrumb.dashboard')],
  'consultant-home': [t('breadcrumb.gnInvoice'), t('breadcrumb.home')],
  'invoices':        () => [t('breadcrumb.gnInvoice'), authStore.isConsultant ? t('breadcrumb.myInvoices') : t('breadcrumb.invoices')],
  'invoice-new':     [t('breadcrumb.gnInvoice'), t('breadcrumb.invoices'), t('breadcrumb.newInvoice')],
  'invoice-view':    () => [t('breadcrumb.gnInvoice'), t('breadcrumb.invoices'), `Invoice #${route.params.id || ''}`],
  'invoice-edit':    () => [t('breadcrumb.gnInvoice'), t('breadcrumb.invoices'), `Edit #${route.params.id || ''}`],
  'accountant':      [t('breadcrumb.gnInvoice'), t('breadcrumb.accountantPortal')],
  'statistics':      [t('breadcrumb.gnInvoice'), t('breadcrumb.statistics')],
  'stock':           () => [t('breadcrumb.gnInvoice'), authStore.isConsultant ? t('breadcrumb.stockView') : t('breadcrumb.stockManagement')],
  'customers':       [t('breadcrumb.gnInvoice'), t('breadcrumb.customers')],
  'settings':        [t('breadcrumb.gnInvoice'), t('breadcrumb.settings')],
  'users':           [t('breadcrumb.gnInvoice'), t('breadcrumb.users')],
}))

const breadcrumb = computed(() => {
  const map = breadcrumbMap.value
  const entry = map[route.name] || [t('breadcrumb.gnInvoice')]
  return typeof entry === 'function' ? entry() : entry
})

function toggleMobile() {
  const sidebar = document.getElementById('sidebar')
  const overlay = document.getElementById('sidebar-overlay')
  if (sidebar) sidebar.classList.toggle('mobile-open')
  if (overlay) overlay.classList.toggle('visible')
}

function toggleNotifications() {
  showNotifications.value = !showNotifications.value
}

function closeNotificationsOnOutside(e) {
  if (showNotifications.value && !e.target.closest('#notification-bell') && !e.target.closest('.notification-dropdown')) {
    showNotifications.value = false
  }
}

onMounted(() => {
  notifStore.init()
  document.addEventListener('click', closeNotificationsOnOutside)
})
onUnmounted(() => document.removeEventListener('click', closeNotificationsOnOutside))
</script>
