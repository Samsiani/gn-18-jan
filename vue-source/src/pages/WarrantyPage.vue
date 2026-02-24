<template>
<div>
  <div v-if="!invoice || !hasWarranty" class="empty-state">
    <h3>{{ t('page.warranty.notAvailable') }}</h3>
    <router-link :to="`/invoices/${id}`" class="btn btn--primary">{{ t('btn.backToInvoice') }}</router-link>
  </div>
  <template v-else>
    <div class="page-header no-print">
      <div style="display:flex; align-items:center; gap:var(--space-4)">
        <button class="btn btn--ghost btn--icon" @click="goBack" :title="t('btn.back')" v-html="icon('arrow-left')"></button>
        <div>
          <h1 class="page-header__title">{{ t('page.warranty.title') }}</h1>
          <p class="page-header__subtitle" style="margin:0">{{ invoice.number }}</p>
        </div>
      </div>
      <div class="page-header__actions">
        <button class="btn btn--primary" @click="printPage"><span v-html="icon('printer', 16)"></span> {{ t('btn.print') }}</button>
      </div>
    </div>

    <div class="card">
      <div class="card__body">
        <div class="invoice-view" style="padding:0">
          <div style="text-align:center; margin-bottom: var(--space-6)">
            <div class="invoice-view__company-name">{{ company.name }}</div>
            <h2 style="margin: var(--space-3) 0 var(--space-1); font-size: var(--text-xl)">{{ t('page.warranty.title') }}</h2>
            <div style="font-size: var(--text-sm); color: var(--color-text-secondary)">
              {{ t('common.invoice') }} {{ invoice.number }} &nbsp;|&nbsp; {{ t('common.date') }} {{ invoice.soldDate ? formatDate(invoice.soldDate) : '—' }}
            </div>
            <div v-if="customer" style="font-size: var(--text-sm); color: var(--color-text-secondary); margin-top: var(--space-1)">
              {{ t('common.buyer') }} {{ customer.name }}
            </div>
          </div>

          <div class="table-scroll-wrapper">
            <table class="invoice-view__items-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ t('col.product') }}</th>
                  <th>{{ t('col.brand') }}</th>
                  <th>{{ t('col.qty') }}</th>
                  <th>{{ t('col.warranty') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, i) in warrantyItems" :key="i">
                  <td>{{ i + 1 }}</td>
                  <td>
                    <strong>{{ getProduct(item.productId)?.name || t('common.unknown') }}</strong>
                    <div v-if="getProduct(item.productId)" style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ getProduct(item.productId).sku }}</div>
                  </td>
                  <td>{{ getProduct(item.productId)?.brand || '—' }}</td>
                  <td>{{ item.qty }}</td>
                  <td>{{ item.warranty }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div style="margin-top: var(--space-6); font-size: var(--text-sm); color: var(--color-text-secondary); line-height: var(--line-height-relaxed)">
            <strong>{{ t('page.warranty.terms') }}</strong>
            <p style="margin-top: var(--space-2)">{{ t('page.warranty.termsText') }}</p>
          </div>

          <div class="invoice-view__footer">
            <div></div>
            <div>
              <div style="margin-bottom:var(--space-1)">{{ t('common.seller') }} {{ company.directorName }}</div>
              <div class="invoice-view__signature-line">{{ t('common.signature') }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
</div>
</template>
<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useNavigationStore } from '@/stores/navigation'
import { useI18n } from '@/composables/useI18n'
import { formatDate } from '@/composables/useFormatters'
import { icon } from '@/composables/useIcons'

const props = defineProps({ id: [String, Number] })
const router = useRouter()
const mainStore = useMainStore()
const navStore = useNavigationStore()
const { t } = useI18n()

const invoice = computed(() => mainStore.invoiceById(props.id))
const company = computed(() => mainStore.company)
const customer = computed(() => invoice.value ? mainStore.customerById(invoice.value.customerId) : null)
const hasWarranty = computed(() => invoice.value?.items.some(item => item.warranty) || false)
const warrantyItems = computed(() => invoice.value?.items.filter(item => item.warranty) || [])

function getProduct(id) { return mainStore.productById(id) }

function goBack() {
  navStore.navigateBack(router, `/invoices/${props.id}`)
}

function printPage() {
  const prev = document.title
  document.title = `Warranty - ${invoice.value.number}`

  const el = document.querySelector('.invoice-view')
  const portal = document.createElement('div')
  portal.id = 'warranty-print-portal'
  portal.appendChild(el.cloneNode(true))
  document.body.appendChild(portal)
  document.body.classList.add('printing-warranty')

  window.onafterprint = () => {
    document.title = prev
    document.body.classList.remove('printing-warranty')
    document.body.removeChild(portal)
    window.onafterprint = null
  }
  window.print()
}
</script>
