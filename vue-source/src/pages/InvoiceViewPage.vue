<template>
<div>
  <div v-if="!invoice" class="empty-state">
    <h3>{{ t('page.invoiceView.notFound') }}</h3>
    <router-link to="/invoices" class="btn btn--primary">{{ t('btn.backToInvoices') }}</router-link>
  </div>
  <template v-else>
    <div class="page-header no-print">
      <div style="display:flex; align-items:center; gap:var(--space-4)">
        <button class="btn btn--ghost btn--icon" @click="goBack" :title="t('btn.back')" v-html="icon('arrow-left')"></button>
        <div>
          <h1 class="page-header__title">{{ invoice.number }}</h1>
          <p class="page-header__subtitle" style="margin:0">{{ t('page.invoiceView.subtitle') }}</p>
        </div>
      </div>
      <div class="page-header__actions">
        <AppBadge :label="tLabel(statusInfo)" :color="statusInfo.color" />
        <AppBadge :label="tLabel(lifecycleInfo)" :color="lifecycleInfo.color" :dot="true" />
        <router-link v-if="invoice.lifecycleStatus !== 'completed'" :to="`/invoices/${invoice.id}/edit`" class="btn btn--secondary">
          <span v-html="icon('edit', 16)"></span> {{ t('btn.edit') }}
        </router-link>
        <router-link v-if="hasWarranty" :to="`/invoices/${invoice.id}/warranty`" class="btn btn--secondary">
          <span v-html="icon('shield', 16)"></span> {{ t('btn.printWarranty') }}
        </router-link>
        <button class="btn btn--primary" @click="printPage"><span v-html="icon('printer', 16)"></span> {{ t('btn.print') }}</button>
      </div>
    </div>

    <div class="card">
      <div class="card__body">
        <div class="invoice-view">
          <!-- Company Header -->
          <div class="invoice-view__header">
            <div class="invoice-view__company">
              <div class="invoice-view__company-name">{{ company.name }}</div>
              <div class="invoice-view__company-details">
                {{ t('common.taxId') }} {{ company.taxId }}<br>
                {{ company.address }}<br>
                {{ company.phone }}<br>
                {{ company.email }}<br>
                <template v-if="company.website">{{ company.website }}</template>
              </div>
            </div>
            <div class="invoice-view__meta">
              <div class="invoice-view__invoice-number">{{ invoice.number }}</div>
              <div class="invoice-view__date">
                {{ t('common.date') }} {{ formatDate(invoice.createdAt) }}
                <template v-if="lifecycleInfo.label === 'Sold' && invoice.soldDate"> &nbsp;|&nbsp; {{ t('common.soldLabel') }} {{ formatDate(invoice.soldDate) }}</template>
              </div>
              <div class="invoice-view__buyer">
                <template v-if="customer">
                  <div class="invoice-view__party-name">{{ customer.name }}</div>
                  <div class="invoice-view__party-detail">{{ t('common.taxId') }} {{ customer.taxId }}</div>
                  <div class="invoice-view__party-detail">{{ customer.address }}</div>
                  <div class="invoice-view__party-detail">{{ customer.phone }}</div>
                  <div class="invoice-view__party-detail">{{ customer.email }}</div>
                </template>
                <div v-else class="invoice-view__party-detail">{{ t('common.noBuyer') }}</div>
              </div>
            </div>
          </div>

          <!-- Items Table -->
          <div class="table-scroll-wrapper">
          <table class="invoice-view__items-table">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ t('col.product') }}</th>
                <th>{{ t('col.brand') }}</th>
                <th>{{ t('col.qty') }}</th>
                <th>{{ t('col.price') }}</th>
                <th>{{ t('col.total') }}</th>
                <th class="no-print">{{ t('col.status') }}</th>
                <th v-if="hasWarranty">{{ t('col.warranty') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in invoice.items" :key="i">
                <td>{{ i + 1 }}</td>
                <td>
                  <strong>{{ getProduct(item.productId)?.name || t('common.unknown') }}</strong>
                  <div v-if="getProduct(item.productId)" style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ getProduct(item.productId).sku }}</div>
                </td>
                <td>{{ getProduct(item.productId)?.brand || '—' }}</td>
                <td :class="{ 'amount--canceled': item.itemStatus === 'canceled' }">{{ item.qty }}</td>
                <td :class="{ 'amount--canceled': item.itemStatus === 'canceled' }">{{ formatCurrency(item.price) }}</td>
                <td :class="['font-semibold', { 'amount--canceled': item.itemStatus === 'canceled' }]">{{ formatCurrency(item.qty * item.price) }}</td>
                <td class="no-print"><AppBadge :label="tLabel(getItemStatus(item.itemStatus))" :color="getItemStatus(item.itemStatus).color" /></td>
                <td v-if="hasWarranty">{{ item.warranty || '—' }}</td>
              </tr>
            </tbody>
          </table>
          </div>

          <!-- Summary -->
          <div class="invoice-view__summary">
            <div class="invoice-view__summary-table">
              <div class="invoice-view__summary-row">
                <span>{{ t('payment.subtotal') }}</span>
                <span :class="{ 'amount--canceled': lifecycleInfo.label === 'Canceled' }">{{ formatCurrency(lifecycleInfo.label === 'Canceled' ? invoice.totalAmount : effectiveTotal) }}</span>
              </div>
              <div v-if="positivePaidAmount > 0" class="invoice-view__summary-row">
                <span>{{ t('payment.paid') }}</span>
                <span style="color:var(--color-success)">{{ formatCurrency(positivePaidAmount) }}</span>
              </div>
              <div v-if="refundedAmount > 0" class="invoice-view__summary-row">
                <span>{{ t('payment.refunded') }}</span>
                <span style="color:var(--color-danger)">− {{ formatCurrency(refundedAmount) }}</span>
              </div>
              <div v-if="consignmentTotal > 0" class="invoice-view__summary-row">
                <span>{{ t('payment.consignment') }}</span>
                <span style="color:var(--color-info)">{{ formatCurrency(consignmentTotal) }}</span>
              </div>
              <div v-if="refundedAmount > 0" class="invoice-view__summary-row">
                <span>{{ t('payment.netPaid') }}</span>
                <span>{{ formatCurrency(invoice.paidAmount) }}</span>
              </div>
              <div v-if="lifecycleInfo.label !== 'Canceled'" class="invoice-view__summary-row invoice-view__summary-row--total" :class="{ 'no-print': lifecycleInfo.label === 'Draft' }">
                <span>{{ remaining > 0 ? t('payment.remainingLabel') : remaining < 0 ? t('payment.refundDueLabel') : t('payment.totalLabel') }}</span>
                <span>{{ remaining !== 0 ? formatCurrency(Math.abs(remaining)) : formatCurrency(effectiveTotal) }}</span>
              </div>
            </div>
          </div>

          <!-- Payment History -->
          <div v-if="invoice.payments.length > 0" class="no-print" style="margin-bottom: var(--space-6)">
            <h4 style="margin-bottom: var(--space-3); font-size: var(--text-sm); color: var(--color-text-secondary)">{{ t('payment.history') }}</h4>

            <!-- Progress Bar -->
            <div class="pmt-progress" style="margin-bottom: var(--space-3)">
              <div class="pmt-progress__track">
                <div class="pmt-progress__fill"
                     :class="{ 'pmt-progress__fill--settled': remaining === 0, 'pmt-progress__fill--over': remaining < 0 }"
                     :style="{ width: paidPercent + '%' }"></div>
              </div>
              <div class="pmt-progress__labels">
                <span v-if="positivePaidAmount > 0">{{ t('payment.paidLabel') }} {{ formatCurrency(positivePaidAmount) }}</span>
                <span v-else style="color: var(--color-text-tertiary)">{{ t('payment.noInbound') }}</span>
                <span v-if="remaining > 0" style="color: var(--color-danger)">{{ t('payment.remaining', { amount: formatCurrency(remaining) }) }}</span>
                <span v-else-if="remaining < 0" style="color: var(--color-warning)">{{ t('payment.refundDue', { amount: formatCurrency(overpaidAmount) }) }}</span>
                <span v-else style="color: var(--color-success)">{{ t('payment.fullSettled') }}</span>
              </div>
            </div>

            <!-- Read-only Ledger -->
            <div class="pmt-ledger">
              <div v-for="(row, i) in paymentsWithBalance" :key="i"
                   :class="['pmt-ledger__entry', row.amount < 0 ? 'pmt-ledger__entry--out' : 'pmt-ledger__entry--in']">
                <div class="pmt-ledger__icon">
                  <span v-html="icon(row.amount < 0 ? 'corner-up-left' : 'arrow-down-circle', 15)"></span>
                </div>
                <div class="pmt-ledger__meta">
                  <AppBadge :label="PAYMENT_METHODS[row.method] ? tLabel(PAYMENT_METHODS[row.method]) : row.method"
                            :color="PAYMENT_METHODS[row.method]?.color || 'neutral'" />
                  <span class="pmt-ledger__date">{{ formatDate(row.date) }}</span>
                </div>
                <div :class="['pmt-ledger__amount', row.amount < 0 ? 'pmt-ledger__amount--out' : 'pmt-ledger__amount--in']">
                  {{ row.amount < 0 ? '−' : '+' }}{{ formatCurrency(Math.abs(row.amount)) }}
                </div>
                <div class="pmt-ledger__balance"
                     :style="{ color: row.balance === 0 ? 'var(--color-success)' : row.balance < 0 ? 'var(--color-warning)' : 'var(--color-text-tertiary)' }">
                  {{ row.balance === 0 ? '✓' : formatCurrency(Math.abs(row.balance)) }}
                </div>
              </div>
            </div>
          </div>

          <!-- General Note -->
          <div v-if="invoice.generalNote" class="no-print" style="margin-bottom: var(--space-6); font-size: var(--text-sm); color: var(--color-text-secondary)">
            <strong>{{ t('common.note') }}</strong> {{ invoice.generalNote }}
          </div>

          <!-- Footer -->
          <div class="invoice-view__footer">
            <div>
              <div class="invoice-view__party-detail">
                {{ t('common.bank') }} {{ company.bankName1 }}<br>
                {{ t('common.iban') }} {{ company.iban1 }}
              </div>
              <div v-if="company.bankName2" class="invoice-view__party-detail" style="margin-top:var(--space-2)">
                {{ t('common.bank2') }} {{ company.bankName2 }}<br>
                {{ t('common.iban') }} {{ company.iban2 }}
              </div>
            </div>
            <div>
              <div style="margin-bottom:var(--space-1)">{{ t('common.seller') }} {{ company.directorName }}</div>
              <div class="invoice-view__signature-line">{{ t('common.signature') }}</div>
            </div>
          </div>

          <!-- Author -->
          <div style="margin-top: var(--space-6); font-size: var(--text-xs); color: var(--color-text-tertiary); text-align:center">
            {{ t('common.createdBy') }} {{ author?.name || '—' }} | {{ formatDate(invoice.createdAt) }}
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
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate } from '@/composables/useFormatters'
import { icon } from '@/composables/useIcons'
import { useI18n } from '@/composables/useI18n'
import { PAYMENT_METHODS, STATUS_LABELS, LIFECYCLE_LABELS, ITEM_STATUS_LABELS, getInvoiceLifecycle, getEffectiveTotal } from '@/data'
import AppBadge from '@/components/ui/AppBadge.vue'

const props = defineProps({ id: [String, Number] })
const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const { t, tLabel } = useI18n()

const invoice = computed(() => mainStore.invoiceById(props.id))
const company = computed(() => mainStore.company)
const customer = computed(() => invoice.value ? mainStore.customerById(invoice.value.customerId) : null)
const author = computed(() => invoice.value ? mainStore.userById(invoice.value.authorId) : null)
const statusInfo = computed(() => STATUS_LABELS[invoice.value?.status] || STATUS_LABELS.standard)
const lifecycleInfo = computed(() => invoice.value ? getInvoiceLifecycle(invoice.value) : LIFECYCLE_LABELS.draft)
const consignmentTotal = computed(() => invoice.value?.payments.filter(p => p.method === 'consignment').reduce((s, p) => s + p.amount, 0) || 0)
const effectiveTotal = computed(() => getEffectiveTotal(invoice.value))
const remaining = computed(() => effectiveTotal.value - (invoice.value?.paidAmount || 0))
const hasWarranty = computed(() => invoice.value?.items.some(item => item.warranty) || false)
const positivePaidAmount = computed(() =>
  invoice.value?.payments
    .filter(p => p.method !== 'consignment' && p.amount > 0)
    .reduce((s, p) => s + p.amount, 0) || 0
)
const refundedAmount = computed(() =>
  invoice.value?.payments
    .filter(p => p.method === 'refund')
    .reduce((s, p) => s + Math.abs(p.amount), 0) || 0
)
const overpaidAmount = computed(() => Math.max(0, -(remaining.value)))
const paidPercent = computed(() => {
  if (effectiveTotal.value <= 0) return 100
  return Math.max(0, Math.min(100, ((invoice.value?.paidAmount || 0) / effectiveTotal.value) * 100))
})
const paymentsWithBalance = computed(() => {
  if (!invoice.value) return []
  let running = effectiveTotal.value
  return invoice.value.payments.map(p => {
    running = running - p.amount
    return { ...p, balance: running }
  })
})

function getProduct(id) { return mainStore.productById(id) }
function getItemStatus(status) { return ITEM_STATUS_LABELS[status] || ITEM_STATUS_LABELS.none }

function printPage() {
  const prev = document.title
  document.title = `Invoice - ${invoice.value.number}`

  // Clone invoice-view directly to body — bypasses all layout constraints,
  // same technique as warranty print. Guarantees full A4 width on mobile.
  const invoiceEl = document.querySelector('.invoice-view')
  const portal = document.createElement('div')
  portal.id = 'invoice-print-portal'
  portal.appendChild(invoiceEl.cloneNode(true))
  document.body.appendChild(portal)
  document.body.classList.add('printing-invoice')

  window.onafterprint = () => {
    document.title = prev
    document.body.classList.remove('printing-invoice')
    document.body.removeChild(portal)
    window.onafterprint = null
  }
  window.print()
}

function goBack() {
  const fallback = authStore.isAccountantRole ? '/accountant' : '/invoices'
  navStore.navigateBack(router, fallback)
}
</script>
