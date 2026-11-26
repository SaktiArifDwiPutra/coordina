<script setup lang="ts">
import { computed } from 'vue'
import { Printer, CheckCircle2, ShieldAlert } from 'lucide-vue-next'
import AppDialog from '~/components/AppDialog.vue'
import { formatDateId, formatTimeRange, workflowTitle } from '~/lib/workflow'
import type { BorrowRequestItem, Facility, OrganizationProfile } from '~/types/coordina'

const props = defineProps<{
  open: boolean
  request: BorrowRequestItem | null
  organizations: OrganizationProfile[]
  facilities: Facility[]
}>()

const emit = defineEmits<{
  (e: 'update:open', val: boolean): void
}>()

const requester = computed(() => props.organizations.find((o) => o.id === props.request?.requesterOrgId))
const owner = computed(() =>
  props.request?.ownerOrgId ? props.organizations.find((o) => o.id === props.request?.ownerOrgId) : null
)
const facility = computed(() => props.facilities.find((f) => f.id === props.request?.facilityId))

function printDocument() {
  window.print()
}
</script>

<template>
  <AppDialog
    :open="open"
    title="Pratinjau Surat Izin Resmi (Simulasi Demo)"
    max-width-class="max-w-3xl"
    @update:open="(v) => emit('update:open', v)"
  >
    <div v-if="request" class="space-y-4">
      <div class="rounded-xl bg-[#FFFBEB] border border-[#FDE68A] px-3.5 py-2.5 text-xs text-[#B45309] flex items-center gap-2 no-print">
        <ShieldAlert class="w-4 h-4 shrink-0" aria-hidden="true" />
        <span><strong>Mode Simulasi Demo:</strong> Pratinjau dokumen dan tanda tangan di bawah adalah data contoh untuk presentasi (bukan PDF hukum aktif).</span>
      </div>

      <!-- SIMULATED A4 LETTER SHEET -->
      <article class="rounded-xl border border-[#DCE4EE] bg-white p-6 sm:p-8 shadow-sm space-y-6 text-[#0F172A]">
        <!-- Kop Surat -->
        <header class="flex items-center justify-between gap-4 pb-4 border-b-2 border-[#0F172A]">
          <div class="flex items-center gap-3">
            <img
              v-if="requester?.logoDataUrl"
              :src="requester.logoDataUrl"
              :alt="`Logo ${requester.name}`"
              class="h-14 w-14 object-contain rounded-lg"
            >
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-[#475569]">SMK Negeri 4 Padalarang · Kesiswaan &amp; MPK Komisi 2</p>
              <h2 class="text-xl font-bold text-[#0F172A]">SURAT IZIN PEMINJAMAN FASILITAS</h2>
              <p class="text-sm font-semibold text-[#1E3A5F] tabular-nums">
                Nomor: {{ request.letterNumber || '(Draft Belum Bernomor)' }}
              </p>
            </div>
          </div>
          <span class="hidden sm:inline-flex items-center gap-1 rounded-full bg-[#ECFDF5] border border-[#A7F3D0] px-3 py-1 text-xs font-bold text-[#15803D]">
            <CheckCircle2 class="w-3.5 h-3.5" aria-hidden="true" />
            <span>Terarsip</span>
          </span>
        </header>

        <p class="text-sm leading-relaxed text-[#0F172A]">
          Berdasarkan koordinasi jadwal dan persetujuan bersama pada sistem <strong>Coordina</strong>
          ({{ workflowTitle(request) }}), bersama ini diberikan izin pemakaian fasilitas sekolah dengan rincian:
        </p>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl bg-[#F8FAFC] border border-[#DCE4EE] p-4 text-sm">
          <div>
            <dt class="text-xs font-semibold text-[#475569]">Organisasi Pemohon</dt>
            <dd class="font-bold text-[#0F172A]">{{ requester?.name || '-' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[#475569]">Pemilik Jadwal Terdampak</dt>
            <dd class="font-bold text-[#0F172A]">{{ owner?.name || 'Tidak ada (Slot Kosong)' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[#475569]">Fasilitas</dt>
            <dd class="font-bold text-[#0F172A]">{{ facility?.name || '-' }} ({{ facility?.type }})</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[#475569]">Hari &amp; Tanggal</dt>
            <dd class="font-semibold text-[#0F172A] tabular-nums">{{ formatDateId(request.date) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[#475569]">Waktu Pemakaian</dt>
            <dd class="font-semibold text-[#0F172A] tabular-nums">{{ formatTimeRange(request.startTime, request.endTime) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold text-[#475569]">Tanggal Terbit</dt>
            <dd class="font-semibold text-[#0F172A] tabular-nums">{{ formatDateId(request.letterDate) }}</dd>
          </div>
          <div class="sm:col-span-2 pt-2 border-t border-[#DCE4EE]">
            <dt class="text-xs font-semibold text-[#475569]">Keperluan Kegiatan</dt>
            <dd class="text-[#0F172A] mt-0.5">“{{ request.reason }}”</dd>
          </div>
        </dl>

        <!-- EXACT 2-BLOCK (Workflow B) OR 3-BLOCK (Workflow A) SIGNATURE GRID -->
        <div class="space-y-2.5">
          <div class="flex items-center justify-between text-xs text-[#475569]">
            <span class="font-bold text-[#0F172A]">Lembar Pengesahan ({{ request.signatures.length }} Blok Tanda Tangan)</span>
            <span>Tanda tangan simulasi untuk keperluan presentasi</span>
          </div>
          <div
            :class="[
              'grid grid-cols-1 gap-3',
              request.signatures.length === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3',
            ]"
          >
            <div
              v-for="sig in request.signatures"
              :key="sig.stageOrder"
              class="rounded-xl border border-[#DCE4EE] bg-[#F8FAFC] p-3.5 text-center flex flex-col justify-between"
            >
              <div>
                <p class="text-xs font-bold text-[#1E3A5F]">{{ sig.roleLabel }}</p>
                <p class="text-xs text-[#475569]">{{ sig.organizationName }}</p>
              </div>
              <div class="my-3 h-14 flex items-center justify-center">
                <img
                  v-if="sig.signed && sig.signatureDataUrl"
                  :src="sig.signatureDataUrl"
                  :alt="`Tanda tangan simulasi ${sig.signerName}`"
                  class="h-12 object-contain"
                >
                <span v-else class="text-xs text-[#475569] italic">Belum ditandatangani</span>
              </div>
              <div>
                <p class="text-xs font-bold text-[#0F172A] underline">{{ sig.signerName }}</p>
                <p class="text-xs text-[#475569]">{{ sig.signerTitle }}</p>
              </div>
            </div>
          </div>
        </div>
      </article>

      <div class="flex justify-end gap-2.5 no-print">
        <button
          type="button"
          class="px-4 py-2 rounded-xl border border-[#DCE4EE] text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]"
          @click="emit('update:open', false)"
        >
          Tutup
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold"
          @click="printDocument"
        >
          <Printer class="w-4 h-4" aria-hidden="true" />
          <span>Cetak Surat (Print)</span>
        </button>
      </div>
    </div>
  </AppDialog>
</template>

