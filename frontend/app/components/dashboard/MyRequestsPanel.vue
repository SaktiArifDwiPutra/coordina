<script setup>
import { ref } from 'vue'
import { Card } from '~/components/ui/card'
import { Button } from '~/components/ui/button'

defineProps({
  requests: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  profileComplete: { type: Boolean, default: false },
  downloadingId: { type: Number, default: null },
  signingId: { type: Number, default: null },
})

const emit = defineEmits(['revise', 'sign', 'download-pdf', 'download-docx'])

const expandedId = ref(null)

function toggleHistory(id) {
  expandedId.value = expandedId.value === id ? null : id
}

function requiredSignatureCount(req) {
  if (req.workflow_type === 'mpk_borrow') return 2
  if (req.workflow_type === 'free_slot') {
    return req.organization?.is_mpk ? 1 : 2
  }
  return 3
}

function activeSignatures(req) {
  return req.document?.active_signatures ?? req.document?.signatures?.filter((s) => s.is_valid) ?? []
}

function canRequesterSignStageOne(req) {
  if (!req.document) return false
  const hasStage1 = activeSignatures(req).some((s) => s.stage_order === 1)
  if (hasStage1) return false

  if (
    ['inter_eskul', 'free_slot'].includes(req.workflow_type) &&
    req.status === 'pending_mpk_screening' &&
    req.document.document_status === 'waiting_mpk_screening'
  ) {
    return true
  }

  return req.workflow_type === 'mpk_borrow' && req.status === 'pending_owner'
}

function statusBadge(req) {
  const docStatus = req.document?.document_status
  if (docStatus === 'needs_correction') {
    return { label: 'Perlu Revisi (Ajukan Ulang)', cls: 'bg-amber-100 text-amber-800' }
  }
  if (docStatus === 'pending_pdf_generation') {
    return { label: 'Disetujui · Memproses PDF Resmi', cls: 'bg-sky-50 text-sky-700' }
  }
  if (docStatus === 'pdf_generation_failed') {
    return { label: 'Disetujui · PDF Gagal Dibuat', cls: 'bg-amber-100 text-amber-800' }
  }
  const map = {
    pending_mpk_screening: { label: 'Menunggu Screening MPK', cls: 'bg-zinc-100 text-zinc-700' },
    pending_owner: { label: 'Menunggu Pemilik Jadwal', cls: 'bg-blue-50 text-blue-700' },
    owner_approved: { label: 'Disetujui Pemilik (Menunggu Pencatatan MPK)', cls: 'bg-amber-50 text-amber-800' },
    owner_rejected: { label: 'Ditolak Pemilik (Menunggu Pencatatan MPK)', cls: 'bg-orange-50 text-orange-800' },
    approved: { label: 'Disetujui · PDF Resmi Siap', cls: 'bg-green-50 text-green-700' },
    rejected: { label: 'Ditolak', cls: 'bg-red-50 text-red-700' },
  }
  return map[req.status] || { label: req.status, cls: 'bg-zinc-100 text-zinc-700' }
}

function latestRevisionNote(req) {
  const revisions = req.document?.revisions || []
  return revisions.length > 0 ? revisions[revisions.length - 1].reason : null
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
              <span v-if="req.letter_number" class="text-xs px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">
                {{ req.letter_number }}
              </span>
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
            <div
              v-if="req.document?.document_status === 'needs_correction'"
              class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
            >
              <span>
                <strong>Catatan Revisi MPK:</strong> {{ latestRevisionNote(req) || 'Periksa kembali jadwal dan alasan peminjaman.' }}
              </span>
              <Button size="sm" class="bg-amber-600 text-white shrink-0" @click="emit('revise', req)">
                Ajukan Revisi
              </Button>
            </div>
            <p
              v-if="req.document?.document_status === 'pdf_generation_failed'"
              class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2"
            >
              Pembuatan PDF resmi tertunda ({{ req.document.pdf_generation_error || 'konverter belum siap' }}). Hubungi MPK untuk generate ulang PDF.
            </p>
          </div>
          <div class="flex flex-col items-start md:items-end justify-between gap-2 shrink-0">
            <span :class="['px-3 py-1 text-xs font-semibold rounded-lg', statusBadge(req).cls]">
              {{ statusBadge(req).label }}
            </span>
            <span v-if="req.document" class="text-[11px] text-zinc-500">
              TTD: {{ activeSignatures(req).length }}/{{ requiredSignatureCount(req) }}
              · {{ req.document.document_status }} (v{{ req.document.current_revision || 1 }})
            </span>
            <Button
              v-if="canRequesterSignStageOne(req)"
              size="sm"
              class="bg-indigo-600 text-white text-xs h-7 px-2.5"
              :disabled="!profileComplete || signingId === req.id"
              @click="emit('sign', req)"
            >
              {{ signingId === req.id ? 'Menyimpan...' : 'Bubuhkan TTD Tahap 1' }}
            </Button>
            <div
              v-if="req.status === 'approved' && req.document?.document_status === 'finalized' && req.document?.final_pdf_path"
              class="flex flex-wrap gap-1.5"
            >
              <Button
                size="sm"
                class="bg-indigo-600 text-white text-xs h-7 px-2.5"
                :disabled="downloadingId === req.id"
                @click="emit('download-pdf', req, true)"
              >
                Pratinjau / Cetak PDF
              </Button>
              <Button
                size="sm"
                variant="outline"
                class="text-xs h-7 px-2.5"
                :disabled="downloadingId === req.id"
                @click="emit('download-pdf', req, false)"
              >
                Unduh PDF
              </Button>
              <Button
                v-if="req.document.merged_docx_path"
                size="sm"
                variant="outline"
                class="text-xs h-7 px-2.5"
                :disabled="downloadingId === req.id"
                @click="emit('download-docx', req)"
              >
                Unduh DOCX
              </Button>
            </div>
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
