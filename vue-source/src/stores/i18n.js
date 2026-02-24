import { defineStore } from 'pinia'
import en from '@/locales/en.js'
import ka from '@/locales/ka.js'

export const useI18nStore = defineStore('i18n', {
  state: () => ({
    locale: localStorage.getItem('gn-locale') || 'en',
    customKa: JSON.parse(localStorage.getItem('gn-translations-ka') || '{}'),
  }),
  getters: {
    t: (state) => (key, params = {}) => {
      let str = state.locale === 'ka'
        ? (state.customKa[key] ?? ka[key] ?? en[key] ?? key)
        : (en[key] ?? key)
      return str.replace(/\{(\w+)\}/g, (_, k) => params[k] ?? `{${k}}`)
    },
    allTranslations: (state) =>
      Object.keys(en).map(key => ({
        key,
        en: en[key],
        ka: state.customKa[key] ?? ka[key],
        builtInKa: ka[key],
        isCustom: key in state.customKa,
      })),
  },
  actions: {
    setLocale(loc) {
      this.locale = loc
      localStorage.setItem('gn-locale', loc)
    },
    updateTranslation(key, value) {
      const trimmed = value.trim()
      if (!trimmed || trimmed === ka[key]) {
        delete this.customKa[key]
      } else {
        this.customKa[key] = trimmed
      }
      localStorage.setItem('gn-translations-ka', JSON.stringify(this.customKa))
    },
    resetTranslation(key) {
      delete this.customKa[key]
      localStorage.setItem('gn-translations-ka', JSON.stringify(this.customKa))
    },
    resetAll() {
      this.customKa = {}
      localStorage.removeItem('gn-translations-ka')
    },
  },
})
