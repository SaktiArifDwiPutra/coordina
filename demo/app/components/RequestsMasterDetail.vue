<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  ArrowLeft,
  Building2,
  Calendar,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Clock,
  FileText,
  Info,
  PenTool,
  XCircle,
} from 'lucide-vue-next'
import StatusBadge from '~/components/StatusBadge.vue'
import WorkflowStepper from '~/components/WorkflowStepper.vue'
import {
  formatDateId,
  formatTimeRange,
  getActionGuidance,
  getHumanStatusBadge,
  workflowTitle,
} from '~/lib/workflow'
import type { BorrowRequestItem, DemoRole, Facility, OrganizationProfile } from '~/types/coordina'

const props = defineProps<{
  requests: BorrowRequestItem[]
  role: DemoRole
  activeEskulOrgId: number
  organizations: OrganizationProfile[]
  facilities: Facility[]
  selectedId: number | null
}>()

const emit = defineEmits<{
  (e: 'update:selectedId', id: number): void
  (e: 'screen-accept', req: BorrowRequestItem): void
  (e: 'screen-reject', req: BorrowRequestItem): void
  (e: 'owner-approve', req: BorrowRequestItem): void
  (e: 'owner-reject', req: BorrowRequestItem): void
  (e: 'finalize', req: BorrowRequestItem): void
  (e: 'preview-letter', req: BorrowRequestItem): void
  (e: 'go-apply'): void
}>()

const filterMode = ref<'all' | 'action' | 'done'>('all')
const searchQuery = ref('')
const mobilePane = ref<'list' | 'detail'>('list')
const showHistory = ref(false)
const searchId = useId()

function orgName(id: number | null) {
  if (!id) return null
  return props.organizations.find((o) => o.id === id)?.name || '-'
}

function facilityName(id: number) {
  return props.facilities.find((f) => f.id === id)?.name || '-'
}

function needsMyAction(req: BorrowRequestItem): boolean {
  if (props.role === 'mpk') {
    return ['pending_mpk_screening', 'owner_approved', 'owner_rejected'].includes(req.status)
  }
  return req.ownerOrgId === props.activeEskulOrgId && req.status === 'pending_owner'
}

const counts = computed(() => ({
  all: props.requests.length,
  action: props.requests.filter((r) => needsMyAction(r)).length,
  done: props.requests.filter((r) => ['approved', 'rejected'].includes(r.status)).length,
}))

const filteredRequests = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return props.requests.filter((req) => {
    if (filterMode.value === 'action' && !needsMyAction(req)) return false
    if (filterMode.value === 'done' && !['approved', 'rejected'].includes(req.status)) return false
    if (!q) return true
    const text = [
      req.letterNumber,
      orgName(req.requesterOrgId),
      orgName(req.ownerOrgId),
      facilityName(req.facilityId),
      req.reason,
      req.date,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return text.includes(q)
  })
})

watch(
  filteredRequests,
  (list) => {
    if (list.length === 0) {
      mobilePane.value = 'list'
      return
    }
    if (!list.some((r) => r.id === props.selectedId)) {
      emit('update:selectedId', list[0].id)
    }
  },
  { immediate: true }
)

const activeRequest = computed(
  () => filteredRequests.value.find((r) => r.id === props.selectedId) || filteredRequests.value[0] || null
)

const guidance = computed(() =>
  activeRequest.value
    ? getActionGuidance(activeRequest.value, props.role, props.activeEskulOrgId, props.organizations)
    : null
)

function selectCard(req: BorrowRequestItem) {
  emit('update:selectedId', req.id)
  showHistory.value = false
  mobilePane.value = 'detail'
}

function guidanceBoxCls(tone: 'blue' | 'emerald' | 'amber' | 'rose') {
  if (tone === 'emerald') return 'border-[#A7F3D0] border-l-[#15803D] bg-[#ECFDF5]/40'
  if (tone === 'amber') return 'border-[#FDE68A] border-l-[#B45309] bg-[#FFFBEB]/50'
  if (tone === 'rose') return 'border-[#FECDD3] border-l-[#BE123C] bg-[#FFF1F2]/50'
  return 'border-[#BFDBFE] border-l-[#1E3A5F] bg-[#EFF6FF]/45'
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
    <!-- MASTER QUEUE LIST -->
    <section
      :class="[
        'lg:col-span-5 bg-white rounded-2xl border border-[#DCE4EE] shadow-sm overflow-hidden',
        mobilePane === 'detail' && activeRequest ? 'hidden lg:block' : 'block',
      ]"
      aria-label="Antrean Permohonan"
    >
      <div class="p-4 sm:p-5 border-b border-[#DCE4EE] space-y-3">
        <div class="flex items-center justify-between gap-2">
          <div>
            <h2 class="text-base font-bold text-[#0F172A]">
              {{ role === 'mpk' ? 'Antrean Pemeriksaan & Verifikasi' : 'Permohonan & Keputusan Jadwal' }}
            </h2>
            <p class="text-xs text-[#475569] mt-0.5">
              {{ counts.action }} tugas membutuhkan tindakan Anda saat ini.
            </p>
          </div>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg border border-[#DCE4EE] bg-[#F8FAFC] hover:bg-[#EFF6FF] text-xs font-semibold text-[#1E3A5F]"
            @click="emit('go-apply')"
          >
            + Ajukan
          </button>
        </div>

        <search>
          <label :for="searchId" class="sr-only">Cari permohonan</label>
          <input
            :id="searchId"
            v-model="searchQuery"
            type="search"
            placeholder="Cari fasilitas, ekskul, atau nomor surat…"
            class="w-full h-10 rounded-xl border border-[#DCE4EE] bg-[#F8FAFC] px-3.5 text-sm text-[#0F172A] placeholder:text-[#475569]"
          >
        </search>

        <div class="grid grid-cols-3 gap-1.5 bg-[#F4F7FA] p-1 rounded-xl border border-[#DCE4EE]" role="tablist" aria-label="Filter antrean">
          <button
            type="button"
            role="tab"
            :aria-selected="filterMode === 'all'"
            :class="[
              'py-1.5 px-2 rounded-lg text-xs font-semibold transition-colors',
              filterMode === 'all' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-[#475569] hover:text-[#0F172A]',
            ]"
            @click="filterMode = 'all'"
          >
            Semua ({{ counts.all }})
          </button>
          <button
            type="button"
            role="tab"
            :aria-selected="filterMode === 'action'"
            :class="[
              'py-1.5 px-2 rounded-lg text-xs font-semibold transition-colors',
              filterMode === 'action' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-[#475569] hover:text-[#0F172A]',
            ]"
            @click="filterMode = 'action'"
          >
            Tugas ({{ counts.action }})
          </button>
          <button
            type="button"
            role="tab"
            :aria-selected="filterMode === 'done'"
            :class="[
              'py-1.5 px-2 rounded-lg text-xs font-semibold transition-colors',
              filterMode === 'done' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-[#475569] hover:text-[#0F172A]',
            ]"
            @click="filterMode = 'done'"
          >
            Selesai ({{ counts.done }})
          </button>
        </div>
      </div>

      <div v-if="filteredRequests.length === 0" class="p-8 text-center space-y-2.5">
        <FileText class="w-8 h-8 text-[#475569] mx-auto" aria-hidden="true" />
        <p class="text-sm font-semibold text-[#0F172A]">Tidak ada berkas pada filter ini</p>
        <button
          type="button"
          class="px-3.5 py-1.5 rounded-lg border border-[#DCE4EE] text-xs font-semibold text-[#1E3A5F] hover:bg-[#F8FAFC]"
          @click="filterMode = 'all'; searchQuery = ''"
        >
          Tampilkan Semua Berkas
        </button>
      </div>

      <ul v-else class="divide-y divide-[#DCE4EE] max-h-[680px] overflow-y-auto">
        <li v-for="req in filteredRequests" :key="req.id">
          <button
            type="button"
            :aria-current="activeRequest?.id === req.id ? 'true' : undefined"
            :class="[
              'w-full text-left p-4 sm:p-5 transition-colors relative block',
              activeRequest?.id === req.id ? 'bg-[#EFF6FF]/80' : 'bg-white hover:bg-[#F8FAFC]',
            ]"
            @click="selectCard(req)"
          >
            <span
              v-if="activeRequest?.id === req.id"
              class="hidden lg:block absolute left-0 top-0 bottom-0 w-1 bg-[#1E3A5F]"
              aria-hidden="true"
            />
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
              <StatusBadge
                :label="getHumanStatusBadge(req).label"
                :tone="getHumanStatusBadge(req).tone"
                :icon="getHumanStatusBadge(req).icon"
              />
              <span class="text-xs font-semibold text-[#475569]">
                {{ req.workflowType === 'mpk_borrow' ? 'Alur B · 2 TTD' : req.workflowType === 'inter_eskul' ? 'Alur A · 3 TTD' : 'Slot Kosong' }}
              </span>
            </div>

            <h3 class="text-sm font-bold text-[#0F172A] leading-snug">
              {{ orgName(req.requesterOrgId) }} → {{ facilityName(req.facilityId) }}
            </h3>
            <p v-if="req.ownerOrgId" class="text-xs text-[#475569] mt-0.5">
              Pemilik Jadwal: <strong class="text-[#0F172A]">{{ orgName(req.ownerOrgId) }}</strong>
            </p>
            <p class="text-xs text-[#475569] mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 tabular-nums">
              <span class="inline-flex items-center gap-1">
                <Calendar class="w-3.5 h-3.5 text-[#475569]" aria-hidden="true" />
                {{ req.date }}
              </span>
              <span class="inline-flex items-center gap-1">
                <Clock class="w-3.5 h-3.5 text-[#475569]" aria-hidden="true" />
                {{ formatTimeRange(req.startTime, req.endTime) }}
              </span>
            </p>
          </button>
        </li>
      </ul>
    </section>

    <!-- DETAIL PANEL (Alternates cleanly on mobile <lg) -->
    <section
      :class="[
        'lg:col-span-7 space-y-4',
        mobilePane === 'list' && filteredRequests.length > 0 ? 'hidden lg:block' : 'block',
      ]"
      aria-label="Rincian Permohonan"
    >
      <div class="lg:hidden">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-[#DCE4EE] bg-white text-xs font-semibold text-[#0F172A] hover:bg-[#F8FAFC]"
          @click="mobilePane = 'list'"
        >
          <ArrowLeft class="w-4 h-4" aria-hidden="true" />
          <span>Kembali ke Daftar Antrean</span>
        </button>
      </div>

      <div
        v-if="!activeRequest"
        class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-10 text-center space-y-2"
      >
        <Info class="w-8 h-8 text-[#475569] mx-auto" aria-hidden="true" />
        <p class="text-base font-bold text-[#0F172A]">Belum Ada Berkas Dipilih</p>
        <p class="text-sm text-[#475569]">Pilih salah satu permohonan pada daftar untuk melihat rinciannya.</p>
      </div>

      <template v-else>
        <!-- GUIDE CARD: "Apa yang Harus Dilakukan Sekarang?" -->
        <div
          v-if="guidance"
          :class="['rounded-2xl border border-l-4 shadow-sm p-5 space-y-3', guidanceBoxCls(guidance.tone)]"
        >
          <div class="space-y-1">
            <p class="text-xs font-bold uppercase tracking-wider text-[#1E3A5F]">Apa yang Harus Dilakukan Sekarang?</p>
            <h2 class="text-lg font-bold text-[#0F172A]">{{ guidance.title }}</h2>
            <p class="text-sm text-[#475569] leading-relaxed">{{ guidance.description }}</p>
          </div>

          <!-- ROLE-RESTRICTED ACTION BUTTONS -->
          <div class="flex flex-wrap items-center gap-2.5 pt-1">
            <!-- MPK: Workflow A / Free Slot Screening -->
            <template v-if="role === 'mpk' && activeRequest.status === 'pending_mpk_screening'">
              <button
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold shadow-sm"
                @click="emit('screen-accept', activeRequest)"
              >
                <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
                <span>
                  {{ activeRequest.ownerOrgId ? 'Setujui Pemeriksaan & TTD MPK' : 'Setujui & Terbitkan Surat' }}
                </span>
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#FECDD3] bg-white hover:bg-[#FFF1F2] text-[#BE123C] text-sm font-semibold"
                @click="emit('screen-reject', activeRequest)"
              >
                <XCircle class="w-4 h-4" aria-hidden="true" />
                <span>Tolak dengan Alasan</span>
              </button>
            </template>

            <!-- MPK: Final Administrative Verification (Workflow A) or Recording (Workflow B) -->
            <template v-if="role === 'mpk' && ['owner_approved', 'owner_rejected'].includes(activeRequest.status)">
              <button
                type="button"
                :class="[
                  'inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-white text-sm font-semibold shadow-sm',
                  activeRequest.status === 'owner_approved'
                    ? 'bg-[#15803D] hover:bg-emerald-800'
                    : 'bg-[#0F172A] hover:bg-slate-800',
                ]"
                @click="emit('finalize', activeRequest)"
              >
                <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
                <span>
                  {{
                    activeRequest.status === 'owner_approved'
                      ? (activeRequest.workflowType === 'mpk_borrow'
                        ? 'Catat & Terbitkan Surat Resmi'
                        : 'Verifikasi & Publikasikan Surat')
                      : 'Catat Hasil Penolakan'
                  }}
                </span>
              </button>
            </template>

            <!-- ESKUL: Owner Approval / Rejection (strictly when active org is affected owner) -->
            <template
              v-if="role === 'eskul' && activeRequest.ownerOrgId === activeEskulOrgId && activeRequest.status === 'pending_owner'"
            >
              <button
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#15803D] hover:bg-emerald-800 text-white text-sm font-semibold shadow-sm"
                @click="emit('owner-approve', activeRequest)"
              >
                <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
                <span>Setujui &amp; TTD Pemilik</span>
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#FECDD3] bg-white hover:bg-[#FFF1F2] text-[#BE123C] text-sm font-semibold"
                @click="emit('owner-reject', activeRequest)"
              >
                <XCircle class="w-4 h-4" aria-hidden="true" />
                <span>Tolak dengan Alasan</span>
              </button>
            </template>

            <!-- PREVIEW ARCHIVED LETTER -->
            <button
              v-if="activeRequest.status === 'approved' && activeRequest.letterNumber"
              type="button"
              class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold shadow-sm"
              @click="emit('preview-letter', activeRequest)"
            >
              <FileText class="w-4 h-4" aria-hidden="true" />
              <span>Pratinjau &amp; Cetak Surat</span>
            </button>
          </div>
        </div>

        <!-- DETAILS + SIGNATURE BLOCKS + WORKFLOW STEPPER -->
        <div class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-5 sm:p-6 space-y-6">
          <div class="flex flex-wrap items-start justify-between gap-3 pb-4 border-b border-[#DCE4EE]">
            <div class="space-y-1">
              <span class="text-xs font-semibold text-[#475569]">{{ workflowTitle(activeRequest) }}</span>
              <h3 class="text-lg font-bold text-[#0F172A] flex items-center gap-2">
                <Building2 class="w-5 h-5 text-[#1E3A5F] shrink-0" aria-hidden="true" />
                <span>{{ facilityName(activeRequest.facilityId) }}</span>
              </h3>
            </div>
            <div class="flex flex-col sm:items-end gap-1.5">
              <StatusBadge
                :label="getHumanStatusBadge(activeRequest).label"
                :tone="getHumanStatusBadge(activeRequest).tone"
                :icon="getHumanStatusBadge(activeRequest).icon"
              />
              <span
                v-if="activeRequest.letterNumber"
                class="text-xs font-bold text-[#15803D] bg-[#ECFDF5] border border-[#A7F3D0] px-2.5 py-1 rounded-lg tabular-nums"
              >
                No. Surat: {{ activeRequest.letterNumber }}
              </span>
            </div>
          </div>

          <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-[#F8FAFC] border border-[#DCE4EE] p-4 text-sm">
            <div>
              <dt class="text-xs font-semibold text-[#475569]">Organisasi Pemohon</dt>
              <dd class="font-bold text-[#0F172A] mt-0.5">{{ orgName(activeRequest.requesterOrgId) }}</dd>
            </div>
            <div>
              <dt class="text-xs font-semibold text-[#475569]">Pemilik Jadwal Terdampak</dt>
              <dd class="font-bold text-[#0F172A] mt-0.5">{{ orgName(activeRequest.ownerOrgId) || 'Slot Kosong (Tanpa Pemilik)' }}</dd>
            </div>
            <div>
              <dt class="text-xs font-semibold text-[#475569]">Tanggal Peminjaman</dt>
              <dd class="font-semibold text-[#0F172A] mt-0.5 tabular-nums">{{ formatDateId(activeRequest.date) }}</dd>
            </div>
            <div>
              <dt class="text-xs font-semibold text-[#475569]">Waktu Pemakaian</dt>
              <dd class="font-semibold text-[#0F172A] mt-0.5 tabular-nums">{{ formatTimeRange(activeRequest.startTime, activeRequest.endTime) }}</dd>
            </div>
            <div class="sm:col-span-2 pt-2.5 border-t border-[#DCE4EE]">
              <dt class="text-xs font-semibold text-[#475569]">Alasan Peminjaman</dt>
              <dd class="text-[#0F172A] mt-1 leading-relaxed">“{{ activeRequest.reason }}”</dd>
            </div>
          </dl>

          <div
            v-if="activeRequest.rejectionReason"
            class="rounded-xl border border-[#FECDD3] bg-[#FFF1F2] px-4 py-3 text-sm text-[#BE123C] flex items-start gap-2.5"
          >
            <XCircle class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
            <div>
              <p class="font-bold">Alasan Penolakan Tercatat</p>
              <p class="mt-0.5">{{ activeRequest.rejectionReason }}</p>
            </div>
          </div>

          <!-- SIMULATED SIGNATURE BLOCKS (2 for Workflow B, 3 for Workflow A) -->
          <div class="space-y-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4 class="text-sm font-bold text-[#0F172A] flex items-center gap-1.5">
                <PenTool class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
                <span>Status Tanda Tangan Simulasi ({{ activeRequest.signatures.filter((s) => s.signed).length }}/{{ activeRequest.signatures.length }})</span>
              </h4>
              <span class="text-xs text-[#475569]">Simulasi demo · bukan tanda tangan kriptografis</span>
            </div>
            <div
              :class="[
                'grid grid-cols-1 gap-3',
                activeRequest.signatures.length === 2 ? 'sm:grid-cols-2' : activeRequest.signatures.length === 1 ? 'sm:grid-cols-1 max-w-sm' : 'sm:grid-cols-3',
              ]"
            >
              <div
                v-for="sig in activeRequest.signatures"
                :key="sig.stageOrder"
                :class="[
                  'rounded-xl border p-3.5 text-center space-y-1.5',
                  sig.signed ? 'border-[#A7F3D0] bg-[#ECFDF5]/40' : 'border-dashed border-[#DCE4EE] bg-[#F8FAFC]',
                ]"
              >
                <p class="text-xs font-bold text-[#1E3A5F]">{{ sig.roleLabel }}</p>
                <p class="text-xs text-[#475569]">{{ sig.organizationName }}</p>
                <div class="h-12 flex items-center justify-center">
                  <img
                    v-if="sig.signed && sig.signatureDataUrl"
                    :src="sig.signatureDataUrl"
                    :alt="`Tanda tangan simulasi ${sig.signerName}`"
                    class="h-10 object-contain"
                  >
                  <span v-else class="text-xs text-[#475569]">Menunggu TTD</span>
                </div>
                <p class="text-xs font-bold text-[#0F172A]">{{ sig.signerName }}</p>
                <StatusBadge
                  :label="sig.signed ? 'Tertandatangani' : 'Belum TTD'"
                  :tone="sig.signed ? 'emerald' : 'amber'"
                  :icon="sig.signed ? 'check' : 'clock'"
                />
              </div>
            </div>
          </div>

          <!-- ACCURATE WORKFLOW STEPPER -->
          <WorkflowStepper :request="activeRequest" :organizations="organizations" />

          <!-- COLLAPSIBLE ACTIVITY TIMELINE -->
          <div v-if="activeRequest.history.length" class="pt-4 border-t border-[#DCE4EE]">
            <button
              type="button"
              :aria-expanded="showHistory"
              class="inline-flex items-center gap-1.5 text-xs font-bold text-[#1E3A5F] hover:underline"
              @click="showHistory = !showHistory"
            >
              <component :is="showHistory ? ChevronUp : ChevronDown" class="w-4 h-4" aria-hidden="true" />
              <span>{{ showHistory ? 'Sembunyikan Riwayat Aktivitas' : `Lihat Riwayat Aktivitas (${activeRequest.history.length})` }}</span>
            </button>
            <ul v-if="showHistory" class="mt-3 space-y-2">
              <li
                v-for="ev in activeRequest.history"
                :key="ev.id"
                class="rounded-xl bg-[#F8FAFC] border border-[#DCE4EE] px-3.5 py-2.5 text-xs text-[#0F172A] flex flex-col sm:flex-row sm:items-center justify-between gap-1.5"
              >
                <div>
                  <strong>{{ ev.actor }}</strong> <span class="text-[#475569]">({{ ev.roleText }})</span> — {{ ev.summary }}
                  <p v-if="ev.note" class="text-[#475569] italic mt-0.5">“{{ ev.note }}”</p>
                </div>
                <span class="text-[#475569] tabular-nums shrink-0">{{ ev.timestamp }}</span>
              </li>
            </ul>
          </div>
        </div>
      </template>
    </section>
  </div>
</template>

