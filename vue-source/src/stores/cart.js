import { defineStore } from 'pinia'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: [],
    expanded: false,
    pendingCartItems: null
  }),

  getters: {
    count: (state) => state.items.length,
    total: (state) => state.items.reduce((sum, p) => sum + p.price, 0),
    isInCart: (state) => (productId) => state.items.some(c => c.id === parseInt(productId))
  },

  actions: {
    addToCart(product) {
      if (this.items.some(c => c.id === product.id)) return false
      this.items.push({ ...product })
      return true
    },

    removeFromCart(productId) {
      this.items = this.items.filter(c => c.id !== parseInt(productId))
    },

    toggleCartItem(productId, productsOrFn) {
      const pid = parseInt(productId)
      const idx = this.items.findIndex(c => c.id === pid)
      if (idx >= 0) {
        this.items.splice(idx, 1)
        return false // removed
      } else {
        const product = typeof productsOrFn === 'function'
          ? productsOrFn(pid)
          : Array.isArray(productsOrFn)
            ? productsOrFn.find(p => p.id === pid)
            : null
        if (product) {
          this.items.push({ ...product })
          return true // added
        }
        return false
      }
    },

    clearCart() {
      this.items = []
      this.expanded = false
    },

    cartToInvoiceItems() {
      return this.items.map(product => ({
        productId: product.id,
        qty: 1,
        price: product.price,
        itemStatus: 'reserved',
        warranty: '1_year',
        reservationDays: 14
      }))
    },

    setPendingCartItems(items) {
      this.pendingCartItems = items
    },

    consumePendingCartItems() {
      const items = this.pendingCartItems
      this.pendingCartItems = null
      return items
    }
  }
})
