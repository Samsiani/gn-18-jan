import { ref } from 'vue'

export function usePagination(perPageDefault = 15) {
  const currentPage = ref(1)
  const perPage = ref(perPageDefault)
  const totalPages = ref(1)

  function paginate(items) {
    totalPages.value = Math.max(1, Math.ceil(items.length / perPage.value))
    if (currentPage.value > totalPages.value) currentPage.value = totalPages.value
    const start = (currentPage.value - 1) * perPage.value
    return items.slice(start, start + perPage.value)
  }

  function setPage(page) {
    currentPage.value = page
  }

  function resetPage() {
    currentPage.value = 1
  }

  return { currentPage, totalPages, perPage, paginate, setPage, resetPage }
}
