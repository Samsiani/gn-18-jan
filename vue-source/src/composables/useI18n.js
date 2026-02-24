import { computed } from 'vue'
import { useI18nStore } from '@/stores/i18n'

export function useI18n() {
  const store = useI18nStore()
  const t = (key, params) => store.t(key, params)

  // For data constants with label/labelKa (PAYMENT_METHODS, LIFECYCLE_LABELS, etc.)
  // If the object has an i18nKey, route through the i18n store so it appears in
  // Translation Manager and respects user overrides. Falls back to labelKa/label.
  function tLabel(obj) {
    if (!obj) return ''
    if (obj.i18nKey) return store.t(obj.i18nKey)
    if (store.locale === 'ka') return obj.labelKa || obj.label || ''
    return obj.labelEn || obj.label || ''
  }

  // WARRANTY_OPTIONS has inverted naming: label=Georgian, labelEn=English
  function tWarranty(opt) {
    if (!opt) return ''
    return store.locale === 'ka' ? (opt.label || '') : (opt.labelEn || opt.label || '')
  }

  const locale = computed(() => store.locale)
  return { t, tLabel, tWarranty, locale }
}
