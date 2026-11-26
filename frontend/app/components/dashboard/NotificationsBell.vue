<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Bell, Check, X } from 'lucide-vue-next'

defineProps({
  notifications: { type: Array, default: () => [] },
  unreadCount: { type: Number, default: 0 },
})

const emit = defineEmits(['mark-read'])
const isOpen = ref(false)
const rootRef = ref(null)

function onDocumentClick(event) {
  if (!isOpen.value || !rootRef.value) return
  if (!rootRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

function onDocumentKeyDown(event) {
  if (isOpen.value && event.key === 'Escape') {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onDocumentKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onDocumentKeyDown)
})
</script>

<template>
  <div ref="rootRef" class="relative shrink-0">
    <button
      type="button"
      :aria-expanded="isOpen"
      aria-haspopup="true"
      :aria-label="unreadCount > 0 ? `Notifikasi, ${unreadCount} belum dibaca` : 'Buka notifikasi'"
      class="relative inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white transition-colors"
      @click="isOpen = !isOpen"
    >
      <Bell class="w-5 h-5" aria-hidden="true" />
      <span
        v-if="unreadCount > 0"
        class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 text-[11px] font-bold leading-none text-slate-950 bg-amber-400 rounded-full ring-2 ring-[#1E3A5F] tabular-nums"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-80 sm:w-96 max-w-[calc(100vw-2rem)] bg-white border border-border rounded-2xl shadow-xl z-40 overflow-hidden text-slate-900"
    >
      <div class="px-4 py-3 border-b border-border flex justify-between items-center bg-slate-50">
        <span class="font-bold text-sm text-slate-900">Pemberitahuan Terbaru</span>
        <button
          type="button"
          aria-label="Tutup daftar notifikasi"
          class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
          @click="isOpen = false"
        >
          <X class="w-3.5 h-3.5" aria-hidden="true" />
          <span>Tutup</span>
        </button>
      </div>
      <div class="max-h-80 overflow-y-auto divide-y divide-border">
        <div
          v-for="item in notifications"
          :key="item.id"
          :class="['p-4 text-sm', item.read_at ? 'bg-white' : 'bg-blue-50/50']"
        >
          <div class="flex justify-between items-start gap-2">
            <p class="font-bold text-slate-900 text-sm">{{ item.data?.title || 'Pembaruan Permohonan' }}</p>
            <button
              v-if="!item.read_at"
              type="button"
              class="inline-flex items-center gap-1 text-xs font-semibold text-[#1E3A5F] hover:underline shrink-0"
              @click="emit('mark-read', item.id)"
            >
              <Check class="w-3.5 h-3.5" aria-hidden="true" />
              <span>Dibaca</span>
            </button>
          </div>
          <p class="text-xs text-slate-600 mt-1 leading-relaxed">{{ item.data?.message }}</p>
          <p v-if="item.data?.facility_name" class="text-xs text-slate-500 mt-1 tabular-nums">
            {{ item.data.facility_name }} · {{ item.data.date }}
          </p>
        </div>
        <div v-if="notifications.length === 0" class="p-6 text-center text-xs text-slate-500">
          Belum ada pemberitahuan terbaru.
        </div>
      </div>
    </div>
  </div>
</template>
