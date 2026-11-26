<script setup>
import { computed } from 'vue'
import { Card } from '~/components/ui/card'
import { Button } from '~/components/ui/button'

const props = defineProps({
  requests: { type: Array, default: () => [] },
  profileComplete: { type: Boolean, default: false },
  screeningId: { type: Number, default: null },
  finalizingId: { type: Number, default: null },
  signingId: { type: Number, default: null },
  regeneratingId: { type: Number, default: null },
})

const emit = defineEmits(['screen-accept', 'screen-reject', 'finalize', 'request-correction', 'sign', 'regenerate-pdf'])

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

function requiredSignaturesCount(req) {
  if (req.workflow_type === 'mpk_borrow') return 2
  if (req.workflow_type === 'free_slot') {
    return req.organization?.is_mpk ? 1 : 2
  }
  return 3
}

function activeSignatureCount(req) {
  return req.document?.active_signatures?.length ?? req.document?.signatures?.filter((s) => s.is_valid).length ?? 0
}

function canFinalize(req) {
  if (req.document?.document_status === 'needs_correction') return false
  if (req.status === 'owner_rejected') return true
  if (req.document?.document_status !== 'pending_mpk_verification') return false
  return activeSignatureCount(req) >= requiredSignaturesCount(req)
}

function completedPdfStatusBadge(req) {
  if (req.status === 'rejected') {
    return { label: 'Ditolak', cls: 'bg-red-50 text-red-700' }
  }
  const docStatus = req.document?.document_status
  if (docStatus === 'finalized' && req.document?.final_pdf_path) {
    return { label: 'Disetujui · PDF Resmi Siap', cls: 'bg-green-50 text-green-700' }
  }
  if (docStatus === 'pdf_generation_failed') {
    return { label: 'Disetujui · PDF Gagal', cls: 'bg-amber-100 text-amber-800' }
  }
  return { label: 'Disetujui · Memproses PDF', cls: 'bg-sky-50 text-sky-700' }
}

function latestRevisionReason(req) {
  const revisions = req.document?.revisions || []
  return revisions.length > 0 ? revisions[revisions.length - 1].reason : null
}

function scrollToProfile() {
  document.getElementById('organization-profile')?.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
  <div class="space-y-8">
    <div
      v-if="!profileComplete"
      class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
    >
      <span>
        <strong>Profil tanda tangan MPK belum lengkap.</strong> Lengkapi identitas Ketua Komisi 2 dan gambar tanda tangan sebelum menerima & menandatangani screening.
      </span>
      <button type="button" class="font-semibold underline shrink-0 text-left" @click="scrollToProfile">
        Lengkapi Profil MPK →
      </button>
    </div>

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
              <Button
                :disabled="!profileComplete || screeningId === req.id"
                @click="emit('screen-accept', req)"
                :class="req.owner_organization_id ? 'bg-blue-600 text-white' : 'bg-green-600 text-white'"
              >
                {{
                  screeningId === req.id
                    ? 'Memproses...'
                    : req.owner_organization_id
                      ? 'Lolos Screening, TTD & Teruskan'
                      : 'Setujui & TTD Slot Kosong'
                }}
              </Button>
              <Button
                :disabled="screeningId === req.id"
                @click="emit('screen-reject', req)"
                variant="outline"
                class="text-red-600 border-red-200"
              >
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
              <p v-if="req.document?.document_status === 'needs_correction'" class="text-xs font-semibold text-amber-800 bg-amber-100 border border-amber-200 rounded-md px-2.5 py-1.5 mt-2">
                Menunggu Revisi Pemohon: "{{ latestRevisionReason(req) || 'Perlu perbaikan sesuai catatan MPK.' }}"
              </p>
              <p v-else-if="req.status === 'owner_approved'" class="text-xs font-semibold text-green-700 mt-2">
                ✅ Pemilik jadwal menyetujui peminjaman ini. (TTD: {{ activeSignatureCount(req) }}/{{ requiredSignaturesCount(req) }})
              </p>
              <p v-else class="text-xs font-semibold text-red-700 mt-2">
                ❌ Pemilik jadwal menolak: "{{ req.rejection_reason }}"
              </p>
              <p v-if="req.status === 'owner_approved' && req.document?.document_status !== 'needs_correction' && !canFinalize(req)" class="text-xs text-red-600 mt-1">
                Tanda tangan wajib belum lengkap — dokumen tidak dapat difinalisasi.
              </p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
              <Button
                v-if="req.status === 'owner_approved' && req.document?.document_status === 'pending_mpk_verification'"
                variant="outline"
                class="border-amber-300 text-amber-800 hover:bg-amber-50"
                :disabled="finalizingId === req.id"
                @click="emit('request-correction', req)"
              >
                Minta Revisi
              </Button>
              <Button
                :disabled="!canFinalize(req) || finalizingId === req.id"
                @click="emit('finalize', req)"
                class="bg-zinc-900 text-white shrink-0"
              >
                {{
                  finalizingId === req.id
                    ? 'Memproses...'
                    : req.status === 'owner_approved'
                      ? 'Catat Persetujuan & Terbitkan PDF'
                      : 'Catat & Sampaikan Penolakan'
                }}
              </Button>
            </div>
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
              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-md">Menunggu Pemilik</span>
                <Button
                  v-if="req.workflow_type === 'inter_eskul' && req.document?.document_status === 'waiting_mpk_signature'"
                  size="sm"
                  class="bg-indigo-600 text-white text-xs h-7 px-2.5"
                  :disabled="!profileComplete || signingId === req.id"
                  @click="emit('sign', req)"
                >
                  {{ signingId === req.id ? 'Menyimpan...' : 'Bubuhkan TTD MPK' }}
                </Button>
              </div>
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
                <div class="flex flex-wrap items-center gap-1.5">
                  <p class="font-semibold text-sm text-zinc-900">{{ req.organization?.name }} · {{ req.facility?.name }}</p>
                  <span v-if="req.letter_number" class="text-[11px] font-semibold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700">
                    {{ req.letter_number }}
                  </span>
                </div>
                <p class="text-xs text-zinc-600">{{ req.date }} ({{ req.start_time.substring(0, 5) }}-{{ req.end_time.substring(0, 5) }})</p>
                <p v-if="req.rejection_reason" class="text-xs text-red-600 mt-1">Alasan: "{{ req.rejection_reason }}"</p>
                <p v-if="req.document?.document_status === 'pdf_generation_failed' && req.document?.pdf_generation_error" class="text-xs text-amber-800 mt-1">
                  Error PDF: {{ req.document.pdf_generation_error }}
                </p>
              </div>
              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span :class="['px-2.5 py-1 text-xs font-medium rounded-md', completedPdfStatusBadge(req).cls]">
                  {{ completedPdfStatusBadge(req).label }}
                </span>
                <Button
                  v-if="req.status === 'approved' && ['pending_pdf_generation', 'pdf_generation_failed'].includes(req.document?.document_status)"
                  size="sm"
                  class="bg-amber-600 text-white text-xs h-7 px-2.5"
                  :disabled="regeneratingId === req.id"
                  @click="emit('regenerate-pdf', req)"
                >
                  {{ regeneratingId === req.id ? 'Memproses...' : 'Generate PDF' }}
                </Button>
              </div>
            </div>
          </Card>
          <p v-if="completed.length === 0" class="text-xs text-zinc-500">Belum ada riwayat.</p>
        </div>
      </div>
    </div>
  </div>
</template>
