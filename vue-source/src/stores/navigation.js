import { defineStore } from 'pinia'

export const useNavigationStore = defineStore('navigation', {
  state: () => ({
    navReturn: null,
    isBackNavigation: false,
    isForwardNavigation: false,
    pendingPageRestore: null,
    drilldownHighlight: null
  }),

  actions: {
    setDrilldownHighlight(columnKey) {
      this.drilldownHighlight = columnKey
    },

    consumeDrilldownHighlight() {
      const col = this.drilldownHighlight
      this.drilldownHighlight = null
      return col
    },

    setNavReturn(hash, pageState = {}) {
      this.navReturn = { hash, pageState }
      this.isForwardNavigation = true
    },

    getNavReturnHash() {
      return this.navReturn ? this.navReturn.hash : null
    },

    navigateBack(router, fallbackPath) {
      if (this.navReturn) {
        const { hash, pageState } = this.navReturn
        const isValidPath = hash && typeof hash === 'string' && hash.startsWith('/') && hash !== '/login'
        if (!isValidPath) {
          this.navReturn = null
          this.pendingPageRestore = null
          router.push(fallbackPath)
          return
        }
        this.pendingPageRestore = Object.keys(pageState).length > 0 ? pageState : null
        this.isBackNavigation = true
        this.navReturn = null
        router.push(hash)
      } else {
        router.push(fallbackPath)
      }
    },

    consumePageRestore() {
      const data = this.pendingPageRestore
      this.pendingPageRestore = null
      return data
    },

    clearNavReturnIfNotBack() {
      if (this.isBackNavigation) {
        this.isBackNavigation = false
        return
      }
      if (this.isForwardNavigation) {
        this.isForwardNavigation = false
        return
      }
      this.navReturn = null
      this.pendingPageRestore = null
    }
  }
})
