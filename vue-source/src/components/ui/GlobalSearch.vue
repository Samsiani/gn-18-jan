<template>
  <div :class="['topbar__search', { 'topbar__search--open': isOpen }]" ref="wrapRef">
    <span class="topbar__search-icon" v-html="icon('search', 16)"></span>
    <input
      ref="inputRef"
      id="global-search"
      type="text"
      class="topbar__search-input"
      :placeholder="t('topbar.search')"
      v-model="query"
      @keydown="onKeydown"
      autocomplete="off"
      spellcheck="false"
    />
    <kbd v-if="!query" class="topbar__search-kbd">{{ shortcutLabel }}</kbd>
    <button v-if="query" class="topbar__search-clear" @mousedown.prevent="clear" v-html="icon('x', 13)"></button>
  </div>

  <Teleport to="body">
    <Transition name="gsearch">
      <div v-if="isOpen" class="gsearch-panel" :style="panelStyle" ref="panelRef">

        <!-- No results -->
        <div v-if="!hasResults" class="gsearch-empty">
          <span v-html="icon('search', 18)"></span>
          <span>{{ t('search.noResults') }}&nbsp;<strong>{{ query }}</strong></span>
        </div>

        <!-- Result sections -->
        <template v-else>
          <div v-for="section in visibleSections" :key="section.type" class="gsearch-section">
            <div class="gsearch-section__header">
              <span v-html="icon(section.icon, 11)"></span>
              {{ section.label }}
            </div>
            <button
              v-for="item in section.items"
              :key="item.key"
              :class="['gsearch-item', { 'gsearch-item--active': activeKey === item.key }]"
              @mouseenter="activeKey = item.key"
              @mousedown.prevent="selectItem(item)"
            >
              <span class="gsearch-item__icon" v-html="icon(section.itemIcon, 15)"></span>
              <span class="gsearch-item__body">
                <span class="gsearch-item__title" v-html="hl(item.title)"></span>
                <span class="gsearch-item__sub">{{ item.sub }}</span>
              </span>
              <span class="gsearch-item__meta">{{ item.meta }}</span>
            </button>
          </div>
        </template>

        <!-- Footer hints -->
        <div class="gsearch-footer">
          <span><kbd>↑↓</kbd> {{ t('search.navigate') }}</span>
          <span><kbd>↵</kbd> {{ t('search.open') }}</span>
          <span><kbd>Esc</kbd> {{ t('search.close') }}</span>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useMainStore } from '@/stores/main'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'
import { formatCurrency, escapeHtml } from '@/composables/useFormatters'

const router = useRouter()
const mainStore = useMainStore()
const { t } = useI18n()

const inputRef  = ref(null)
const wrapRef   = ref(null)
const panelRef  = ref(null)
const query     = ref('')
const activeKey = ref(null)
const panelStyle = ref({})

const isMac = /Mac|iPhone|iPad|iPod/.test(navigator.platform)
const shortcutLabel = isMac ? '⌘K' : 'Ctrl K'

const isOpen = computed(() => query.value.trim().length >= 1)

// ── Search ────────────────────────────────────────────────────────────────────
const n = (s) => (s || '').toLowerCase()

const results = computed(() => {
  const q = n(query.value.trim())
  if (!q) return { invoices: [], customers: [], products: [] }

  const invoices = mainStore.invoices
    .filter(inv => {
      const c = mainStore.customers.find(c => c.id === inv.customerId)
      return (
        n(inv.number).includes(q) ||
        n(c?.name).includes(q) ||
        n(c?.nameEn).includes(q) ||
        String(inv.totalAmount).includes(q)
      )
    })
    .slice(0, 4)
    .map(inv => {
      const c = mainStore.customers.find(c => c.id === inv.customerId)
      return {
        key:   `inv-${inv.id}`,
        title: inv.number,
        sub:   c?.nameEn || c?.name || '—',
        meta:  formatCurrency(inv.totalAmount),
        route: { name: 'invoice-view', params: { id: inv.id } },
      }
    })

  const customers = mainStore.customers
    .filter(c =>
      n(c.name).includes(q) ||
      n(c.nameEn).includes(q) ||
      n(c.taxId).includes(q) ||
      n(c.phone).includes(q)
    )
    .slice(0, 4)
    .map(c => ({
      key:   `cust-${c.id}`,
      title: c.nameEn || c.name,
      sub:   c.taxId,
      meta:  c.phone,
      route: '/customers',
    }))

  const products = mainStore.products
    .filter(p =>
      n(p.name).includes(q) ||
      n(p.nameKa).includes(q) ||
      n(p.sku).includes(q) ||
      n(p.brand).includes(q)
    )
    .slice(0, 4)
    .map(p => ({
      key:   `prod-${p.id}`,
      title: p.name,
      sub:   `${p.brand} · ${p.sku}`,
      meta:  `${p.stock} ${t('search.inStock')}`,
      route: '/stock',
    }))

  return { invoices, customers, products }
})

const sections = computed(() => [
  { type: 'invoice',  label: t('search.invoices'),  icon: 'file-text', itemIcon: 'file-text', items: results.value.invoices  },
  { type: 'customer', label: t('search.customers'), icon: 'users',     itemIcon: 'building',  items: results.value.customers },
  { type: 'product',  label: t('search.products'),  icon: 'package',   itemIcon: 'package',   items: results.value.products  },
])

const visibleSections = computed(() => sections.value.filter(s => s.items.length > 0))
const flatItems       = computed(() => visibleSections.value.flatMap(s => s.items))
const hasResults      = computed(() => flatItems.value.length > 0)

// Reset active item when result list changes
watch(flatItems, items => {
  if (!activeKey.value || !items.find(i => i.key === activeKey.value)) {
    activeKey.value = items[0]?.key ?? null
  }
})

// ── Keyboard navigation ───────────────────────────────────────────────────────
function onKeydown(e) {
  if (!isOpen.value) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    const idx = flatItems.value.findIndex(i => i.key === activeKey.value)
    activeKey.value = flatItems.value[(idx + 1) % flatItems.value.length]?.key ?? null
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    const idx = flatItems.value.findIndex(i => i.key === activeKey.value)
    const prev = idx <= 0 ? flatItems.value.length - 1 : idx - 1
    activeKey.value = flatItems.value[prev]?.key ?? null
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const item = flatItems.value.find(i => i.key === activeKey.value)
    if (item) selectItem(item)
  } else if (e.key === 'Escape') {
    close()
  }
}

function selectItem(item) {
  router.push(item.route)
  close()
}

function clear() {
  query.value = ''
  nextTick(() => inputRef.value?.focus())
}

function close() {
  query.value   = ''
  activeKey.value = null
  inputRef.value?.blur()
}

// ── Panel positioning ─────────────────────────────────────────────────────────
function updatePos() {
  if (!wrapRef.value) return
  const rect = wrapRef.value.getBoundingClientRect()
  panelStyle.value = {
    top:   (rect.bottom + 8) + 'px',
    left:  rect.left + 'px',
    width: Math.max(rect.width, 420) + 'px',
  }
}

watch(isOpen, val => { if (val) nextTick(updatePos) })

// ── Global shortcuts ──────────────────────────────────────────────────────────
function onGlobalKey(e) {
  // Cmd+K / Ctrl+K — focus search from anywhere
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault()
    inputRef.value?.focus()
    inputRef.value?.select()
    return
  }
  // '/' when not in a text field
  if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
    e.preventDefault()
    inputRef.value?.focus()
  }
}

function onClickOutside(e) {
  if (
    isOpen.value &&
    !wrapRef.value?.contains(e.target) &&
    !panelRef.value?.contains(e.target)
  ) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('keydown', onGlobalKey)
  document.addEventListener('mousedown', onClickOutside)
})
onUnmounted(() => {
  document.removeEventListener('keydown', onGlobalKey)
  document.removeEventListener('mousedown', onClickOutside)
})

// ── Text highlight ────────────────────────────────────────────────────────────
function hl(text) {
  const q = query.value.trim()
  if (!q || !text) return escapeHtml(text || '')
  const safe = escapeHtml(text)
  const idx  = safe.toLowerCase().indexOf(q.toLowerCase())
  if (idx === -1) return safe
  return (
    safe.slice(0, idx) +
    `<mark class="gsearch-mark">${safe.slice(idx, idx + q.length)}</mark>` +
    safe.slice(idx + q.length)
  )
}
</script>
