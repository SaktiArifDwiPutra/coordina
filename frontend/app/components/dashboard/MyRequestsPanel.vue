<script setup>
import { ref } from 'vue'
import { Card } from '~/components/ui/card'

defineProps({
  requests: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const expandedId = ref(null)

function toggleHistory(id) {
  expandedId.value = expandedId.value === id ? null : id
}

function statusBadge(status) {
  const map = {
    pending_mpk_screening: { label: 'Menunggu Screening MPK', cls: 'bg-zinc-100 text-zinc-700' },
    pending_owner: { label: 'Menunggu Pemilik Jadwal', cls: 'bg-blue-50 text-blue-700' },
    owner_approved: { label: 'Disetujui Pemilik (Menunggu Pencatatan MPK)', cls: 'bg-amber-50 text-amber-800' },
    owner_rejected: { label: 'Ditolak Pemilik (Menunggu Pencatatan MPK)', cls: 'bg-orange-50 text-orange-800' },
    approved: { label: 'Disetujui', cls: 'bg-green-50 text-green-700' },
    rejected: { label: 'Ditolak', cls: 'bg-red-50 text-red-700' },
  }
  return map[status] || { label: status, cls: 'bg-zinc-100 text-zinc-700' }
}

function workflowLabel(type) {
  if (type === 'mpk_borrow') return 'Peminjaman MPK'
  if (type === 'free_slot') return 'Slot Kosong'
  return 'Antar-Eskul'
}
</script>

<template>
  <div>
    <h2 class="text-xl font-bold text-zinc-900 mb-4">Status Pengajuan Saya</h2>
    <div v-if="loading" class="text-sm text-zinc-500">Memuat riwayat pengajuan...</div>
    <div v-else-if="requests.length === 0" class="text-sm text-zinc-500 bg-white border border-zinc-200 rounded-xl p-6">
      Belum ada pengajuan peminjaman yang dikirim.
    </div>
    <div v-else class="space-y-4">
      <Card v-for="req in requests" :key="req.id" class="border-zinc-200 shadow-sm p-5">
        <div class="flex flex-col md:flex-row justify-between gap-4">
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-bold text-base text-zinc-900">{{ req.facility?.name }}</h3>
              <span class="text-xs px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">
                {{ workflowLabel(req.workflow_type) }}
              </span>
            </div>
            <p class="text-sm text-zinc-600">
              📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0, 5) }} - {{ req.end_time.substring(0, 5) }}
              <span v-if="req.owner_organization"> · Pemilik: <strong>{{ req.owner_organization.name }}</strong></span>
            </p>
            <p class="text-sm text-zinc-700">📝 "{{ req.reason }}"</p>
            <p v-if="req.rejection_reason" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mt-2">
              Alasan Penolakan: {{ req.rejection_reason }}
            </p>
          </div>
          <div class="flex flex-col items-start md:items-end justify-between gap-2 shrink-0">
            <span :class="['px-3 py-1 text-xs font-semibold rounded-lg', statusBadge(req.status).cls]">
              {{ statusBadge(req.status).label }}
            </span>
            <button
              v-if="req.histories?.length"
              class="text-xs text-indigo-600 hover:underline"
              @click="toggleHistory(req.id)"
            >
              {{ expandedId === req.id ? 'Sembunyikan Riwayat' : 'Lihat Riwayat Proses' }}
            </button>
          </div>
        </div>

        <div v-if="expandedId === req.id && req.histories?.length" class="mt-4 pt-4 border-t border-zinc-100 space-y-2">
          <div v-for="h in req.histories" :key="h.id" class="text-xs text-zinc-600 flex justify-between gap-4">
            <span>
              <strong>{{ h.action }}</strong> ({{ h.actor_role }}) → <span class="font-medium">{{ h.to_status }}</span>
              <span v-if="h.note"> — "{{ h.note }}"</span>
            </span>
            <span class="text-zinc-400 shrink-0">{{ new Date(h.created_at).toLocaleString() }}</span>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>

