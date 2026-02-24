<template>
  <div v-if="grouped.length > 0" class="payment-cell" @click.stop="open = !open">
    <div class="payment-cell__methods">
      <span
        v-for="g in grouped"
        :key="g.method"
        :class="['payment-badge', `payment-badge--${g.method}`, { 'payment-badge--active': isMethodHighlighted(g.method) }]"
      >{{ g.label }}: {{ formatCurrency(g.amt) }}</span>
    </div>
    <span class="payment-expand-btn" :title="open ? 'Collapse' : 'Show breakdown'">
      <span class="payment-expand-icon" v-html="icon(open ? 'minus' : 'plus', 14)"></span>
    </span>

    <Transition :css="false" @enter="onEnter" @leave="onLeave">
      <div v-if="open" class="payment-breakdown">
        <div
          v-for="(p, i) in props.payments"
          :key="i"
          :class="['payment-breakdown__row', { 'payment-breakdown__row--match': isPaymentMatch(p) }]"
        >
          <span class="payment-breakdown__method"><span :class="['payment-badge', 'payment-badge--xs', `payment-badge--${p.method}`]">{{ PAYMENT_METHODS[p.method] ? tLabel(PAYMENT_METHODS[p.method]) : p.method }}</span></span>
          <span class="payment-breakdown__amount">{{ formatCurrency(p.amount) }}</span>
          <span class="payment-breakdown__date">{{ formatDate(p.date) }}</span>
        </div>
      </div>
    </Transition>
  </div>
  <span v-else style="color:var(--color-text-tertiary)">&mdash;</span>
</template>
<script setup>
import { ref, computed } from 'vue'
import { PAYMENT_METHODS } from '@/data'
import { formatCurrency, formatDate } from '@/composables/useFormatters'
import { icon } from '@/composables/useIcons'
import { useI18n } from '@/composables/useI18n'

const props = defineProps({
  payments:      { type: Array,  default: () => [] },
  totalAmount:   { type: Number, default: 0 },
  filterMethod:  { type: String, default: '' },
  filterDateFrom:{ type: String, default: '' },
  filterDateTo:  { type: String, default: '' }
})

const { tLabel } = useI18n()
const open = ref(false)

const grouped = computed(() => {
  const map = {}
  props.payments.forEach(p => { if (!map[p.method]) map[p.method] = 0; map[p.method] += p.amount })
  return Object.entries(map).map(([method, amt]) => ({
    method,
    amt,
    label: PAYMENT_METHODS[method] ? tLabel(PAYMENT_METHODS[method]) : method
  }))
})

function isMethodHighlighted(method) {
  if (props.filterMethod) return method === props.filterMethod
  return false
}

function isPaymentMatch(payment) {
  if (props.filterMethod) return payment.method === props.filterMethod
  return false
}

function onEnter(el, done) {
  el.style.overflow = 'hidden'
  el.style.height = '0'
  el.style.opacity = '0'
  el.offsetHeight // force reflow
  el.style.transition = 'height 0.22s ease, opacity 0.22s ease'
  el.style.height = el.scrollHeight + 'px'
  el.style.opacity = '1'
  el.addEventListener('transitionend', () => {
    el.style.height = ''
    el.style.overflow = ''
    el.style.transition = ''
    el.style.opacity = ''
    done()
  }, { once: true })
}

function onLeave(el, done) {
  el.style.overflow = 'hidden'
  el.style.height = el.scrollHeight + 'px'
  el.style.opacity = '1'
  el.offsetHeight // force reflow
  el.style.transition = 'height 0.18s ease, opacity 0.18s ease'
  el.style.height = '0'
  el.style.opacity = '0'
  el.addEventListener('transitionend', done, { once: true })
}
</script>
