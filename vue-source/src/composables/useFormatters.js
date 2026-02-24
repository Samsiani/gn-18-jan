/* ============================================
   GN Invoice SaaS - Formatting Utilities
   Vue 3 Composable + Standalone Exports
   ============================================ */

export function formatCurrency(amount, symbol = '₾') {
  if (amount == null || isNaN(amount)) return `0.00 ${symbol}`
  return `${Number(amount).toLocaleString('ka-GE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${symbol}`
}

export function formatNumber(num) {
  if (num == null || isNaN(num)) return '0'
  return Number(num).toLocaleString('ka-GE')
}

export function formatDate(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('ka-GE', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

export function formatDateShort(dateStr) {
  if (!dateStr) return '—'
  const d = new Date(dateStr)
  return d.toLocaleDateString('ka-GE', { month: 'short', day: 'numeric' })
}

export function formatDateISO(date) {
  if (!date) return ''
  const d = date instanceof Date ? date : new Date(date)
  return d.toISOString().split('T')[0]
}

export function daysAgo(dateStr) {
  if (!dateStr) return null
  const d = new Date(dateStr)
  const now = new Date()
  return Math.floor((now - d) / (1000 * 60 * 60 * 24))
}

export function daysRemaining(dateStr, totalDays) {
  if (!dateStr || !totalDays) return 0
  const created = new Date(dateStr)
  const now = new Date()
  const elapsed = Math.floor((now - created) / (1000 * 60 * 60 * 24))
  return Math.max(0, totalDays - elapsed)
}

export function todayISO() {
  return new Date().toISOString().split('T')[0]
}

export function generateId(items) {
  if (!items || items.length === 0) return 1
  return Math.max(...items.map(i => i.id)) + 1
}

export function debounce(fn, delay = 300) {
  let timer
  return function (...args) {
    clearTimeout(timer)
    timer = setTimeout(() => fn.apply(this, args), delay)
  }
}

export function escapeHtml(str) {
  if (!str) return ''
  const div = document.createElement('div')
  div.textContent = str
  return div.innerHTML
}

export function getTimeAgo(isoString, t) {
  const now = new Date()
  const time = new Date(isoString)
  const diff = Math.floor((now - time) / 1000)
  if (!t) {
    if (diff < 60) return 'just now'
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    return `${Math.floor(diff / 86400)}d ago`
  }
  if (diff < 60) return t('time.justNow')
  if (diff < 3600) return t('time.mAgo', { n: Math.floor(diff / 60) })
  if (diff < 86400) return t('time.hAgo', { n: Math.floor(diff / 3600) })
  return t('time.dAgo', { n: Math.floor(diff / 86400) })
}

/**
 * Generate organic sparkline data points based on a percentage change value.
 * Uses a seeded pseudo-random generator so the same percentage always
 * produces the same sparkline shape (no flickering on re-renders).
 *
 * Rules:
 *  pct > 100  → sharply rising exponential curve
 *  0 < pct ≤ 100 → steady moderate rise scaled to magnitude
 *  pct < 0    → declining line scaled to magnitude
 *  pct === 0 or NaN/null → flat line with minimal wobble
 *
 * @param {string|number|null} percentageInput — e.g. "+12.5", "-8.3", null
 * @param {number} points — number of data points (default 6)
 * @returns {number[]}
 */
export function generateTrendlineData(percentageInput, points = 6) {
  const pct = parseFloat(percentageInput)
  const isFlat = isNaN(pct) || pct === 0

  // Park-Miller LCG — deterministic noise keyed to percentage value
  const seedVal = isFlat ? 42 : Math.round(pct * 17 + 137)
  let s = ((seedVal % 2147483647) + 2147483647) % 2147483647 || 1
  const rand = () => { s = (s * 16807) % 2147483647; return s / 2147483647 }

  if (isFlat) {
    return Array.from({ length: points }, () => 50 + (rand() * 4 - 2))
  }

  if (pct > 100) {
    // Exponential rise — starts low, ends high with a steep curve
    return Array.from({ length: points }, (_, i) => {
      const t = i / (points - 1)
      const curve = 10 + 80 * Math.pow(t, 2.2)
      const noise = (rand() * 8 - 4) * (1 - t * 0.4)
      return Math.max(5, curve + noise)
    })
  }

  if (pct > 0) {
    // Moderate steady rise — gain up to 40 points over 100%
    const gain = (pct / 100) * 40
    return Array.from({ length: points }, (_, i) => {
      const t = i / (points - 1)
      const value = 30 + gain * t
      const noise = rand() * 6 - 3
      return Math.max(5, value + noise)
    })
  }

  // pct < 0: declining — drop up to 40 points over -100%
  const drop = (Math.min(Math.abs(pct), 100) / 100) * 40
  return Array.from({ length: points }, (_, i) => {
    const t = i / (points - 1)
    const value = 70 - drop * t
    const noise = rand() * 6 - 3
    return Math.max(5, value + noise)
  })
}
