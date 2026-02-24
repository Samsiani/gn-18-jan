import { ref } from 'vue'

export function useSortable(defaultField = '', defaultDir = 'asc') {
  const sortField = ref(defaultField)
  const sortDir = ref(defaultDir)

  function toggleSort(field) {
    if (sortField.value === field) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortField.value = field
      sortDir.value = 'asc'
    }
  }

  function sortItems(items, fieldOverride) {
    const field = fieldOverride || sortField.value
    if (!field) return items
    return [...items].sort((a, b) => {
      let va = a[field]
      let vb = b[field]
      if (va == null && vb == null) return 0
      if (va == null) return sortDir.value === 'asc' ? 1 : -1
      if (vb == null) return sortDir.value === 'asc' ? -1 : 1
      if (typeof va === 'string') va = va.toLowerCase()
      if (typeof vb === 'string') vb = vb.toLowerCase()
      if (va < vb) return sortDir.value === 'asc' ? -1 : 1
      if (va > vb) return sortDir.value === 'asc' ? 1 : -1
      return 0
    })
  }

  return { sortField, sortDir, toggleSort, sortItems }
}
