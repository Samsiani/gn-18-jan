<template>
  <div class="autocomplete" ref="wrapperEl">
    <input
      ref="inputEl"
      :class="inputClass || 'form-input'"
      :value="localValue"
      :placeholder="placeholder"
      :disabled="disabled"
      @input="onInput($event.target.value)"
      @focus="onFocus"
      @keydown="onKeydown"
    >
    <Teleport to="body">
      <div
        v-if="showList && filtered.length"
        ref="listEl"
        class="autocomplete__list"
        :style="listStyle"
      >
        <div
          v-for="(item, idx) in filtered"
          :key="idx"
          :class="['autocomplete__item', { highlighted: highlightedIndex === idx }]"
          @click="select(item)"
          @mousemove="highlightedIndex = idx"
        >
          <slot name="item" :item="item">
            <span class="autocomplete__item-main">{{ displayFn(item) }}</span>
          </slot>
        </div>
      </div>
    </Teleport>
  </div>
</template>
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  items: { type: Array, default: () => [] },
  displayKey: { type: [String, Function], default: 'name' },
  searchFields: { type: Array, default: null },
  placeholder: { type: String, default: '' },
  inputClass: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  minDropdownWidth: { type: Number, default: 0 },
  minChars: { type: Number, default: 1 }
})
const emit = defineEmits(['update:modelValue', 'select'])

const wrapperEl = ref(null)
const inputEl = ref(null)
const listEl = ref(null)
const showList = ref(false)
const highlightedIndex = ref(-1)
const listStyle = ref({})

// Internal value so typing works even when parent uses :modelValue (not v-model)
const localValue = ref(props.modelValue)
watch(() => props.modelValue, (val) => { localValue.value = val })

const displayFn = (item) => {
  if (typeof props.displayKey === 'function') return props.displayKey(item)
  return item[props.displayKey] || ''
}

const filtered = computed(() => {
  const query = (localValue.value || '').toLowerCase().trim()
  if (query.length < props.minChars) return []
  return props.items.filter(item => {
    if (props.searchFields && props.searchFields.length > 0) {
      return props.searchFields.some(f => {
        const val = item[f]
        return val && String(val).toLowerCase().includes(query)
      })
    }
    const val = displayFn(item)
    return val && val.toLowerCase().includes(query)
  }).slice(0, 10)
})

function updatePosition() {
  if (!inputEl.value) return
  const rect = inputEl.value.getBoundingClientRect()
  const width = Math.max(rect.width, props.minDropdownWidth)
  const maxDropdownH = 280
  const spaceBelow = window.innerHeight - rect.bottom
  const showAbove = spaceBelow < maxDropdownH && rect.top > maxDropdownH

  if (showAbove) {
    // Anchor the list's BOTTOM edge 4px above the input top.
    // Using `bottom` (distance from viewport bottom) so the list hugs the input
    // regardless of actual rendered list height — no gap when list is short.
    listStyle.value = {
      position: 'fixed',
      top: 'auto',
      bottom: `${window.innerHeight - rect.top + 4}px`,
      left: `${rect.left}px`,
      width: `${width}px`,
      maxHeight: `${maxDropdownH}px`,
      zIndex: 9999
    }
  } else {
    listStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      bottom: 'auto',
      left: `${rect.left}px`,
      width: `${width}px`,
      maxHeight: `${maxDropdownH}px`,
      zIndex: 9999
    }
  }
}

// After the list mounts (v-if flip), nudge position in case DOM height changed
watch(showList, (val) => { if (val) nextTick(updatePosition) })

function onFocus() {
  if (!props.disabled && localValue.value && localValue.value.length >= props.minChars) {
    updatePosition()   // pre-calculate so first render has correct coords
    showList.value = true
    highlightedIndex.value = -1
  }
}

function onInput(val) {
  if (props.disabled) return
  localValue.value = val
  emit('update:modelValue', val)
  updatePosition()     // pre-calculate so first render has correct coords
  showList.value = true
  highlightedIndex.value = -1
}

function select(item) {
  emit('select', item)
  showList.value = false
}

function onClickOutside(e) {
  if (!wrapperEl.value?.contains(e.target) && !listEl.value?.contains(e.target)) {
    showList.value = false
  }
}

function onScroll(e) {
  if (!showList.value) return
  // Don't reposition when scrolling inside the dropdown itself
  if (listEl.value?.contains(e.target)) return
  updatePosition()
}

function onKeydown(e) {
  if (!showList.value || filtered.value.length === 0) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filtered.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
  } else if (e.key === 'Enter' && highlightedIndex.value >= 0) {
    e.preventDefault()
    select(filtered.value[highlightedIndex.value])
  } else if (e.key === 'Escape') {
    showList.value = false
  }
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  window.addEventListener('scroll', onScroll, true)
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  window.removeEventListener('scroll', onScroll, true)
})
</script>
