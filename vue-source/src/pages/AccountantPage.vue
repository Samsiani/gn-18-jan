<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.accountant.title') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ t('page.accountant.subtitle') }}</p>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-bar">
    <div class="autocomplete" style="flex:1; min-width:250px">
      <input type="text" class="form-input form-input--search" v-model="search" :placeholder="t('page.accountant.searchPlaceholder')" @input="onSearch">
    </div>
    <DateFilter v-model:dateFrom="dateFrom" v-model:dateTo="dateTo" prefix="acc" @change="onDateChange" />
    <select class="form-select" v-model="completion" @change="resetPage" style="min-width:130px">
      <option value="">{{ t('filter.allStatus') }}</option>
      <option value="completed">{{ t('filter.completed') }}</option>
      <option value="incomplete">{{ t('filter.incomplete') }}</option>
    </select>
    <select class="form-select" v-model="typeFilter" @change="resetPage" style="min-width:130px">
      <option value="">{{ t('filter.allTypes') }}</option>
      <option v-for="(val, key) in PAYMENT_METHODS" :key="key" :value="key">{{ tLabel(val) }}</option>
    </select>
    <button v-if="hasFilters" class="btn btn--ghost btn--sm" @click="resetFilters"><span v-html="icon('x', 14)"></span> {{ t('btn.reset') }}</button>
  </div>

  <!-- Table -->
  <div v-if="paginatedItems.length === 0">
    <EmptyState :title="t('page.accountant.notFound')" :message="t('page.accountant.adjustFilters')" />
  </div>
  <div v-else class="data-table-wrapper">
    <table class="data-table accountant-table">
      <thead>
        <tr>
          <SortableHeader :label="t('col.invoiceNumber')" field="number" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.invoiceDate')" field="createdAt" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.soldDate')" field="saleDate" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.client')" field="customer" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th>{{ t('col.payment') }}</th>
          <SortableHeader :label="t('col.total')" field="totalAmount" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th class="checkbox-cell">{{ t('col.rs') }}</th>
          <th class="checkbox-cell">{{ t('col.credit') }}</th>
          <th class="checkbox-cell">{{ t('col.receipt') }}</th>
          <th class="checkbox-cell">{{ t('col.corrected') }}</th>
          <th class="text-center">{{ t('col.notes') }}</th>
          <th class="text-center">{{ t('col.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="inv in paginatedItems" :key="inv.id">
          <td><a @click.prevent="navigateToInvoice(inv.id)" :href="`#/invoices/${inv.id}`" style="font-weight:600">{{ inv.number }}</a></td>
          <td>{{ formatDate(inv.createdAt) }}</td>
          <td>{{ getInvoiceLifecycle(inv).label === 'Sold' ? formatDate(inv.soldDate || inv.saleDate) : '—' }}</td>
          <td>
            <div class="text-truncate" style="max-width:150px; font-weight:500">{{ getCustomerName(inv.customerId) }}</div>
            <div style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ getCustomerTaxId(inv.customerId) }}</div>
          </td>
          <td><PaymentBadges :payments="inv.payments" /></td>
          <td :class="getRemaining(inv) > 0 ? 'text-danger font-semibold' : 'font-medium'">
            {{ formatCurrency(inv.totalAmount) }}
            <div v-if="getRemaining(inv) > 0" style="font-size:var(--text-xs)">rem: {{ formatCurrency(getRemaining(inv)) }}</div>
          </td>
          <td class="checkbox-cell">
            <label class="form-checkbox"><input type="checkbox" :checked="inv.isRsUploaded" @change="toggleField(inv, 'isRsUploaded', $event)"></label>
          </td>
          <td class="checkbox-cell">
            <label class="form-checkbox"><input type="checkbox" :checked="inv.isCreditChecked" @change="toggleField(inv, 'isCreditChecked', $event)"></label>
          </td>
          <td class="checkbox-cell">
            <label class="form-checkbox"><input type="checkbox" :checked="inv.isReceiptChecked" @change="toggleField(inv, 'isReceiptChecked', $event)"></label>
          </td>
          <td class="checkbox-cell">
            <label class="form-checkbox"><input type="checkbox" :checked="inv.isCorrected" @change="toggleField(inv, 'isCorrected', $event)"></label>
          </td>
          <td class="text-center">
            <button :class="['accountant-note-btn', { 'has-note': inv.accountantNote || inv.consultantNote }]" @click="openNoteModal(inv)">
              <span v-html="icon('message-square', 16)"></span>
            </button>
          </td>
          <td class="text-center">
            <InvoiceActions :invoice="inv" @view="navigateToInvoice(inv.id)" @edit="$router.push(`/invoices/${inv.id}/edit`)" @mark-sold="markAsSold(inv)" @delete="deleteInvoice(inv)" />
          </td>
        </tr>
      </tbody>
    </table>
    <AppPagination :currentPage="currentPage" :totalPages="totalPages" :total="filteredInvoices.length" @page-change="currentPage = $event" />
  </div>

  <!-- Note Modal -->
  <AppModal v-model:visible="noteModalVisible" :title="`${t('col.notes')} - ${noteInvoice?.number || ''}`">
    <div class="accountant-note-modal">
      <div v-if="noteInvoice?.consultantNote" style="margin-bottom: var(--space-4)">
        <label class="form-label">{{ t('form.consultantNote') }}</label>
        <div class="consultant-note">{{ noteInvoice.consultantNote }}</div>
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('form.accountantNote') }}</label>
        <textarea class="form-textarea" v-model="noteText" :placeholder="t('form.addNote')"></textarea>
      </div>
    </div>
    <template #footer>
      <button class="btn btn--secondary" @click="noteModalVisible = false">{{ t('btn.cancel') }}</button>
      <button class="btn btn--primary" @click="saveNote">{{ t('btn.save') }}</button>
    </template>
  </AppModal>

  <!-- Confirm Dialog -->
  <ConfirmDialog v-model:visible="confirmVisible" :title="confirmTitle" :message="confirmMessage" @confirm="confirmAction" />
</div>
</template>
<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, debounce } from '@/composables/useFormatters'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { useToast } from '@/composables/useToast'
import { useSortable } from '@/composables/useSortable'
import { usePagination } from '@/composables/usePagination'
import { PAYMENT_METHODS, getInvoiceLifecycle } from '@/data'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import PaymentBadges from '@/components/ui/PaymentBadges.vue'
import DateFilter from '@/components/ui/DateFilter.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AppModal from '@/components/ui/AppModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'

const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const { showToast } = useToast()
const { t, tLabel } = useI18n()
const { sortField, sortDir, toggleSort, sortItems } = useSortable('createdAt', 'desc')
const { currentPage, totalPages, paginate, resetPage } = usePagination(15)

const search = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const completion = ref('')
const typeFilter = ref('')

// Note modal
const noteModalVisible = ref(false)
const noteInvoice = ref(null)
const noteText = ref('')

// Confirm dialog
const confirmVisible = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
let confirmAction = () => {}

const hasFilters = computed(() => search.value || dateFrom.value || dateTo.value || completion.value || typeFilter.value)

function isCompleted(inv) {
  return inv.isRsUploaded && inv.isCreditChecked && inv.isReceiptChecked && inv.isCorrected
}

const filteredInvoices = computed(() => {
  let invoices = mainStore.invoices.filter(inv => inv.status === 'standard')

  if (search.value) {
    const q = search.value.toLowerCase()
    invoices = invoices.filter(inv => {
      const customer = mainStore.customerById(inv.customerId)
      return inv.number.toLowerCase().includes(q) ||
        (customer && customer.name.toLowerCase().includes(q)) ||
        (customer && customer.taxId.includes(q))
    })
  }

  if (dateFrom.value) invoices = invoices.filter(inv => inv.createdAt >= dateFrom.value)
  if (dateTo.value) invoices = invoices.filter(inv => inv.createdAt <= dateTo.value)

  if (completion.value === 'completed') invoices = invoices.filter(isCompleted)
  else if (completion.value === 'incomplete') invoices = invoices.filter(inv => !isCompleted(inv))

  if (typeFilter.value) {
    invoices = invoices.filter(inv => inv.payments.some(p => p.method === typeFilter.value))
  }

  invoices = invoices.map(inv => {
    const customer = mainStore.customerById(inv.customerId)
    return { ...inv, _customerName: customer ? customer.name : '', _saleDate: inv.soldDate || inv.saleDate || '' }
  })

  const field = sortField.value === 'customer' ? '_customerName' : sortField.value === 'saleDate' ? '_saleDate' : sortField.value
  return sortItems(invoices, field)
})

const paginatedItems = computed(() => paginate(filteredInvoices.value))

function onSort(field) {
  toggleSort(field)
  resetPage()
}

const debouncedSearch = debounce(() => resetPage(), 400)
function onSearch() { debouncedSearch() }
function onDateChange() { resetPage() }

function resetFilters() {
  search.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  completion.value = ''
  typeFilter.value = ''
  resetPage()
}

function getCustomerName(id) {
  const c = mainStore.customerById(id)
  return c ? c.name : '—'
}
function getCustomerTaxId(id) {
  const c = mainStore.customerById(id)
  return c ? c.taxId : ''
}
function getRemaining(inv) {
  return Math.max(0, inv.totalAmount - inv.paidAmount)
}

function navigateToInvoice(id) {
  navStore.setNavReturn('/accountant')
  router.push(`/invoices/${id}`)
}

function markAsSold(inv) {
  confirmTitle.value = t('msg.titleMarkSold')
  confirmMessage.value = t('msg.confirmMarkSold', { number: inv.number })
  confirmAction = () => {
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
  confirmAction = () => {
    mainStore.deleteInvoice(inv.id)
    showToast('success', t('msg.invoiceDeleted'))
  }
  confirmVisible.value = true
}

function toggleField(inv, field, event) {
  const newVal = event.target.checked
  event.target.checked = !newVal // revert until confirmed
  const fieldLabels = {
    isRsUploaded: t('page.accountant.rsUploaded'),
    isCreditChecked: t('page.accountant.creditChecked'),
    isReceiptChecked: t('page.accountant.receiptChecked'),
    isCorrected: t('page.accountant.corrected'),
  }
  const label = fieldLabels[field]
  confirmTitle.value = t('msg.titleEnableField', { action: newVal ? t('msg.enable') : t('msg.disable'), field: label })
  confirmMessage.value = t('msg.confirmFieldMark', { action: newVal ? t('msg.mark') : t('msg.unmark'), field: label, number: inv.number })
  confirmAction = () => {
    const fresh = mainStore.invoiceById(inv.id)
    if (fresh) {
      fresh[field] = newVal
      mainStore.saveInvoice({ ...fresh })
      showToast('success', `${label} ${newVal ? t('msg.enabled') : t('msg.disabled')}`)
    }
  }
  confirmVisible.value = true
}

function openNoteModal(inv) {
  noteInvoice.value = inv
  noteText.value = inv.accountantNote || ''
  noteModalVisible.value = true
}

function saveNote() {
  if (noteInvoice.value) {
    const fresh = mainStore.invoiceById(noteInvoice.value.id)
    if (fresh) {
      fresh.accountantNote = noteText.value
      mainStore.saveInvoice({ ...fresh })
      showToast('success', t('msg.noteSaved'))
    }
  }
  noteModalVisible.value = false
}
</script>
