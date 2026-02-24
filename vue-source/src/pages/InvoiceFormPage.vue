<template>
<div>
  <div v-if="!invoice" class="empty-state">
    <h3>{{ t('page.invoiceForm.notFound') }}</h3>
    <router-link to="/invoices" class="btn btn--primary">{{ t('btn.backToInvoices') }}</router-link>
  </div>
  <div v-else class="invoice-form">
    <div class="page-header">
      <div style="display:flex; align-items:center; gap:var(--space-4)">
        <button class="btn btn--ghost btn--icon" @click="goBack" :title="t('btn.back')" v-html="icon('arrow-left')"></button>
        <div>
          <h1 class="page-header__title">{{ isNew ? t('page.invoiceForm.newTitle') : t('page.invoiceForm.editTitle', { number: invoice.number }) }}</h1>
          <p class="page-header__subtitle" style="margin:0">{{ isReadOnly ? t('page.invoiceForm.subtitleSold') : t('page.invoiceForm.subtitleEdit') }}</p>
        </div>
      </div>
      <div class="page-header__actions">
        <AppBadge :label="tLabel(lifecycleInfo)" :color="lifecycleInfo.color" :dot="true" />
      </div>
    </div>

    <!-- Header Fields -->
    <div class="card" style="margin-bottom: var(--space-6)">
      <div class="card__body">
        <div class="invoice-form__header">
          <div class="invoice-form__number">{{ invoice.number }}</div>
          <div class="form-group" style="margin:0">
            <label class="form-label">{{ t('form.date') }}</label>
            <input type="date" class="form-input" v-model="invoice.createdAt" :disabled="isReadOnly">
          </div>
          <div class="form-group" style="margin:0">
            <label class="form-label">{{ t('form.type') }}</label>
            <div :class="['form-switch', { active: invoice.status === 'fictive' }]" @click="toggleFictive" :style="{ cursor: isReadOnly ? 'default' : 'pointer' }">
              <div class="form-switch__track"><div class="form-switch__thumb"></div></div>
              <span class="form-switch__label">{{ invoice.status === 'fictive' ? t('filter.fictive') : t('filter.standard') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Buyer Section -->
    <div class="card" style="margin-bottom: var(--space-6)">
      <div class="card__body">
        <div class="buyer-section-header">
          <div class="invoice-form__section-title" style="margin-bottom:0"><span v-html="icon('users')"></span> {{ t('section.buyerInfo') }}</div>
          <span v-if="invoice.customerId && !isReadOnly" class="buyer-linked-chip">
            <span v-html="icon('check-circle', 12)"></span> {{ t('page.invoiceForm.autoFilled') }}
            <button class="buyer-linked-chip__clear" @click="clearBuyer" title="Unlink customer"><span v-html="icon('x', 12)"></span></button>
          </span>
        </div>
        <div class="buyer-fields">
          <div class="form-group buyer-fields__full">
            <label class="form-label">{{ t('form.companyName') }}</label>
            <Autocomplete
              v-model="buyerName"
              :items="mainStore.customers"
              :searchFields="['name', 'nameEn', 'taxId']"
              :placeholder="t('form.searchCustomer')"
              :disabled="isReadOnly"
              @select="onBuyerSelect">
              <template #item="{ item }">
                <div>
                  <div class="autocomplete__item-main">{{ item.name }}</div>
                  <div class="autocomplete__item-sub">{{ t('common.taxId') }} {{ item.taxId }}</div>
                </div>
              </template>
            </Autocomplete>
          </div>
          <div class="form-group">
            <label class="form-label">{{ t('form.taxId') }}</label>
            <input type="text" class="form-input" v-model="buyerTaxId" :disabled="isReadOnly">
          </div>
          <div class="form-group">
            <label class="form-label">{{ t('form.phone') }}</label>
            <input type="text" class="form-input" v-model="buyerPhone" :disabled="isReadOnly">
          </div>
          <div class="form-group">
            <label class="form-label">{{ t('form.address') }}</label>
            <input type="text" class="form-input" v-model="buyerAddress" :disabled="isReadOnly">
          </div>
          <div class="form-group">
            <label class="form-label">{{ t('form.email') }}</label>
            <input type="email" class="form-input" v-model="buyerEmail" :disabled="isReadOnly">
          </div>
        </div>
      </div>
    </div>

    <!-- Items Section -->
    <div class="card" style="margin-bottom: var(--space-6)">
      <div class="card__body">
        <div class="invoice-form__section-title"><span v-html="icon('package')"></span> {{ t('section.items') }}</div>
        <div class="items-table-wrapper">
          <table class="data-table items-table">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ t('col.product') }}</th>
                <th>{{ t('col.image') }}</th>
                <th>{{ t('col.brand') }}</th>
                <th>{{ t('col.qty') }}</th>
                <th>{{ t('col.price') }}</th>
                <th>{{ t('col.total') }}</th>
                <th>{{ t('col.status') }}</th>
                <th>{{ t('col.warranty') }}</th>
                <th>{{ t('col.resDays') }}</th>
                <th class="text-center">{{ t('col.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in invoice.items" :key="idx">
                <td style="width:30px; color:var(--color-text-tertiary)">{{ idx + 1 }}</td>
                <td class="product-cell">
                  <Autocomplete
                    :modelValue="getProductName(item.productId)"
                    :items="mainStore.products"
                    :searchFields="['name', 'sku', 'brand']"
                    :placeholder="t('form.nameOrSku')"
                    :disabled="isReadOnly"
                    :minDropdownWidth="400"
                    :minChars="3"
                    @select="(p) => onProductSelect(idx, p)">
                    <template #item="{ item: p }">
                      <div class="product-result">
                        <div class="product-result__main">
                          <div class="product-result__name">{{ p.name }}</div>
                          <div class="product-result__meta">
                            <span class="product-result__sku">{{ p.sku }}</span>
                            <template v-if="p.brand"> · {{ p.brand }}</template>
                            <span :class="['product-result__stock', productStockClass(p)]"> · {{ productStockLabel(p) }}</span>
                          </div>
                        </div>
                        <div class="product-result__price">{{ formatCurrency(p.price) }}</div>
                      </div>
                    </template>
                  </Autocomplete>
                </td>
                <td style="width:60px"><div class="stock-thumb">{{ getProduct(item.productId)?.sku?.slice(-3) || '—' }}</div></td>
                <td style="width:100px; font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ getProduct(item.productId)?.brand || '—' }}</td>
                <td class="qty-cell">
                  <div :class="['qty-stepper', { 'qty-stepper--exceed': qtyExceedIdx === idx }]">
                    <button class="qty-stepper__btn" @click="decQty(idx)" :disabled="isReadOnly || item.qty <= 1">-</button>
                    <input type="number" class="qty-stepper__input" :value="item.qty" min="1" :max="getMaxQty(idx)" @change="setQty(idx, $event)" :disabled="isReadOnly">
                    <button class="qty-stepper__btn" @click="incQty(idx)" :disabled="isReadOnly || item.qty >= getMaxQty(idx)">+</button>
                  </div>
                </td>
                <td class="price-cell">
                  <input type="number" class="form-input form-input--sm" :value="item.price" step="0.01" min="0" @change="setPrice(idx, $event)" :disabled="isReadOnly" style="width:100px">
                </td>
                <td class="total-cell font-medium">{{ formatCurrency(item.qty * item.price) }}</td>
                <td class="status-cell">
                  <select class="form-select form-input--sm" :value="item.itemStatus" @change="setItemStatus(idx, $event)" :disabled="isReadOnly || invoice.status === 'fictive'">
                    <option v-for="(val, key) in ITEM_STATUS_LABELS" :key="key" :value="key">{{ tLabel(val) }}</option>
                  </select>
                </td>
                <td style="width:120px">
                  <select class="form-select form-input--sm" :value="item.warranty" @change="item.warranty = $event.target.value" :disabled="isReadOnly">
                    <option value="">{{ t('form.none') }}</option>
                    <option v-for="w in WARRANTY_OPTIONS" :key="w.value" :value="w.value">{{ tWarranty(w) }}</option>
                  </select>
                </td>
                <td style="width:80px">
                  <input v-if="item.itemStatus === 'reserved' && invoice.status !== 'fictive'" type="number" class="form-input form-input--sm" :value="item.reservationDays || 14" min="1" max="90" @change="item.reservationDays = Math.max(1, Math.min(90, parseInt($event.target.value) || 14))" :disabled="isReadOnly" style="width:60px">
                  <span v-else style="color:var(--color-text-tertiary)">—</span>
                </td>
                <td class="actions-cell text-center">
                  <button v-if="!isReadOnly" class="btn btn--ghost btn--icon" @click="removeItem(idx)" style="color:var(--color-danger)"><span v-html="icon('trash-2', 16)"></span></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button v-if="!isReadOnly" class="btn btn--secondary btn--sm" @click="addItem"><span v-html="icon('plus', 14)"></span> {{ t('btn.addItem') }}</button>
      </div>
    </div>

    <!-- Payment Section -->
    <div class="card" :class="{ 'section--fictive-disabled': invoice?.status === 'fictive' }" style="margin-bottom: var(--space-6)">
      <div class="card__body">
        <div class="invoice-form__section-title"><span v-html="icon('credit-card')"></span> {{ t('section.payments') }}</div>
        <div v-if="invoice?.status === 'fictive'" class="fictive-notice">
          <span v-html="icon('alert-circle', 14)"></span> {{ t('payment.fictiveNotice') }}
        </div>

        <!-- Zone A — Progress Bar -->
        <div class="pmt-progress">
          <div class="pmt-progress__track">
            <div class="pmt-progress__fill"
                 :class="{
                   'pmt-progress__fill--settled': remaining === 0,
                   'pmt-progress__fill--over':    remaining < 0
                 }"
                 :style="{ width: paidPercent + '%' }"></div>
          </div>
          <div class="pmt-progress__labels">
            <span v-if="positivePaidAmount > 0">{{ t('payment.paidLabel') }} {{ formatCurrency(positivePaidAmount) }}</span>
            <span v-else style="color: var(--color-text-tertiary)">{{ t('payment.noPayments') }}</span>
            <span v-if="remaining > 0" style="color: var(--color-danger)">{{ t('payment.remaining', { amount: formatCurrency(remaining) }) }}</span>
            <span v-else-if="remaining < 0" style="color: var(--color-warning)">{{ t('payment.refundDue', { amount: formatCurrency(overpaidAmount) }) }}</span>
            <span v-else style="color: var(--color-success)">{{ t('payment.fullSettled') }}</span>
          </div>
        </div>

        <!-- Zone B — Transaction Ledger -->
        <div class="pmt-ledger">
          <div v-if="invoice.payments.length === 0" class="pmt-ledger__empty">
            <span v-html="icon('inbox', 20)"></span> {{ t('payment.noTransactions') }}
          </div>
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
            <button v-if="!isReadOnly && invoice?.status !== 'fictive'"
                    class="pmt-ledger__delete btn btn--ghost btn--icon"
                    @click="removePayment(i)" style="color: var(--color-danger)">
              <span v-html="icon('trash-2', 14)"></span>
            </button>
          </div>
        </div>

        <!-- Zone C — Add Transaction Panel -->
        <div v-if="!isReadOnly && invoice?.status !== 'fictive'" class="pmt-add-panel">
          <div class="pmt-add-panel__inputs">
            <div class="form-group" style="margin:0; flex:0 0 140px">
              <label class="form-label">{{ t('form.date') }}</label>
              <input type="date" class="form-input form-input--sm" v-model="paymentDate">
            </div>
            <div class="form-group" style="margin:0; flex:1; min-width:140px">
              <label class="form-label">{{ t('form.method') }}</label>
              <select class="form-select" v-model="paymentMethod">
                <option v-for="(val, key) in PAYMENT_METHODS" v-show="key !== 'refund'" :key="key" :value="key">
                  {{ tLabel(val) }}
                </option>
              </select>
            </div>
            <div class="form-group" style="margin:0; flex:0 0 120px">
              <label class="form-label">{{ t('form.amount') }}</label>
              <input type="number" class="form-input form-input--sm"
                     v-model.number="paymentAmount" step="0.01" min="0" placeholder="0.00">
            </div>
            <button class="btn btn--primary btn--sm" @click="addPayment" style="align-self:flex-end">
              <span v-html="icon('plus', 14)"></span> {{ t('btn.add') }}
            </button>
          </div>
          <div v-if="remaining > 0 || overpaidAmount > 0" class="pmt-add-panel__quick">
            <button v-if="remaining > 0" class="btn btn--success btn--sm" @click="payFull">
              <span v-html="icon('check-circle', 14)"></span> {{ t('btn.payFull', { amount: formatCurrency(remaining) }) }}
            </button>
            <button v-if="overpaidAmount > 0" class="btn btn--danger btn--sm" @click="issueRefund">
              <span v-html="icon('corner-up-left', 14)"></span> {{ t('btn.issueRefund', { amount: formatCurrency(overpaidAmount) }) }}
            </button>
          </div>
        </div>

        <div class="invoice-totals" style="margin-top: var(--space-4)">
          <div class="invoice-totals__table">

            <!-- Row 1: Subtotal (active items only) -->
            <div class="invoice-totals__row">
              <span>{{ t('payment.subtotal') }}</span>
              <span class="font-semibold">{{ formatCurrency(grandTotal) }}</span>
            </div>

            <!-- Row 2: Paid (positive inbound only) -->
            <div v-if="positivePaidAmount > 0" class="invoice-totals__row">
              <span>{{ t('payment.paid') }}</span>
              <span class="text-success font-medium">{{ formatCurrency(positivePaidAmount) }}</span>
            </div>

            <!-- Row 3: Refunded (only if refunds exist) -->
            <div v-if="refundedAmount > 0" class="invoice-totals__row">
              <span>{{ t('payment.refunded') }}</span>
              <span class="text-danger font-medium">− {{ formatCurrency(refundedAmount) }}</span>
            </div>

            <!-- Row 4: Consignment (unchanged) -->
            <div v-if="consignmentTotal > 0" class="invoice-totals__row">
              <span>{{ t('payment.consignment') }}</span>
              <span style="color: var(--color-info)">{{ formatCurrency(consignmentTotal) }}</span>
            </div>

            <!-- Row 5: Net Paid — only when refunds exist to make breakdown meaningful -->
            <div v-if="refundedAmount > 0" class="invoice-totals__row">
              <span>{{ t('payment.netPaid') }}</span>
              <span class="font-medium">{{ formatCurrency(paidAmount) }}</span>
            </div>

            <!-- Final row: Remaining / Fully Settled / Refund Due -->
            <div :class="[
              'invoice-totals__row',
              remaining > 0 ? 'invoice-totals__row--remaining' :
              remaining < 0 ? 'invoice-totals__row--refund-due' :
                              'invoice-totals__row--total'
            ]">
              <span>{{ remaining > 0 ? t('payment.remainingLabel') : remaining < 0 ? t('payment.refundDueLabel') : t('payment.fullSettled') }}</span>
              <span>{{ remaining !== 0 ? formatCurrency(Math.abs(remaining)) : '✓' }}</span>
            </div>

          </div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="card" style="margin-bottom: var(--space-6)">
      <div class="card__body">
        <div class="invoice-form__section-title"><span v-html="icon('message-square')"></span> {{ t('section.notes') }}</div>
        <div class="form-group">
          <label class="form-label">{{ t('form.generalNote') }}</label>
          <textarea class="form-textarea" v-model="invoice.generalNote" :disabled="isReadOnly"></textarea>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div v-if="!isReadOnly" class="invoice-form__actions">
      <button v-if="invoice.status === 'fictive'" class="btn btn--secondary" @click="saveDraft"><span v-html="icon('save', 16)"></span> {{ isNew ? t('btn.saveDraft') : t('btn.updateDraft') }}</button>
      <button v-if="invoice.status === 'standard'" class="btn btn--primary" @click="saveActivate"><span v-html="icon('check', 16)"></span> {{ isNew ? t('btn.saveInvoice') : t('btn.updateInvoice') }}</button>
      <router-link :to="`/invoices/${invoice.id}`" class="btn btn--ghost"><span v-html="icon('printer', 16)"></span> {{ t('btn.printPreview') }}</router-link>
    </div>
  </div>
</div>
</template>
<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { api } from '@/api'
import { formatCurrency, formatDate, todayISO, generateId } from '@/composables/useFormatters'
import { icon } from '@/composables/useIcons'
import { useToast } from '@/composables/useToast'
import { useNotificationStore } from '@/stores/notifications'
import { useI18n } from '@/composables/useI18n'
import { PAYMENT_METHODS, ITEM_STATUS_LABELS, LIFECYCLE_LABELS, WARRANTY_OPTIONS, getInvoiceLifecycle } from '@/data'
import Autocomplete from '@/components/ui/Autocomplete.vue'
import AppBadge from '@/components/ui/AppBadge.vue'

const props = defineProps({ id: [String, Number] })
const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const cartStore = useCartStore()
const { showToast } = useToast()
const notifStore = useNotificationStore()
const { t, tLabel, tWarranty } = useI18n()

const isNew = ref(false)
const invoice = ref(null)
let preFictiveItemStates = null
let preFictivePayments = null   // cached payments while invoice is in fictive mode
const qtyExceedIdx = ref(null)
let qtyExceedTimer = null

function triggerQtyExceed(idx) {
  qtyExceedIdx.value = null          // reset first so re-trigger works
  clearTimeout(qtyExceedTimer)
  requestAnimationFrame(() => {
    qtyExceedIdx.value = idx
    qtyExceedTimer = setTimeout(() => { qtyExceedIdx.value = null }, 500)
  })
}

// Buyer fields
const buyerName = ref('')
const buyerTaxId = ref('')
const buyerAddress = ref('')
const buyerPhone = ref('')
const buyerEmail = ref('')

// Payment fields
const paymentDate = ref(todayISO())
const paymentMethod = ref('company_transfer')
const paymentAmount = ref(0)

onMounted(async () => {
  if (props.id) {
    const existing = mainStore.invoiceById(props.id)
    if (existing) {
      invoice.value = JSON.parse(JSON.stringify(existing))
      isNew.value = false
    }
  } else {
    // Determine invoice number
    let invoiceNumber
    if (api.isWordPress()) {
      try {
        const res = await api.get('invoices/next-number')
        invoiceNumber = res?.data?.number || `${mainStore.company.invoicePrefix || 'N'}—`
      } catch {
        invoiceNumber = `${mainStore.company.invoicePrefix || 'N'}—`
      }
    } else {
      const invoices = mainStore.invoices
      const company = mainStore.company
      invoiceNumber = `${company.invoicePrefix}-${company.startingInvoiceNumber + invoices.length}`
    }

    invoice.value = {
      id: api.isWordPress() ? null : generateId(mainStore.invoices),
      number: invoiceNumber,
      customerId: null,
      status: 'standard',
      lifecycleStatus: 'draft',
      items: [],
      payments: [],
      totalAmount: 0,
      paidAmount: 0,
      createdAt: todayISO(),
      saleDate: null,
      soldDate: null,
      authorId: authStore.currentUser?.id || 1,
      generalNote: '',
      isRsUploaded: false,
      isCreditChecked: false,
      isReceiptChecked: false,
      isCorrected: false,
      accountantNote: '',
      consultantNote: ''
    }
    isNew.value = true
  }

  if (invoice.value) {
    // Populate buyer fields (always reset first to avoid residual state)
    buyerName.value = ''
    buyerTaxId.value = ''
    buyerAddress.value = ''
    buyerPhone.value = ''
    buyerEmail.value = ''
    if (invoice.value.customerId) {
      const customer = mainStore.customerById(invoice.value.customerId)
      if (customer) {
        buyerName.value = customer.name
        buyerTaxId.value = customer.taxId
        buyerAddress.value = customer.address
        buyerPhone.value = customer.phone
        buyerEmail.value = customer.email
      }
    }

    // Auto-populate from pending cart items
    const pendingItems = cartStore.consumePendingCartItems()
    if (pendingItems && pendingItems.length > 0) {
      invoice.value.items = invoice.value.items.concat(pendingItems)
    }
  }
})

const isReadOnly = computed(() => {
  if (!invoice.value) return false
  return getInvoiceLifecycle(invoice.value).label === 'Sold' && !authStore.isAdmin
})
const lifecycleInfo = computed(() => getInvoiceLifecycle(invoice.value) || LIFECYCLE_LABELS.draft)

const grandTotal = computed(() =>
  invoice.value
    ? invoice.value.items
        .filter(item => item.itemStatus !== 'canceled')
        .reduce((sum, item) => sum + item.qty * item.price, 0)
    : 0
)
const paidAmount = computed(() => invoice.value ? invoice.value.payments.filter(p => p.method !== 'consignment').reduce((sum, p) => sum + p.amount, 0) : 0)
const consignmentTotal = computed(() => invoice.value ? invoice.value.payments.filter(p => p.method === 'consignment').reduce((s, p) => s + p.amount, 0) : 0)
const paymentsWithBalance = computed(() => {
  if (!invoice.value) return []
  let running = grandTotal.value
  return invoice.value.payments.map(p => {
    running = running - p.amount
    return { ...p, balance: running }
  })
})
const remaining = computed(() => grandTotal.value - paidAmount.value)

const positivePaidAmount = computed(() =>
  invoice.value
    ? invoice.value.payments
        .filter(p => p.method !== 'consignment' && p.amount > 0)
        .reduce((s, p) => s + p.amount, 0)
    : 0
)
const refundedAmount = computed(() =>
  invoice.value
    ? invoice.value.payments
        .filter(p => p.method === 'refund')
        .reduce((s, p) => s + Math.abs(p.amount), 0)
    : 0
)
const overpaidAmount = computed(() => Math.max(0, -(remaining.value)))
const paidPercent = computed(() => {
  if (grandTotal.value <= 0) return 100
  return Math.max(0, Math.min(100, (paidAmount.value / grandTotal.value) * 100))
})

function getProduct(id) { return mainStore.productById(id) }
function getProductName(id) { const p = mainStore.productById(id); return p ? p.name : '' }

function productStockLabel(p) {
  const avail = (p.stock || 0) - (p.reserved || 0)
  if (avail <= 0) return t('page.invoiceForm.outOfStock')
  if (avail <= 5) return t('page.invoiceForm.lowStock', { n: avail })
  return t('page.invoiceForm.inStock', { n: avail })
}
function productStockClass(p) {
  const avail = (p.stock || 0) - (p.reserved || 0)
  if (avail <= 0) return 'product-result__stock--out'
  if (avail <= 5) return 'product-result__stock--low'
  return 'product-result__stock--ok'
}

function onBuyerSelect(customer) {
  invoice.value.customerId = customer.id
  buyerName.value = customer.name
  buyerTaxId.value = customer.taxId
  buyerAddress.value = customer.address
  buyerPhone.value = customer.phone
  buyerEmail.value = customer.email
}

function clearBuyer() {
  invoice.value.customerId = null
  buyerName.value = ''
  buyerTaxId.value = ''
  buyerAddress.value = ''
  buyerPhone.value = ''
  buyerEmail.value = ''
}

function onProductSelect(idx, product) {
  invoice.value.items[idx].productId   = product.id
  invoice.value.items[idx].name        = product.name
  invoice.value.items[idx].sku         = product.sku  || ''
  invoice.value.items[idx].description = product.description || ''
  invoice.value.items[idx].image       = product.image || ''
  invoice.value.items[idx].price       = product.price
}

function addItem() {
  const isFictive = invoice.value.status === 'fictive'
  invoice.value.items.push({
    productId: null, qty: 1, price: 0,
    itemStatus: isFictive ? 'none' : 'reserved',
    warranty: isFictive ? '' : '1_year',
    reservationDays: isFictive ? 0 : 14
  })
}

function removeItem(idx) { invoice.value.items.splice(idx, 1) }
function getMaxQty(idx) {
  const product = mainStore.productById(invoice.value.items[idx].productId)
  if (!product) return 9999
  const available = (product.stock || 0) - (product.reserved || 0)
  return Math.max(1, available)
}
function incQty(idx) {
  const max = getMaxQty(idx)
  if (invoice.value.items[idx].qty < max) {
    invoice.value.items[idx].qty++
  } else {
    triggerQtyExceed(idx)
    showToast('warning', t('msg.onlyNAvailable', { n: max }))
  }
}
function decQty(idx) { if (invoice.value.items[idx].qty > 1) invoice.value.items[idx].qty-- }
function setQty(idx, e) {
  const max = getMaxQty(idx)
  const raw = parseInt(e.target.value) || 1
  if (raw > max) {
    invoice.value.items[idx].qty = max
    e.target.value = max
    triggerQtyExceed(idx)
    showToast('warning', t('msg.onlyNAvailable', { n: max }))
  } else {
    invoice.value.items[idx].qty = Math.max(1, raw)
  }
}
function setPrice(idx, e) { invoice.value.items[idx].price = Math.max(0, parseFloat(e.target.value) || 0) }

function setItemStatus(idx, e) {
  const val = e.target.value
  invoice.value.items[idx].itemStatus = val
  if (val !== 'reserved') {
    invoice.value.items[idx].reservationDays = 0
  } else {
    invoice.value.items[idx].reservationDays = invoice.value.items[idx].reservationDays || 14
  }
}

function toggleFictive() {
  if (isReadOnly.value) return
  if (invoice.value.status === 'standard') {
    // Switch to fictive — cache item states and payments, then clear them
    preFictiveItemStates = invoice.value.items.map(item => ({ itemStatus: item.itemStatus, reservationDays: item.reservationDays }))
    preFictivePayments = JSON.parse(JSON.stringify(invoice.value.payments))
    invoice.value.items.forEach(item => { item.itemStatus = 'none' })
    invoice.value.payments = []
    invoice.value.saleDate = null
    invoice.value.lifecycleStatus = 'draft'
    invoice.value.status = 'fictive'
    if (preFictivePayments.length > 0) {
      showToast('info', t('msg.paymentsHidden'), t('msg.switchBackToRestore'))
    }
  } else {
    // Switch back to standard — restore item states and cached payments
    invoice.value.items.forEach((item, i) => {
      if (preFictiveItemStates && preFictiveItemStates[i] && item.itemStatus === 'none') {
        item.itemStatus = preFictiveItemStates[i].itemStatus
        item.reservationDays = preFictiveItemStates[i].reservationDays
      } else if (item.itemStatus === 'none') {
        item.itemStatus = 'reserved'
        item.reservationDays = 14
      }
    })
    if (preFictivePayments && preFictivePayments.length > 0) {
      invoice.value.payments = preFictivePayments
      showToast('success', t('msg.paymentsRestored'))
    }
    preFictiveItemStates = null
    preFictivePayments = null
    invoice.value.status = 'standard'
  }
}

function addPayment() {
  if (paymentAmount.value <= 0) {
    showToast('warning', t('msg.enterValidAmount'))
    return
  }
  invoice.value.payments.push({ date: paymentDate.value, method: paymentMethod.value, amount: paymentAmount.value })
  if (invoice.value.status === 'standard' && !invoice.value.saleDate) {
    invoice.value.saleDate = todayISO()
    invoice.value.lifecycleStatus = 'active'
  }
  notifStore.push({ type: 'invoice', titleKey: 'notif.newPayment', message: `${invoice.value.number} · ${formatCurrency(paymentAmount.value)}`, icon: 'credit-card', invoiceId: invoice.value.id })
  paymentAmount.value = 0
}

function payFull() {
  if (remaining.value <= 0) {
    showToast('info', t('msg.invoiceFullyPaid'))
    return
  }
  invoice.value.payments.push({ date: paymentDate.value, method: paymentMethod.value, amount: remaining.value })
  if (invoice.value.status === 'standard' && !invoice.value.saleDate) {
    invoice.value.saleDate = todayISO()
    invoice.value.lifecycleStatus = 'active'
  }
}

function issueRefund() {
  if (overpaidAmount.value <= 0) return
  invoice.value.payments.push({
    date: paymentDate.value,
    method: 'refund',
    amount: -overpaidAmount.value
  })
}

function removePayment(idx) { invoice.value.payments.splice(idx, 1) }

function recalcAndSave() {
  invoice.value.totalAmount = grandTotal.value
  invoice.value.paidAmount = paidAmount.value
  const data = JSON.parse(JSON.stringify(invoice.value))
  // Include buyer info so the REST API can upsert the customer record
  data.buyer = {
    name:    buyerName.value,
    taxId:   buyerTaxId.value,
    phone:   buyerPhone.value,
    email:   buyerEmail.value,
    address: buyerAddress.value,
  }
  return data
}

async function saveDraft() {
  // Permanently discard any cached payments — user committed to fictive state
  preFictivePayments = null
  const data = recalcAndSave()
  try {
    const saved = await mainStore.saveInvoice(data)
    if (saved?.id) invoice.value.id = saved.id
    if (saved?.number) invoice.value.number = saved.number
    cartStore.clearCart()
    showToast('success', t('msg.invoiceSavedDraft'))
    if (isNew.value) {
      router.replace(`/invoices/${invoice.value.id}/edit`)
      isNew.value = false
    }
  } catch (e) {
    showToast('error', t('msg.error'), e.message)
  }
}

async function saveActivate() {
  if (invoice.value.status === 'standard') {
    invoice.value.lifecycleStatus = 'active'
    if (!invoice.value.saleDate) invoice.value.saleDate = todayISO()
  }
  const data = recalcAndSave()
  try {
    const saved = await mainStore.saveInvoice(data)
    const targetId = saved?.id || invoice.value.id
    cartStore.clearCart()
    showToast('success', t('msg.invoiceSaved'))
    router.push(`/invoices/${targetId}`)
  } catch (e) {
    showToast('error', t('msg.error'), e.message)
  }
}

function goBack() {
  if (!isNew.value && invoice.value) {
    router.push(`/invoices/${invoice.value.id}`)
  } else {
    router.push('/invoices')
  }
}
</script>
