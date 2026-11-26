<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'

const props = defineProps<{
  open: boolean
  title: string
  description?: string
  maxWidthClass?: string
}>()

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

const dialogEl = ref<HTMLDialogElement | null>(null)
const headingId = useId()
let pointerDownOutside = false

function isOutside(dialog: HTMLDialogElement, event: PointerEvent) {
  const r = dialog.getBoundingClientRect()
  return event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom
}

function sync(isOpen: boolean) {
  const el = dialogEl.value
  if (!el) return
  if (isOpen && !el.open) el.showModal()
  else if (!isOpen && el.open) {
    el.close()
    el.returnValue = ''
  }
}

watch(() => props.open, (v) => sync(v))

function onClose() {
  if (dialogEl.value) dialogEl.value.returnValue = ''
  if (props.open) emit('update:open', false)
}

function onPointerDown(e: PointerEvent) {
  if (dialogEl.value) pointerDownOutside = isOutside(dialogEl.value, e)
}

function onPointerUp(e: PointerEvent) {
  if (dialogEl.value && pointerDownOutside && isOutside(dialogEl.value, e)) {
    emit('update:open', false)
  }
  pointerDownOutside = false
}

onMounted(() => sync(props.open))
onUnmounted(() => {
  if (dialogEl.value?.open) dialogEl.value.close()
})
</script>

<template>
  <dialog
    ref="dialogEl"
    closedby="any"
    :aria-labelledby="headingId"
    :class="[
      'm-auto w-[calc(100%-2rem)] rounded-2xl border border-[#DCE4EE] bg-white p-0 text-[#0F172A] shadow-2xl',
      maxWidthClass || 'max-w-lg',
    ]"
    @close="onClose"
    @pointerdown="onPointerDown"
    @pointerup="onPointerUp"
  >
    <div class="p-6 space-y-4">
      <div class="flex items-start justify-between gap-4">
        <div class="space-y-1">
          <h1 :id="headingId" class="text-lg font-bold text-[#0F172A]">{{ title }}</h1>
          <p v-if="description" class="text-sm text-[#475569] leading-relaxed">{{ description }}</p>
        </div>
        <button
          type="button"
          aria-label="Tutup jendela"
          class="rounded-lg p-1.5 text-[#475569] hover:bg-[#F8FAFC] hover:text-[#0F172A] shrink-0 no-print"
          @click="emit('update:open', false)"
        >
          <X class="w-4 h-4" aria-hidden="true" />
        </button>
      </div>
      <slot />
    </div>
  </dialog>
</template>

