import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../auth.js'

// ---------------------------------------------------------------------------
// Demo users fixture — matches the real data shape (no @/data import needed)
// ---------------------------------------------------------------------------
const DEMO_USERS = [
  { id: 1, nameEn: 'Giorgi Nozadze', avatar: 'GN', role: 'admin' },
  { id: 2, nameEn: 'Ana Kvaratskhelia', avatar: 'AK', role: 'manager' },
  { id: 3, nameEn: 'Lasha Beridze',    avatar: 'LB', role: 'sales' },
  { id: 5, nameEn: 'Davit Chavchavadze', avatar: 'DC', role: 'accountant' },
]
const DEMO_PASSWORD = 'gn2024'

describe('useAuthStore', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  // -------------------------------------------------------------------------
  // login — success paths
  // -------------------------------------------------------------------------
  describe('login', () => {
    it('succeeds with the user\'s first name', () => {
      const store = useAuthStore()
      const result = store.login('Giorgi', DEMO_PASSWORD, DEMO_USERS)

      expect(result.success).toBe(true)
      expect(store.isLoggedIn).toBe(true)
      expect(store.currentUser.nameEn).toBe('Giorgi Nozadze')
    })

    it('succeeds with the full nameEn (case-insensitive)', () => {
      const store = useAuthStore()
      const result = store.login('giorgi nozadze', DEMO_PASSWORD, DEMO_USERS)

      expect(result.success).toBe(true)
      expect(store.currentUser.id).toBe(1)
    })

    it('succeeds with avatar initials (case-insensitive)', () => {
      const store = useAuthStore()
      const result = store.login('gn', DEMO_PASSWORD, DEMO_USERS)

      expect(result.success).toBe(true)
      expect(store.currentUser.avatar).toBe('GN')
    })

    it('persists session to localStorage on success', () => {
      const store = useAuthStore()
      store.login('Giorgi', DEMO_PASSWORD, DEMO_USERS)

      expect(localStorage.getItem('gn_logged_in')).toBe('1')
      expect(localStorage.getItem('gn_user_id')).toBe('1')
    })

    it('returns an error for a wrong password', () => {
      const store = useAuthStore()
      const result = store.login('Giorgi', 'wrong-password', DEMO_USERS)

      expect(result.success).toBe(false)
      expect(result.error).toBe('Invalid password.')
      expect(store.isLoggedIn).toBe(false)
    })

    it('returns an error for an unknown username', () => {
      const store = useAuthStore()
      const result = store.login('Unknown User', DEMO_PASSWORD, DEMO_USERS)

      expect(result.success).toBe(false)
      expect(result.error).toMatch(/User not found/)
      expect(store.isLoggedIn).toBe(false)
    })
  })

  // -------------------------------------------------------------------------
  // logout
  // -------------------------------------------------------------------------
  describe('logout', () => {
    it('clears state and removes localStorage keys', () => {
      const store = useAuthStore()
      // Log in first
      store.login('Giorgi', DEMO_PASSWORD, DEMO_USERS)
      expect(store.isLoggedIn).toBe(true)

      store.logout()

      expect(store.isLoggedIn).toBe(false)
      expect(store.currentUser).toBeNull()
      expect(localStorage.getItem('gn_logged_in')).toBeNull()
      expect(localStorage.getItem('gn_user_id')).toBeNull()
    })
  })

  // -------------------------------------------------------------------------
  // init — session restore
  // -------------------------------------------------------------------------
  describe('init', () => {
    it('restores session from localStorage when keys are present', () => {
      localStorage.setItem('gn_logged_in', '1')
      localStorage.setItem('gn_user_id', '1')

      const store = useAuthStore()
      store.init(DEMO_USERS)

      expect(store.isLoggedIn).toBe(true)
      expect(store.currentUser.nameEn).toBe('Giorgi Nozadze')
    })

    it('stays logged out when localStorage is empty', () => {
      const store = useAuthStore()
      store.init(DEMO_USERS)

      expect(store.isLoggedIn).toBe(false)
      expect(store.currentUser).toBeNull()
    })

    it('stays logged out when user id does not match any user', () => {
      localStorage.setItem('gn_logged_in', '1')
      localStorage.setItem('gn_user_id', '999') // non-existent

      const store = useAuthStore()
      store.init(DEMO_USERS)

      expect(store.isLoggedIn).toBe(false)
    })
  })

  // -------------------------------------------------------------------------
  // isAdmin getter
  // -------------------------------------------------------------------------
  describe('isAdmin getter', () => {
    it('is true for the admin role', () => {
      const store = useAuthStore()
      store.login('Giorgi', DEMO_PASSWORD, DEMO_USERS) // role: admin

      expect(store.isAdmin).toBe(true)
    })

    it('is true for the manager role', () => {
      const store = useAuthStore()
      store.login('Ana', DEMO_PASSWORD, DEMO_USERS) // role: manager

      expect(store.isAdmin).toBe(true)
    })

    it('is false for the sales role', () => {
      const store = useAuthStore()
      store.login('Lasha', DEMO_PASSWORD, DEMO_USERS) // role: sales

      expect(store.isAdmin).toBe(false)
    })

    it('is false for the accountant role', () => {
      const store = useAuthStore()
      store.login('Davit', DEMO_PASSWORD, DEMO_USERS) // role: accountant

      expect(store.isAdmin).toBe(false)
    })
  })
})
