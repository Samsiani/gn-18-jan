<template>
  <Teleport to="body">
    <div v-if="visible" class="modal-backdrop" @click.self="close" @keydown.escape="close">
      <div :class="['modal', sizeClass]" :style="noHeader ? 'position:relative' : ''">
        <div v-if="!noHeader" class="modal__header">
          <h3 class="modal__title">{{ title }}</h3>
          <button class="modal__close" @click="close" v-html="icon('x', 18)"></button>
        </div>
        <button v-else class="modal__close" style="position:absolute;top:var(--space-3);right:var(--space-3)" @click="close" v-html="icon('x', 18)"></button>
        <div class="modal__body">
          <slot></slot>
        </div>
        <div v-if="$slots.footer" class="modal__footer">
          <slot name="footer"></slot>
        </div>
      </div>
    </div>
  </Teleport>
</template>
<script setup>
import { computed, watch } from 'vue'
import { icon } from '@/composables/useIcons'

const props = defineProps({
  visible: Boolean,
  title: String,
  size: { type: String, default: '' },
  noHeader: Boolean
})

const emit = defineEmits(['update:visible'])

const sizeClass = computed(() => props.size ? `modal--${props.size}` : '')

function close() {
  emit('update:visible', false)
}

watch(() => props.visible, (val) => {
  if (val) {
    const handler = (e) => {
      if (e.key === 'Escape') {
        close()
        document.removeEventListener('keydown', handler)
      }
    }
    document.addEventListener('keydown', handler)
  }
})
</script>
