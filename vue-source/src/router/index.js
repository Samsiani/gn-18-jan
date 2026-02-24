import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNavigationStore } from '@/stores/navigation'

const routes = [
  { path: '/login', name: 'login', component: () => import('@/pages/LoginPage.vue'), meta: { public: true } },
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue') },
  { path: '/consultant-home', name: 'consultant-home', component: () => import('@/pages/ConsultantDashboardPage.vue') },
  { path: '/invoices', name: 'invoices', component: () => import('@/pages/InvoiceListPage.vue') },
  { path: '/invoices/new', name: 'invoice-new', component: () => import('@/pages/InvoiceFormPage.vue') },
  { path: '/invoices/:id', name: 'invoice-view', component: () => import('@/pages/InvoiceViewPage.vue'), props: true },
  { path: '/invoices/:id/edit', name: 'invoice-edit', component: () => import('@/pages/InvoiceFormPage.vue'), props: true },
  { path: '/invoices/:id/warranty', name: 'invoice-warranty', component: () => import('@/pages/WarrantyPage.vue'), props: true },
  { path: '/accountant', name: 'accountant', component: () => import('@/pages/AccountantPage.vue') },
  { path: '/statistics', name: 'statistics', component: () => import('@/pages/StatisticsPage.vue') },
  { path: '/stock', name: 'stock', component: () => import('@/pages/StockPage.vue') },
  { path: '/customers', name: 'customers', component: () => import('@/pages/CustomersPage.vue') },
  { path: '/settings', name: 'settings', component: () => import('@/pages/SettingsPage.vue') },
  { path: '/users', name: 'users', component: () => import('@/pages/UsersPage.vue') },
  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior() {
    return { top: 0, left: 0 }
  }
})

// Navigation guard
router.beforeEach((to, from) => {
  const auth = useAuthStore()
  const nav = useNavigationStore()

  // Clear stale navigation return state
  nav.clearNavReturnIfNotBack()

  // Public routes (login) — redirect to app if already logged in
  if (to.meta.public) {
    if (auth.isLoggedIn) {
      if (auth.isAdmin) return { name: 'dashboard' }
      if (auth.isAccountantRole) return { name: 'accountant' }
      return { name: 'consultant-home' }
    }
    return true
  }

  // Require authentication for all other routes
  if (!auth.isLoggedIn) {
    return { name: 'login' }
  }

  const adminRoutes = ['statistics', 'customers', 'settings', 'users']

  // Route guard: redirect consultants from admin-only routes
  if (auth.isConsultant && adminRoutes.includes(to.name)) {
    return { name: auth.isAccountantRole ? 'accountant' : 'consultant-home' }
  }

  // Sales blocked from accountant
  if (to.name === 'accountant' && auth.isConsultant && !auth.isAccountantRole) {
    return { name: 'consultant-home' }
  }

  // Accountant blocked from invoice list and stock
  if (auth.isAccountantRole && (to.name === 'invoices' || to.name === 'stock')) {
    return { name: 'accountant' }
  }

  // Consultant from dashboard -> consultant-home or accountant
  if (auth.isConsultant && to.name === 'dashboard') {
    return { name: auth.isAccountantRole ? 'accountant' : 'consultant-home' }
  }

  // Admin from consultant-home -> dashboard
  if (auth.isAdmin && to.name === 'consultant-home') {
    return { name: 'dashboard' }
  }

  // Accountant from consultant-home -> accountant
  if (auth.isAccountantRole && to.name === 'consultant-home') {
    return { name: 'accountant' }
  }

  // Default redirect from /
  if (to.path === '/') {
    if (auth.isAdmin) return { name: 'dashboard' }
    if (auth.isAccountantRole) return { name: 'accountant' }
    return { name: 'consultant-home' }
  }

  // Allow navigation
  return true
})

export default router
