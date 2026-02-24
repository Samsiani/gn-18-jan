<template>
  <div v-if="isInvoiceForm && cartStore.count > 0" class="cart-fab" id="cart-fab">
    <button class="cart-fab__btn" @click.stop="togglePopover">
      <span v-html="icon('shopping-cart', 22)"></span>
      <span class="cart-fab__badge">{{ cartStore.count }}</span>
    </button>
    <div :class="['cart-fab__popover', { open: popoverOpen }]" id="cart-fab-popover">
      <div class="cart-fab__popover-header">
        <span class="font-semibold">{{ cartStore.count }} item(s)</span>
        <span style="color:var(--color-text-secondary); font-size:var(--text-xs)">{{ formatCurrency(cartStore.total) }}</span>
      </div>
      <div class="cart-fab__popover-items">
        <div v-for="product in cartStore.items" :key="product.id" class="cart-fab__popover-item">
          <div class="cart-fab__popover-item-info">
            <div class="font-medium" style="font-size:var(--text-sm)">{{ product.name }}</div>
            <div style="font-size:var(--text-xs); color:var(--color-text-tertiary)">{{ product.sku }} &middot; {{ formatCurrency(product.price) }}</div>
          </div>
          <span class="cart-fab__popover-item-remove" @click.stop="removeItem(product.id)">
            <span v-html="icon('x', 14)"></span>
          </span>
        </div>
      </div>
      <div class="cart-fab__popover-footer">
        <button class="btn btn--primary btn--sm btn--block" @click="addToInvoice">
          <span v-html="icon('file-plus', 14)"></span> Add to Invoice
        </button>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCartStore } from '@/stores/cart'
import { useToast } from '@/composables/useToast'
import { icon } from '@/composables/useIcons'
import { formatCurrency } from '@/composables/useFormatters'

const cartStore = useCartStore()
const route = useRoute()
const router = useRouter()
const { showToast } = useToast()
const popoverOpen = ref(false)

const isInvoiceForm = computed(() => route.name === 'invoice-new' || route.name === 'invoice-edit')

function togglePopover() { popoverOpen.value = !popoverOpen.value }

function removeItem(id) {
  cartStore.removeFromCart(id)
  showToast('info', 'Removed from cart')
}

function addToInvoice() {
  if (cartStore.count === 0) return
  cartStore.setPendingCartItems(cartStore.cartToInvoiceItems())
  showToast('success', `${cartStore.count} item(s) added to invoice`)
  popoverOpen.value = false
  // Force re-render by navigating away and back
  const currentPath = route.fullPath
  router.replace('/_reload').then(() => router.replace(currentPath))
}

function closeOnOutsideClick(e) {
  if (!e.target.closest('#cart-fab')) popoverOpen.value = false
}

onMounted(() => document.addEventListener('click', closeOnOutsideClick))
onUnmounted(() => document.removeEventListener('click', closeOnOutsideClick))
</script>
