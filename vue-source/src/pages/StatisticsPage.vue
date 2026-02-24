<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.statistics.title') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ t('page.statistics.subtitle') }}</p>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom:var(--space-4)">
    <DateFilter v-model:dateFrom="dateFrom" v-model:dateTo="dateTo" prefix="stats" @change="onDateChange" />
  </div>

  <AppTabs :tabs="TABS" v-model="activeTab" />

  <div class="tab-content" style="margin-top:var(--space-6)">
    <!-- Overview Tab -->
    <div v-if="activeTab === 'overview'">
      <div class="stats-kpi-grid">
        <div class="card stat-card" @click="statNav('/invoices')" style="cursor:pointer">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.totalRevenue') }}</span><div class="stat-card__icon stat-card__icon--success" v-html="icon('dollar-sign')"></div></div>
          <div class="stat-card__value">{{ formatCurrency(overviewStats.totalRevenue) }}</div>
        </div>
        <div class="card stat-card" @click="statNav('/invoices')" style="cursor:pointer">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.totalInvoices') }}</span><div class="stat-card__icon stat-card__icon--primary" v-html="icon('file-text')"></div></div>
          <div class="stat-card__value">{{ overviewStats.totalInvoices }}</div>
        </div>
        <div class="card stat-card" @click="statNav('/invoices?filter=reserved', 'lifecycle')" style="cursor:pointer">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.pendingReservations') }}</span><div class="stat-card__icon stat-card__icon--warning" v-html="icon('clock')"></div></div>
          <div class="stat-card__value">{{ overviewStats.pendingReservations }}</div>
        </div>
        <div class="card stat-card" @click="statNav('/invoices?filter=outstanding', 'remaining')" style="cursor:pointer">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.outstanding') }}</span><div class="stat-card__icon stat-card__icon--danger" v-html="icon('wallet')"></div></div>
          <div class="stat-card__value">{{ formatCurrency(overviewStats.outstanding) }}</div>
        </div>
      </div>

      <!-- Payment Breakdown -->
      <div class="card" style="margin-bottom: var(--space-6)">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.methodBreakdown') }}</h3></div>
        <div class="card__body" style="padding:0">
          <div class="data-table-wrapper">
          <table class="data-table">
            <thead><tr><th>{{ t('col.method') }}</th><th>{{ t('col.amount') }}</th><th>{{ t('col.share') }}</th><th>{{ t('col.distribution') }}</th></tr></thead>
            <tbody>
              <tr v-for="[method, amount] in overviewStats.methodEntries" :key="method">
                <td class="font-medium">{{ PAYMENT_METHODS[method] ? tLabel(PAYMENT_METHODS[method]) : method }}</td>
                <td class="font-medium">{{ formatCurrency(amount) }}</td>
                <td>{{ overviewStats.methodTotal > 0 ? (amount / overviewStats.methodTotal * 100).toFixed(1) : 0 }}%</td>
                <td>
                  <div style="background:var(--color-bg-tertiary); border-radius:var(--radius-full); height:8px; overflow:hidden">
                    <div style="background:var(--color-primary); height:100%; border-radius:var(--radius-full)" :style="{ width: (overviewStats.methodTotal > 0 ? amount / overviewStats.methodTotal * 100 : 0) + '%' }"></div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <div class="stats-charts">
        <div class="card">
          <div class="card__header"><h3 class="card__title">{{ t('page.statistics.revenueVsCash') }}</h3></div>
          <div class="chart-container"><canvas ref="revenueChartRef"></canvas></div>
        </div>
        <div class="card">
          <div class="card__header"><h3 class="card__title">{{ t('page.statistics.distribution') }}</h3></div>
          <div class="chart-container"><canvas ref="distributionChartRef"></canvas></div>
        </div>
      </div>

      <div class="card">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.topUsers') }}</h3></div>
        <div class="card__body" style="padding:0">
          <div class="data-table-wrapper">
          <table class="data-table">
            <thead><tr><th>{{ t('col.user') }}</th><th>{{ t('col.invoices') }}</th><th>{{ t('col.revenue') }}</th><th>{{ t('col.average') }}</th></tr></thead>
            <tbody>
              <tr v-for="user in topUsers" :key="user.id">
                <td>
                  <div style="display:flex; align-items:center; gap:var(--space-2)">
                    <div class="sidebar__user-avatar" style="width:30px;height:30px;font-size:var(--text-xs)">{{ user.avatar }}</div>
                    <span class="font-medium">{{ user.name }}</span>
                  </div>
                </td>
                <td>{{ user.invoiceCount }}</td>
                <td class="font-medium">{{ formatCurrency(user.revenue) }}</td>
                <td>{{ formatCurrency(user.invoiceCount > 0 ? user.revenue / user.invoiceCount : 0) }}</td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Fictive Tab -->
    <div v-if="activeTab === 'fictive'">
      <div class="stats-kpi-grid" style="grid-template-columns: repeat(3, 1fr)">
        <div class="card stat-card">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.fictiveInvoices') }}</span><div class="stat-card__icon stat-card__icon--warning" v-html="icon('file-text')"></div></div>
          <div class="stat-card__value">{{ fictiveStats.count }}</div>
        </div>
        <div class="card stat-card">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.totalValue') }}</span><div class="stat-card__icon stat-card__icon--info" v-html="icon('dollar-sign')"></div></div>
          <div class="stat-card__value">{{ formatCurrency(fictiveStats.totalAmount) }}</div>
        </div>
        <div class="card stat-card">
          <div class="stat-card__header"><span class="stat-card__label">{{ t('page.statistics.conversionRate') }}</span><div class="stat-card__icon stat-card__icon--success" v-html="icon('trending-up')"></div></div>
          <div class="stat-card__value">{{ fictiveStats.convRate }}%</div>
        </div>
      </div>

      <div class="card">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.fictiveList') }}</h3></div>
        <div class="card__body" style="padding:0">
          <EmptyState v-if="fictiveStats.invoices.length === 0" :title="t('page.statistics.noFictive')" :message="t('page.statistics.allStandard')" />
          <div v-else class="data-table-wrapper">
          <table class="data-table">
            <thead><tr><th>{{ t('col.invoiceNumber') }}</th><th>{{ t('col.date') }}</th><th>{{ t('col.customer') }}</th><th>{{ t('col.amount') }}</th><th>{{ t('col.author') }}</th><th class="text-center" style="width:60px">{{ t('col.actions') }}</th></tr></thead>
            <tbody>
              <tr v-for="inv in fictiveStats.invoices" :key="inv.id">
                <td><a @click.prevent="viewFictive(inv.id)" :href="`#/invoices/${inv.id}`" class="font-semibold">{{ inv.number }}</a></td>
                <td>{{ formatDate(inv.createdAt) }}</td>
                <td>{{ getCustomerName(inv.customerId) }}</td>
                <td class="font-medium">{{ formatCurrency(inv.totalAmount) }}</td>
                <td>{{ getUserName(inv.authorId) }}</td>
                <td class="text-center">
                  <InvoiceActions :invoice="inv" @view="viewFictive(inv.id)" @edit="$router.push(`/invoices/${inv.id}/edit`)" @mark-sold="markAsSold(inv)" @delete="deleteInvoice(inv)" />
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Deposits Tab -->
    <div v-if="activeTab === 'deposits'">
      <div class="deposit-balance-card">
        <div class="deposit-balance-card__label">{{ t('page.statistics.currentBalance') }}</div>
        <div class="deposit-balance-card__value">{{ formatCurrency(mainStore.depositBalance) }}</div>
      </div>

      <div class="card" style="margin-bottom: var(--space-6)">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.addTransaction') }}</h3></div>
        <div class="card__body">
          <div class="deposit-form">
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('form.date') }}</label>
              <input type="date" class="form-input" v-model="depDate">
            </div>
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('form.amount') }}</label>
              <input type="number" class="form-input" v-model.number="depAmount" step="0.01" placeholder="0.00">
            </div>
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('form.type') }}</label>
              <select class="form-select" v-model="depType">
                <option value="credit">{{ t('filter.creditIn') }}</option>
                <option value="debit">{{ t('filter.debitOut') }}</option>
              </select>
            </div>
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('col.note') }}</label>
              <div style="display:flex; gap:var(--space-2)">
                <input type="text" class="form-input" v-model="depNote" :placeholder="t('form.description')">
                <button class="btn btn--primary" @click="addDeposit"><span v-html="icon('plus', 14)"></span> {{ t('btn.add') }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.txHistory') }}</h3></div>
        <div class="card__body" style="padding:0">
          <table class="data-table">
            <thead><tr><th>{{ t('col.date') }}</th><th>{{ t('form.type') }}</th><th>{{ t('col.amount') }}</th><th>{{ t('col.note') }}</th><th class="text-center">{{ t('col.actions') }}</th></tr></thead>
            <tbody>
              <tr v-for="dep in sortedDeposits" :key="dep.id">
                <td>{{ formatDate(dep.date) }}</td>
                <td><AppBadge :label="dep.type === 'credit' ? t('filter.creditIn') : t('filter.debitOut')" :color="dep.type === 'credit' ? 'success' : 'danger'" /></td>
                <td :class="['font-medium', dep.amount >= 0 ? 'text-success' : 'text-danger']">{{ formatCurrency(Math.abs(dep.amount)) }}</td>
                <td style="max-width:250px" class="text-truncate">{{ dep.note }}</td>
                <td class="text-center">
                  <button class="btn btn--ghost btn--icon btn--sm" @click="deleteDeposit(dep.id)"><span v-html="icon('trash-2', 14)"></span></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Other Balance Tab -->
    <div v-if="activeTab === 'other'">
      <!-- 3 KPI Cards -->
      <div class="stats-kpi-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: var(--space-6)">
        <div class="card stat-card">
          <div class="stat-card__header">
            <span class="stat-card__label">{{ t('page.statistics.accumulated') }}</span>
            <div class="stat-card__icon stat-card__icon--primary" v-html="icon('trending-up')"></div>
          </div>
          <div class="stat-card__value">{{ formatCurrency(otherStats.accumulated) }}</div>
          <div class="stat-card__sub">{{ t('page.statistics.selectedPeriod') }}</div>
        </div>
        <div class="card stat-card">
          <div class="stat-card__header">
            <span class="stat-card__label">{{ t('page.statistics.delivered') }}</span>
            <div class="stat-card__icon stat-card__icon--success" v-html="icon('check-circle')"></div>
          </div>
          <div class="stat-card__value">{{ formatCurrency(otherStats.delivered) }}</div>
          <div class="stat-card__sub">{{ t('page.statistics.selectedPeriod') }}</div>
        </div>
        <div class="card stat-card">
          <div class="stat-card__header">
            <span class="stat-card__label">{{ t('page.statistics.currentBalance') }}</span>
            <div class="stat-card__icon" :class="otherStats.balance >= 0 ? 'stat-card__icon--warning' : 'stat-card__icon--success'" v-html="icon('wallet')"></div>
          </div>
          <div class="stat-card__value" :style="{ color: otherStats.balance > 0 ? 'var(--color-warning)' : otherStats.balance < 0 ? 'var(--color-success)' : 'var(--color-text-primary)' }">
            {{ formatCurrency(Math.abs(otherStats.balance)) }}
          </div>
          <div class="stat-card__sub">{{ otherStats.balance > 0 ? t('page.statistics.remainingDebt') : otherStats.balance < 0 ? t('page.statistics.overDelivered') : t('page.statistics.fullySettled') }}</div>
        </div>
      </div>

      <!-- Add Record Form -->
      <div class="card" style="margin-bottom: var(--space-6)">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.addDelivery') }}</h3></div>
        <div class="card__body">
          <div class="deposit-form">
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('form.date') }}</label>
              <input type="date" class="form-input" v-model="odDate">
            </div>
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('form.amount') }}</label>
              <input type="number" class="form-input" v-model.number="odAmount" step="0.01" placeholder="0.00">
            </div>
            <div class="form-group" style="margin:0"><label class="form-label">{{ t('col.note') }}</label>
              <div style="display:flex; gap:var(--space-2)">
                <input type="text" class="form-input" v-model="odNote" :placeholder="t('form.description')">
                <button class="btn btn--primary" @click="addOtherDelivery"><span v-html="icon('plus', 14)"></span> {{ t('btn.add') }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Records Table -->
      <div class="card">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.deliveryRecords') }}</h3></div>
        <div class="card__body" style="padding:0">
          <EmptyState v-if="sortedOtherDeliveries.length === 0" :title="t('page.statistics.noRecords')" :message="t('page.statistics.addDeliveryAbove')" />
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>{{ t('col.date') }}</th>
                <th>{{ t('col.amount') }}</th>
                <th>{{ t('col.note') }}</th>
                <th class="text-center" style="width:100px">{{ t('col.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rec in sortedOtherDeliveries" :key="rec.id">
                <template v-if="editingOdId !== rec.id">
                  <td>{{ formatDate(rec.date) }}</td>
                  <td class="font-medium">{{ formatCurrency(rec.amount) }}</td>
                  <td style="max-width:260px" class="text-truncate">{{ rec.note || '—' }}</td>
                  <td class="text-center">
                    <div style="display:flex; gap:4px; justify-content:center">
                      <button class="btn btn--ghost btn--icon btn--sm" @click="startEditOd(rec)" data-tooltip="Edit"><span v-html="icon('edit', 14)"></span></button>
                      <button class="btn btn--ghost btn--icon btn--sm" style="color:var(--color-danger)" @click="confirmDeleteOd(rec.id)" data-tooltip="Delete"><span v-html="icon('trash-2', 14)"></span></button>
                    </div>
                  </td>
                </template>
                <template v-else>
                  <td><input type="date" class="form-input form-input--sm" v-model="editOdDate"></td>
                  <td><input type="number" class="form-input form-input--sm" v-model.number="editOdAmount" step="0.01"></td>
                  <td><input type="text" class="form-input form-input--sm" v-model="editOdNote" :placeholder="t('form.description')"></td>
                  <td class="text-center">
                    <div style="display:flex; gap:4px; justify-content:center">
                      <button class="btn btn--primary btn--sm" @click="saveEditOd(rec.id)"><span v-html="icon('check', 14)"></span></button>
                      <button class="btn btn--ghost btn--sm" @click="editingOdId = null"><span v-html="icon('x', 14)"></span></button>
                    </div>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Customer Insights Tab -->
    <div v-if="activeTab === 'customers'">
      <div class="card">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.customerAnalysis') }}</h3></div>
        <div class="card__body" style="padding:0">
          <table class="data-table">
            <thead>
              <tr>
                <SortableHeader :label="t('col.customer')" field="name" :sortField="custSortField" :sortDir="custSortDir" @sort="onCustSort" />
                <SortableHeader :label="t('col.taxId')" field="taxId" :sortField="custSortField" :sortDir="custSortDir" @sort="onCustSort" />
                <SortableHeader :label="t('page.statistics.totalPurchases')" field="filteredSpent" :sortField="custSortField" :sortDir="custSortDir" @sort="onCustSort" />
                <SortableHeader :label="t('col.invoices')" field="filteredCount" :sortField="custSortField" :sortDir="custSortDir" @sort="onCustSort" />
                <SortableHeader :label="t('col.outstanding')" field="filteredOutstanding" :sortField="custSortField" :sortDir="custSortDir" @sort="onCustSort" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in custPaginatedItems" :key="c.id" style="cursor:pointer" @click="$router.push('/customers')">
                <td class="font-medium">{{ c.name }}</td>
                <td>{{ c.taxId }}</td>
                <td class="font-medium">{{ formatCurrency(c.filteredSpent) }}</td>
                <td>{{ c.filteredCount }}</td>
                <td :class="c.filteredOutstanding > 0 ? 'text-danger font-semibold' : ''">{{ formatCurrency(c.filteredOutstanding) }}</td>
              </tr>
            </tbody>
          </table>
          <AppPagination :currentPage="custCurrentPage" :totalPages="custTotalPages" :total="customerInsights.length" @page-change="custCurrentPage = $event" />
        </div>
      </div>
    </div>

    <!-- Product Performance Tab -->
    <div v-if="activeTab === 'products'">
      <div class="card" style="margin-bottom: var(--space-6)">
        <div class="card__header"><h3 class="card__title">{{ t('page.statistics.topProducts') }}</h3></div>
        <div class="chart-container"><canvas ref="productChartRef"></canvas></div>
      </div>

      <div class="card">
        <div class="card__header">
          <h3 class="card__title">{{ t('page.statistics.productDetails') }}</h3>
          <div style="display:flex; align-items:center; gap:var(--space-2)">
            <input type="text" class="form-input form-input--search" v-model="productSearch" @input="onProductSearch" :placeholder="t('page.stock.searchPlaceholder')" style="min-width:250px">
            <button v-if="productSearch" class="btn btn--ghost btn--sm" @click="productSearch = ''; prodCurrentPage = 1"><span v-html="icon('x', 14)"></span></button>
          </div>
        </div>
        <div class="card__body" style="padding:0">
          <EmptyState v-if="prodPaginatedItems.length === 0" :title="t('common.noData')" :message="productSearch ? t('common.tryDifferent') : t('common.noProductData')" />
          <template v-else>
            <table class="data-table">
              <thead>
                <tr>
                  <SortableHeader :label="t('col.product')" field="name" :sortField="prodSortField" :sortDir="prodSortDir" @sort="onProdSort" />
                  <SortableHeader :label="t('col.sku')" field="sku" :sortField="prodSortField" :sortDir="prodSortDir" @sort="onProdSort" />
                  <th>{{ t('col.brand') }}</th>
                  <SortableHeader :label="t('col.units')" field="unitsSold" :sortField="prodSortField" :sortDir="prodSortDir" @sort="onProdSort" />
                  <SortableHeader :label="t('col.revenue')" field="revenue" :sortField="prodSortField" :sortDir="prodSortDir" @sort="onProdSort" />
                  <th>{{ t('col.share') }}</th>
                  <SortableHeader :label="t('col.avgPrice')" field="avgPrice" :sortField="prodSortField" :sortDir="prodSortDir" @sort="onProdSort" />
                  <SortableHeader :label="t('col.reserved')" field="reserved" :sortField="prodSortField" :sortDir="prodSortDir" @sort="onProdSort" />
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in prodPaginatedItems" :key="item._name">
                  <td class="font-medium">{{ item.product ? item.product.name : t('common.unknown') }}</td>
                  <td><code style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ item.product ? item.product.sku : '—' }}</code></td>
                  <td><AppBadge v-if="item._brand" :label="item._brand" color="neutral" /><span v-else>—</span></td>
                  <td>{{ formatNumber(item.unitsSold) }}</td>
                  <td class="font-medium">{{ formatCurrency(item.revenue) }}</td>
                  <td>
                    <div style="display:flex; align-items:center; gap:var(--space-2)">
                      <div style="flex:1; max-width:60px; background:var(--color-bg-tertiary); border-radius:var(--radius-full); height:6px; overflow:hidden">
                        <div style="background:var(--color-primary); height:100%; border-radius:var(--radius-full)" :style="{ width: prodTotalRevenue > 0 ? (item.revenue / prodTotalRevenue * 100) + '%' : '0%' }"></div>
                      </div>
                      <span style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ prodTotalRevenue > 0 ? (item.revenue / prodTotalRevenue * 100).toFixed(1) : 0 }}%</span>
                    </div>
                  </td>
                  <td>{{ formatCurrency(item.avgPrice) }}</td>
                  <td>
                    <AppBadge v-if="item.reserved > 0" :label="item.reserved.toString()" color="warning" />
                    <span v-else style="color:var(--color-text-tertiary)">0</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <AppPagination :currentPage="prodCurrentPage" :totalPages="prodTotalPages" :total="productPerformance.length" @page-change="prodCurrentPage = $event" />
          </template>
        </div>
      </div>
    </div>
  </div>

  <ConfirmDialog v-model:visible="confirmVisible" :title="confirmTitle" :message="confirmMessage" :danger="true" @confirm="confirmCallback" />
</div>
</template>
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, formatNumber, todayISO, generateId, debounce } from '@/composables/useFormatters'
import { icon } from '@/composables/useIcons'
import { useToast } from '@/composables/useToast'
import { useI18n } from '@/composables/useI18n'
import { useSortable } from '@/composables/useSortable'
import { usePagination } from '@/composables/usePagination'
import { PAYMENT_METHODS, LIFECYCLE_LABELS, getInvoiceLifecycle, getEffectiveTotal } from '@/data'
import AppTabs from '@/components/ui/AppTabs.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import DateFilter from '@/components/ui/DateFilter.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'
import Chart from 'chart.js/auto'

const router = useRouter()
const mainStore = useMainStore()
const navStore = useNavigationStore()
const { showToast } = useToast()
const { t, tLabel, locale } = useI18n()

const TABS = computed(() => [
  { id: 'overview', label: t('tab.overview') },
  { id: 'fictive', label: t('tab.fictive') },
  { id: 'deposits', label: t('tab.deposits') },
  { id: 'other', label: t('tab.other') },
  { id: 'customers', label: t('tab.customers') },
  { id: 'products', label: t('tab.products') }
])

const activeTab = ref('overview')
const dateFrom = ref('')
const dateTo = ref('')
let charts = []

// Deposit form
const depDate = ref(todayISO())
const depAmount = ref(0)
const depType = ref('credit')
const depNote = ref('')

// Other Balance tab
const odDate = ref(todayISO())
const odAmount = ref(0)
const odNote = ref('')
const editingOdId = ref(null)
const editOdDate = ref('')
const editOdAmount = ref(0)
const editOdNote = ref('')

// Product search
const productSearch = ref('')

// Confirm dialog
const confirmVisible = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
let confirmCallback = () => {}

// Customer sort/pagination
const { sortField: custSortField, sortDir: custSortDir, toggleSort: custToggleSort, sortItems: custSortItems } = useSortable('filteredSpent', 'desc')
const { currentPage: custCurrentPage, totalPages: custTotalPages, paginate: custPaginate, resetPage: custResetPage } = usePagination(10)

// Product sort/pagination
const { sortField: prodSortField, sortDir: prodSortDir, toggleSort: prodToggleSort, sortItems: prodSortItems } = useSortable('revenue', 'desc')
const { currentPage: prodCurrentPage, totalPages: prodTotalPages, paginate: prodPaginate, resetPage: prodResetPage } = usePagination(10)

// Chart refs
const revenueChartRef = ref(null)
const distributionChartRef = ref(null)
const productChartRef = ref(null)

function getFilteredInvoices(statusFilter) {
  let invoices = mainStore.invoices
  if (statusFilter) invoices = invoices.filter(inv => inv.status === statusFilter)
  if (statusFilter !== 'fictive') invoices = invoices.filter(inv => getInvoiceLifecycle(inv).label !== 'Canceled')
  if (dateFrom.value) invoices = invoices.filter(inv => inv.createdAt >= dateFrom.value)
  if (dateTo.value) invoices = invoices.filter(inv => inv.createdAt <= dateTo.value)
  return invoices
}

// Overview stats
const overviewStats = computed(() => {
  const invoices = getFilteredInvoices('standard')
  const totalRevenue = invoices.reduce((s, inv) => s + inv.paidAmount, 0)
  const totalInvoices = invoices.length
  const outstanding = invoices.reduce((s, inv) => s + Math.max(0, getEffectiveTotal(inv) - inv.paidAmount), 0)
  const pendingReservations = getFilteredInvoices().filter(inv => inv.lifecycleStatus === 'active' && inv.items.some(it => it.itemStatus === 'reserved')).length

  // Refund excluded from breakdown — it's a net adjustment to revenue, not a payment method
  const methodTotals = {}
  invoices.forEach(inv => {
    inv.payments.forEach(p => {
      if (p.method === 'refund') return
      methodTotals[p.method] = (methodTotals[p.method] || 0) + p.amount
    })
  })
  const methodEntries = Object.entries(methodTotals).sort((a, b) => b[1] - a[1])
  const methodTotal = methodEntries.reduce((s, [, v]) => s + v, 0)

  return { totalRevenue, totalInvoices, outstanding, pendingReservations, methodEntries, methodTotal }
})

const topUsers = computed(() => mainStore.users.filter(u => u.role !== 'accountant').sort((a, b) => b.revenue - a.revenue))

// Fictive stats
const fictiveStats = computed(() => {
  const invoices = getFilteredInvoices('fictive')
  const totalAmount = invoices.reduce((s, inv) => s + inv.totalAmount, 0)
  const allInvoices = getFilteredInvoices().length
  const convRate = allInvoices > 0 ? ((allInvoices - invoices.length) / allInvoices * 100).toFixed(1) : 0
  return { count: invoices.length, totalAmount, convRate, invoices }
})

// Sorted deposits
const sortedDeposits = computed(() => [...mainStore.deposits].sort((a, b) => new Date(b.date) - new Date(a.date)))

// Other Balance computed
const otherStats = computed(() => {
  const from = dateFrom.value
  const to = dateTo.value
  // Accumulated = "other" payments in selected period (by payment.date)
  let accumulated = 0
  mainStore.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled').forEach(inv => {
    inv.payments.forEach(p => {
      if (p.method !== 'other') return
      if (from && p.date < from) return
      if (to && p.date > to) return
      accumulated += p.amount
    })
  })
  // Delivered in selected period
  const delivered = mainStore.otherDeliveries
    .filter(r => (!from || r.date >= from) && (!to || r.date <= to))
    .reduce((s, r) => s + r.amount, 0)
  // Current balance = all-time accumulated minus all-time delivered
  let allTimeAccumulated = 0
  mainStore.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled').forEach(inv => {
    inv.payments.forEach(p => { if (p.method === 'other') allTimeAccumulated += p.amount })
  })
  const allTimeDelivered = mainStore.otherDeliveries.reduce((s, r) => s + r.amount, 0)
  const balance = allTimeAccumulated - allTimeDelivered
  return { accumulated, delivered, balance }
})

const sortedOtherDeliveries = computed(() =>
  [...mainStore.otherDeliveries].sort((a, b) => new Date(b.date) - new Date(a.date))
)

function addOtherDelivery() {
  if (odAmount.value <= 0) { showToast('warning', t('msg.enterValidAmount')); return }
  mainStore.addOtherDelivery({ id: generateId(mainStore.otherDeliveries), date: odDate.value, amount: odAmount.value, note: odNote.value })
  showToast('success', t('msg.recordAdded'))
  odAmount.value = 0
  odNote.value = ''
}

function startEditOd(rec) {
  editingOdId.value = rec.id
  editOdDate.value = rec.date
  editOdAmount.value = rec.amount
  editOdNote.value = rec.note
}

function saveEditOd(id) {
  if (editOdAmount.value <= 0) { showToast('warning', t('msg.enterValidAmount')); return }
  mainStore.updateOtherDelivery(id, { date: editOdDate.value, amount: editOdAmount.value, note: editOdNote.value })
  editingOdId.value = null
  showToast('success', t('msg.recordUpdated'))
}

function confirmDeleteOd(id) {
  confirmTitle.value = t('msg.titleDeleteRecord')
  confirmMessage.value = t('msg.confirmDeleteRecord')
  confirmCallback = () => {
    mainStore.deleteOtherDelivery(id)
    showToast('success', t('msg.recordDeleted'))
  }
  confirmVisible.value = true
}

// Customer insights
const customerInsights = computed(() => {
  const filteredInvoices = getFilteredInvoices('standard')
  return mainStore.customers.map(c => {
    const custInvoices = filteredInvoices.filter(inv => inv.customerId === c.id)
    return {
      ...c,
      filteredSpent: custInvoices.reduce((s, inv) => s + inv.paidAmount, 0),
      filteredCount: custInvoices.length,
      filteredOutstanding: custInvoices.reduce((s, inv) => s + Math.max(0, getEffectiveTotal(inv) - inv.paidAmount), 0)
    }
  })
})

const custPaginatedItems = computed(() => custPaginate(custSortItems(customerInsights.value)))

// Product performance
const productPerformance = computed(() => {
  const stats = {}
  getFilteredInvoices('standard').forEach(inv => {
    inv.items.forEach(item => {
      if (item.itemStatus === 'canceled') return
      if (!stats[item.productId]) stats[item.productId] = { unitsSold: 0, revenue: 0, reserved: 0, prices: [] }
      stats[item.productId].unitsSold += item.qty
      stats[item.productId].revenue += item.qty * item.price
      stats[item.productId].prices.push(item.price)
      if (item.itemStatus === 'reserved') stats[item.productId].reserved += item.qty
    })
  })

  let list = Object.entries(stats).map(([pid, s]) => {
    const product = mainStore.productById(parseInt(pid))
    return { ...s, product, _name: product ? product.name : '', _sku: product ? product.sku : '', _brand: product ? product.brand : '', avgPrice: s.prices.length > 0 ? s.prices.reduce((a, b) => a + b, 0) / s.prices.length : 0 }
  })

  if (productSearch.value) {
    const q = productSearch.value.toLowerCase()
    list = list.filter(item => item._name.toLowerCase().includes(q) || item._sku.toLowerCase().includes(q) || item._brand.toLowerCase().includes(q))
  }
  return list
})

const prodTotalRevenue = computed(() => productPerformance.value.reduce((s, p) => s + p.revenue, 0))

const prodPaginatedItems = computed(() => {
  const field = prodSortField.value === 'name' ? '_name' : prodSortField.value === 'sku' ? '_sku' : prodSortField.value
  return prodPaginate(prodSortItems(productPerformance.value, field))
})

function onDateChange() { destroyCharts(); nextTick(() => initTabCharts()) }
function onCustSort(field) { custToggleSort(field); custResetPage() }
function onProdSort(field) { prodToggleSort(field); prodResetPage() }
const debouncedProductSearch = debounce(() => { prodCurrentPage.value = 1 }, 250)
function onProductSearch() { debouncedProductSearch() }

function getCustomerName(id) { const c = mainStore.customerById(id); return c ? c.name : '—' }
function getUserName(id) { const u = mainStore.userById(id); return u ? u.name : '—' }

function statNav(path, highlight) {
  let url = path
  const params = []
  if (dateFrom.value) params.push(`dateFrom=${dateFrom.value}`)
  if (dateTo.value) params.push(`dateTo=${dateTo.value}`)
  if (params.length) url += (url.includes('?') ? '&' : '?') + params.join('&')
  if (highlight) {
    const hlMap = { outstanding: 'remaining', reserved: 'lifecycle', completed: 'lifecycle' }
    const filterMatch = url.match(/filter=(\w+)/)
    if (filterMatch && hlMap[filterMatch[1]]) navStore.setDrilldownHighlight(hlMap[filterMatch[1]])
    if (highlight === 'remaining') navStore.setDrilldownHighlight('remaining')
    if (highlight === 'lifecycle') navStore.setDrilldownHighlight('lifecycle')
  }
  router.push(url)
}

function viewFictive(id) {
  navStore.setNavReturn('/statistics')
  router.push(`/invoices/${id}`)
}

function markAsSold(inv) {
  confirmTitle.value = t('msg.titleMarkSold')
  confirmMessage.value = t('msg.confirmMarkSold', { number: inv.number })
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
  confirmCallback = () => {
    mainStore.deleteInvoice(inv.id)
    showToast('success', t('msg.invoiceDeleted'))
  }
  confirmVisible.value = true
}

function addDeposit() {
  if (depAmount.value <= 0) { showToast('warning', t('msg.enterValidAmount')); return }
  mainStore.addDeposit({
    id: generateId(mainStore.deposits),
    date: depDate.value,
    amount: depType.value === 'debit' ? -Math.abs(depAmount.value) : Math.abs(depAmount.value),
    type: depType.value,
    note: depNote.value,
    customerId: null
  })
  showToast('success', t('msg.transactionAdded'))
  depAmount.value = 0
  depNote.value = ''
}

function deleteDeposit(id) {
  confirmTitle.value = t('msg.titleDeleteTx')
  confirmMessage.value = t('msg.confirmDeleteTx')
  confirmCallback = () => {
    mainStore.deleteDeposit(id)
    showToast('success', t('msg.transactionDeleted'))
  }
  confirmVisible.value = true
}

function destroyCharts() { charts.forEach(c => c.destroy()); charts = [] }

function initTabCharts() {
  if (activeTab.value === 'overview') initOverviewCharts()
  if (activeTab.value === 'products') initProductChart()
}

function initOverviewCharts() {
  const style = getComputedStyle(document.documentElement)
  const primary = style.getPropertyValue('--color-primary').trim() || '#4f46e5'
  const success = '#16a34a'
  const warning = '#f59e0b'
  const border = style.getPropertyValue('--color-border').trim() || '#e2e8f0'
  Chart.defaults.font.family = "'FiraGO', sans-serif"

  const now = new Date()
  const months = []
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const label = d.toLocaleDateString('en', { month: 'short' })
    const start = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]
    const invs = getFilteredInvoices('standard').filter(inv => inv.createdAt >= start && inv.createdAt <= end)
    months.push({ label, revenue: invs.reduce((s, inv) => s + inv.totalAmount, 0), cashIn: invs.reduce((s, inv) => s + inv.paidAmount, 0) })
  }

  if (revenueChartRef.value) {
    charts.push(new Chart(revenueChartRef.value, {
      type: 'line',
      data: { labels: months.map(m => m.label), datasets: [
        { label: t('chart.revenue'), data: months.map(m => m.revenue), borderColor: primary, backgroundColor: primary + '20', fill: true, tension: 0.4 },
        { label: t('chart.cashIn'), data: months.map(m => m.cashIn), borderColor: success, backgroundColor: success + '20', fill: true, tension: 0.4 }
      ] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${formatCurrency(ctx.raw)}` } } }, scales: { y: { beginAtZero: true, grid: { color: border + '80' }, ticks: { callback: v => formatCurrency(v) } }, x: { grid: { display: false } } } }
    }))
  }

  if (distributionChartRef.value) {
    const invoices = getFilteredInvoices()
    const sold = invoices.filter(i => getInvoiceLifecycle(i).label === 'Sold').length
    const reserved = invoices.filter(i => getInvoiceLifecycle(i).label === 'Reserved').length
    const draft = invoices.filter(i => getInvoiceLifecycle(i).label === 'Draft').length
    charts.push(new Chart(distributionChartRef.value, {
      type: 'doughnut',
      data: { labels: [t('chart.sold'), t('chart.reserved'), t('chart.draft')], datasets: [{ data: [sold, reserved, draft], backgroundColor: [success + 'cc', primary + 'cc', warning + 'cc'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '65%' }
    }))
  }
}

function initProductChart() {
  const style = getComputedStyle(document.documentElement)
  const primary = style.getPropertyValue('--color-primary').trim() || '#4f46e5'
  const border = style.getPropertyValue('--color-border').trim() || '#e2e8f0'

  const productStats = {}
  getFilteredInvoices('standard').forEach(inv => { inv.items.forEach(item => { productStats[item.productId] = (productStats[item.productId] || 0) + item.qty * item.price }) })
  const top5 = Object.entries(productStats).map(([pid, rev]) => ({ name: mainStore.productById(parseInt(pid))?.name || t('common.unknown'), revenue: rev })).sort((a, b) => b.revenue - a.revenue).slice(0, 5)
  const colors = [primary, '#16a34a', '#f59e0b', '#0ea5e9', '#dc2626']

  if (productChartRef.value) {
    charts.push(new Chart(productChartRef.value, {
      type: 'bar',
      data: { labels: top5.map(p => p.name.length > 25 ? p.name.slice(0, 25) + '...' : p.name), datasets: [{ label: t('chart.revenue'), data: top5.map(p => p.revenue), backgroundColor: colors.map(c => c + 'cc'), borderColor: colors, borderWidth: 1, borderRadius: 6, barPercentage: 0.5 }] },
      options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => formatCurrency(ctx.raw) } } }, scales: { x: { beginAtZero: true, grid: { color: border + '80' }, ticks: { callback: v => formatCurrency(v) } }, y: { grid: { display: false } } } }
    }))
  }
}

watch(activeTab, () => { destroyCharts(); nextTick(() => initTabCharts()) })
watch(locale, () => { destroyCharts(); nextTick(() => initTabCharts()) })

onMounted(() => { nextTick(() => initTabCharts()) })
onUnmounted(() => destroyCharts())
</script>
