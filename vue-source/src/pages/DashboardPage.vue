<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.dashboard.title') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ t('page.dashboard.subtitle') }}</p>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom: var(--space-4)">
    <DateFilter v-model:dateFrom="dateFrom" v-model:dateTo="dateTo" prefix="dash" @change="() => {}" />
  </div>

  <!-- KPI Cards -->
  <div class="kpi-grid">
    <!-- Row 1: Core Business -->
    <div class="card kpi-card animate-fade-in-up" @click="drillDown('/invoices?filter=standard')" style="cursor:pointer; animation-delay: 0ms">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.dashboard.standardInvoices') }}</span>
          <div class="kpi-card__value">{{ kpi.totalInvoices }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--primary" v-html="icon('file-text')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span v-html="changeHtml(kpi.invoicesChange)"></span>
        <div class="kpi-card__sparkline"><SparklineChart :data="generateTrendlineData(kpi.invoicesChange)" color="#4f46e5" /></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" @click="drillDown('/invoices?filter=reserved', 'lifecycle')" style="cursor:pointer; animation-delay: 40ms">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.dashboard.pendingReservations') }}</span>
          <div class="kpi-card__value">{{ kpi.pendingReservations }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--warning" v-html="icon('clock')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span class="kpi-card__change kpi-card__change--flat"><span v-html="icon('alert-triangle', 12)"></span> {{ t('page.dashboard.requiresAttention') }}</span>
        <div class="kpi-card__sparkline"></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" @click="drillDown('/statistics')" style="cursor:pointer; animation-delay: 80ms">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.dashboard.grossRevenue') }}</span>
          <div class="kpi-card__value">{{ formatCurrency(kpi.grossRevenue) }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--success" v-html="icon('dollar-sign')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span v-html="changeHtml(kpi.revenueChange)"></span>
        <div class="kpi-card__sparkline"><SparklineChart :data="generateTrendlineData(kpi.revenueChange)" color="#16a34a" /></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" @click="drillDown('/statistics')" style="cursor:pointer; animation-delay: 120ms">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.dashboard.totalPaid') }}</span>
          <div class="kpi-card__value">{{ formatCurrency(kpi.totalPaid) }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--info" v-html="icon('check-circle')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span v-html="changeHtml(kpi.paidChange)"></span>
        <div class="kpi-card__sparkline"><SparklineChart :data="generateTrendlineData(kpi.paidChange)" color="#0ea5e9" /></div>
      </div>
    </div>

    <div class="card kpi-card animate-fade-in-up" @click="drillDown('/invoices?filter=outstanding', 'remaining')" style="cursor:pointer; animation-delay: 160ms">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('page.dashboard.outstandingBalance') }}</span>
          <div class="kpi-card__value">{{ formatCurrency(kpi.outstandingBalance) }}</div>
        </div>
        <div class="kpi-card__icon kpi-card__icon--danger" v-html="icon('wallet')"></div>
      </div>
      <div class="kpi-card__bottom">
        <span v-html="changeHtml(kpi.outstandingChange)"></span>
        <div class="kpi-card__sparkline"><SparklineChart :data="generateTrendlineData(kpi.outstandingChange)" color="#dc2626" /></div>
      </div>
    </div>

    <!-- Row 2: Payment Methods -->
    <div v-for="(pm, key) in paymentKPIs" :key="key" class="card kpi-card animate-fade-in-up" @click="drillDown(`/invoices?paymentMethod=${key}`, 'payment')" style="cursor:pointer" :style="{ animationDelay: pm.delay + 'ms' }">
      <div class="kpi-card__top">
        <div>
          <span class="kpi-card__label">{{ t('payment.totalLabel') }} {{ tLabel(PAYMENT_METHODS[key]) }}</span>
          <div class="kpi-card__value">{{ formatCurrency(kpi.methodTotals[key] || 0) }}</div>
        </div>
        <div :class="['kpi-card__icon', `kpi-card__icon--${pm.iconColor}`]" v-html="icon(pm.iconName)"></div>
      </div>
      <div class="kpi-card__bottom">
        <span class="kpi-card__change kpi-card__change--flat">{{ tLabel(PAYMENT_METHODS[key]) }}</span>
        <div class="kpi-card__sparkline"></div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <div class="dashboard-charts">
    <div class="card animate-fade-in-up" style="animation-delay: 200ms">
      <div class="card__header"><h3 class="card__title">{{ t('page.dashboard.monthlyRevenue') }}</h3></div>
      <div class="chart-container"><canvas ref="revenueChartRef"></canvas></div>
    </div>
    <div class="card animate-fade-in-up" style="animation-delay: 250ms">
      <div class="card__header"><h3 class="card__title">{{ t('page.dashboard.topSellers') }}</h3></div>
      <div class="chart-container"><canvas ref="topProductsChartRef"></canvas></div>
    </div>
  </div>

  <!-- Bottom Section -->
  <div class="dashboard-bottom">
    <div class="card animate-fade-in-up" style="animation-delay: 300ms">
      <div class="card__header">
        <h3 class="card__title">{{ t('page.dashboard.recentInvoices') }}</h3>
        <router-link to="/invoices" class="btn btn--ghost btn--sm">{{ t('btn.viewAll') }} <span v-html="icon('chevron-right', 14)"></span></router-link>
      </div>
      <div class="card__body" style="padding:0">
        <table class="data-table">
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
            <tr v-for="inv in pagedRecentInvoices" :key="inv.id" style="cursor:pointer" @click="$router.push(`/invoices/${inv.id}`)">
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
      <AppPagination
        :currentPage="invPage"
        :totalPages="invTotalPages"
        :total="recentInvoices.length"
        @page-change="invPage = $event"
      />
    </div>

    <div class="card animate-fade-in-up" style="animation-delay: 350ms">
      <div class="card__header"><h3 class="card__title">{{ t('page.dashboard.expiringReservations') }}</h3></div>
      <div class="card__body" style="padding:0">
        <p v-if="expiringReservations.length === 0" style="text-align:center; color:var(--color-text-tertiary); margin:0; padding:var(--space-5) var(--space-6)">{{ t('page.dashboard.noExpiring') }}</p>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ t('col.product') }}</th>
              <th>{{ t('col.sku') }}</th>
              <th>{{ t('col.invoiceNumber') }}</th>
              <th>{{ t('col.customer') }}</th>
              <th class="text-center">{{ t('col.daysLeft') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in pagedReservations" :key="r.invoiceNumber + r.productName">
              <td class="text-truncate" style="max-width:140px">{{ r.productName }}</td>
              <td>{{ r.sku }}</td>
              <td><strong>{{ r.invoiceNumber }}</strong></td>
              <td class="text-truncate" style="max-width:120px">{{ r.customerName }}</td>
              <td class="text-center">
                <AppBadge :label="r.daysRemaining + 'd'" :color="r.daysRemaining <= 3 ? 'danger' : r.daysRemaining <= 7 ? 'warning' : 'neutral'" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <AppPagination
        :currentPage="resPage"
        :totalPages="resTotalPages"
        :total="expiringReservations.length"
        @page-change="resPage = $event"
      />
    </div>
  </div>

  <ConfirmDialog v-model:visible="confirmVisible" :title="confirmTitle" :message="confirmMessage" :danger="confirmDanger" @confirm="confirmCallback" />
</div>
</template>
<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, formatNumber, daysRemaining, generateTrendlineData } from '@/composables/useFormatters'
import { useToast } from '@/composables/useToast'
import { usePagination } from '@/composables/usePagination'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { LIFECYCLE_LABELS, PAYMENT_METHODS, getInvoiceLifecycle, getEffectiveTotal } from '@/data'
import DateFilter from '@/components/ui/DateFilter.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import SparklineChart from '@/components/ui/SparklineChart.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'
import Chart from 'chart.js/auto'

const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const { showToast } = useToast()
const { t, tLabel, locale } = useI18n()

const dateFrom = ref('')
const dateTo = ref('')
const revenueChartRef = ref(null)
const topProductsChartRef = ref(null)
let charts = []

const paymentKPIs = {
  cash: { iconColor: 'success', iconName: 'dollar-sign', delay: 200 },
  company_transfer: { iconColor: 'primary', iconName: 'credit-card', delay: 240 },
  credit: { iconColor: 'info', iconName: 'trending-up', delay: 280 },
  consignment: { iconColor: 'warning', iconName: 'package', delay: 320 },
  other: { iconColor: 'danger', iconName: 'info', delay: 360 }
}

// Confirm dialog
const confirmVisible = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
const confirmDanger = ref(false)
let confirmCallback = () => {}

const kpi = computed(() => {
  // Gross Revenue + Invoice count: filtered by invoice.createdAt
  let invoicesByDate = mainStore.invoices
  if (dateFrom.value) invoicesByDate = invoicesByDate.filter(inv => inv.createdAt >= dateFrom.value)
  if (dateTo.value) invoicesByDate = invoicesByDate.filter(inv => inv.createdAt <= dateTo.value)
  const allStandardByDate = invoicesByDate.filter(inv => inv.status === 'standard')
  const standardByDate = allStandardByDate.filter(inv => getInvoiceLifecycle(inv).label !== 'Canceled')
  const grossRevenue = standardByDate.reduce((s, inv) => s + getEffectiveTotal(inv), 0)
  const totalInvoices = allStandardByDate.length
  const pendingReservations = invoicesByDate.filter(inv => getInvoiceLifecycle(inv).label === 'Reserved').length
  const outstandingBalance = standardByDate.filter(inv => getInvoiceLifecycle(inv).label !== 'Sold').reduce((s, inv) => s + Math.max(0, getEffectiveTotal(inv) - inv.paidAmount), 0)

  // Total Paid + Method Totals: filtered by payment.date (independent of invoice date)
  const methodTotals = { cash: 0, company_transfer: 0, credit: 0, consignment: 0, other: 0 }
  let totalPaid = 0
  mainStore.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled').forEach(inv => {
    inv.payments.forEach(p => {
      if ((dateFrom.value && p.date < dateFrom.value) || (dateTo.value && p.date > dateTo.value)) return
      if (p.method in methodTotals) methodTotals[p.method] += p.amount
      if (p.method !== 'consignment') totalPaid += p.amount
    })
  })

  const trend = mainStore.getMonthlyTrend(6)
  const invoicesChange = mainStore.getMonthOverMonthChange(totalInvoices, trend.map(t => t.count))
  const revenueChange = mainStore.getMonthOverMonthChange(grossRevenue, trend.map(t => t.revenue))
  const paidChange = mainStore.getMonthOverMonthChange(totalPaid, trend.map(t => t.paid))
  const outstandingChange = mainStore.getMonthOverMonthChange(outstandingBalance, trend.map(t => t.outstanding))

  return { totalInvoices, pendingReservations, grossRevenue, totalPaid, outstandingBalance, methodTotals, trend, invoicesChange, revenueChange, paidChange, outstandingChange }
})

const recentInvoices = computed(() => [...mainStore.invoices].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)))

const { currentPage: invPage, totalPages: invTotalPages, paginate: invPaginate } = usePagination(10)
const pagedRecentInvoices = computed(() => invPaginate(recentInvoices.value))

const expiringReservations = computed(() => {
  const reservations = []
  mainStore.invoices.filter(inv => inv.lifecycleStatus === 'active').forEach(inv => {
    inv.items.forEach(item => {
      if (item.itemStatus === 'reserved' && item.reservationDays > 0) {
        const remaining = daysRemaining(inv.createdAt, item.reservationDays)
        const product = mainStore.productById(item.productId)
        const customer = mainStore.customerById(inv.customerId)
        reservations.push({ invoiceNumber: inv.number, productName: product ? product.name : t('common.unknown'), sku: product ? (product.sku || '—') : '—', customerName: customer ? customer.name : t('common.unknown'), daysRemaining: remaining, totalDays: item.reservationDays })
      }
    })
  })
  return reservations.sort((a, b) => a.daysRemaining - b.daysRemaining)
})

const { currentPage: resPage, totalPages: resTotalPages, paginate: resPaginate } = usePagination(10)
const pagedReservations = computed(() => resPaginate(expiringReservations.value))

function changeHtml(val) {
  if (val === null) return '<span class="kpi-card__change kpi-card__change--flat">—</span>'
  const num = parseFloat(val)
  if (num > 0) return `<span class="kpi-card__change kpi-card__change--up">${icon('trending-up', 12)} +${val}%</span>`
  if (num < 0) return `<span class="kpi-card__change kpi-card__change--down">${icon('trending-down', 12)} ${val}%</span>`
  return '<span class="kpi-card__change kpi-card__change--flat">0%</span>'
}

function drillDown(path, highlightCol) {
  let url = path
  const params = []
  if (dateFrom.value) params.push(`dateFrom=${dateFrom.value}`)
  if (dateTo.value) params.push(`dateTo=${dateTo.value}`)
  if (params.length) url += (url.includes('?') ? '&' : '?') + params.join('&')
  if (highlightCol) navStore.setDrilldownHighlight(highlightCol)
  else if (path.includes('paymentMethod=')) navStore.setDrilldownHighlight('payment')
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

function initCharts() {
  destroyCharts()
  const style = getComputedStyle(document.documentElement)
  const primary = style.getPropertyValue('--color-primary').trim() || '#4f46e5'
  const textSecondary = style.getPropertyValue('--color-text-secondary').trim() || '#475569'
  const border = style.getPropertyValue('--color-border').trim() || '#e2e8f0'
  Chart.defaults.font.family = "'FiraGO', sans-serif"
  Chart.defaults.color = textSecondary

  // Revenue Chart
  const now = new Date()
  const monthlyData = []
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const monthStr = d.toLocaleDateString('en', { month: 'short', year: '2-digit' })
    const start = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]
    const revenue = mainStore.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled' && inv.createdAt >= start && inv.createdAt <= end).reduce((sum, inv) => sum + getEffectiveTotal(inv), 0)
    monthlyData.push({ label: monthStr, value: revenue })
  }

  if (revenueChartRef.value) {
    charts.push(new Chart(revenueChartRef.value, {
      type: 'bar',
      data: { labels: monthlyData.map(m => m.label), datasets: [{ label: t('chart.revenue'), data: monthlyData.map(m => m.value), backgroundColor: primary + 'cc', borderColor: primary, borderWidth: 1, borderRadius: 6, borderSkipped: false, barPercentage: 0.6 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => formatCurrency(ctx.raw) } } }, scales: { y: { beginAtZero: true, grid: { color: border + '80' }, ticks: { callback: v => formatCurrency(v) } }, x: { grid: { display: false } } } }
    }))
  }

  // Top Products Chart
  const productSales = {}
  mainStore.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled').forEach(inv => { inv.items.forEach(item => { productSales[item.productId] = (productSales[item.productId] || 0) + item.qty * item.price }) })
  const topProducts = Object.entries(productSales).map(([pid, revenue]) => { const product = mainStore.productById(parseInt(pid)); return { name: product ? product.name : t('common.unknown'), revenue } }).sort((a, b) => b.revenue - a.revenue).slice(0, 5)
  const colors = [primary, '#16a34a', '#f59e0b', '#0ea5e9', '#dc2626']

  if (topProductsChartRef.value) {
    charts.push(new Chart(topProductsChartRef.value, {
      type: 'bar',
      data: { labels: topProducts.map(p => p.name.length > 20 ? p.name.slice(0, 20) + '...' : p.name), datasets: [{ label: t('chart.revenue'), data: topProducts.map(p => p.revenue), backgroundColor: colors.map(c => c + 'cc'), borderColor: colors, borderWidth: 1, borderRadius: 6, borderSkipped: false, barPercentage: 0.6 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => formatCurrency(ctx.raw) } } }, scales: { x: { beginAtZero: true, grid: { color: border + '80' }, ticks: { callback: v => formatCurrency(v) } }, y: { grid: { display: false } } } }
    }))
  }
}

function destroyCharts() { charts.forEach(c => c.destroy()); charts = [] }

onMounted(() => initCharts())
onUnmounted(() => destroyCharts())
watch(() => mainStore.theme, () => { initCharts() })
watch(locale, () => { initCharts() })
</script>
