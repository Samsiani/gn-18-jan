<template>
  <div class="filter-dates">
    <div class="quick-dates">
      <button v-for="btn in quickDates" :key="btn.period"
        :class="['btn', 'btn--ghost', 'btn--sm', 'quick-date-btn', { active: activePeriod === btn.period }]"
        @click="setQuickDate(btn.period)">{{ btn.label }}</button>
    </div>
    <div class="date-range-inputs" style="display:flex; align-items:center; gap:var(--space-2)">
      <input type="date" class="form-input form-input--sm" :value="dateFrom" @change="onFromChange($event.target.value)" style="width:140px">
      <span style="color:var(--color-text-tertiary)">&mdash;</span>
      <input type="date" class="form-input form-input--sm" :value="dateTo" @change="onToChange($event.target.value)" style="width:140px">
    </div>
  </div>
</template>
<script setup>
import { ref, computed } from 'vue'
import { useI18n } from '@/composables/useI18n'

const props = defineProps({
  dateFrom: { type: String, default: '' },
  dateTo: { type: String, default: '' },
  prefix: { type: String, default: '' }
})

defineOptions({ inheritAttrs: false })

const emit = defineEmits(['update:dateFrom', 'update:dateTo', 'change'])

const { t } = useI18n()
const activePeriod = ref('all')

const quickDates = computed(() => [
  { period: 'today',     label: t('filter.today') },
  { period: 'yesterday', label: t('filter.yesterday') },
  { period: 'week',      label: t('filter.week') },
  { period: 'month',     label: t('filter.month') },
  { period: 'all',       label: t('filter.all') },
])

function todayISO() {
  return new Date().toISOString().split('T')[0]
}

function setQuickDate(period) {
  activePeriod.value = period
  const now = new Date()
  let from = '', to = ''
  switch (period) {
    case 'today':
      from = to = todayISO()
      break
    case 'yesterday': {
      const d = new Date(now)
      d.setDate(d.getDate() - 1)
      from = to = d.toISOString().split('T')[0]
      break
    }
    case 'week': {
      const d = new Date(now)
      d.setDate(d.getDate() - 7)
      from = d.toISOString().split('T')[0]
      to = todayISO()
      break
    }
    case 'month': {
      from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0]
      to = todayISO()
      break
    }
    case 'all':
      from = ''
      to = ''
      break
  }
  emit('update:dateFrom', from)
  emit('update:dateTo', to)
  emit('change', { dateFrom: from, dateTo: to })
}

function onFromChange(val) {
  activePeriod.value = ''
  emit('update:dateFrom', val)
  emit('change', { dateFrom: val, dateTo: props.dateTo })
}

function onToChange(val) {
  activePeriod.value = ''
  emit('update:dateTo', val)
  emit('change', { dateFrom: props.dateFrom, dateTo: val })
}
</script>
