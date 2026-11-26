<script setup>
import { ref } from 'vue'
import { Bell } from 'lucide-vue-next'
import { Button } from '~/components/ui/button'

defineProps({
  notifications: { type: Array, default: () => [] },
  unreadCount: { type: Number, default: 0 },
})

const emit = defineEmits(['mark-read'])
const isOpen = ref(false)
</script>

<template>
  <div class="relative">
    <Button variant="outline" size="sm" class="relative" @click="isOpen = !isOpen">
      <Bell class="w-4 h-4 mr-1.5" />
      Notifikasi
      <span
        v-if="unreadCount > 0"
        class="ml-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-600 rounded-full"
      >
        {{ unreadCount }}
      </span>
    </Button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-zinc-200 rounded-xl shadow-lg z-40 overflow-hidden"
    >
      <div class="px-4 py-3 border-b border-zinc-100 flex justify-between items-center">
        <span class="font-semibold text-sm text-zinc-900">Notifikasi Terbaru</span>
        <button class="text-xs text-zinc-500 hover:text-zinc-800" @click="isOpen = false">Tutup</button>
      </div>
      <div class="max-h-80 overflow-y-auto divide-y divide-zinc-100">
        <div
          v-for="item in notifications"
          :key="item.id"
          :class="['p-3.5 text-sm', item.read_at ? 'bg-white' : 'bg-indigo-50/50']"
        >
          <div class="flex justify-between items-start gap-2">
            <p class="font-semibold text-zinc-900">{{ item.data?.title || 'Pembaruan Permohonan' }}</p>
            <button
              v-if="!item.read_at"
              class="text-xs text-indigo-600 hover:underline shrink-0"
              @click="emit('mark-read', item.id)"
            >
              Tandai dibaca
            </button>
          </div>
          <p class="text-xs text-zinc-600 mt-1">{{ item.data?.message }}</p>
          <p v-if="item.data?.facility_name" class="text-xs text-zinc-500 mt-1">
            {{ item.data.facility_name }} · {{ item.data.date }}
          </p>
        </div>
        <div v-if="notifications.length === 0" class="p-6 text-center text-xs text-zinc-500">
          Belum ada notifikasi.
        </div>
      </div>
    </div>
  </div>
</template>

