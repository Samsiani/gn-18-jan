<template>
  <div :class="['cart-bar', { visible: cartStore.count > 0 && !isInvoiceForm, expanded: cartStore.expanded }]" id="global-cart-bar" v-show="!isInvoiceForm">
    <template v-if="cartStore.count > 0">
      <div class="cart-bar__header" @click="toggleExpand">
        <div class="cart-bar__title">
          <span v-html="icon('shopping-cart', 16)"></span>
          <span>Cart</span>
          <span class="cart-bar__count">{{ cartStore.count }}</span>
          <span style="font-weight:var(--font-weight-normal); color:var(--color-text-secondary); font-size:var(--text-xs); margin-left:var(--space-2)">{{ formatCurrency(cartStore.total) }}</span>
        </div>
        <div style="display:flex; align-items:center; gap:var(--space-2)">
          <button class="btn btn--primary btn--sm cart-bar__convert-btn" @click.stop="convertToInvoice">
            <span v-html="icon('file-plus', 14)"></span> <span>ინვოისში დამატება</span>
          </button>
          <button class="btn btn--ghost btn--sm" @click.stop="clearCart" data-tooltip="Clear cart">
            <span v-html="icon('trash-2', 14)"></span>
          </button>
          <span class="cart-bar__toggle-icon" v-html="cartStore.expanded ? icon('chevron-down', 16) : icon('chevron-up', 16)"></span>
        </div>
      </div>
      <div class="cart-bar__items">
        <div v-for="product in cartStore.items" :key="product.id" class="cart-bar__item">
          <div class="cart-bar__item-thumb">{{ (product.sku || '').slice(-3) }}</div>
          <div class="cart-bar__item-info">
            <div class="font-medium">{{ product.name }}</div>
            <div style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ product.sku }} &middot; {{ formatCurrency(product.price) }}</div>
          </div>
          <span class="cart-bar__item-remove" @click="removeItem(product.id)" data-tooltip="Remove">
            <span v-html="icon('x', 14)"></span>
          </span>
        </div>
      </div>
    </template>
  </div>

  <ConfirmDialog v-model:visible="showClearConfirm" title="Clear Cart" message="Remove all items from the cart?" @confirm="doClear" />
</template>
<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useToast } from '@/composables/useToast'
import { icon } from '@/composables/useIcons'
import { formatCurrency } from '@/composables/useFormatters'
import ConfirmDialog from './ConfirmDialog.vue'

const cartStore = useCartStore()
const route = useRoute()
const router = useRouter()
const { showToast } = useToast()
const showClearConfirm = ref(false)

const isInvoiceForm = computed(() => {
  return route.name === 'invoice-new' || route.name === 'invoice-edit'
})

function toggleExpand(e) {
  if (e.target.closest('.btn')) return
  cartStore.expanded = !cartStore.expanded
}

function removeItem(id) {
  cartStore.removeFromCart(id)
  showToast('info', 'Removed from cart')
}

function convertToInvoice() {
  if (cartStore.count === 0) return
  cartStore.setPendingCartItems(cartStore.cartToInvoiceItems())
  router.push('/invoices/new')
  showToast('success', `Creating invoice with ${cartStore.count} item(s) from cart`)
}

function clearCart() { showClearConfirm.value = true }
function doClear() {
  cartStore.clearCart()
  showToast('info', 'Cart cleared')
}
</script>
