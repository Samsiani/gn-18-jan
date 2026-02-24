<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ t('page.users.title') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ t('page.users.subtitle') }}</p>
    </div>
    <div class="page-header__actions">
      <button class="btn btn--primary" @click="openAdd">
        <span v-html="icon('user-plus', 16)"></span>
        {{ t('btn.addUser') }}
      </button>
    </div>
  </div>

  <div class="filter-bar" style="margin-bottom: var(--space-4)">
    <div style="position:relative; flex:1; min-width:250px">
      <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--color-text-tertiary)" v-html="icon('search', 16)"></span>
      <input type="text" class="form-input form-input--search" v-model="searchQuery" @input="onSearch" :placeholder="t('page.users.searchPlaceholder')" style="padding-left:36px">
    </div>
    <select class="form-select" v-model="roleFilter" @change="resetPage" style="min-width:160px">
      <option value="">{{ t('filter.allRoles') }}</option>
      <option v-for="(val, key) in ROLE_LABELS" :key="key" :value="key">{{ tLabel(val) }}</option>
    </select>
  </div>

  <div v-if="pagedUsers.length === 0">
    <EmptyState :title="t('page.users.notFound')" :message="t('page.users.adjustFilter')" />
  </div>
  <div v-else class="data-table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>{{ t('col.user') }}</th>
          <th>{{ t('col.role') }}</th>
          <SortableHeader :label="t('col.invoices')" field="_invoices" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.revenue')" field="_revenue" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.average')" field="_avgOrder" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th class="text-center" style="width:110px">{{ t('col.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(u, idx) in pagedUsers" :key="u.id">
          <td class="text-center" style="color:var(--color-text-tertiary); font-size:var(--text-sm)">{{ (currentPage - 1) * 15 + idx + 1 }}</td>
          <td>
            <div style="display:flex; align-items:center; gap:var(--space-2)">
              <div class="sidebar__user-avatar" style="width:30px;height:30px;font-size:var(--text-xs)">{{ u.avatar }}</div>
              <div>
                <div class="font-medium">{{ u.nameEn }}</div>
                <div style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ u.name }}</div>
              </div>
            </div>
          </td>
          <td><AppBadge :label="tLabel(ROLE_LABELS[u.role])" :color="ROLE_LABELS[u.role]?.color || 'neutral'" /></td>
          <td>{{ userStats[u.id]?.invoices || 0 }}</td>
          <td class="font-medium">{{ formatCurrency(userStats[u.id]?.revenue || 0) }}</td>
          <td>{{ formatCurrency(avgOrderFor(u.id)) }}</td>
          <td class="text-center">
            <div style="display:flex; align-items:center; justify-content:center; gap:var(--space-1)">
              <button class="btn btn--ghost btn--icon btn--sm" @click="openPanel(u)" :data-tooltip="t('section.userDetails')">
                <span v-html="icon('eye', 14)"></span>
              </button>
              <button class="btn btn--ghost btn--icon btn--sm" @click="openEdit(u)" :data-tooltip="t('btn.edit')">
                <span v-html="icon('edit', 14)"></span>
              </button>
              <button class="btn btn--ghost btn--icon btn--sm" @click="confirmDelete(u)" :data-tooltip="t('btn.delete')" style="color:var(--color-danger)">
                <span v-html="icon('trash-2', 14)"></span>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <AppPagination :currentPage="currentPage" :totalPages="totalPages" :total="filteredUsers.length" @page-change="currentPage = $event" />
  </div>

  <!-- Slide Panel -->
  <SlidePanel :visible="panelVisible" @close="closePanel" size="lg">
    <template #header>{{ t('section.userDetails') }}</template>
    <template v-if="selectedUser">
      <div class="user-detail__header">
        <div class="user-detail__avatar">{{ selectedUser.avatar }}</div>
        <div>
          <div style="font-size:var(--text-lg); font-weight:var(--font-weight-bold)">{{ selectedUser.nameEn }}</div>
          <div style="font-size:var(--text-sm); color:var(--color-text-tertiary)">{{ selectedUser.name }}</div>
          <AppBadge :label="tLabel(ROLE_LABELS[selectedUser.role])" :color="ROLE_LABELS[selectedUser.role]?.color || 'neutral'" style="margin-top:var(--space-1)" />
          <a v-if="selectedUser.phone" :href="`tel:${selectedUser.phone}`" style="display:flex; align-items:center; gap:6px; font-size:var(--text-sm); color:var(--color-text-secondary); text-decoration:none; margin-top:var(--space-2)"><span v-html="icon('phone', 14)"></span>{{ selectedUser.phone }}</a>
          <a v-if="selectedUser.email" :href="`mailto:${selectedUser.email}`" style="display:flex; align-items:center; gap:6px; font-size:var(--text-sm); color:var(--color-text-secondary); text-decoration:none; margin-top:2px"><span v-html="icon('mail', 14)"></span>{{ selectedUser.email }}</a>
        </div>
      </div>

      <div class="user-detail__stats">
        <div class="user-detail__stat">
          <div class="user-detail__stat-label">{{ t('page.users.invoicesCreated') }}</div>
          <div class="user-detail__stat-value">{{ userStats[selectedUser.id]?.invoices || 0 }}</div>
        </div>
        <div class="user-detail__stat">
          <div class="user-detail__stat-label">{{ t('page.users.revenueGenerated') }}</div>
          <div class="user-detail__stat-value">{{ formatCurrency(userStats[selectedUser.id]?.revenue || 0) }}</div>
        </div>
        <div class="user-detail__stat">
          <div class="user-detail__stat-label">{{ t('page.users.avgOrderValue') }}</div>
          <div class="user-detail__stat-value">{{ formatCurrency(avgOrderFor(selectedUser.id)) }}</div>
        </div>
        <div class="user-detail__stat">
          <div class="user-detail__stat-label">{{ t('page.users.outstandingBal') }}</div>
          <div class="user-detail__stat-value" :class="(userStats[selectedUser.id]?.outstanding || 0) > 0 ? 'text-danger' : ''">
            {{ formatCurrency(userStats[selectedUser.id]?.outstanding || 0) }}
          </div>
        </div>
      </div>

      <div style="padding: var(--space-5) var(--space-6)">
        <h4 style="margin-bottom:var(--space-3); font-size:var(--text-md)">{{ t('section.invoiceHistory') }}</h4>
        <DateFilter v-model:dateFrom="panelDateFrom" v-model:dateTo="panelDateTo" prefix="user-panel" @change="() => {}" />
        <div style="margin-bottom:var(--space-4)"></div>
        <p v-if="panelInvoices.length === 0" style="color:var(--color-text-tertiary); font-size:var(--text-sm)">{{ t('page.users.noInvoices') }}</p>
        <table v-else class="data-table" style="font-size:var(--text-sm)">
          <thead>
            <tr>
              <th>{{ t('col.invoiceNumber') }}</th>
              <th>{{ t('col.date') }}</th>
              <th>{{ t('col.customer') }}</th>
              <th>{{ t('col.total') }}</th>
              <th>{{ t('col.status') }}</th>
              <th class="text-center" style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in panelInvoices" :key="inv.id">
              <td><a @click.prevent="navigateToInvoice(inv.id)" :href="`#/invoices/${inv.id}`" class="font-medium">{{ inv.number }}</a></td>
              <td>{{ formatDate(inv.createdAt) }}</td>
              <td style="font-size:var(--text-xs)">{{ mainStore.customerById(inv.customerId)?.nameEn || '—' }}</td>
              <td>{{ formatCurrency(inv.totalAmount) }}</td>
              <td><AppBadge :label="tLabel(getInvoiceLifecycle(inv))" :color="getInvoiceLifecycle(inv).color" :dot="true" /></td>
              <td class="text-center"><InvoiceActions compact :invoice="inv" @view="navigateToInvoice(inv.id)" /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </SlidePanel>

  <!-- Add / Edit Modal -->
  <AppModal v-model:visible="formVisible" :title="isNew ? t('page.users.addTitle') : t('page.users.editTitle')" size="sm">
    <div style="display:flex; flex-direction:column; gap:var(--space-4)">
      <div class="form-group">
        <label class="form-label">{{ t('form.fullNameKa') }}</label>
        <input type="text" class="form-input" v-model="form.name" placeholder="e.g. გიორგი ნოზაძე">
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('form.fullNameEn') }}</label>
        <input type="text" class="form-input" v-model="form.nameEn" placeholder="e.g. Giorgi Nozadze">
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('form.avatar') }}</label>
        <input type="text" class="form-input" v-model="form.avatar" maxlength="2" placeholder="GN" style="text-transform:uppercase">
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('form.role') }}</label>
        <select class="form-select" v-model="form.role">
          <option v-for="(val, key) in ROLE_LABELS" :key="key" :value="key">{{ tLabel(val) }}</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('col.phone') }}</label>
        <input type="tel" class="form-input" v-model="form.phone" placeholder="+995 599 000 000">
      </div>
      <div class="form-group">
        <label class="form-label">{{ t('col.email') }}</label>
        <input type="email" class="form-input" v-model="form.email" placeholder="name@company.ge">
      </div>
    </div>
    <template #footer>
      <button class="btn btn--ghost" @click="formVisible = false">{{ t('btn.cancel') }}</button>
      <button class="btn btn--primary" @click="saveUser">{{ isNew ? t('btn.saveUser') : t('btn.updateUser') }}</button>
    </template>
  </AppModal>

  <!-- Delete Confirm -->
  <ConfirmDialog
    v-model:visible="deleteDialogVisible"
    :title="t('msg.titleDeleteUser')"
    :message="t('msg.confirmDeleteUser', { name: deleteTarget?.nameEn || 'this user' })"
    :danger="true"
    @confirm="doDelete"
  />
</div>
</template>
<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'
import { formatCurrency, formatDate, debounce } from '@/composables/useFormatters'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { useSortable } from '@/composables/useSortable'
import { usePagination } from '@/composables/usePagination'
import { useToast } from '@/composables/useToast'
import { ROLE_LABELS, getInvoiceLifecycle } from '@/data'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import AppBadge from '@/components/ui/AppBadge.vue'
import AppModal from '@/components/ui/AppModal.vue'
import SlidePanel from '@/components/ui/SlidePanel.vue'
import DateFilter from '@/components/ui/DateFilter.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import InvoiceActions from '@/components/ui/InvoiceActions.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'

const router = useRouter()
const mainStore = useMainStore()
const authStore = useAuthStore()
const navStore = useNavigationStore()
const { sortField, sortDir, toggleSort, sortItems } = useSortable('_revenue', 'desc')
const { currentPage, totalPages, paginate, resetPage } = usePagination(15)
const { showToast } = useToast()
const { t, tLabel } = useI18n()

const searchQuery = ref('')
const roleFilter = ref('')
const panelVisible = ref(false)
const selectedUserId = ref(null)
const panelDateFrom = ref('')
const panelDateTo = ref('')
const formVisible = ref(false)
const deleteDialogVisible = ref(false)
const deleteTarget = ref(null)
const form = ref({ id: null, name: '', nameEn: '', avatar: '', role: 'sales', phone: '', email: '' })

const isNew = computed(() => !form.value.id)

const userStats = computed(() => {
  const stats = {}
  mainStore.invoices.filter(inv => inv.status === 'standard').forEach(inv => {
    if (!stats[inv.authorId]) stats[inv.authorId] = { invoices: 0, revenue: 0, outstanding: 0 }
    stats[inv.authorId].invoices++
    stats[inv.authorId].revenue += inv.paidAmount
    const rem = inv.totalAmount - inv.paidAmount
    if (rem > 0 && getInvoiceLifecycle(inv).label !== 'Sold') stats[inv.authorId].outstanding += rem
  })
  return stats
})

function avgOrderFor(uid) {
  const s = userStats.value[uid]
  return s && s.invoices > 0 ? s.revenue / s.invoices : 0
}

const filteredUsers = computed(() => {
  let list = [...mainStore.users]
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(u =>
      u.nameEn.toLowerCase().includes(q) ||
      u.name.toLowerCase().includes(q) ||
      u.avatar.toLowerCase().includes(q)
    )
  }
  if (roleFilter.value) list = list.filter(u => u.role === roleFilter.value)
  list = list.map(u => ({
    ...u,
    _revenue: userStats.value[u.id]?.revenue || 0,
    _invoices: userStats.value[u.id]?.invoices || 0,
    _avgOrder: avgOrderFor(u.id)
  }))
  return sortItems(list)
})

const pagedUsers = computed(() => paginate(filteredUsers.value))

const selectedUser = computed(() => selectedUserId.value ? mainStore.userById(selectedUserId.value) : null)

const panelInvoices = computed(() => {
  if (!selectedUserId.value) return []
  let invoices = mainStore.invoices.filter(inv => inv.authorId === selectedUserId.value)
  if (panelDateFrom.value) invoices = invoices.filter(inv => inv.createdAt >= panelDateFrom.value)
  if (panelDateTo.value) invoices = invoices.filter(inv => inv.createdAt <= panelDateTo.value)
  return invoices.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
})

const debouncedSearch = debounce(() => resetPage(), 400)
function onSearch() { debouncedSearch() }

function onSort(field) {
  toggleSort(field)
  resetPage()
}

function openPanel(user) {
  selectedUserId.value = user.id
  panelVisible.value = true
}

function closePanel() {
  panelVisible.value = false
  selectedUserId.value = null
  panelDateFrom.value = ''
  panelDateTo.value = ''
}

function openAdd() {
  form.value = { id: null, name: '', nameEn: '', avatar: '', role: 'sales', phone: '', email: '' }
  formVisible.value = true
}

function openEdit(user) {
  form.value = { ...user }
  formVisible.value = true
}

function saveUser() {
  if (!form.value.nameEn.trim() || !form.value.role) return
  const isAdd = !form.value.id
  const user = { ...form.value, id: form.value.id || Date.now(), invoiceCount: 0, revenue: 0 }
  mainStore.saveUser(user)
  if (authStore.currentUser?.id === user.id) authStore.currentUser = { ...user }
  formVisible.value = false
  showToast('success', isAdd ? t('msg.userAdded') : t('msg.userUpdated'))
}

function confirmDelete(user) {
  if (user.id === authStore.currentUser?.id) {
    showToast('warning', t('msg.cannotDeleteSelf'))
    return
  }
  deleteTarget.value = user
  deleteDialogVisible.value = true
}

function doDelete() {
  if (!deleteTarget.value) return
  mainStore.deleteUser(deleteTarget.value.id)
  deleteTarget.value = null
  showToast('success', t('msg.userDeleted'))
}

function navigateToInvoice(id) {
  navStore.setNavReturn('/users', { selectedUserId: selectedUserId.value })
  router.push(`/invoices/${id}`)
}

watch(() => form.value.nameEn, val => {
  if (!form.value.id) {
    form.value.avatar = val.trim().split(/\s+/).map(p => p[0] || '').join('').toUpperCase().slice(0, 2)
  }
})
</script>
