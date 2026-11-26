<script setup>
import { ref, computed, watch } from 'vue'
import {
  ArrowLeft,
  Calendar,
  Clock,
  FileText,
  Printer,
  Download,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  ChevronDown,
  ChevronUp,
  Info,
  Building2,
} from 'lucide-vue-next'
import StatusBadge from '~/components/dashboard/StatusBadge.vue'
import WorkflowStepper from '~/components/dashboard/WorkflowStepper.vue'
import { Button } from '~/components/ui/button'
import {
  activeSignatures,
  buildWorkflowSteps,
  formatDateId,
  formatTimeRange,
  getActionGuidance,
  getRequestStatusBadge,
  hasSignatureStage,
  latestRevisionNote,
  requiredSignatureCount,
  workflowTypeLabel,
} from '~/lib/workflow'

const props = defineProps({
  requests: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  isMpk: { type: Boolean, default: false },
  userOrgId: { type: Number, default: null },
  userId: { type: Number, default: null },
  profileComplete: { type: Boolean, default: false },
  screeningId: { type: Number, default: null },
  finalizingId: { type: Number, default: null },
  signingId: { type: Number, default: null },
  downloadingId: { type: Number, default: null },
  regeneratingId: { type: Number, default: null },
})

const emit = defineEmits([
  'screen-accept',
  'screen-reject',
  'request-correction',
  'finalize',
  'approve',
  'reject',
  'sign',
  'revise',
  'download-pdf',
  'download-docx',
  'regenerate-pdf',
  'go-profile',
  'go-apply',
])

const activeFilter = ref('all')
const searchQuery = ref('')
const selectedId = ref(null)
const mobilePane = ref('list') // 'list' | 'detail'
const showHistory = ref(false)

const filterTabs = computed(() => {
  if (props.isMpk) {
    return [
      { key: 'all', label: 'Semua', count: props.requests.length },
      {
        key: 'screening',
        label: 'Screening Awal',
        count: props.requests.filter((r) => r.status === 'pending_mpk_screening').length,
      },
      {
        key: 'finalize',
        label: 'Siap Dicatat / Verifikasi',
        count: props.requests.filter((r) => ['owner_approved', 'owner_rejected'].includes(r.status)).length,
      },
      {
        key: 'waiting_owner',
        label: 'Menunggu Pemilik',
        count: props.requests.filter((r) => r.status === 'pending_owner').length,
      },
      {
        key: 'done',
        label: 'Selesai',
        count: props.requests.filter((r) => ['approved', 'rejected'].includes(r.status)).length,
      },
    ]
  }

  return [
    { key: 'all', label: 'Semua', count: props.requests.length },
    {
      key: 'incoming',
      label: 'Izin Jadwal Masuk',
      count: props.requests.filter((r) => isIncomingNeedsMe(r)).length,
    },
    {
      key: 'mine',
      label: 'Pengajuan Saya',
      count: props.requests.filter((r) => isMySubmission(r)).length,
    },
    {
      key: 'done',
      label: 'Selesai',
      count: props.requests.filter((r) => ['approved', 'rejected'].includes(r.status)).length,
    },
  ]
})

function isIncomingNeedsMe(req) {
  return (
    req.owner_organization_id === props.userOrgId &&
    (req.status === 'pending_owner' ||
      (req.status === 'owner_approved' && req.document?.document_status === 'waiting_owner_signature'))
  )
}

function isMySubmission(req) {
  return req.organization_id === props.userOrgId || req.user_id === props.userId
}

const filteredRequests = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return props.requests.filter((r) => {
    if (props.isMpk) {
      if (activeFilter.value === 'screening' && r.status !== 'pending_mpk_screening') return false
      if (activeFilter.value === 'finalize' && !['owner_approved', 'owner_rejected'].includes(r.status)) return false
      if (activeFilter.value === 'waiting_owner' && r.status !== 'pending_owner') return false
      if (activeFilter.value === 'done' && !['approved', 'rejected'].includes(r.status)) return false
    } else {
      if (activeFilter.value === 'incoming' && !isIncomingNeedsMe(r)) return false
      if (activeFilter.value === 'mine' && !isMySubmission(r)) return false
      if (activeFilter.value === 'done' && !['approved', 'rejected'].includes(r.status)) return false
    }

    if (!q) return true
    const haystack = [
      r.letter_number,
      r.facility?.name,
      r.organization?.name,
      r.owner_organization?.name,
      r.reason,
      r.date,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(q)
  })
})

watch(
  filteredRequests,
  (list) => {
    if (list.length === 0) {
      selectedId.value = null
      mobilePane.value = 'list'
      return
    }
    if (!list.some((item) => item.id === selectedId.value)) {
      selectedId.value = list[0].id
    }
  },
  { immediate: true }
)

const selectedRequest = computed(
  () => filteredRequests.value.find((r) => r.id === selectedId.value) || filteredRequests.value[0] || null
)

function selectRequest(req) {
  selectedId.value = req.id
  showHistory.value = false
  mobilePane.value = 'detail'
}

function canFinalize(req) {
  if (!req) return false
  if (req.document?.document_status === 'needs_correction') return false
  if (req.status === 'owner_rejected') return true
  if (req.document?.document_status !== 'pending_mpk_verification') return false
  return activeSignatures(req).length >= requiredSignatureCount(req)
}

function canRequesterSignStageOne(req) {
  if (!req?.document || props.isMpk) return false
  if (!isMySubmission(req)) return false
  if (hasSignatureStage(req, 1)) return false
  return (
    ['inter_eskul', 'free_slot'].includes(req.workflow_type) &&
    req.status === 'pending_mpk_screening' &&
    req.document.document_status === 'waiting_mpk_screening'
  )
}

function canMpkSignStageTwo(req) {
  return (
    props.isMpk &&
    req?.workflow_type === 'inter_eskul' &&
    req?.status === 'pending_owner' &&
    req?.document?.document_status === 'waiting_mpk_signature'
  )
}

function canOwnerSignDeferred(req) {
  return (
    !props.isMpk &&
    req?.owner_organization_id === props.userOrgId &&
    req?.status === 'owner_approved' &&
    req?.document?.document_status === 'waiting_owner_signature'
  )
}

function guidanceToneClass(tone) {
  if (tone === 'emerald') return 'border-emerald-200 border-l-emerald-700 bg-emerald-50/40'
  if (tone === 'rose') return 'border-rose-200 border-l-rose-700 bg-rose-50/40'
  if (tone === 'amber') return 'border-amber-300 border-l-amber-600 bg-amber-50/50'
  return 'border-blue-200 border-l-[#1E3A5F] bg-blue-50/35'
}

const guidance = computed(() => getActionGuidance(selectedRequest.value, props.isMpk))
</script>

<template>
  <div class="space-y-4">
    <!-- Profile completeness alert -->
    <div
      v-if="!profileComplete"
      class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3.5 text-sm text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      role="status"
    >
      <div class="flex items-start gap-2.5">
        <AlertTriangle class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
        <div>
          <p class="font-bold">Profil tanda tangan belum lengkap</p>
          <p class="text-xs text-amber-900 mt-0.5">
            Lengkapi nama ketua, jabatan, dan gambar tanda tangan sebelum menyetujui atau menandatangani surat resmi.
          </p>
        </div>
      </div>
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="border-amber-400 bg-white text-amber-950 hover:bg-amber-100 shrink-0"
        @click="emit('go-profile')"
      >
        Lengkapi di Pengaturan
      </Button>
    </div>

    <!-- Responsive Master-Detail Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
      <!-- LEFT MASTER LIST (Hidden on mobile when inspecting detail) -->
      <section
        :class="[
          'lg:col-span-5 bg-white rounded-2xl border border-border shadow-sm overflow-hidden',
          mobilePane === 'detail' && selectedRequest ? 'hidden lg:block' : 'block',
        ]"
        aria-label="Daftar permohonan"
      >
        <div class="p-4 sm:p-5 border-b border-border space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div>
              <h2 class="text-base font-bold text-slate-900">
                {{ isMpk ? 'Antrean Pemeriksaan & Verifikasi' : 'Permohonan & Keputusan Jadwal' }}
              </h2>
              <p class="text-xs text-slate-500 mt-0.5">Pilih satu berkas untuk melihat rincian &amp; tindakannya.</p>
            </div>
            <Button type="button" size="sm" variant="outline" @click="emit('go-apply')">
              + Ajukan
            </Button>
          </div>

          <search>
            <label for="master-search" class="sr-only">Cari permohonan</label>
            <input
              id="master-search"
              v-model="searchQuery"
              type="search"
              placeholder="Cari fasilitas, ekskul, atau nomor surat..."
              class="w-full h-10 rounded-xl border border-input bg-background px-3.5 text-sm text-foreground placeholder:text-muted-foreground"
            >
          </search>

          <!-- Filter pills -->
          <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Filter status permohonan">
            <button
              v-for="tab in filterTabs"
              :key="tab.key"
              type="button"
              role="tab"
              :aria-selected="activeFilter === tab.key"
              :class="[
                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors',
                activeFilter === tab.key
                  ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]'
                  : 'bg-white text-slate-700 border-border hover:bg-slate-50',
              ]"
              @click="activeFilter = tab.key"
            >
              <span>{{ tab.label }}</span>
              <span
                :class="[
                  'px-1.5 py-0.5 rounded-full text-xs font-bold tabular-nums leading-none',
                  activeFilter === tab.key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700',
                ]"
              >
                {{ tab.count }}
              </span>
            </button>
          </div>
        </div>

        <div v-if="loading" class="p-8 text-center text-sm text-slate-500">
          Memuat daftar permohonan…
        </div>

        <div v-else-if="filteredRequests.length === 0" class="p-8 text-center space-y-3">
          <FileText class="w-8 h-8 text-slate-400 mx-auto" aria-hidden="true" />
          <p class="text-sm font-medium text-slate-600">Tidak ada permohonan pada filter ini.</p>
          <Button type="button" size="sm" variant="outline" @click="activeFilter = 'all'; searchQuery = ''">
            Tampilkan Semua Berkas
          </Button>
        </div>

        <ul v-else class="divide-y divide-border max-h-[680px] overflow-y-auto">
          <li v-for="req in filteredRequests" :key="req.id">
            <button
              type="button"
              :aria-current="selectedRequest?.id === req.id ? 'true' : undefined"
              :class="[
                'w-full text-left p-4 sm:p-5 transition-colors relative block',
                selectedRequest?.id === req.id ? 'bg-blue-50/70' : 'bg-white hover:bg-slate-50/80',
              ]"
              @click="selectRequest(req)"
            >
              <span
                v-if="selectedRequest?.id === req.id"
                class="hidden lg:block absolute left-0 top-0 bottom-0 w-1 bg-[#1E3A5F]"
                aria-hidden="true"
              />
              <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <StatusBadge
                  :label="getRequestStatusBadge(req).label"
                  :tone="getRequestStatusBadge(req).tone"
                  :icon="getRequestStatusBadge(req).icon"
                />
                <span class="text-xs font-medium text-slate-500">{{ workflowTypeLabel(req) }}</span>
              </div>

              <h3 class="text-sm font-bold text-slate-900 leading-snug">
                {{ req.organization?.name || 'Organisasi' }} → {{ req.facility?.name || 'Fasilitas' }}
              </h3>

              <p class="text-xs text-slate-600 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 tabular-nums">
                <span class="inline-flex items-center gap-1">
                  <Calendar class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" />
                  {{ req.date }}
                </span>
                <span class="inline-flex items-center gap-1">
                  <Clock class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" />
                  {{ formatTimeRange(req.start_time, req.end_time) }}
                </span>
              </p>

              <p class="text-xs font-medium text-slate-500 mt-1.5">
                {{ buildWorkflowSteps(req).summary }}
              </p>
            </button>
          </li>
        </ul>
      </section>

      <!-- RIGHT DETAIL PANE (Alternates cleanly on mobile!) -->
      <section
        :class="[
          'lg:col-span-7 space-y-4',
          mobilePane === 'list' && filteredRequests.length > 0 ? 'hidden lg:block' : 'block',
        ]"
        aria-label="Rincian permohonan terpilih"
      >
        <!-- Mobile Back Button -->
        <div class="lg:hidden">
          <Button
            type="button"
            variant="outline"
            size="sm"
            class="bg-white"
            @click="mobilePane = 'list'"
          >
            <ArrowLeft class="w-4 h-4" aria-hidden="true" />
            <span>Kembali ke Daftar Permohonan</span>
          </Button>
        </div>

        <div
          v-if="!selectedRequest"
          class="bg-white rounded-2xl border border-border shadow-sm p-10 text-center space-y-2"
        >
          <Info class="w-8 h-8 text-slate-400 mx-auto" aria-hidden="true" />
          <p class="text-base font-bold text-slate-900">Belum Ada Berkas Dipilih</p>
          <p class="text-sm text-slate-500">Pilih permohonan dari daftar di sebelah kiri untuk melihat rincian dan status tanda tangannya.</p>
        </div>

        <template v-else>
          <!-- GUIDANCE & ACTION CARD -->
          <div
            v-if="guidance"
            :class="['rounded-2xl border border-l-4 bg-white shadow-sm p-5 space-y-3', guidanceToneClass(guidance.tone)]"
          >
            <div class="space-y-1">
              <p class="text-xs font-bold uppercase tracking-wider text-slate-600">Panduan Langkah Berikutnya</p>
              <h2 class="text-lg font-bold text-slate-900">{{ guidance.title }}</h2>
              <p class="text-sm text-slate-700 leading-relaxed">{{ guidance.description }}</p>
            </div>

            <!-- Action Buttons strictly scoped per role -->
            <div class="flex flex-wrap items-center gap-2.5 pt-1">
              <!-- MPK: Screening Queue -->
              <template v-if="isMpk && selectedRequest.status === 'pending_mpk_screening'">
                <Button
                  type="button"
                  :disabled="!profileComplete || screeningId === selectedRequest.id"
                  class="bg-[#1E3A5F] hover:bg-[#152943] text-white"
                  @click="emit('screen-accept', selectedRequest)"
                >
                  <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
                  <span>
                    {{
                      screeningId === selectedRequest.id
                        ? 'Memproses…'
                        : selectedRequest.owner_organization_id
                          ? 'Lolos Screening & TTD MPK'
                          : 'Setujui & TTD Slot Kosong'
                    }}
                  </span>
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  :disabled="screeningId === selectedRequest.id"
                  class="border-rose-300 text-rose-700 hover:bg-rose-50"
                  @click="emit('screen-reject', selectedRequest)"
                >
                  <XCircle class="w-4 h-4" aria-hidden="true" />
                  <span>Tolak dengan Alasan</span>
                </Button>
              </template>

              <!-- MPK: Deferred MPK signature on pending_owner -->
              <Button
                v-if="canMpkSignStageTwo(selectedRequest)"
                type="button"
                :disabled="!profileComplete || signingId === selectedRequest.id"
                class="bg-[#1E3A5F] hover:bg-[#152943] text-white"
                @click="emit('sign', selectedRequest)"
              >
                {{ signingId === selectedRequest.id ? 'Menyimpan…' : 'Bubuhkan TTD MPK' }}
              </Button>

              <!-- MPK: Finalize / Request Correction -->
              <template v-if="isMpk && ['owner_approved', 'owner_rejected'].includes(selectedRequest.status)">
                <Button
                  type="button"
                  :disabled="!canFinalize(selectedRequest) || finalizingId === selectedRequest.id"
                  :class="
                    selectedRequest.status === 'owner_approved'
                      ? 'bg-emerald-700 hover:bg-emerald-800 text-white'
                      : 'bg-slate-900 hover:bg-slate-800 text-white'
                  "
                  @click="emit('finalize', selectedRequest)"
                >
                  <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
                  <span>
                    {{
                      finalizingId === selectedRequest.id
                        ? 'Memproses…'
                        : selectedRequest.status === 'owner_approved'
                          ? (selectedRequest.workflow_type === 'mpk_borrow'
                            ? 'Catat Persetujuan & Terbitkan PDF'
                            : 'Verifikasi & Terbitkan Surat PDF')
                          : 'Catat & Sampaikan Penolakan'
                    }}
                  </span>
                </Button>
                <Button
                  v-if="selectedRequest.status === 'owner_approved' && selectedRequest.document?.document_status === 'pending_mpk_verification'"
                  type="button"
                  variant="outline"
                  :disabled="finalizingId === selectedRequest.id"
                  class="border-amber-400 text-amber-900 hover:bg-amber-50"
                  @click="emit('request-correction', selectedRequest)"
                >
                  Minta Perbaikan Berkas
                </Button>
              </template>

              <!-- ESKUL: Owner Decision Inbox -->
              <template v-if="!isMpk && selectedRequest.owner_organization_id === userOrgId && selectedRequest.status === 'pending_owner'">
                <Button
                  type="button"
                  :disabled="!profileComplete || signingId === selectedRequest.id"
                  class="bg-emerald-700 hover:bg-emerald-800 text-white"
                  @click="emit('approve', selectedRequest)"
                >
                  <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
                  <span>Izinkan &amp; Bubuhkan TTD</span>
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  class="border-rose-300 text-rose-700 hover:bg-rose-50"
                  @click="emit('reject', selectedRequest)"
                >
                  <XCircle class="w-4 h-4" aria-hidden="true" />
                  <span>Tolak dengan Alasan</span>
                </Button>
              </template>

              <!-- ESKUL: Deferred Owner signature -->
              <Button
                v-if="canOwnerSignDeferred(selectedRequest)"
                type="button"
                :disabled="!profileComplete || signingId === selectedRequest.id"
                class="bg-[#1E3A5F] hover:bg-[#152943] text-white"
                @click="emit('sign', selectedRequest)"
              >
                {{ signingId === selectedRequest.id ? 'Menyimpan…' : 'Bubuhkan TTD Pemilik' }}
              </Button>

              <!-- ESKUL: Requester Stage 1 Sign -->
              <Button
                v-if="canRequesterSignStageOne(selectedRequest)"
                type="button"
                :disabled="!profileComplete || signingId === selectedRequest.id"
                class="bg-[#1E3A5F] hover:bg-[#152943] text-white"
                @click="emit('sign', selectedRequest)"
              >
                {{ signingId === selectedRequest.id ? 'Menyimpan…' : 'Bubuhkan TTD Pemohon (Tahap 1)' }}
              </Button>

              <!-- ESKUL: Revise if needs_correction -->
              <Button
                v-if="!isMpk && isMySubmission(selectedRequest) && selectedRequest.document?.document_status === 'needs_correction'"
                type="button"
                class="bg-amber-600 hover:bg-amber-700 text-white"
                @click="emit('revise', selectedRequest)"
              >
                Ajukan Revisi Permohonan
              </Button>

              <!-- FINAL DOCUMENT DOWNLOADS -->
              <template
                v-if="selectedRequest.status === 'approved' && selectedRequest.document?.document_status === 'finalized' && selectedRequest.document?.final_pdf_path"
              >
                <Button
                  type="button"
                  class="bg-[#1E3A5F] hover:bg-[#152943] text-white"
                  :disabled="downloadingId === selectedRequest.id"
                  @click="emit('download-pdf', selectedRequest, true)"
                >
                  <Printer class="w-4 h-4" aria-hidden="true" />
                  <span>Buka / Cetak Surat PDF</span>
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  :disabled="downloadingId === selectedRequest.id"
                  @click="emit('download-pdf', selectedRequest, false)"
                >
                  <Download class="w-4 h-4" aria-hidden="true" />
                  <span>Unduh PDF</span>
                </Button>
                <Button
                  v-if="selectedRequest.document?.merged_docx_path"
                  type="button"
                  variant="outline"
                  :disabled="downloadingId === selectedRequest.id"
                  @click="emit('download-docx', selectedRequest)"
                >
                  <FileText class="w-4 h-4" aria-hidden="true" />
                  <span>Unduh DOCX</span>
                </Button>
              </template>

              <!-- MPK: Regenerate PDF if pending/failed -->
              <Button
                v-if="isMpk && selectedRequest.status === 'approved' && ['pending_pdf_generation', 'pdf_generation_failed'].includes(selectedRequest.document?.document_status)"
                type="button"
                class="bg-amber-600 hover:bg-amber-700 text-white"
                :disabled="regeneratingId === selectedRequest.id"
                @click="emit('regenerate-pdf', selectedRequest)"
              >
                <RefreshCw class="w-4 h-4" aria-hidden="true" />
                <span>{{ regeneratingId === selectedRequest.id ? 'Memproses PDF…' : 'Generate Ulang PDF Resmi' }}</span>
              </Button>
            </div>
          </div>

          <!-- DETAILED DOSSIER CARD -->
          <div class="bg-white rounded-2xl border border-border shadow-sm p-5 sm:p-6 space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-border">
              <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-xs font-semibold text-slate-500">{{ workflowTypeLabel(selectedRequest) }}</span>
                  <span
                    v-if="(selectedRequest.document?.current_revision || 1) > 1"
                    class="px-2 py-0.5 rounded-md bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold"
                  >
                    Revisi ke-{{ selectedRequest.document.current_revision }}
                  </span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                  <Building2 class="w-5 h-5 text-[#1E3A5F] shrink-0" aria-hidden="true" />
                  <span>{{ selectedRequest.facility?.name || '-' }}</span>
                </h3>
              </div>

              <div class="flex flex-col sm:items-end gap-1.5">
                <StatusBadge
                  :label="getRequestStatusBadge(selectedRequest).label"
                  :tone="getRequestStatusBadge(selectedRequest).tone"
                  :icon="getRequestStatusBadge(selectedRequest).icon"
                />
                <span
                  v-if="selectedRequest.letter_number"
                  class="text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg tabular-nums"
                >
                  Nomor Surat: {{ selectedRequest.letter_number }}
                </span>
              </div>
            </div>

            <!-- Structured Metadata -->
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-slate-50 border border-border p-4 text-sm">
              <div>
                <dt class="text-xs font-semibold text-slate-500">Organisasi Pemohon</dt>
                <dd class="font-bold text-slate-900 mt-0.5">{{ selectedRequest.organization?.name || '-' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-slate-500">Pemilik Jadwal Tetap</dt>
                <dd class="font-bold text-slate-900 mt-0.5">
                  {{ selectedRequest.owner_organization?.name || 'Tidak ada (Slot Kosong)' }}
                </dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-slate-500">Tanggal Pemakaian</dt>
                <dd class="font-semibold text-slate-800 mt-0.5 tabular-nums">{{ formatDateId(selectedRequest.date) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-slate-500">Rentang Waktu</dt>
                <dd class="font-semibold text-slate-800 mt-0.5 tabular-nums">
                  {{ formatTimeRange(selectedRequest.start_time, selectedRequest.end_time) }}
                </dd>
              </div>
              <div class="sm:col-span-2 pt-3 border-t border-border">
                <dt class="text-xs font-semibold text-slate-500">Alasan / Keperluan</dt>
                <dd class="text-slate-800 mt-1 leading-relaxed">“{{ selectedRequest.reason }}”</dd>
              </div>
            </dl>

            <div
              v-if="selectedRequest.rejection_reason"
              class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 flex items-start gap-2.5"
            >
              <XCircle class="w-4 h-4 text-rose-700 shrink-0 mt-0.5" aria-hidden="true" />
              <div>
                <p class="font-bold">Alasan Penolakan Tercatat</p>
                <p class="mt-0.5">{{ selectedRequest.rejection_reason }}</p>
              </div>
            </div>

            <div
              v-if="selectedRequest.document?.document_status === 'needs_correction'"
              class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 flex items-start gap-2.5"
            >
              <AlertTriangle class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
              <div>
                <p class="font-bold">Catatan Revisi dari MPK</p>
                <p class="mt-0.5">{{ latestRevisionNote(selectedRequest) || 'Periksa kembali jadwal dan alasan peminjaman.' }}</p>
              </div>
            </div>

            <!-- Accurate Per-Workflow Stepper -->
            <WorkflowStepper :request="selectedRequest" />

            <!-- Collapsible Audit History -->
            <div v-if="selectedRequest.histories?.length" class="pt-4 border-t border-border">
              <button
                type="button"
                :aria-expanded="showHistory"
                class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1E3A5F] hover:underline"
                @click="showHistory = !showHistory"
              >
                <component :is="showHistory ? ChevronUp : ChevronDown" class="w-4 h-4" aria-hidden="true" />
                <span>{{ showHistory ? 'Sembunyikan Riwayat Aktivitas' : `Lihat Riwayat Aktivitas (${selectedRequest.histories.length})` }}</span>
              </button>

              <ul v-if="showHistory" class="mt-3 space-y-2">
                <li
                  v-for="h in selectedRequest.histories"
                  :key="h.id"
                  class="rounded-lg bg-slate-50 border border-border px-3 py-2 text-xs text-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-1"
                >
                  <span>
                    <strong class="text-slate-900">{{ h.action }}</strong>
                    <span class="text-slate-500"> ({{ h.actor_role }}) → {{ h.to_status }}</span>
                    <span v-if="h.note" class="block sm:inline text-slate-600"> — “{{ h.note }}”</span>
                  </span>
                  <span class="text-slate-500 tabular-nums shrink-0">
                    {{ new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(h.created_at)) }}
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </template>
      </section>
    </div>
  </div>
</template>

