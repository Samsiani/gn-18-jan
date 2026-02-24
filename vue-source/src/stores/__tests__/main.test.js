import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useMainStore } from '../main.js'

// ---------------------------------------------------------------------------
// Minimal invoice fixture — avoids importing the full @/data module
// ---------------------------------------------------------------------------
const makeInvoice = (overrides = {}) => ({
  id: 1,
  invoiceNumber: 'GN-1001',
  status: 'standard',
  lifecycleStatus: 'active',
  createdAt: '2025-01-01',
  totalAmount: 1000,
  paidAmount: 500,
  customerId: 1,
  authorId: 1,
  payments: [],
  items: [{ itemStatus: 'reserved', productId: 10, qty: 1, price: 1000, itemTotal: 1000 }],
  ...overrides,
})

describe('useMainStore', () => {
  beforeEach(() => {
    // Create a fresh pinia instance for every test (no data module side-effects)
    setActivePinia(createPinia())
  })

  // -------------------------------------------------------------------------
  // saveInvoice
  // -------------------------------------------------------------------------
  describe('saveInvoice', () => {
    it('creates a new invoice when id is not found', () => {
      const store = useMainStore()
      expect(store.invoices).toHaveLength(0)

      store.saveInvoice(makeInvoice({ id: 999, invoiceNumber: 'GN-9999' }))

      expect(store.invoices).toHaveLength(1)
      expect(store.invoices[0].id).toBe(999)
    })

    it('updates an existing invoice without changing the array length', () => {
      const store = useMainStore()
      store.invoices = [makeInvoice({ id: 1, totalAmount: 1000 })]

      store.saveInvoice(makeInvoice({ id: 1, totalAmount: 2500 }))

      expect(store.invoices).toHaveLength(1)
      expect(store.invoices[0].totalAmount).toBe(2500)
    })
  })

  // -------------------------------------------------------------------------
  // deleteInvoice
  // -------------------------------------------------------------------------
  describe('deleteInvoice', () => {
    it('removes the invoice with the matching id', () => {
      const store = useMainStore()
      store.invoices = [
        makeInvoice({ id: 1 }),
        makeInvoice({ id: 2 }),
      ]

      store.deleteInvoice(1)

      expect(store.invoices).toHaveLength(1)
      expect(store.invoices[0].id).toBe(2)
    })

    it('leaves the array unchanged when the id does not exist', () => {
      const store = useMainStore()
      store.invoices = [makeInvoice({ id: 1 })]

      store.deleteInvoice(9999)

      expect(store.invoices).toHaveLength(1)
    })
  })

  // -------------------------------------------------------------------------
  // invoiceById getter
  // -------------------------------------------------------------------------
  describe('invoiceById getter', () => {
    it('returns the correct invoice for a given id', () => {
      const store = useMainStore()
      store.invoices = [
        makeInvoice({ id: 1 }),
        makeInvoice({ id: 2, invoiceNumber: 'GN-1002' }),
      ]

      const result = store.invoiceById(2)

      expect(result).toBeDefined()
      expect(result.invoiceNumber).toBe('GN-1002')
    })

    it('returns undefined for an unknown id', () => {
      const store = useMainStore()
      store.invoices = [makeInvoice({ id: 1 })]

      expect(store.invoiceById(99)).toBeUndefined()
    })
  })

  // -------------------------------------------------------------------------
  // getMonthOverMonthChange
  // -------------------------------------------------------------------------
  describe('getMonthOverMonthChange', () => {
    it('returns null when trendData has fewer than 2 entries', () => {
      const store = useMainStore()

      expect(store.getMonthOverMonthChange(100, [])).toBeNull()
      expect(store.getMonthOverMonthChange(100, [{ paid: 80 }])).toBeNull()
    })

    it('returns null when the previous month value is falsy (zero)', () => {
      const store = useMainStore()
      // trendData[length-2] is the "last month" value; must be 0 to trigger null
      const trend = [0, 200]  // lastMonth = trend[0] = 0

      expect(store.getMonthOverMonthChange(200, trend)).toBeNull()
    })

    it('computes a positive percentage change correctly', () => {
      const store = useMainStore()
      // lastMonth = trend[0] = 100; current = 150 → (150-100)/100*100 = 50.0
      const trend = [100, 100]
      const pct = store.getMonthOverMonthChange(150, trend)

      expect(parseFloat(pct)).toBeCloseTo(50, 1)
    })
  })
})
