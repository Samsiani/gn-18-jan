<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.customers.title') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ t('page.customers.subtitle') }}</p>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom: var(--space-4)">
    <div style="position:relative; flex:1; min-width:280px">
      <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--color-text-tertiary)" v-html="icon('search', 16)"></span>
      <input type="text" class="form-input form-input--search" v-model="searchQuery" @input="onSearch" :placeholder="t('page.customers.searchPlaceholder')" style="padding-left:36px">
    </div>
  </div>

  <div v-if="paginatedItems.length === 0">
    <EmptyState :title="t('page.customers.notFound')" :message="t('page.customers.adjustSearch')" />
  </div>
  <div v-else class="data-table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <SortableHeader :label="t('col.name')" field="name" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.taxId')" field="taxId" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th>{{ t('col.phone') }}</th>
          <th>{{ t('col.email') }}</th>
          <SortableHeader :label="t('col.totalSpent')" field="totalSpent" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.invoices')" field="invoiceCount" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.outstanding')" field="outstanding" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th class="text-center" style="width:60px">{{ t('col.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in paginatedItems" :key="c.id"
          :class="['customer-row', { selected: selectedCustomerId === c.id }]"
          @click="selectCustomer(c.id)">
          <td>
            <div style="display:flex; align-items:center; gap:var(--space-2)">
              <div class="sidebar__user-avatar" style="width:32px;height:32px;font-size:var(--text-xs)">{{ c.name.charAt(0) }}{{ c.name.includes(' ') ? c.name.split(' ').pop().charAt(0) : '' }}</div>
              <div>
                <div class="font-medium">{{ c.name }}</div>
                <div style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ c.nameEn }}</div>
              </div>
            </div>
          </td>
          <td style="font-size:var(--text-sm)">{{ c.taxId }}</td>
          <td style="font-size:var(--text-sm)">{{ c.phone }}</td>
          <td style="font-size:var(--text-sm)">{{ c.email }}</td>
          <td class="font-medium">{{ formatCurrency(c.totalSpent) }}</td>
          <td>{{ c.invoiceCount }}</td>
          <td :class="c.outstanding > 0 ? 'text-danger font-semibold' : ''">{{ formatCurrency(c.outstanding) }}</td>
          <td class="text-center">
            <button class="btn btn--ghost btn--icon btn--sm" @click.stop="selectCustomer(c.id)" data-tooltip="View details">
              <span v-html="icon('eye', 14)"></span>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <AppPagination :currentPage="currentPage" :totalPages="totalPages" :total="filteredCustomers.length" @page-change="currentPage = $event" />
  </div>

  <!-- Slide Panel -->
  <SlidePanel :visible="!!selectedCustomerId" @close="closePanel" size="lg">
    <template #header>{{ t('page.customers.panelHeader') }}</template>
    <template v-if="selectedCustomer">
      <div class="customer-detail">
        <div class="customer-detail__header">
          <div class="customer-detail__avatar">{{ selectedCustomer.name.charAt(0) }}</div>
          <div>
            <div class="customer-detail__name">{{ selectedCustomer.name }}</div>
            <div style="font-size:var(--text-sm); color:var(--color-text-tertiary)">{{ selectedCustomer.nameEn }}</div>
            <div style="font-size:var(--text-sm); color:var(--color-text-tertiary)">{{ t('col.taxId') }}: {{ selectedCustomer.taxId }}</div>
          </div>
        </div>

        <div style="margin-bottom:var(--space-4)">
          <div style="font-size:var(--text-sm); color:var(--color-text-secondary); margin-bottom:2px"><span v-html="icon('building', 14)"></span> {{ selectedCustomer.address }}</div>
          <a v-if="selectedCustomer.phone" :href="`tel:${selectedCustomer.phone}`" style="display:flex; align-items:center; gap:6px; font-size:var(--text-sm); color:var(--color-text-secondary); text-decoration:none; margin-bottom:2px"><span v-html="icon('phone', 14)"></span>{{ selectedCustomer.phone }}</a>
          <a v-if="selectedCustomer.email" :href="`mailto:${selectedCustomer.email}`" style="display:flex; align-items:center; gap:6px; font-size:var(--text-sm); color:var(--color-text-secondary); text-decoration:none"><span v-html="icon('mail', 14)"></span>{{ selectedCustomer.email }}</a>
        </div>

        <div class="customer-detail__stats">
          <div class="customer-detail__stat">
            <div class="customer-detail__stat-value">{{ formatCurrency(panelTotalPaid) }}</div>
            <div class="customer-detail__stat-label">{{ t('page.customers.totalPaid') }}</div>
          </div>
          <div class="customer-detail__stat">
            <div class="customer-detail__stat-value">{{ customerInvoices.length }}</div>
            <div class="customer-detail__stat-label">{{ t('col.invoices') }}</div>
          </div>
          <div class="customer-detail__stat">
            <div class="customer-detail__stat-value text-danger">{{ formatCurrency(panelTotalOutstanding) }}</div>
            <div class="customer-detail__stat-label">{{ t('col.outstanding') }}</div>
          </div>
        </div>

        <h4 style="margin-bottom:var(--space-3); font-size:var(--text-md)">{{ t('section.invoiceHistory') }}</h4>
        <DateFilter v-model:dateFrom="panelDateFrom" v-model:dateTo="panelDateTo" prefix="cust-panel" @change="() => {}" />
        <div style="margin-bottom:var(--space-4)"></div>

        <p v-if="customerInvoices.length === 0" style="color:var(--color-text-tertiary); font-size:var(--text-sm)">{{ t('page.customers.noInvoices') }}</p>
        <table v-else class="data-table" style="font-size:var(--text-sm)">
          <thead>
            <tr>
              <th>{{ t('col.invoiceNumber') }}</th>
              <th>{{ t('col.date') }}</th>
              <th>{{ t('col.total') }}</th>
              <th>{{ t('col.status') }}</th>
              <th class="text-center" style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in customerInvoices" :key="inv.id">
              <td><a @click.prevent="navigateToInvoice(inv.id)" :href="`#/invoices/${inv.id}`" class="font-medium">{{ inv.number }}</a></td>
              <td>{{ formatDate(inv.createdAt) }}</td>
              <td>{{ formatCurrency(inv.totalAmount) }}</td>
              <td><AppBadge :label="tLabel(getInvoiceLifecycle(inv))" :color="getInvoiceLifecycle(inv).color" :dot="true" /></td>
              <td class="text-center"><InvoiceActions compact :invoice="inv" @view="navigateToInvoice(inv.id)" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </SlidePanel>
</div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, debounce } from '@/composables/useFormatters'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { useSortable } from '@/composables/useSortable'
import { usePagination } from '@/composables/usePagination'
import { getInvoiceLifecycle } from '@/data'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import SlidePanel from '@/components/ui/SlidePanel.vue'
import DateFilter from '@/components/ui/DateFilter.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'

const router = useRouter()
const mainStore = useMainStore()
const navStore = useNavigationStore()
const { t, tLabel } = useI18n()
const { sortField, sortDir, toggleSort, sortItems } = useSortable('totalSpent', 'desc')
const { currentPage, totalPages, paginate, resetPage } = usePagination(15)

const searchQuery = ref('')
const selectedCustomerId = ref(null)
const panelDateFrom = ref('')
const panelDateTo = ref('')

const filteredCustomers = computed(() => {
  let customers = [...mainStore.customers]
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    customers = customers.filter(c =>
      c.name.toLowerCase().includes(q) ||
      c.nameEn.toLowerCase().includes(q) ||
      c.taxId.includes(q) ||
      c.phone.includes(q) ||
      c.email.toLowerCase().includes(q)
    )
  }
  return sortItems(customers)
})

const paginatedItems = computed(() => paginate(filteredCustomers.value))

const selectedCustomer = computed(() => selectedCustomerId.value ? mainStore.customerById(selectedCustomerId.value) : null)

const customerInvoices = computed(() => {
  if (!selectedCustomerId.value) return []
  let invoices = mainStore.invoices.filter(inv => inv.customerId === selectedCustomerId.value)
  if (panelDateFrom.value) invoices = invoices.filter(inv => inv.createdAt >= panelDateFrom.value)
  if (panelDateTo.value) invoices = invoices.filter(inv => inv.createdAt <= panelDateTo.value)
  return invoices.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
})

const panelTotalPaid = computed(() => customerInvoices.value.reduce((s, inv) => s + inv.paidAmount, 0))
const panelTotalOutstanding = computed(() => customerInvoices.value.reduce((s, inv) => s + Math.max(0, inv.totalAmount - inv.paidAmount), 0))

const debouncedSearch = debounce(() => resetPage(), 400)
function onSearch() { debouncedSearch() }

function onSort(field) {
  toggleSort(field)
  resetPage()
}

function selectCustomer(id) {
  selectedCustomerId.value = id
}

function closePanel() {
  selectedCustomerId.value = null
  panelDateFrom.value = ''
  panelDateTo.value = ''
}

function navigateToInvoice(id) {
  navStore.setNavReturn('/customers', {
    selectedCustomerId: selectedCustomerId.value,
    panelDateFilter: { dateFrom: panelDateFrom.value, dateTo: panelDateTo.value }
  })
  router.push(`/invoices/${id}`)
}

onMounted(() => {
  const restoreData = navStore.consumePageRestore()
  if (restoreData && restoreData.selectedCustomerId) {
    selectedCustomerId.value = restoreData.selectedCustomerId
    if (restoreData.panelDateFilter) {
      panelDateFrom.value = restoreData.panelDateFilter.dateFrom || ''
      panelDateTo.value = restoreData.panelDateFilter.dateTo || ''
    }
  }
})
</script>
