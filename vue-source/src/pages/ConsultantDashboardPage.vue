<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.consultant.welcome', { name: authStore.currentUser?.name || 'Consultant' }) }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ t('page.consultant.subtitle') }}</p>
    </div>
    <div class="page-header__actions">
      <router-link to="/stock" class="btn btn--secondary"><span v-html="icon('package', 16)"></span> {{ t('btn.viewStock') }}</router-link>
      <router-link to="/invoices/new" class="btn btn--primary"><span v-html="icon('plus', 16)"></span> {{ t('btn.newInvoice') }}</router-link>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom: var(--space-4)">
    <DateFilter v-model:dateFrom="dateFrom" v-model:dateTo="dateTo" prefix="cons" @change="() => {}" />
  </div>

  <!-- Stat Cards -->
  <div class="dashboard-grid">
    <div class="card kpi-card animate-fade-in-up" style="cursor:pointer; animation-delay: 0ms" @click="drillDown('/invoices', 'remaining')">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.consultant.myRevenue') }}</span>
          <div class="kpi-card__value">{{ formatCurrency(stats.totalRevenue) }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--success" v-html="icon('dollar-sign')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span class="kpi-card__change kpi-card__change--flat">{{ t('page.consultant.totalInvoices', { n: stats.totalInvoices }) }}</span>
        <div class="kpi-card__sparkline"><SparklineChart :data="generateTrendlineData(revenueChange)" color="#16a34a" /></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" style="cursor:pointer; animation-delay: 50ms" @click="drillDown('/invoices', 'number')">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.consultant.myInvoices') }}</span>
          <div class="kpi-card__value">{{ stats.totalInvoices }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--primary" v-html="icon('file-text')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span class="kpi-card__change kpi-card__change--flat">{{ t('page.consultant.thisMonth', { n: stats.thisMonth }) }}</span>
        <div class="kpi-card__sparkline"></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" style="cursor:pointer; animation-delay: 100ms" @click="drillDown('/invoices?filter=reserved', 'lifecycle')">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.consultant.myReservations') }}</span>
          <div class="kpi-card__value">{{ reservations.length }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--warning" v-html="icon('clock')"></div>
      </div>
      <div class="kpi-card__bottom">
        <template v-if="urgentReservations.length > 0">
          <span class="kpi-card__change kpi-card__change--down"><span v-html="icon('alert-triangle', 12)"></span> {{ t('page.consultant.expiringSoon', { n: urgentReservations.length }) }}</span>
        </template>
        <template v-else>
          <span class="kpi-card__change kpi-card__change--flat">{{ t('page.consultant.activeReservations') }}</span>
        </template>
        <div class="kpi-card__sparkline"></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" style="cursor:pointer; animation-delay: 150ms" @click="drillDown('/invoices?filter=outstanding', 'remaining')">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.consultant.outstanding') }}</span>
          <div class="kpi-card__value">{{ formatCurrency(stats.totalOutstanding) }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--danger" v-html="icon('wallet')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span class="kpi-card__change kpi-card__change--flat">{{ t('page.consultant.pendingCollection') }}</span>
        <div class="kpi-card__sparkline"></div>
      </div>
    </div>
  </div>

  <!-- Bottom Section -->
  <div class="dashboard-bottom">
    <!-- Expiring Reservations -->
    <div class="card animate-fade-in-up" style="animation-delay: 250ms">
      <div class="card__header">
        <h3 class="card__title"><span v-html="icon('alert-triangle', 18)"></span> {{ t('page.consultant.expiringTitle') }}</h3>
      </div>
      <div class="card__body">
        <p v-if="reservations.length === 0" style="text-align:center; color:var(--color-text-tertiary); margin:0">{{ t('page.consultant.noReservations') }}</p>
        <template v-else>
          <div v-for="r in reservations" :key="r.invoiceId + '-' + r.productName"
            :class="['reservation-alert', { 'reservation-alert--urgent': r.daysRemaining <= 3 }]"
            style="cursor:pointer"
            @click="$router.push(`/invoices/${r.invoiceId}`)">
            <span :class="['reservation-alert__days', { 'text-danger': r.daysRemaining <= 3 }]">{{ t('common.daysLeft', { n: r.daysRemaining }) }}</span>
            <div style="flex:1">
              <div style="font-weight:500; color:var(--color-text-primary)">{{ r.productName }}</div>
              <div style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ r.invoiceNumber }} - {{ r.customerName }}</div>
            </div>
            <span v-if="r.daysRemaining <= 3" class="badge badge--danger">{{ t('common.urgent') }}</span>
          </div>
        </template>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="card animate-fade-in-up" style="animation-delay: 300ms">
      <div class="card__header">
        <h3 class="card__title">{{ t('page.consultant.recentInvoices') }}</h3>
        <router-link to="/invoices" class="btn btn--ghost btn--sm">{{ t('btn.viewAll') }} <span v-html="icon('chevron-right', 14)"></span></router-link>
      </div>
      <div class="card__body" style="padding:0">
        <p v-if="recentInvoices.length === 0" style="text-align:center; color:var(--color-text-tertiary); padding:var(--space-6)">{{ t('page.consultant.noInvoices') }}</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('col.invoiceNumber') }}</th>
              <th>{{ t('col.customer') }}</th>
              <th>{{ t('col.total') }}</th>
              <th>{{ t('col.status') }}</th>
              <th class="text-center" style="width:60px">{{ t('col.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in recentInvoices" :key="inv.id" style="cursor:pointer" @click="$router.push(`/invoices/${inv.id}`)">
              <td><strong>{{ inv.number }}</strong></td>
              <td class="text-truncate" style="max-width:150px">{{ getCustomerName(inv.customerId) }}</td>
              <td>{{ formatCurrency(inv.totalAmount) }}</td>
              <td><AppBadge :label="tLabel(getInvoiceLifecycle(inv))" :color="getInvoiceLifecycle(inv).color" :dot="true" /></td>
              <td class="text-center" @click.stop>
                <InvoiceActions :invoice="inv" @view="$router.push(`/invoices/${inv.id}`)" @edit="$router.push(`/invoices/${inv.id}/edit`)" @mark-sold="markAsSold(inv)" @delete="deleteInvoice(inv)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <ConfirmDialog v-model:visible="confirmVisible" :title="confirmTitle" :message="confirmMessage" :danger="confirmDanger" @confirm="confirmCallback" />
</div>
</template>
<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, daysRemaining, generateTrendlineData } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { LIFECYCLE_LABELS, getInvoiceLifecycle } from '@/data'
import DateFilter from '@/components/ui/DateFilter.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import SparklineChart from '@/components/ui/SparklineChart.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'

const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const { showToast } = useToast()
const { t, tLabel } = useI18n()

const dateFrom = ref('')
const dateTo = ref('')

// Confirm dialog
const confirmVisible = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
const confirmDanger = ref(false)
let confirmCallback = () => {}

const myInvoices = computed(() => authStore.getMyInvoices(mainStore.invoices))

const stats = computed(() => {
  let invs = myInvoices.value
  if (dateFrom.value) invs = invs.filter(inv => inv.createdAt >= dateFrom.value)
  if (dateTo.value) invs = invs.filter(inv => inv.createdAt <= dateTo.value)
  const standard = invs.filter(inv => inv.status === 'standard')
  const totalRevenue = standard.reduce((sum, inv) => sum + inv.paidAmount, 0)
  const totalOutstanding = standard
    .filter(inv => getInvoiceLifecycle(inv).label !== 'Sold')
    .reduce((sum, inv) => sum + Math.max(0, inv.totalAmount - inv.paidAmount), 0)
  const now = new Date()
  const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
  const thisMonth = invs.filter(inv => inv.createdAt >= firstOfMonth).length

  const monthlyRevenue = []
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const start = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]
    monthlyRevenue.push(standard.filter(inv => inv.createdAt >= start && inv.createdAt <= end).reduce((s, inv) => s + inv.paidAmount, 0))
  }

  return { totalRevenue, totalInvoices: invs.length, thisMonth, totalOutstanding, monthlyRevenue }
})

const reservations = computed(() => {
  const result = []
  let invs = myInvoices.value
  if (dateFrom.value) invs = invs.filter(inv => inv.createdAt >= dateFrom.value)
  if (dateTo.value) invs = invs.filter(inv => inv.createdAt <= dateTo.value)
  invs
    .filter(inv => inv.lifecycleStatus === 'active')
    .forEach(inv => {
      inv.items.forEach(item => {
        if (item.itemStatus === 'reserved' && item.reservationDays > 0) {
          const remaining = daysRemaining(inv.createdAt, item.reservationDays)
          const product = mainStore.productById(item.productId)
          const customer = mainStore.customerById(inv.customerId)
          result.push({
            invoiceId: inv.id,
            invoiceNumber: inv.number,
            productName: product ? product.name : t('common.unknown'),
            customerName: customer ? customer.name : t('common.unknown'),
            daysRemaining: remaining,
            totalDays: item.reservationDays
          })
        }
      })
    })
  return result.sort((a, b) => a.daysRemaining - b.daysRemaining)
})

const urgentReservations = computed(() => reservations.value.filter(r => r.daysRemaining <= 3))

const revenueChange = computed(() => {
  const data = stats.value.monthlyRevenue
  if (!data || data.length < 2) return null
  const prev = data[data.length - 2]
  const curr = data[data.length - 1]
  if (!prev) return null
  return ((curr - prev) / prev * 100).toFixed(1)
})

const recentInvoices = computed(() => {
  let invs = myInvoices.value
  if (dateFrom.value) invs = invs.filter(inv => inv.createdAt >= dateFrom.value)
  if (dateTo.value) invs = invs.filter(inv => inv.createdAt <= dateTo.value)
  return [...invs].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 5)
})

function drillDown(path, highlightCol) {
  let url = path
  const params = []
  if (dateFrom.value) params.push(`dateFrom=${dateFrom.value}`)
  if (dateTo.value) params.push(`dateTo=${dateTo.value}`)
  if (params.length) url += (url.includes('?') ? '&' : '?') + params.join('&')
  if (highlightCol) navStore.setDrilldownHighlight(highlightCol)
  router.push(url)
}

function getCustomerName(id) {
  const c = mainStore.customerById(id)
  return c ? c.name : '—'
}

function markAsSold(inv) {
  confirmTitle.value = t('msg.titleMarkSold')
  confirmMessage.value = t('msg.confirmMarkSold', { number: inv.number })
  confirmDanger.value = false
  confirmCallback = () => {
    const fresh = mainStore.invoiceById(inv.id)
    if (fresh) {
      if (fresh.totalAmount - fresh.paidAmount > 0) {
        showToast('warning', t('msg.outstandingWarning', { number: fresh.number }))
        return
      }
      fresh.items.forEach(item => { if (item.itemStatus !== 'none') item.itemStatus = 'sold' })
      fresh.saleDate = fresh.saleDate || new Date().toISOString().split('T')[0]
      fresh.soldDate = fresh.soldDate || new Date().toISOString().split('T')[0]
      fresh.lifecycleStatus = 'sold'
      mainStore.saveInvoice({ ...fresh })
      showToast('success', t('msg.invoiceMarkedSold', { number: fresh.number }))
    }
  }
  confirmVisible.value = true
}

function deleteInvoice(inv) {
  confirmTitle.value = t('msg.titleDeleteInvoice')
  confirmMessage.value = t('msg.confirmDelete', { number: inv.number })
  confirmDanger.value = true
  confirmCallback = () => {
    mainStore.deleteInvoice(inv.id)
    showToast('success', t('msg.invoiceDeleted'))
  }
  confirmVisible.value = true
}
</script>
