<script setup>
import { computed } from 'vue'
import { Card } from '~/components/ui/card'
import { Button } from '~/components/ui/button'

const props = defineProps({
  requests: { type: Array, default: () => [] },
})

const emit = defineEmits(['screen-accept', 'screen-reject', 'finalize'])

const screeningQueue = computed(() =>
  props.requests.filter((r) => r.status === 'pending_mpk_screening')
)
const readyToFinalize = computed(() =>
  props.requests.filter((r) => ['owner_approved', 'owner_rejected'].includes(r.status))
)
const awaitingOwner = computed(() =>
  props.requests.filter((r) => r.status === 'pending_owner')
)
const completed = computed(() =>
  props.requests.filter((r) => ['approved', 'rejected'].includes(r.status))
)
</script>

<template>
  <div class="space-y-8">
    <div>
      <h2 class="text-xl font-bold text-zinc-900 mb-4">Meja Kerja MPK — Antrean Screening</h2>
      <div v-if="screeningQueue.length === 0" class="text-sm text-zinc-500 bg-white border border-zinc-200 rounded-xl p-5">
        Tidak ada pengajuan yang menunggu screening MPK.
      </div>
      <div v-else class="space-y-4">
        <Card v-for="req in screeningQueue" :key="req.id" class="border-zinc-200 shadow-sm p-5">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
              <h3 class="font-bold text-lg text-zinc-900">
                {{ req.organization?.name }}
                <span class="text-zinc-500 font-normal text-sm">ingin meminjam</span>
                {{ req.facility?.name }}
              </h3>
              <p class="text-sm text-zinc-600 mt-1">
                📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0, 5) }} - {{ req.end_time.substring(0, 5) }}
              </p>
              <p class="text-sm text-zinc-600 mt-1">📝 Alasan: "{{ req.reason }}"</p>
              <div
                v-if="req.owner_organization_id"
                class="mt-2 text-xs font-semibold px-2 py-1 bg-amber-100 text-amber-800 rounded-md inline-block"
              >
                ⚠️ Bertabrakan dengan jadwal tetap: {{ req.owner_organization?.name }}
              </div>
              <div
                v-else
                class="mt-2 text-xs font-semibold px-2 py-1 bg-green-100 text-green-800 rounded-md inline-block"
              >
                ✅ Slot Kosong (Tidak ada pemilik jadwal tetap)
              </div>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
              <Button @click="emit('screen-accept', req)" :class="req.owner_organization_id ? 'bg-blue-600 text-white' : 'bg-green-600 text-white'">
                {{ req.owner_organization_id ? 'Lolos Screening & Teruskan ke Pemilik' : 'Setujui Slot Kosong' }}
              </Button>
              <Button @click="emit('screen-reject', req)" variant="outline" class="text-red-600 border-red-200">
                Tolak Screening
              </Button>
            </div>
          </div>
        </Card>
      </div>
    </div>

    <div>
      <h2 class="text-xl font-bold text-zinc-900 mb-4">Keputusan Pemilik — Siap Dicatat MPK</h2>
      <div v-if="readyToFinalize.length === 0" class="text-sm text-zinc-500 bg-white border border-zinc-200 rounded-xl p-5">
        Belum ada keputusan pemilik jadwal yang menunggu pencatatan MPK.
      </div>
      <div v-else class="space-y-4">
        <Card v-for="req in readyToFinalize" :key="req.id" class="border-indigo-200 bg-indigo-50/40 shadow-sm p-5">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
              <h3 class="font-bold text-lg text-zinc-900">
                {{ req.organization?.name }} → {{ req.facility?.name }}
              </h3>
              <p class="text-sm text-zinc-600 mt-1">
                📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0, 5) }} - {{ req.end_time.substring(0, 5) }} · Pemilik:
                <strong>{{ req.owner_organization?.name }}</strong>
              </p>
              <p v-if="req.status === 'owner_approved'" class="text-xs font-semibold text-green-700 mt-2">
                ✅ Pemilik jadwal menyetujui peminjaman ini.
              </p>
              <p v-else class="text-xs font-semibold text-red-700 mt-2">
                ❌ Pemilik jadwal menolak: "{{ req.rejection_reason }}"
              </p>
            </div>
            <Button @click="emit('finalize', req)" class="bg-zinc-900 text-white shrink-0">
              {{ req.status === 'owner_approved' ? 'Catat Persetujuan & Perbarui Jadwal' : 'Catat & Sampaikan Penolakan' }}
            </Button>
          </div>
        </Card>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <div>
        <h3 class="text-lg font-bold text-zinc-900 mb-3">Menunggu Keputusan Pemilik ({{ awaitingOwner.length }})</h3>
        <div class="space-y-3">
          <Card v-for="req in awaitingOwner" :key="req.id" class="border-zinc-200 p-4">
            <div class="flex justify-between items-start gap-2">
              <div>
                <p class="font-semibold text-sm text-zinc-900">{{ req.organization?.name }} · {{ req.facility?.name }}</p>
                <p class="text-xs text-zinc-600">{{ req.date }} ({{ req.start_time.substring(0, 5) }}-{{ req.end_time.substring(0, 5) }})</p>
                <p class="text-xs text-zinc-500 mt-1">Menunggu: {{ req.owner_organization?.name }}</p>
              </div>
              <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-md">Menunggu Pemilik</span>
            </div>
          </Card>
          <p v-if="awaitingOwner.length === 0" class="text-xs text-zinc-500">Tidak ada.</p>
        </div>
      </div>

      <div>
        <h3 class="text-lg font-bold text-zinc-900 mb-3">Riwayat Selesai ({{ completed.length }})</h3>
        <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
          <Card v-for="req in completed" :key="req.id" class="border-zinc-200 p-4">
            <div class="flex justify-between items-start gap-2">
              <div>
                <p class="font-semibold text-sm text-zinc-900">{{ req.organization?.name }} · {{ req.facility?.name }}</p>
                <p class="text-xs text-zinc-600">{{ req.date }} ({{ req.start_time.substring(0, 5) }}-{{ req.end_time.substring(0, 5) }})</p>
                <p v-if="req.rejection_reason" class="text-xs text-red-600 mt-1">Alasan: "{{ req.rejection_reason }}"</p>
              </div>
              <span
                :class="[
                  'px-2.5 py-1 text-xs font-medium rounded-md',
                  req.status === 'approved' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700',
                ]"
              >
                {{ req.status === 'approved' ? 'Disetujui' : 'Ditolak' }}
              </span>
            </div>
          </Card>
          <p v-if="completed.length === 0" class="text-xs text-zinc-500">Belum ada riwayat.</p>
        </div>
      </div>
    </div>
  </div>
</template>

