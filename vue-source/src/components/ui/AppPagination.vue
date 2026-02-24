<template>
  <div class="table-footer">
    <span>{{ total }} {{ total !== 1 ? t('common.results') : t('common.result') }}</span>
    <div v-if="totalPages > 1" class="pagination">
      <button class="pagination__btn" :disabled="page === 1" @click="$emit('page-change', page - 1)" v-html="icon('chevron-left', 14)"></button>
      <template v-if="startPage > 1">
        <button class="pagination__btn" @click="$emit('page-change', 1)">1</button>
        <span v-if="startPage > 2" class="pagination__btn" style="cursor:default">...</span>
      </template>
      <button v-for="i in visiblePages" :key="i" :class="['pagination__btn', { active: i === page }]" @click="$emit('page-change', i)">{{ i }}</button>
      <template v-if="endPage < totalPages">
        <span v-if="endPage < totalPages - 1" class="pagination__btn" style="cursor:default">...</span>
        <button class="pagination__btn" @click="$emit('page-change', totalPages)">{{ totalPages }}</button>
      </template>
      <button class="pagination__btn" :disabled="page === totalPages" @click="$emit('page-change', page + 1)" v-html="icon('chevron-right', 14)"></button>
    </div>
  </div>
</template>
<script setup>
import { computed } from 'vue'
import { icon } from '@/composables/useIcons'
import { useI18n } from '@/composables/useI18n'
const props = defineProps({ currentPage: { type: Number, default: 1 }, totalPages: { type: Number, default: 1 }, total: { type: Number, default: 0 } })
const page = computed(() => props.currentPage)
defineEmits(['page-change'])
const { t } = useI18n()
const maxVisible = 7
const startPage = computed(() => {
  let s = Math.max(1, page.value - Math.floor(maxVisible / 2))
  const e = Math.min(props.totalPages, s + maxVisible - 1)
  if (e - s < maxVisible - 1) s = Math.max(1, e - maxVisible + 1)
  return s
})
const endPage = computed(() => Math.min(props.totalPages, startPage.value + maxVisible - 1))
const visiblePages = computed(() => {
  const pages = []
  for (let i = startPage.value; i <= endPage.value; i++) pages.push(i)
  return pages
})
</script>
