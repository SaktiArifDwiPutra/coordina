<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps<{
  open: boolean
  title: string
  description?: string
  persistent?: boolean
  maxWidthClass?: string
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

const dialogEl = ref<HTMLDialogElement | null>(null)
const headingId = useId()
let pointerDownOutside = false

function isPointOutside(dialog: HTMLDialogElement, event: PointerEvent) {
  const rect = dialog.getBoundingClientRect()
  return (
    event.clientX < rect.left ||
    event.clientX > rect.right ||
    event.clientY < rect.top ||
    event.clientY > rect.bottom
  )
}

function syncDialog(isOpen: boolean) {
  const el = dialogEl.value
  if (!el) return
  if (isOpen && !el.open) {
    el.showModal()
  } else if (!isOpen && el.open) {
    el.close()
    el.returnValue = ''
  }
}

watch(
  () => props.open,
  (val) => {
    syncDialog(val)
  }
)

function handleNativeClose() {
  const el = dialogEl.value
  if (el) el.returnValue = ''
  if (props.open) {
    emit('update:open', false)
  }
}

function handleNativeCancel(event: Event) {
  if (props.persistent) {
    event.preventDefault()
  }
}

function handlePointerDown(event: PointerEvent) {
  const el = dialogEl.value
  if (!el || props.persistent) return
  pointerDownOutside = isPointOutside(el, event)
}

function handlePointerUp(event: PointerEvent) {
  const el = dialogEl.value
  if (!el || props.persistent) return
  if (pointerDownOutside && isPointOutside(el, event)) {
    emit('update:open', false)
  }
  pointerDownOutside = false
}

onMounted(() => {
  syncDialog(props.open)
})

onUnmounted(() => {
  if (dialogEl.value?.open) {
    dialogEl.value.close()
  }
})
</script>

<template>
  <dialog
    ref="dialogEl"
    :closedby="persistent ? 'none' : 'any'"
    :aria-labelledby="headingId"
    :class="[
      'm-auto w-[calc(100%-2rem)] rounded-2xl border border-border bg-card p-0 text-card-foreground shadow-2xl',
      maxWidthClass || 'max-w-md',
    ]"
    @close="handleNativeClose"
    @cancel="handleNativeCancel"
    @pointerdown="handlePointerDown"
    @pointerup="handlePointerUp"
  >
    <div class="p-6 space-y-4">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <h1 :id="headingId" class="text-lg font-bold text-slate-900">
            {{ title }}
          </h1>
          <p v-if="description" class="text-sm text-slate-600 leading-relaxed">
            {{ description }}
          </p>
        </div>
        <button
          v-if="!persistent"
          type="button"
          aria-label="Tutup dialog"
          class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900 shrink-0"
          @click="emit('update:open', false)"
        >
          <X class="w-4 h-4" aria-hidden="true" />
        </button>
      </div>

      <slot />
    </div>
  </dialog>
</template>

