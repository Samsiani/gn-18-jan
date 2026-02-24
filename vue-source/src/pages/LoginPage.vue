<template>
  <div class="login-page">
    <!-- Left Panel -->
    <div class="login-page__brand">
      <div class="login-page__brand-inner">
        <div class="login-page__logo">
          <div class="login-page__logo-icon">GN</div>
          <span class="login-page__logo-text">GN Industrial</span>
        </div>
        <h1 class="login-page__brand-title">{{ t('page.login.brandTitle') }}</h1>
        <p class="login-page__brand-subtitle">{{ t('page.login.brandSubtitle') }}</p>
        <div class="login-page__brand-features">
          <div class="login-page__brand-feature">
            <span class="login-page__brand-feature-dot"></span>
            {{ t('page.login.feature1') }}
          </div>
          <div class="login-page__brand-feature">
            <span class="login-page__brand-feature-dot"></span>
            {{ t('page.login.feature2') }}
          </div>
          <div class="login-page__brand-feature">
            <span class="login-page__brand-feature-dot"></span>
            {{ t('page.login.feature3') }}
          </div>
          <div class="login-page__brand-feature">
            <span class="login-page__brand-feature-dot"></span>
            {{ t('page.login.feature4') }}
          </div>
        </div>
      </div>
    </div>

    <!-- Right Panel — Login Form -->
    <div class="login-page__form-panel">
      <div class="login-page__form-inner">
        <div class="login-page__form-header">
          <h2 class="login-page__form-title">{{ t('page.login.welcomeBack') }}</h2>
          <p class="login-page__form-subtitle">{{ t('page.login.signInSubtitle') }}</p>
        </div>

        <form class="login-page__form" @submit.prevent="handleLogin">
          <!-- Username -->
          <div class="login-page__field">
            <label class="login-page__label" for="login-username">{{ t('form.username') }}</label>
            <div class="login-page__input-wrap">
              <span class="login-page__input-icon" v-html="icon('users', 16)"></span>
              <input
                id="login-username"
                v-model="username"
                class="login-page__input"
                type="text"
                :placeholder="t('page.login.usernamePlaceholder')"
                autocomplete="username"
                :disabled="loading"
                @input="clearError"
              />
            </div>
          </div>

          <!-- Password -->
          <div class="login-page__field">
            <label class="login-page__label" for="login-password">{{ t('form.password') }}</label>
            <div class="login-page__input-wrap">
              <span class="login-page__input-icon" v-html="icon('lock', 16)"></span>
              <input
                id="login-password"
                v-model="password"
                class="login-page__input"
                :type="showPassword ? 'text' : 'password'"
                :placeholder="t('page.login.passwordPlaceholder')"
                autocomplete="current-password"
                :disabled="loading"
                @input="clearError"
              />
              <button
                type="button"
                class="login-page__toggle-pw"
                @click="showPassword = !showPassword"
                :title="showPassword ? t('page.login.hidePassword') : t('page.login.showPassword')"
                tabindex="-1"
              >
                <span v-html="icon(showPassword ? 'eye-off' : 'eye', 16)"></span>
              </button>
            </div>
          </div>

          <!-- Error -->
          <div v-if="errorMsg" class="login-page__error">
            <span v-html="icon('alert-circle', 14)"></span>
            {{ errorMsg }}
          </div>

          <!-- Submit -->
          <button
            type="submit"
            class="login-page__submit"
            :disabled="loading || !username.trim() || !password"
          >
            <span v-if="loading" class="login-page__spinner"></span>
            <span v-else v-html="icon('log-out', 16)"></span>
            {{ loading ? t('page.login.signingIn') : t('page.login.signIn') }}
          </button>
        </form>

        <!-- Demo Accounts -->
        <div class="login-page__demo">
          <div class="login-page__demo-label">{{ t('page.login.demoAccounts') }} <code>gn2024</code></div>
          <div class="login-page__demo-accounts">
            <button
              v-for="user in demoUsers"
              :key="user.id"
              type="button"
              class="login-page__demo-account"
              @click="fillDemo(user)"
              :title="`Login as ${user.nameEn}`"
            >
              <span class="login-page__demo-avatar">{{ user.avatar }}</span>
              <span class="login-page__demo-name">{{ user.nameEn.split(' ')[0] }}</span>
              <span class="login-page__demo-role">{{ user.role }}</span>
            </button>
          </div>
        </div>

        <div class="login-page__footer-note">
          {{ mainStore.company.loginFooterNote || t('page.login.footer') }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useMainStore } from '@/stores/main'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'

const router = useRouter()
const authStore = useAuthStore()
const mainStore = useMainStore()
const { t } = useI18n()

const username = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const errorMsg = ref('')

const demoUsers = mainStore.users

function clearError() { errorMsg.value = '' }

function fillDemo(user) {
  username.value = user.nameEn.split(' ')[0]
  password.value = 'gn2024'
  errorMsg.value = ''
}

async function handleLogin() {
  if (!username.value.trim() || !password.value) return
  loading.value = true
  errorMsg.value = ''

  // Tiny artificial delay for UX feedback
  await new Promise(r => setTimeout(r, 400))

  const result = authStore.login(username.value, password.value, mainStore.users)
  loading.value = false

  if (!result.success) {
    errorMsg.value = result.error
    return
  }

  // Wait for App.vue to swap from bare router-view → full layout before navigating
  await nextTick()

  // Navigate to role-appropriate home
  if (authStore.isAdmin) router.push('/dashboard')
  else if (authStore.isAccountantRole) router.push('/accountant')
  else router.push('/consultant-home')
}
</script>
