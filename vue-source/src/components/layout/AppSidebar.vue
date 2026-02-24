<template>
  <aside class="sidebar" id="sidebar">
    <div class="sidebar__header">
      <router-link :to="logoLink" class="sidebar__logo">
        <div class="sidebar__logo-icon">GN</div>
        <span class="sidebar__logo-text">GN Invoice</span>
      </router-link>
    </div>
    <nav class="sidebar__nav">
      <!-- Admin Nav -->
      <template v-if="authStore.isAdmin">
        <div class="sidebar__section">
          <div class="sidebar__section-label">{{ t('nav.sectionMain') }}</div>
          <router-link to="/dashboard" class="sidebar__item" :class="{ active: isActive('dashboard') }" data-page="dashboard">
            <span class="sidebar__item-icon" v-html="icon('layout-dashboard')"></span>
            <span class="sidebar__item-label">{{ t('nav.dashboard') }}</span>
          </router-link>
          <router-link to="/invoices" class="sidebar__item" :class="{ active: isActive('invoices') }" data-page="invoices">
            <span class="sidebar__item-icon" v-html="icon('file-text')"></span>
            <span class="sidebar__item-label">{{ t('nav.invoices') }}</span>
          </router-link>
          <router-link to="/invoices/new" class="sidebar__item" :class="{ active: isActive('invoice-new') }" data-page="invoices/new">
            <span class="sidebar__item-icon" v-html="icon('file-plus')"></span>
            <span class="sidebar__item-label">{{ t('nav.newInvoice') }}</span>
          </router-link>
        </div>
        <div class="sidebar__section">
          <div class="sidebar__section-label">{{ t('nav.sectionManagement') }}</div>
          <router-link to="/accountant" class="sidebar__item" :class="{ active: isActive('accountant') }" data-page="accountant">
            <span class="sidebar__item-icon" v-html="icon('calculator')"></span>
            <span class="sidebar__item-label">{{ t('nav.accountant') }}</span>
          </router-link>
          <router-link v-if="authStore.isAdmin" to="/users" class="sidebar__item" :class="{ active: isActive('users') }" data-page="users">
            <span class="sidebar__item-icon" v-html="icon('users')"></span>
            <span class="sidebar__item-label">{{ t('nav.users') }}</span>
          </router-link>
          <router-link to="/statistics" class="sidebar__item" :class="{ active: isActive('statistics') }" data-page="statistics">
            <span class="sidebar__item-icon" v-html="icon('bar-chart-3')"></span>
            <span class="sidebar__item-label">{{ t('nav.statistics') }}</span>
          </router-link>
          <router-link to="/stock" class="sidebar__item" :class="{ active: isActive('stock') }" data-page="stock">
            <span class="sidebar__item-icon" v-html="icon('package')"></span>
            <span class="sidebar__item-label">{{ t('nav.stock') }}</span>
            <span v-if="cartStore.count > 0" class="sidebar__item-badge sidebar__item-badge--cart">{{ cartStore.count }}</span>
            <span v-else-if="pendingReservations > 0" class="sidebar__item-badge">{{ pendingReservations }}</span>
          </router-link>
        </div>
        <div class="sidebar__section">
          <div class="sidebar__section-label">{{ t('nav.sectionOther') }}</div>
          <router-link to="/customers" class="sidebar__item" :class="{ active: isActive('customers') }" data-page="customers">
            <span class="sidebar__item-icon" v-html="icon('users')"></span>
            <span class="sidebar__item-label">{{ t('nav.customers') }}</span>
          </router-link>
          <router-link to="/settings" class="sidebar__item" :class="{ active: isActive('settings') }" data-page="settings">
            <span class="sidebar__item-icon" v-html="icon('settings')"></span>
            <span class="sidebar__item-label">{{ t('nav.settings') }}</span>
          </router-link>
        </div>
      </template>

      <!-- Accountant Nav -->
      <template v-else-if="authStore.isAccountantRole">
        <div class="sidebar__section">
          <div class="sidebar__section-label">{{ t('nav.sectionMain') }}</div>
          <router-link to="/accountant" class="sidebar__item" :class="{ active: isActive('accountant') }" data-page="accountant">
            <span class="sidebar__item-icon" v-html="icon('calculator')"></span>
            <span class="sidebar__item-label">{{ t('nav.accountantDashboard') }}</span>
          </router-link>
        </div>
      </template>

      <!-- Consultant Nav -->
      <template v-else>
        <div class="sidebar__section">
          <div class="sidebar__section-label">{{ t('nav.sectionMain') }}</div>
          <router-link to="/consultant-home" class="sidebar__item" :class="{ active: isActive('consultant-home') }" data-page="consultant-home">
            <span class="sidebar__item-icon" v-html="icon('layout-dashboard')"></span>
            <span class="sidebar__item-label">{{ t('nav.home') }}</span>
          </router-link>
          <router-link to="/invoices/new" class="sidebar__item" :class="{ active: isActive('invoice-new') }" data-page="invoices/new">
            <span class="sidebar__item-icon" v-html="icon('file-plus')"></span>
            <span class="sidebar__item-label">{{ t('nav.createInvoice') }}</span>
          </router-link>
          <router-link to="/invoices" class="sidebar__item" :class="{ active: isActive('invoices') }" data-page="invoices">
            <span class="sidebar__item-icon" v-html="icon('file-text')"></span>
            <span class="sidebar__item-label">{{ t('nav.myInvoices') }}</span>
            <span v-if="myReservations > 0" class="sidebar__item-badge">{{ myReservations }}</span>
          </router-link>
        </div>
        <div class="sidebar__section">
          <div class="sidebar__section-label">{{ t('nav.sectionOther') }}</div>
          <router-link to="/stock" class="sidebar__item" :class="{ active: isActive('stock') }" data-page="stock">
            <span class="sidebar__item-icon" v-html="icon('package')"></span>
            <span class="sidebar__item-label">{{ t('nav.stockView') }}</span>
            <span v-if="cartStore.count > 0" class="sidebar__item-badge sidebar__item-badge--cart">{{ cartStore.count }}</span>
          </router-link>
        </div>
      </template>
    </nav>
    <div class="sidebar__footer">
      <div class="sidebar__user-bar">
        <div class="sidebar__user">
          <div class="sidebar__user-avatar">{{ authStore.currentUser?.avatar || 'GN' }}</div>
          <div class="sidebar__user-info">
            <div class="sidebar__user-name">{{ authStore.currentUser?.nameEn || authStore.currentUser?.name || 'User' }}</div>
            <div class="sidebar__user-role">{{ authStore.currentUser?.role || 'admin' }}</div>
          </div>
        </div>
        <button class="sidebar__logout-btn" @click="handleLogout" :title="t('nav.signOut') || 'Sign out'">
          <span v-html="icon('log-out', 16)"></span>
        </button>
      </div>
    </div>
  </aside>
</template>
<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'

const route = useRoute()
const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const cartStore = useCartStore()
const { t } = useI18n()
const pendingReservations = computed(() => mainStore.pendingReservations.length)
const myReservations = computed(() => authStore.getMyReservations(mainStore.invoices).length)

const logoLink = computed(() => {
  if (authStore.isAdmin) return '/dashboard'
  if (authStore.isAccountantRole) return '/accountant'
  return '/consultant-home'
})

function isActive(name) {
  if (name === 'invoices') return route.name === 'invoices' || (route.name && route.name.toString().startsWith('invoice'))
  return route.name === name
}

function handleLogout() {
  authStore.logout()
  router.push('/login')
}
</script>
