<script setup>
import { Card } from '~/components/ui/card'
import { Button } from '~/components/ui/button'

defineProps({
  requests: { type: Array, default: () => [] },
})

const emit = defineEmits(['approve', 'reject'])
</script>

<template>
  <div v-if="requests.length > 0">
    <h2 class="text-xl font-bold text-zinc-900 mb-4 flex items-center gap-2">
      <span>🔔</span> Permohonan Izin Masuk
    </h2>
    <div class="space-y-4">
      <Card v-for="req in requests" :key="'inbox-' + req.id" class="border-amber-200 bg-amber-50 shadow-sm p-5">
        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-bold text-lg text-zinc-900">
                {{ req.organization?.name }}
                <span class="text-zinc-600 font-normal text-sm">meminta izin memakai jadwal Anda di</span>
                {{ req.facility?.name }}
              </h3>
              <span
                v-if="req.workflow_type === 'mpk_borrow'"
                class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold"
              >
                Permintaan MPK
              </span>
            </div>
            <p class="text-sm text-zinc-700 mt-1">
              📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0, 5) }} - {{ req.end_time.substring(0, 5) }}
            </p>
            <p class="text-sm text-zinc-700 mt-1">📝 Alasan: "{{ req.reason }}"</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <Button @click="emit('approve', req)" class="bg-green-600 hover:bg-green-700 text-white">Izinkan</Button>
            <Button @click="emit('reject', req)" variant="outline" class="text-red-600 border-red-200 hover:bg-red-50">Tolak</Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>

