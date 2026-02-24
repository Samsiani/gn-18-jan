<template>
  <!-- Login page — bare router-view, no sidebar/topbar -->
  <router-view v-if="!authStore.isLoggedIn" />

  <!-- Full app layout -->
  <div v-else :class="['app', { 'sidebar-collapsed': mainStore.sidebarCollapsed }]">
    <AppSidebar />
    <SidebarOverlay />
    <div class="app__main">
      <AppTopbar />
      <main :class="['app__content', { 'locale-fading': localeFading }]">
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" :key="$route.fullPath" />
          </transition>
        </router-view>
      </main>
      <CartBar />
      <CartFAB />
    </div>
    <AppToast />
  </div>
</template>

<script setup>
import { ref, provide, nextTick, onMounted } from 'vue'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useI18nStore } from '@/stores/i18n'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTopbar from '@/components/layout/AppTopbar.vue'
import SidebarOverlay from '@/components/layout/SidebarOverlay.vue'
import CartBar from '@/components/ui/CartBar.vue'
import CartFAB from '@/components/ui/CartFAB.vue'
import AppToast from '@/components/ui/AppToast.vue'

const mainStore = useMainStore()
const authStore = useAuthStore()
const i18nStore = useI18nStore()

const localeFading = ref(false)

provide('switchLocale', async (loc) => {
  if (loc === i18nStore.locale) return
  localeFading.value = true
  await new Promise(r => setTimeout(r, 220))
  i18nStore.setLocale(loc)
  await nextTick()
  localeFading.value = false
})

onMounted(async () => {
  await mainStore.init()
  authStore.init(mainStore.users)
})
</script>

<style>
/* Page leave: fast fade + slight drift up */
.fade-leave-active {
  transition: opacity 0.16s cubic-bezier(0.4, 0, 1, 1),
              transform 0.16s cubic-bezier(0.4, 0, 1, 1);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}

/* Page enter: slower, decelerating rise from below */
.fade-enter-active {
  transition: opacity 0.28s cubic-bezier(0.0, 0, 0.2, 1),
              transform 0.28s cubic-bezier(0.0, 0, 0.2, 1);
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(14px);
}
</style>
