import { defineStore } from 'pinia'

const DEMO_PASSWORD = 'gn2024'
const LS_USER_KEY = 'gn_user_id'
const LS_LOGGED_IN = 'gn_logged_in'

function isWordPressMode() {
  return typeof window !== 'undefined' && !!window.cigGlobal?.restUrl
}

export const useAuthStore = defineStore('auth', {
  state: () => {
    if (isWordPressMode()) {
      return {
        currentUser: window.cigGlobal.currentUser || null,
        isLoggedIn: window.cigGlobal.isLoggedIn === true,
      }
    }
    return {
      currentUser: null,
      isLoggedIn: false,
    }
  },

  getters: {
    isAdmin: (state) => {
      const role = state.currentUser?.role
      return role === 'admin' || role === 'manager'
    },
    isConsultant() {
      return !this.isAdmin
    },
    isSalesRole: (state) => state.currentUser?.role === 'sales',
    isAccountantRole: (state) => state.currentUser?.role === 'accountant'
  },

  actions: {
    init(users) {
      // WordPress mode: state is already initialised from cigGlobal in state()
      if (isWordPressMode()) return

      // Demo mode: restore session from localStorage
      const loggedIn = localStorage.getItem(LS_LOGGED_IN) === '1'
      const savedId = parseInt(localStorage.getItem(LS_USER_KEY))
      if (loggedIn && savedId && users) {
        const user = users.find(u => u.id === savedId)
        if (user) {
          this.currentUser = { ...user }
          this.isLoggedIn = true
          return
        }
      }
      // No saved session — stay logged out
      this.isLoggedIn = false
      this.currentUser = null
    },

    login(username, password, users) {
      if (password !== DEMO_PASSWORD) {
        return { success: false, error: 'Invalid password.' }
      }
      const q = username.trim().toLowerCase()
      const user = users.find(u =>
        u.nameEn.toLowerCase() === q ||
        u.nameEn.split(' ')[0].toLowerCase() === q ||
        u.avatar.toLowerCase() === q
      )
      if (!user) {
        return { success: false, error: 'User not found. Try your first name or initials.' }
      }
      this.currentUser = { ...user }
      this.isLoggedIn = true
      localStorage.setItem(LS_USER_KEY, user.id)
      localStorage.setItem(LS_LOGGED_IN, '1')
      return { success: true }
    },

    logout() {
      this.currentUser = null
      this.isLoggedIn = false
      localStorage.removeItem(LS_USER_KEY)
      localStorage.removeItem(LS_LOGGED_IN)
    },

    switchUser(userId, users) {
      const user = users.find(u => u.id === parseInt(userId))
      if (user) {
        this.currentUser = { ...user }
        localStorage.setItem(LS_USER_KEY, user.id)
      }
    },

    getMyInvoices(invoices) {
      const userId = this.currentUser?.id
      return invoices.filter(inv => inv.authorId === userId)
    },

    getMyReservations(invoices) {
      const userId = this.currentUser?.id
      return invoices.filter(inv =>
        inv.authorId === userId &&
        inv.items.some(item => item.itemStatus === 'reserved')
      )
    }
  }
})
