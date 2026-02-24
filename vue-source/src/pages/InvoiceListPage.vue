<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ authStore.isConsultant ? t('page.invoices.myTitle') : t('page.invoices.title') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ authStore.isConsultant ? t('page.invoices.mySubtitle') : t('page.invoices.subtitle') }}</p>
    </div>
    <div class="page-header__actions">
      <button v-if="selectedIds.size > 0 && !authStore.isConsultant" class="btn btn--danger btn--sm" @click="bulkDelete"><span v-html="icon('trash-2', 14)"></span> {{ t('btn.deleteSelected', { n: selectedIds.size }) }}</button>
      <router-link to="/invoices/new" class="btn btn--primary"><span v-html="icon('plus', 16)"></span> {{ t('btn.newInvoice') }}</router-link>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-bar">
    <div class="autocomplete" style="flex:1; min-width:250px">
      <input type="text" class="form-input form-input--search" v-model="filters.search" @input="onSearch" :placeholder="t('page.invoices.searchPlaceholder')">
    </div>
    <select class="form-select" v-model="filters.status" @change="onStatusChange">
      <option value="">{{ t('filter.allTypes') }}</option>
      <option value="standard">{{ t('filter.standard') }}</option>
      <option value="fictive">{{ t('filter.fictive') }}</option>
    </select>
    <select class="form-select" v-model="filters.lifecycle" @change="resetPage">
      <option value="">{{ t('filter.allStatus') }}</option>
      <option v-if="filters.status !== 'standard'" value="draft">{{ t('filter.draft') }}</option>
      <option v-if="filters.status !== 'fictive'" value="reserved">{{ t('filter.reserved') }}</option>
      <option v-if="filters.status !== 'fictive'" value="canceled">{{ t('filter.canceled') }}</option>
      <option v-if="filters.status !== 'fictive'" value="sold">{{ t('filter.sold') }}</option>
    </select>
    <DateFilter v-model:dateFrom="filters.dateFrom" v-model:dateTo="filters.dateTo" prefix="inv" @change="resetPage" />
    <button v-if="hasFilters" class="btn btn--ghost btn--sm" @click="resetFilters"><span v-html="icon('x', 14)"></span> {{ t('btn.reset') }}</button>
  </div>

  <!-- Table -->
  <div v-if="paginatedItems.length === 0">
    <EmptyState :title="t('page.invoices.notFound')" :message="t('page.invoices.notFoundMsg')">
      <router-link to="/invoices/new" class="btn btn--primary"><span v-html="icon('plus', 16)"></span> {{ t('btn.newInvoice') }}</router-link>
    </EmptyState>
  </div>
  <div v-else class="data-table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th v-if="!authStore.isConsultant" style="width:40px">
            <label class="form-checkbox"><input type="checkbox" :checked="allSelected" @change="toggleSelectAll"></label>
          </th>
          <SortableHeader :label="t('col.invoiceNumber')" field="number" :sortField="sortField" :sortDir="sortDir" @sort="onSort" :class="hlClass('number')" />
          <SortableHeader :label="t('col.date')" field="createdAt" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.customer')" field="customer" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.total')" field="totalAmount" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.paid')" field="paidAmount" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.remaining')" field="remaining" :sortField="sortField" :sortDir="sortDir" @sort="onSort" :class="hlClass('remaining')" />
          <SortableHeader :label="t('col.type')" field="status" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.status')" field="lifecycleStatus" :sortField="sortField" :sortDir="sortDir" @sort="onSort" :class="hlClass('lifecycle')" />
          <th :class="hlClass('payment')">{{ t('col.payment') }}</th>
          <th class="text-center">{{ t('col.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="inv in paginatedItems" :key="inv.id" :class="{ selected: selectedIds.has(inv.id) }">
          <td v-if="!authStore.isConsultant">
            <label class="form-checkbox"><input type="checkbox" :checked="selectedIds.has(inv.id)" @change="toggleSelect(inv.id)"></label>
          </td>
          <td :class="hlClass('number')"><router-link :to="`/invoices/${inv.id}`" style="font-weight:600">{{ inv.number }}</router-link></td>
          <td>{{ formatDate(inv.createdAt) }}</td>
          <td class="text-truncate" style="max-width:180px">{{ getCustomerName(inv.customerId) }}</td>
          <td class="font-medium">
            <span v-if="getInvoiceLifecycle(inv).label === 'Canceled'" class="amount--canceled">{{ formatCurrency(inv.totalAmount) }}</span>
            <span v-else>{{ formatCurrency(getEffectiveTotal(inv)) }}</span>
          </td>
          <td :class="{ 'amount--canceled': getInvoiceLifecycle(inv).label === 'Canceled' }">{{ formatCurrency(inv.paidAmount) }}</td>
          <td :class="[hlClass('remaining'), getRemaining(inv) > 0 ? 'text-danger font-semibold' : '']">{{ formatCurrency(getRemaining(inv)) }}</td>
          <td><AppBadge :label="tLabel(getStatus(inv.status))" :color="getStatus(inv.status).color" /></td>
          <td :class="hlClass('lifecycle')"><AppBadge :label="tLabel(getInvoiceLifecycle(inv))" :color="getInvoiceLifecycle(inv).color" :dot="true" /></td>
          <td :class="hlClass('payment')">
            <PaymentBadges
              :payments="inv.payments"
              :totalAmount="inv.totalAmount"
              :filterMethod="filters.paymentMethod"
              :filterDateFrom="filters.dateFrom"
              :filterDateTo="filters.dateTo"
            />
          </td>
          <td class="text-center">
            <InvoiceActions :invoice="inv" @view="$router.push(`/invoices/${inv.id}`)" @edit="$router.push(`/invoices/${inv.id}/edit`)" @mark-sold="markAsSold(inv)" @delete="deleteInvoice(inv)" />
          </td>
        </tr>
      </tbody>
    </table>
    <AppPagination :currentPage="currentPage" :totalPages="totalPages" :total="filteredInvoices.length" @page-change="currentPage = $event" />
  </div>

  <ConfirmDialog v-model:visible="confirmVisible" :title="confirmTitle" :message="confirmMessage" :danger="confirmDanger" @confirm="confirmCallback" />
</div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, debounce } from '@/composables/useFormatters'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { useToast } from '@/composables/useToast'
import { useNotificationStore } from '@/stores/notifications'
import { useSortable } from '@/composables/useSortable'
import { usePagination } from '@/composables/usePagination'
import { STATUS_LABELS, LIFECYCLE_LABELS, PAYMENT_METHODS, getInvoiceLifecycle, getEffectiveTotal } from '@/data'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import PaymentBadges from '@/components/ui/PaymentBadges.vue'
import DateFilter from '@/components/ui/DateFilter.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'

const route = useRoute()
const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const { showToast } = useToast()
const notifStore = useNotificationStore()
const { t, tLabel } = useI18n()
const { sortField, sortDir, toggleSort, sortItems } = useSortable('createdAt', 'desc')
const { currentPage, totalPages, paginate, resetPage } = usePagination(20)

const filters = reactive({ search: '', status: '', lifecycle: '', dateFrom: '', dateTo: '', paymentMethod: '', outstanding: false, reserved: false })
const selectedIds = ref(new Set())
const highlightColumn = ref(null)
// Confirm dialog
const confirmVisible = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
const confirmDanger = ref(false)
let confirmCallback = () => {}

const hasFilters = computed(() => filters.search || filters.status || filters.lifecycle || filters.dateFrom || filters.dateTo || filters.paymentMethod || filters.outstanding || filters.reserved)

const filteredInvoices = computed(() => {
  let invoices = authStore.isConsultant ? authStore.getMyInvoices(mainStore.invoices) : [...mainStore.invoices]

  if (filters.search) {
    const q = filters.search.toLowerCase()
    invoices = invoices.filter(inv => {
      const customer = mainStore.customerById(inv.customerId)
      return inv.number.toLowerCase().includes(q) || (customer && customer.name.toLowerCase().includes(q)) || (customer && customer.taxId.includes(q))
    })
  }
  if (filters.status) invoices = invoices.filter(inv => inv.status === filters.status)
  if (filters.lifecycle) invoices = invoices.filter(inv => getInvoiceLifecycle(inv).label.toLowerCase() === filters.lifecycle)
  if (filters.dateFrom || filters.dateTo) {
    invoices = invoices.filter(inv => {
      const from = filters.dateFrom
      const to = filters.dateTo
      const createdMatch = (!from || inv.createdAt >= from) && (!to || inv.createdAt <= to)
      const paymentMatch = inv.payments.some(p => (!from || p.date >= from) && (!to || p.date <= to))
      return createdMatch || paymentMatch
    })
  }
  if (filters.paymentMethod) invoices = invoices.filter(inv => inv.payments.some(p => p.method === filters.paymentMethod))
  if (filters.outstanding) invoices = invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Sold' && getEffectiveTotal(inv) - inv.paidAmount > 0)
  if (filters.reserved) invoices = invoices.filter(inv => getInvoiceLifecycle(inv).label === 'Reserved')

  invoices = invoices.map(inv => {
    const customer = mainStore.customerById(inv.customerId)
    return { ...inv, _customerName: customer ? customer.name : '', _remaining: Math.max(0, getEffectiveTotal(inv) - inv.paidAmount) }
  })

  const field = sortField.value === 'customer' ? '_customerName' : sortField.value === 'remaining' ? '_remaining' : sortField.value
  return sortItems(invoices, field)
})

const paginatedItems = computed(() => paginate(filteredInvoices.value))
const allSelected = computed(() => paginatedItems.value.length > 0 && paginatedItems.value.every(inv => selectedIds.value.has(inv.id)))

function hlClass(colKey) { return highlightColumn.value === colKey ? 'col-highlight' : '' }

function onSort(field) { toggleSort(field); resetPage() }
const debouncedSearch = debounce(() => resetPage(), 400)
function onSearch() { debouncedSearch() }

function onStatusChange() {
  if (filters.status === 'standard' && filters.lifecycle === 'draft') filters.lifecycle = ''
  if (filters.status === 'fictive') filters.lifecycle = 'draft'
  if (filters.status === '' && filters.lifecycle === 'draft') filters.lifecycle = ''
  resetPage()
}

function resetFilters() {
  Object.assign(filters, { search: '', status: '', lifecycle: '', dateFrom: '', dateTo: '', paymentMethod: '', outstanding: false, reserved: false })
  highlightColumn.value = null
  resetPage()
}

function getCustomerName(id) { const c = mainStore.customerById(id); return c ? c.name : '—' }
function getRemaining(inv) { return Math.max(0, getEffectiveTotal(inv) - inv.paidAmount) }
function getStatus(status) { return STATUS_LABELS[status] || STATUS_LABELS.standard }

function toggleSelectAll(e) {
  if (e.target.checked) { paginatedItems.value.forEach(inv => selectedIds.value.add(inv.id)) }
  else { selectedIds.value.clear() }
}

function toggleSelect(id) {
  if (selectedIds.value.has(id)) selectedIds.value.delete(id)
  else selectedIds.value.add(id)
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
      notifStore.push({ type: 'invoice', titleKey: 'notif.invoiceCompleted', message: fresh.number, icon: 'check-circle', invoiceId: fresh.id })
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
    selectedIds.value.delete(inv.id)
    showToast('success', t('msg.invoiceDeleted'))
  }
  confirmVisible.value = true
}

function bulkDelete() {
  confirmTitle.value = t('msg.titleDeleteSelected')
  confirmMessage.value = t('msg.confirmBulkDelete', { n: selectedIds.value.size })
  confirmDanger.value = true
  confirmCallback = () => {
    selectedIds.value.forEach(id => mainStore.deleteInvoice(id))
    selectedIds.value.clear()
    showToast('success', t('msg.invoicesDeleted'))
  }
  confirmVisible.value = true
}

onMounted(() => {
  highlightColumn.value = navStore.consumeDrilldownHighlight()
  const query = route.query

  if (!highlightColumn.value) {
    const hlMap = { outstanding: 'remaining', reserved: 'lifecycle' }
    if (query.filter && hlMap[query.filter]) highlightColumn.value = hlMap[query.filter]
    else if (query.paymentMethod) highlightColumn.value = 'payment'
  }

  if (query.filter === 'standard') { filters.status = 'standard' }
  else if (query.filter === 'reserved') { filters.status = 'standard'; filters.lifecycle = 'reserved' }
  else if (query.filter === 'outstanding') { filters.status = 'standard'; filters.outstanding = true }
  else if (query.filter === 'sold' || query.filter === 'completed') { filters.status = 'standard'; filters.lifecycle = 'sold' }

  if (query.paymentMethod) { filters.status = 'standard'; filters.paymentMethod = query.paymentMethod }
  if (query.dateFrom) filters.dateFrom = query.dateFrom
  if (query.dateTo) filters.dateTo = query.dateTo
})
</script>
