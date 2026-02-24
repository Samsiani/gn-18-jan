<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.settings.title') }}</h1>
      <p class="page-header__subtitle">{{ t('page.settings.subtitle') }}</p>
    </div>
    <div class="page-header__actions">
      <button v-if="activeTab !== 'translations'" class="btn btn--primary" @click="saveSettings"><span v-html="icon('save', 16)"></span> {{ t('btn.saveChanges') }}</button>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <AppTabs :tabs="tabs" v-model="activeTab" />

      <div class="settings-form" style="margin-top:var(--space-6)">
        <!-- Company Tab -->
        <div v-show="activeTab === 'company'">
          <div class="form-grid">
            <div class="form-group"><label class="form-label">{{ t('form.companyName') }}</label><input class="form-input" v-model="form.name"></div>
            <div class="form-group"><label class="form-label">{{ t('form.companyNameKa') }}</label><input class="form-input" v-model="form.nameKa"></div>
            <div class="form-group"><label class="form-label">{{ t('form.taxId') }}</label><input class="form-input" v-model="form.taxId"></div>
            <div class="form-group"><label class="form-label">{{ t('form.address') }}</label><input class="form-input" v-model="form.address"></div>
            <div class="form-group"><label class="form-label">{{ t('form.phone') }}</label><input class="form-input" v-model="form.phone"></div>
            <div class="form-group"><label class="form-label">{{ t('form.email') }}</label><input class="form-input" v-model="form.email"></div>
            <div class="form-group"><label class="form-label">{{ t('form.website') }}</label><input class="form-input" v-model="form.website"></div>
          </div>
        </div>

        <!-- Bank Tab -->
        <div v-show="activeTab === 'bank'">
          <div class="form-grid">
            <div class="form-group"><label class="form-label">{{ t('form.bankName1') }}</label><input class="form-input" v-model="form.bankName1"></div>
            <div class="form-group"><label class="form-label">{{ t('form.iban1') }}</label><input class="form-input" v-model="form.iban1"></div>
            <div class="form-group"><label class="form-label">{{ t('form.bankName2') }}</label><input class="form-input" v-model="form.bankName2"></div>
            <div class="form-group"><label class="form-label">{{ t('form.iban2') }}</label><input class="form-input" v-model="form.iban2"></div>
          </div>
        </div>

        <!-- Director Tab -->
        <div v-show="activeTab === 'director'">
          <div class="form-grid">
            <div class="form-group"><label class="form-label">{{ t('form.directorName') }}</label><input class="form-input" v-model="form.directorName"></div>
            <div class="form-group">
              <label class="form-label">{{ t('form.directorSignature') }}</label>
              <div class="upload-placeholder" @click="simulateUpload('signature')">
                <span v-html="icon('upload', 24)"></span>
                <span>{{ t('form.uploadSignature') }}</span>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">{{ t('form.companyLogo') }}</label>
              <div class="upload-placeholder" @click="simulateUpload('logo')">
                <span v-html="icon('upload', 24)"></span>
                <span>{{ t('form.uploadLogo') }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Invoice Config Tab -->
        <div v-show="activeTab === 'config'">
          <div class="form-grid">
            <div class="form-group"><label class="form-label">{{ t('form.invoicePrefix') }}</label><input class="form-input" v-model="form.invoicePrefix"></div>
            <div class="form-group"><label class="form-label">{{ t('form.startingNumber') }}</label><input class="form-input" type="number" v-model.number="form.startingInvoiceNumber"></div>
            <div class="form-group" style="grid-column: 1/-1">
              <label class="form-label">{{ t('form.reservationDays', { days: form.reservationDays }) }}</label>
              <input type="range" min="1" max="90" v-model.number="form.reservationDays" style="width:100%">
            </div>
          </div>
        </div>

        <!-- Translations Tab -->
        <div v-show="activeTab === 'translations'">
          <div style="display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:var(--space-4); gap:var(--space-4); flex-wrap:wrap">
            <div>
              <h3 style="font-size:var(--text-md); font-weight:600; margin:0 0 2px 0">{{ t('translations.title') }}</h3>
              <p style="font-size:var(--text-sm); color:var(--color-text-secondary); margin:0">{{ t('translations.subtitle') }}</p>
            </div>
            <button class="btn btn--danger btn--sm" @click="confirmResetAll">
              <span v-html="icon('rotate-ccw', 14)"></span> {{ t('translations.resetAll') }}
            </button>
          </div>

          <div v-if="i18nStore.locale !== 'ka'" class="fictive-notice" style="margin-bottom:var(--space-4)">
            <span v-html="icon('info', 14)"></span> {{ t('translations.switchToGeo') }}
          </div>

          <div style="display:flex; align-items:center; gap:var(--space-3); margin-bottom:var(--space-4); flex-wrap:wrap">
            <input type="text" class="form-input form-input--search" v-model="transSearch" @input="onTransSearch" :placeholder="t('translations.searchPlaceholder')" style="flex:1; max-width:360px">
            <span style="font-size:var(--text-sm); color:var(--color-text-tertiary)">{{ t('translations.showing', { from: transFrom, to: transTo, total: filteredTranslations.length }) }}</span>
          </div>

          <div class="data-table-wrapper">
            <table class="data-table trans-table">
              <thead>
                <tr>
                  <th style="width:220px">{{ t('translations.colKey') }}</th>
                  <th>{{ t('translations.colEnglish') }}</th>
                  <th>{{ t('translations.colGeorgian') }}</th>
                  <th style="width:44px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in transPaginatedItems" :key="row.key">
                  <td>
                    <span style="font-family:monospace; font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ row.key }}</span>
                    <span v-if="row.isCustom" class="trans-custom-dot">●</span>
                  </td>
                  <td style="font-size:var(--text-sm); color:var(--color-text-secondary)">{{ row.en }}</td>
                  <td>
                    <input type="text" class="form-input form-input--sm trans-input"
                      :value="row.ka"
                      @blur="onTransBlur(row.key, $event.target.value)"
                      @keydown.enter.prevent="$event.target.blur()">
                  </td>
                  <td class="text-center">
                    <button v-if="row.isCustom" class="btn btn--ghost btn--icon btn--sm" @click="resetTranslation(row.key)" data-tooltip="Reset">
                      <span v-html="icon('x', 12)"></span>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <AppPagination :currentPage="transCurrentPage" :totalPages="transTotalPages" :total="filteredTranslations.length" @page-change="transCurrentPage = $event" />
        </div>

        <!-- Login Page Tab -->
        <div v-show="activeTab === 'login'">
          <div style="max-width:560px">
            <div class="form-group">
              <label class="form-label">{{ t('form.loginFooterNote') }}</label>
              <p style="font-size:var(--text-sm); color:var(--color-text-secondary); margin:0 0 var(--space-2) 0">{{ t('form.loginFooterNoteHint') }}</p>
              <textarea class="form-textarea" v-model="form.loginFooterNote" rows="3" :placeholder="t('page.login.footer')"></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <ConfirmDialog v-model:visible="confirmVisible" :title="confirmTitle" :message="confirmMessage" :danger="true" @confirm="confirmCallback" />
</div>
</template>
<script setup>
import { ref, computed, watch } from 'vue'
import { useMainStore } from '@/stores/main'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/composables/useI18n'
import { useI18nStore } from '@/stores/i18n'
import { icon } from '@/composables/useIcons'
import { debounce } from '@/composables/useFormatters'
import { usePagination } from '@/composables/usePagination'
import AppTabs from '@/components/ui/AppTabs.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const mainStore = useMainStore()
const { showToast } = useToast()
const { t } = useI18n()
const i18nStore = useI18nStore()
const activeTab = ref('company')

const tabs = computed(() => [
  { id: 'company', label: t('tab.company') },
  { id: 'bank', label: t('tab.bank') },
  { id: 'director', label: t('tab.director') },
  { id: 'config', label: t('tab.config') },
  { id: 'translations', label: t('tab.translations') },
  { id: 'login', label: t('tab.loginPage') }
])

const form = ref({})

// Translation Manager
const { currentPage: transCurrentPage, totalPages: transTotalPages, paginate: transPaginate, resetPage: transResetPage } = usePagination(50)
const transSearch = ref('')

// Confirm dialog
const confirmVisible = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
let confirmCallback = () => {}

const filteredTranslations = computed(() => {
  const list = i18nStore.allTranslations
  if (!transSearch.value) return list
  const q = transSearch.value.toLowerCase()
  return list.filter(row => row.key.toLowerCase().includes(q) || row.en.toLowerCase().includes(q))
})

const transPaginatedItems = computed(() => transPaginate(filteredTranslations.value))

const transFrom = computed(() => {
  if (filteredTranslations.value.length === 0) return 0
  return (transCurrentPage.value - 1) * 50 + 1
})
const transTo = computed(() => Math.min(transCurrentPage.value * 50, filteredTranslations.value.length))

const debouncedTransSearch = debounce(() => transResetPage(), 300)
function onTransSearch() { debouncedTransSearch() }

function onTransBlur(key, value) {
  i18nStore.updateTranslation(key, value)
}

function resetTranslation(key) {
  i18nStore.resetTranslation(key)
}

function confirmResetAll() {
  confirmTitle.value = t('translations.resetAllTitle')
  confirmMessage.value = t('translations.confirmReset')
  confirmCallback = () => { i18nStore.resetAll() }
  confirmVisible.value = true
}

// Re-populate form whenever company data arrives (fixes Vue 3 child-before-parent mount order)
watch(() => mainStore.company, (c) => {
  form.value = { ...c }
}, { immediate: true })

async function saveSettings() {
  try {
    await mainStore.saveCompany({ ...form.value })
    showToast('success', t('msg.settingsSaved'), t('msg.settingsSavedMsg'))
  } catch (e) {
    showToast('error', t('msg.error'), e.message)
  }
}

function simulateUpload(type) {
  showToast('info', 'Upload simulation', `${type} upload would open file picker`)
}
</script>
