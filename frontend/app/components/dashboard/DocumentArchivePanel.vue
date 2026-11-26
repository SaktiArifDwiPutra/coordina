<script setup>
import { ref, computed } from 'vue'
import { Printer, Download, RefreshCw, FileText, FileCheck2 } from 'lucide-vue-next'
import StatusBadge from '~/components/dashboard/StatusBadge.vue'
import { Button } from '~/components/ui/button'
import { formatDateId, formatTimeRange, workflowTypeLabel } from '~/lib/workflow'

const props = defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  isMpk: { type: Boolean, default: false },
  downloadingId: { type: Number, default: null },
  regeneratingId: { type: Number, default: null },
})

defineEmits(['refresh', 'download-pdf', 'download-docx', 'regenerate-pdf'])

const searchQuery = ref('')
const workflowFilter = ref('all')
const searchInputId = useId()
const workflowSelectId = useId()

const filteredItems = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return props.items.filter((item) => {
    if (workflowFilter.value !== 'all' && item.workflow_type !== workflowFilter.value) {
      return false
    }
    if (!q) return true
    const haystack = [
      item.letter_number,
      item.reason,
      item.facility?.name,
      item.organization?.name,
      item.owner_organization?.name,
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return haystack.includes(q)
  })
})
</script>

<template>
  <section class="rounded-2xl border border-border bg-white p-5 sm:p-6 shadow-sm space-y-5">
    <div class="flex flex-col gap-4 border-b border-border pb-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Arsip Surat Perizinan Resmi</h2>
        <p class="text-sm text-slate-600 mt-0.5">
          Kumpulan dokumen final yang sudah bernomor surat resmi dan siap dicetak atau diunduh.
        </p>
      </div>
      <search class="flex flex-wrap items-center gap-2.5">
        <label :for="searchInputId" class="sr-only">Cari arsip surat</label>
        <input
          :id="searchInputId"
          v-model="searchQuery"
          type="search"
          placeholder="Cari nomor surat, fasilitas, atau alasan…"
          class="h-10 rounded-xl border border-input bg-background px-3.5 text-sm text-foreground w-full sm:w-64"
        >
        <label :for="workflowSelectId" class="sr-only">Filter jenis alur surat</label>
        <select
          :id="workflowSelectId"
          v-model="workflowFilter"
          class="h-10 rounded-xl border border-input bg-white px-3 text-sm text-slate-800"
        >
          <option value="all">Semua Alur Surat</option>
          <option value="inter_eskul">Antar-Ekskul (3 TTD)</option>
          <option value="mpk_borrow">Peminjaman Resmi MPK (2 TTD)</option>
          <option value="free_slot">Slot Kosong</option>
        </select>
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="h-10"
          :disabled="loading"
          @click="$emit('refresh')"
        >
          <RefreshCw class="w-4 h-4" aria-hidden="true" />
          <span>{{ loading ? 'Memuat…' : 'Muat Ulang' }}</span>
        </Button>
      </search>
    </div>

    <div v-if="loading && items.length === 0" class="py-10 text-center text-sm text-slate-500">
      Memuat arsip dokumen resmi…
    </div>

    <div v-else-if="filteredItems.length === 0" class="py-10 text-center space-y-2">
      <FileCheck2 class="w-8 h-8 text-slate-400 mx-auto" aria-hidden="true" />
      <p class="text-sm font-medium text-slate-600">Belum ada surat resmi yang cocok dengan pencarian Anda.</p>
    </div>

    <div v-else class="space-y-3">
      <article
        v-for="item in filteredItems"
        :key="item.id"
        class="rounded-xl border border-border bg-slate-50/70 p-4 sm:p-5 transition-colors hover:border-slate-300"
      >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="space-y-1.5 min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-lg bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-bold text-[#1E3A5F] tabular-nums">
                {{ item.letter_number || `#${item.id}` }}
              </span>
              <span class="rounded-lg bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                {{ workflowTypeLabel(item) }}
              </span>
              <StatusBadge
                :label="item.document?.final_pdf_path ? 'PDF Resmi Siap' : 'PDF Belum Terkonversi'"
                :tone="item.document?.final_pdf_path ? 'emerald' : 'amber'"
                :icon="item.document?.final_pdf_path ? 'check' : 'alert'"
              />
              <span
                v-if="(item.document?.current_revision || 1) > 1"
                class="rounded-lg bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-800"
              >
                Revisi ke-{{ item.document.current_revision }}
              </span>
            </div>

            <h3 class="text-base font-bold text-slate-900">
              {{ item.facility?.name || '-' }} · <span class="font-semibold text-slate-700 tabular-nums">{{ formatDateId(item.date) }} ({{ formatTimeRange(item.start_time, item.end_time) }})</span>
            </h3>

            <p class="text-sm text-slate-700">
              Pemohon: <strong class="text-slate-900">{{ item.organization?.name || '-' }}</strong>
              <template v-if="item.owner_organization">
                · Pemilik Jadwal: <strong class="text-slate-900">{{ item.owner_organization.name }}</strong>
              </template>
            </p>

            <p class="text-xs text-slate-600 leading-relaxed">{{ item.reason }}</p>

            <p v-if="item.letter_date || item.document?.template" class="text-xs text-slate-500 pt-1 tabular-nums">
              <span v-if="item.letter_date">Tanggal Surat: {{ item.letter_date }}</span>
              <span v-if="item.letter_date && item.document?.template"> · </span>
              <span v-if="item.document?.template">Template: {{ item.document.template.name }} (v{{ item.document.template.version }})</span>
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <Button
              v-if="item.document?.final_pdf_path"
              type="button"
              class="bg-[#1E3A5F] hover:bg-[#152943] text-white"
              size="sm"
              :disabled="downloadingId === item.id"
              @click="$emit('download-pdf', item, true)"
            >
              <Printer class="w-4 h-4" aria-hidden="true" />
              <span>Buka / Cetak PDF</span>
            </Button>
            <Button
              v-if="item.document?.final_pdf_path"
              type="button"
              variant="outline"
              size="sm"
              :disabled="downloadingId === item.id"
              @click="$emit('download-pdf', item, false)"
            >
              <Download class="w-4 h-4" aria-hidden="true" />
              <span>Unduh PDF</span>
            </Button>
            <Button
              v-if="!item.document?.final_pdf_path && isMpk"
              type="button"
              size="sm"
              class="bg-amber-600 hover:bg-amber-700 text-white"
              :disabled="regeneratingId === item.id"
              @click="$emit('regenerate-pdf', item)"
            >
              <RefreshCw class="w-4 h-4" aria-hidden="true" />
              <span>{{ regeneratingId === item.id ? 'Memproses…' : 'Generate Ulang PDF' }}</span>
            </Button>
            <Button
              v-if="item.document?.merged_docx_path"
              type="button"
              variant="outline"
              size="sm"
              :disabled="downloadingId === item.id"
              @click="$emit('download-docx', item)"
            >
              <FileText class="w-4 h-4" aria-hidden="true" />
              <span>Unduh DOCX</span>
            </Button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
