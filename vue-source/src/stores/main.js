import { defineStore } from 'pinia'
import { api } from '@/api'
import { INVOICES, CUSTOMERS, PRODUCTS, USERS, DEPOSITS, OTHER_DELIVERIES, COMPANY, getInvoiceLifecycle, getEffectiveTotal } from '@/data'

function deepClone(obj) {
  return JSON.parse(JSON.stringify(obj))
}

export const useMainStore = defineStore('main', {
  state: () => ({
    theme: localStorage.getItem('gn-theme') || 'light',
    sidebarCollapsed: localStorage.getItem('gn-sidebar-collapsed') === 'true',
    invoices: [],
    customers: [],
    products: [],
    users: [],
    deposits: [],
    otherDeliveries: [],
    company: {},
  }),

  getters: {
    invoiceById: (state) => (id) => state.invoices.find(inv => inv.id === parseInt(id)),
    customerById: (state) => (id) => state.customers.find(c => c.id === parseInt(id)),
    productById: (state) => (id) => state.products.find(p => p.id === parseInt(id)),
    userById: (state) => (id) => state.users.find(u => u.id === parseInt(id)),
    totalRevenue: (state) => state.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled').reduce((sum, inv) => sum + inv.paidAmount, 0),
    outstandingBalance: (state) => state.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Sold' && getInvoiceLifecycle(inv).label !== 'Canceled').reduce((sum, inv) => sum + Math.max(0, getEffectiveTotal(inv) - inv.paidAmount), 0),
    invoicesThisMonth: (state) => {
      const now = new Date()
      const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
      return state.invoices.filter(inv => inv.createdAt >= firstOfMonth && getInvoiceLifecycle(inv).label !== 'Canceled').length
    },
    pendingReservations: (state) => state.invoices.filter(inv => getInvoiceLifecycle(inv).label === 'Reserved'),
    depositBalance: (state) => state.deposits.reduce((sum, d) => sum + d.amount, 0),
    otherDeliveryBalance: (state) => state.otherDeliveries.reduce((sum, r) => sum + r.amount, 0),
  },

  actions: {
    async init() {
      if (this.theme === 'dark') {
        document.documentElement.classList.add('dark')
      }

      if (api.isWordPress()) {
        try {
          const [companyRes, invoicesRes, customersRes, productsRes] = await Promise.all([
            api.get('settings/company'),
            api.get('invoices?per_page=500'),
            api.get('customers?per_page=500'),
            api.get('products?per_page=500'),
          ])
          this.company   = companyRes?.data  || {}
          this.invoices  = invoicesRes?.data  || []
          this.customers = customersRes?.data || []
          this.products  = productsRes?.data  || []
          // users, deposits, otherDeliveries are demo-only; not available via REST
        } catch (e) {
          console.error('[CIG] init failed:', e)
        }
      } else {
        this.invoices        = deepClone(INVOICES)
        this.customers       = deepClone(CUSTOMERS)
        this.products        = deepClone(PRODUCTS)
        this.users           = deepClone(USERS)
        this.deposits        = deepClone(DEPOSITS)
        this.otherDeliveries = deepClone(OTHER_DELIVERIES)
        this.company         = deepClone(COMPANY)
      }
    },

    async saveInvoice(invoice) {
      if (api.isWordPress()) {
        // Attach buyer from the customer store if the caller didn't already provide it
        let payload = { ...invoice }
        if (!payload.buyer) {
          const customer = this.customers.find(c => c.id === invoice.customerId)
          if (customer) {
            payload.buyer = {
              name:    customer.name,
              taxId:   customer.taxId,
              phone:   customer.phone,
              email:   customer.email,
              address: customer.address,
            }
          }
        }
        const isExisting = invoice.id && this.invoices.some(inv => inv.id === invoice.id)
        const res = isExisting
          ? await api.put(`invoices/${invoice.id}`, payload)
          : await api.post('invoices', payload)
        const saved = res?.data
        if (saved) {
          const idx = this.invoices.findIndex(inv => inv.id === saved.id)
          if (idx >= 0) this.invoices[idx] = saved
          else this.invoices.unshift(saved)
        }
        return saved || invoice
      }

      // Demo mode
      const idx = this.invoices.findIndex(inv => inv.id === invoice.id)
      if (idx >= 0) {
        this.invoices[idx] = invoice
      } else {
        this.invoices.push(invoice)
      }
      return invoice
    },

    async deleteInvoice(id) {
      if (api.isWordPress()) {
        await api.del(`invoices/${id}`)
      }
      this.invoices = this.invoices.filter(inv => inv.id !== parseInt(id))
    },

    saveUser(user) {
      const idx = this.users.findIndex(u => u.id === user.id)
      if (idx >= 0) { this.users[idx] = user } else { this.users.push(user) }
    },

    deleteUser(id) {
      this.users = this.users.filter(u => u.id !== parseInt(id))
    },

    updateProduct(id, updates) {
      const product = this.products.find(p => p.id === parseInt(id))
      if (product) {
        Object.assign(product, updates)
      }
    },

    addDeposit(deposit) {
      this.deposits.push(deposit)
    },

    deleteDeposit(id) {
      this.deposits = this.deposits.filter(d => d.id !== parseInt(id))
    },

    addOtherDelivery(record) {
      this.otherDeliveries.push(record)
    },

    updateOtherDelivery(id, updates) {
      const idx = this.otherDeliveries.findIndex(r => r.id === parseInt(id))
      if (idx >= 0) Object.assign(this.otherDeliveries[idx], updates)
    },

    deleteOtherDelivery(id) {
      this.otherDeliveries = this.otherDeliveries.filter(r => r.id !== parseInt(id))
    },

    async saveCompany(company) {
      if (api.isWordPress()) {
        const res = await api.put('settings/company', company)
        this.company = res?.data || company
        return
      }
      this.company = company
    },

    toggleTheme() {
      this.theme = this.theme === 'light' ? 'dark' : 'light'
      localStorage.setItem('gn-theme', this.theme)
      if (this.theme === 'dark') {
        document.documentElement.classList.add('dark')
      } else {
        document.documentElement.classList.remove('dark')
      }
    },

    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
      localStorage.setItem('gn-sidebar-collapsed', this.sidebarCollapsed)
    },

    // KPI Helpers
    getMonthlyTrend(months = 6) {
      const data = []
      const now = new Date()
      for (let i = months - 1; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
        const start = new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0]
        const end = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0]
        // Invoices created in this month → gross revenue + count
        const invs = this.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled' && inv.createdAt >= start && inv.createdAt <= end)
        // Payments made in this month (by payment.date, excl. consignment)
        let paid = 0
        this.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled').forEach(inv => {
          inv.payments.forEach(p => {
            if (p.method !== 'consignment' && p.date >= start && p.date <= end) paid += p.amount
          })
        })
        data.push({
          revenue: invs.reduce((s, inv) => s + getEffectiveTotal(inv), 0),
          paid,
          count: invs.length,
          outstanding: invs.reduce((s, inv) => s + Math.max(0, getEffectiveTotal(inv) - inv.paidAmount), 0),
          completed: invs.filter(inv => getInvoiceLifecycle(inv).label === 'Sold').length,
          avgOrder: invs.length > 0 ? invs.reduce((s, inv) => s + getEffectiveTotal(inv), 0) / invs.length : 0
        })
      }
      return data
    },

    getRevenueThisMonth() {
      const now = new Date()
      const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
      return this.invoices
        .filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled' && inv.createdAt >= firstOfMonth)
        .reduce((sum, inv) => sum + inv.paidAmount, 0)
    },

    getCompletedThisMonth() {
      const now = new Date()
      const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
      return this.invoices
        .filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label === 'Sold' && inv.createdAt >= firstOfMonth)
        .length
    },

    getNewCustomersThisMonth() {
      const now = new Date()
      const firstOfMonth = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
      const customerIds = new Set()
      this.invoices.filter(inv => inv.createdAt >= firstOfMonth).forEach(inv => customerIds.add(inv.customerId))
      return customerIds.size
    },

    getLowStockCount() {
      return this.products.filter(p => p.stock != null && (p.stock - (p.reserved || 0)) <= 5).length
    },

    getAvgOrderValue() {
      const standard = this.invoices.filter(inv => inv.status === 'standard' && getInvoiceLifecycle(inv).label !== 'Canceled')
      if (standard.length === 0) return 0
      return standard.reduce((s, inv) => s + getEffectiveTotal(inv), 0) / standard.length
    },

    getMonthOverMonthChange(currentVal, trendData) {
      if (trendData.length < 2) return null
      const lastMonth = trendData[trendData.length - 2]
      if (!lastMonth || lastMonth === 0) return null
      return ((currentVal - lastMonth) / lastMonth * 100).toFixed(1)
    }
  }
})
