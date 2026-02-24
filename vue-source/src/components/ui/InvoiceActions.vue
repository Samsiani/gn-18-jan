<template>
  <div class="invoice-actions" @click.stop>
    <button class="btn btn--ghost btn--icon btn--sm" @click="$emit('view', invoice)" data-tooltip="View details">
      <span v-html="icon('eye', 14)"></span>
    </button>
    <div v-if="!compact && hasDropdownActions" class="quick-action">
      <button ref="triggerRef" class="quick-action__trigger" @click.stop="toggleMenu">
        <span v-html="icon('more-vertical', 16)"></span>
      </button>
      <Teleport to="body">
        <div v-if="menuOpen" class="quick-action__menu quick-action__menu--fixed" :style="menuStyle" @click.stop>
          <button v-if="canEdit" class="quick-action__item" @click="onEdit"><span v-html="icon('edit', 14)"></span> {{ t('btn.edit') }}</button>
          <button v-if="canMarkSold" class="quick-action__item" @click="onMarkSold" style="color:var(--color-success)"><span v-html="icon('check-circle', 14)"></span> {{ t('msg.titleMarkSold') }}</button>
          <template v-if="canDelete">
            <div class="quick-action__divider"></div>
            <button class="quick-action__item quick-action__item--danger" @click="onDelete"><span v-html="icon('trash-2', 14)"></span> {{ t('btn.delete') }}</button>
          </template>
        </div>
      </Teleport>
    </div>
  </div>
</template>

<script>
import { ref } from 'vue'
// True module-level — one shared ref across ALL instances
const activeMenuId = ref(null)
let menuIdCounter = 0
</script>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from '@/composables/useI18n'
import { icon } from '@/composables/useIcons'

const props = defineProps({
  invoice: { type: Object, required: true },
  compact: { type: Boolean, default: false }
})

const emit = defineEmits(['view', 'edit', 'mark-sold', 'delete'])

const authStore = useAuthStore()
const { t } = useI18n()
const myId = ++menuIdCounter
const menuOpen = computed(() => activeMenuId.value === myId)
const triggerRef = ref(null)
const menuStyle = ref({})

const canEdit = computed(() => {
  if (authStore.isAccountantRole) return false
  if (props.invoice.lifecycleStatus === 'completed' || props.invoice.lifecycleStatus === 'sold') return authStore.isAdmin
  return authStore.isAdmin || props.invoice.authorId === authStore.currentUser?.id
})

const canMarkSold = computed(() => {
  if (authStore.isAccountantRole) return false
  if (props.invoice.lifecycleStatus !== 'active') return false
  if (!props.invoice.items || !props.invoice.items.some(it => it.itemStatus === 'reserved')) return false
  return authStore.isAdmin || props.invoice.authorId === authStore.currentUser?.id
})

const canDelete = computed(() => authStore.isAdmin)
const hasDropdownActions = computed(() => canEdit.value || canMarkSold.value || canDelete.value)

function updatePosition() {
  if (!triggerRef.value) return
  const rect = triggerRef.value.getBoundingClientRect()
  const menuH = 140
  const spaceBelow = window.innerHeight - rect.bottom
  const openAbove = spaceBelow < menuH && rect.top > menuH

  if (openAbove) {
    menuStyle.value = {
      position: 'fixed',
      bottom: `${window.innerHeight - rect.top + 4}px`,
      top: 'auto',
      right: `${window.innerWidth - rect.right}px`
    }
  } else {
    menuStyle.value = {
      position: 'fixed',
      top: `${rect.bottom + 4}px`,
      bottom: 'auto',
      right: `${window.innerWidth - rect.right}px`
    }
  }
}

function toggleMenu() {
  if (activeMenuId.value === myId) {
    activeMenuId.value = null
  } else {
    updatePosition()
    activeMenuId.value = myId
  }
}

function closeMenu(e) {
  if (activeMenuId.value === myId && !triggerRef.value?.contains(e.target)) {
    activeMenuId.value = null
  }
}

function onScroll() {
  activeMenuId.value = null
}

function onEdit()     { activeMenuId.value = null; emit('edit', props.invoice) }
function onMarkSold() { activeMenuId.value = null; emit('mark-sold', props.invoice) }
function onDelete()   { activeMenuId.value = null; emit('delete', props.invoice) }

onMounted(() => {
  document.addEventListener('click', closeMenu)
  window.addEventListener('scroll', onScroll, true)
})
onUnmounted(() => {
  if (activeMenuId.value === myId) activeMenuId.value = null
  document.removeEventListener('click', closeMenu)
  window.removeEventListener('scroll', onScroll, true)
})
</script>

<style scoped>
.invoice-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
}
</style>
