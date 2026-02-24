<template>
<div>
  <div class="page-header">
    <div>
      <h1 class="page-header__title">{{ authStore.isConsultant ? t('page.stock.consultantTitle') : t('page.stock.adminTitle') }}</h1>
      <p class="page-header__subtitle" style="margin:0">{{ authStore.isConsultant ? t('page.stock.consultantSubtitle') : t('page.stock.adminSubtitle') }}</p>
    </div>
    <div class="page-header__actions">
      <span v-if="cartStore.count > 0" class="badge badge--primary">{{ t('page.stock.inCart', { n: cartStore.count }) }}</span>
    </div>
  </div>

  <div class="stock-header">
    <div class="stock-header__search">
      <div style="position:relative">
        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--color-text-tertiary)" v-html="icon('search', 16)"></span>
        <input type="text" class="form-input form-input--search" v-model="searchQuery" @input="onSearch" :placeholder="t('page.stock.searchPlaceholder')" style="padding-left:36px">
      </div>
    </div>
  </div>

  <div class="data-table-wrapper" style="overflow-x:hidden">
    <table class="data-table" style="table-layout:fixed">
      <colgroup>
        <col style="width:52px">   <!-- image -->
        <col style="width:220px">  <!-- name + sku -->
        <col style="width:100px">  <!-- brand -->
        <col style="width:120px">  <!-- price (fits ₾99,999.00 + inline edit) -->
        <col style="width:72px">   <!-- stock -->
        <col style="width:82px">   <!-- reserved -->
        <col style="width:90px">   <!-- available -->
        <col style="width:155px">  <!-- status (fits longest Georgian label) -->
        <col style="width:86px">   <!-- actions -->
      </colgroup>
      <thead>
        <tr>
          <th>{{ t('col.image') }}</th>
          <SortableHeader :label="t('col.title')" field="name" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th>{{ t('col.brand') }}</th>
          <SortableHeader :label="t('col.price')" field="price" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <SortableHeader :label="t('col.stock')" field="stock" :sortField="sortField" :sortDir="sortDir" @sort="onSort" />
          <th>{{ t('col.reserved') }}</th>
          <th>{{ t('col.available') }}</th>
          <th>{{ t('col.status') }}</th>
          <th class="text-center">{{ t('col.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="product in paginatedItems" :key="product.id">
          <td><div class="stock-thumb">{{ product.sku.slice(-3) }}</div></td>
          <td style="overflow:hidden">
            <div class="font-medium" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis">{{ product.name }}</div>
            <div style="font-size:var(--text-xs); color:var(--color-text-tertiary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis">{{ product.nameKa }}</div>
            <div style="font-size:var(--text-xs); color:var(--color-text-tertiary); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"><span style="font-weight:var(--font-weight-medium); color:var(--color-text-secondary)">SKU:</span> {{ product.sku }}</div>
          </td>
          <td style="font-size:var(--text-sm); overflow:hidden; white-space:nowrap; text-overflow:ellipsis">{{ product.brand }}</td>
          <td>
            <template v-if="authStore.isConsultant">
              <span style="padding:var(--space-1) var(--space-2)">{{ formatCurrency(product.price) }}</span>
            </template>
            <template v-else-if="editingCell?.productId === product.id && editingCell?.field === 'price'">
              <div class="inline-edit">
                <input type="number" class="inline-edit__input" :ref="el => setEditRef(el)" :value="product.price" step="0.01" min="0" @keydown.enter="saveEdit(product.id, 'price', $event)" @keydown.escape="cancelEdit">
                <div class="inline-edit__actions">
                  <button class="btn btn--icon btn--sm btn--success" @click="saveEdit(product.id, 'price', $event)"><span v-html="icon('check', 12)"></span></button>
                  <button class="btn btn--icon btn--sm btn--ghost" @click="cancelEdit"><span v-html="icon('x', 12)"></span></button>
                </div>
              </div>
            </template>
            <template v-else>
              <span class="inline-edit__value" @click="startEdit(product.id, 'price')">{{ formatCurrency(product.price) }}</span>
            </template>
          </td>
          <td>
            <template v-if="authStore.isConsultant">
              <span style="padding:var(--space-1) var(--space-2)">{{ formatNumber(product.stock) }}</span>
            </template>
            <template v-else-if="editingCell?.productId === product.id && editingCell?.field === 'stock'">
              <div class="inline-edit">
                <input type="number" class="inline-edit__input" :ref="el => setEditRef(el)" :value="product.stock" min="0" @keydown.enter="saveEdit(product.id, 'stock', $event)" @keydown.escape="cancelEdit">
                <div class="inline-edit__actions">
                  <button class="btn btn--icon btn--sm btn--success" @click="saveEdit(product.id, 'stock', $event)"><span v-html="icon('check', 12)"></span></button>
                  <button class="btn btn--icon btn--sm btn--ghost" @click="cancelEdit"><span v-html="icon('x', 12)"></span></button>
                </div>
              </div>
            </template>
            <template v-else>
              <span class="inline-edit__value" @click="startEdit(product.id, 'stock')">{{ formatNumber(product.stock) }}</span>
            </template>
          </td>
          <td>{{ formatNumber(product.reserved) }}</td>
          <td class="font-medium">{{ formatNumber(product.stock - product.reserved) }}</td>
          <td>
            <div :class="['stock-indicator', `stock-indicator--${stockLevel(product)}`]">
              <span class="stock-indicator__dot"></span>
              {{ stockLabel(stockLevel(product)) }}
            </div>
          </td>
          <td class="text-center">
            <div style="display:flex; gap:4px; justify-content:center">
              <button class="btn btn--ghost btn--icon btn--sm" @click="viewProduct(product.id)" :data-tooltip="t('page.stock.quickView')">
                <span v-html="icon('eye', 14)"></span>
              </button>
              <button :class="['btn', 'btn--ghost', 'btn--icon', 'btn--sm', { 'btn--primary': cartStore.isInCart(product.id) }]"
                @click="toggleCart(product.id)"
                :data-tooltip="cartStore.isInCart(product.id) ? t('page.stock.inCartTooltip') : t('page.stock.addToCartTooltip')">
                <span v-html="icon('shopping-cart', 14)"></span>
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
    <AppPagination :currentPage="currentPage" :totalPages="totalPages" :total="filteredProducts.length" @page-change="currentPage = $event" />
  </div>

  <!-- Product Panel -->
  <SlidePanel :visible="!!selectedProductId" @close="closePanel">
    <template #header>{{ t('section.productDetails') }}</template>
    <template v-if="selectedProduct">
      <div class="product-detail">
        <div class="product-detail__image-placeholder">
          <span v-html="icon('package', 48)"></span>
          <div style="margin-top:var(--space-2); font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ selectedProduct.sku }}</div>
        </div>

        <div class="product-detail__name">{{ selectedProduct.name }}</div>
        <div style="font-size:var(--text-sm); color:var(--color-text-tertiary); margin-bottom:var(--space-4)">{{ selectedProduct.nameKa }}</div>

        <div class="product-detail__meta">
          <div class="product-detail__meta-item">
            <div class="product-detail__meta-label">{{ t('col.brand') }}</div>
            <div class="product-detail__meta-value">{{ selectedProduct.brand }}</div>
          </div>
          <div class="product-detail__meta-item">
            <div class="product-detail__meta-label">SKU</div>
            <div class="product-detail__meta-value">{{ selectedProduct.sku }}</div>
          </div>
          <div class="product-detail__meta-item">
            <div class="product-detail__meta-label">{{ t('col.type') }}</div>
            <div class="product-detail__meta-value">{{ selectedProduct.category }}</div>
          </div>
          <div class="product-detail__meta-item">
            <div class="product-detail__meta-label">{{ t('col.price') }}</div>
            <div class="product-detail__meta-value font-semibold">{{ formatCurrency(selectedProduct.price) }}</div>
          </div>
        </div>

        <p style="font-size:var(--text-sm); color:var(--color-text-secondary); margin-bottom:var(--space-6)">{{ selectedProduct.description }}</p>

        <h4 style="font-size:var(--text-md); font-weight:var(--font-weight-semibold); margin-bottom:var(--space-3)">{{ t('section.stockBreakdown') }}</h4>
        <div class="product-detail__stock-grid">
          <div class="product-detail__stock-item">
            <div class="product-detail__stock-value">{{ formatNumber(selectedProduct.stock) }}</div>
            <div class="product-detail__stock-label">{{ t('page.stock.total') }}</div>
          </div>
          <div class="product-detail__stock-item">
            <div class="product-detail__stock-value" style="color:var(--color-warning)">{{ formatNumber(selectedProduct.reserved) }}</div>
            <div class="product-detail__stock-label">{{ t('page.stock.reserved') }}</div>
          </div>
          <div class="product-detail__stock-item">
            <div class="product-detail__stock-value" :style="{ color: panelAvailable > 5 ? 'var(--color-success)' : panelAvailable > 0 ? 'var(--color-warning)' : 'var(--color-danger)' }">{{ formatNumber(panelAvailable) }}</div>
            <div class="product-detail__stock-label">{{ t('page.stock.available') }}</div>
          </div>
        </div>

        <div style="margin-bottom:var(--space-6)">
          <div :class="['stock-indicator', `stock-indicator--${stockLevel(selectedProduct)}`]">
            <span class="stock-indicator__dot"></span>
            {{ stockLabel(stockLevel(selectedProduct)) }}
          </div>
        </div>

        <button :class="['btn', cartStore.isInCart(selectedProduct.id) ? 'btn--danger' : 'btn--primary', 'btn--block']" @click="togglePanelCart">
          <span v-html="icon('shopping-cart', 16)"></span> {{ cartStore.isInCart(selectedProduct.id) ? t('btn.removeFromCart') : t('btn.addToCart') }}
        </button>
      </div>
    </template>
  </SlidePanel>
</div>
</template>
<script setup>
import { ref, computed, nextTick } from 'vue'
import { useMainStore } from '@/stores/main'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { formatCurrency, formatNumber, debounce } from '@/composables/useFormatters'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { useToast } from '@/composables/useToast'
import { useSortable } from '@/composables/useSortable'
import { usePagination } from '@/composables/usePagination'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import SlidePanel from '@/components/ui/SlidePanel.vue'

const mainStore = useMainStore()
const authStore = useAuthStore()
const cartStore = useCartStore()
const { showToast } = useToast()
const { t } = useI18n()
const { sortField, sortDir, toggleSort, sortItems } = useSortable('name', 'asc')
const { currentPage, totalPages, paginate, resetPage } = usePagination(20)

const searchQuery = ref('')
const editingCell = ref(null)
const selectedProductId = ref(null)
let editInputRef = null

function setEditRef(el) { editInputRef = el; if (el) nextTick(() => { el.focus(); el.select() }) }

const filteredProducts = computed(() => {
  let products = [...mainStore.products]
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    products = products.filter(p =>
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      p.brand.toLowerCase().includes(q) ||
      (p.nameKa && p.nameKa.toLowerCase().includes(q))
    )
  }
  return sortItems(products)
})

const paginatedItems = computed(() => paginate(filteredProducts.value))
const selectedProduct = computed(() => selectedProductId.value ? mainStore.productById(selectedProductId.value) : null)
const panelAvailable = computed(() => selectedProduct.value ? selectedProduct.value.stock - selectedProduct.value.reserved : 0)

function stockLevel(product) {
  const available = product.stock - product.reserved
  if (available > 5) return 'high'
  if (available > 0) return 'medium'
  if (available === 0 && product.stock > 0) return 'low'
  return 'none'
}

function stockLabel(level) {
  const labels = {
    high: t('page.stock.inStock'),
    medium: t('page.stock.lowStock'),
    low: t('page.stock.outOfStock'),
    none: t('page.stock.unmanaged')
  }
  return labels[level] || t('common.unknown')
}

const debouncedSearch = debounce(() => resetPage(), 400)
function onSearch() { debouncedSearch() }
function onSort(field) { toggleSort(field); resetPage() }

function startEdit(productId, field) {
  if (authStore.isConsultant) return
  editingCell.value = { productId, field }
}

function saveEdit(productId, field, event) {
  const input = event.target.tagName === 'INPUT' ? event.target : event.target.closest('.inline-edit')?.querySelector('input')
  if (input) {
    const val = field === 'price' ? parseFloat(input.value) || 0 : parseInt(input.value) || 0
    mainStore.updateProduct(productId, { [field]: val })
    showToast('success', field === 'price' ? t('msg.priceUpdated') : t('msg.stockUpdated'))
  }
  editingCell.value = null
}

function cancelEdit() { editingCell.value = null }

function toggleCart(id) {
  const added = cartStore.toggleCartItem(id, mainStore.products)
  showToast(added ? 'success' : 'info', added ? t('msg.addedToCart') : t('msg.removedFromCart'))
}

function viewProduct(id) { selectedProductId.value = id }
function closePanel() { selectedProductId.value = null }

function togglePanelCart() {
  if (!selectedProduct.value) return
  const added = cartStore.toggleCartItem(selectedProduct.value.id, mainStore.products)
  showToast(added ? 'success' : 'info', added ? t('msg.addedToCart') : t('msg.removedFromCart'))
}
</script>
