<template>
  <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 md:flex-row md:items-center md:justify-between">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Arsip Surat Perizinan Resmi</h2>
        <p class="text-xs text-slate-500">
          Dokumen final yang telah diverifikasi dan bernomor surat resmi. PDF final bersifat permanen.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nomor surat, fasilitas, alasan..."
          class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs text-slate-700 focus:border-indigo-500 focus:outline-none"
        >
        <select
          v-model="workflowFilter"
          class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 focus:border-indigo-500 focus:outline-none"
        >
          <option value="all">Semua Alur</option>
          <option value="inter_eskul">Antar-Ekskul (3 TTD)</option>
          <option value="mpk_borrow">MPK Meminjam (2 TTD)</option>
          <option value="free_slot">Slot Kosong</option>
        </select>
        <button
          type="button"
          class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
          :disabled="loading"
          @click="$emit('refresh')"
        >
          {{ loading ? 'Memuat...' : 'Muat Ulang' }}
        </button>
      </div>
    </div>

    <div v-if="loading && items.length === 0" class="py-10 text-center text-sm text-slate-500">
      Memuat arsip dokumen resmi...
    </div>

    <div v-else-if="filteredItems.length === 0" class="py-10 text-center text-sm text-slate-500">
      Belum ada dokumen perizinan yang difinalisasi sesuai filter.
    </div>

    <div v-else class="mt-4 space-y-3">
      <article
        v-for="item in filteredItems"
        :key="item.id"
        class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 transition hover:border-slate-300"
      >
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div class="space-y-1.5">
            <div class="flex flex-wrap items-center gap-2">
              <span class="rounded-lg bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700">
                {{ item.letter_number || `#${item.id}` }}
              </span>
              <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700">
                {{ workflowLabel(item.workflow_type) }}
              </span>
              <span
                class="rounded-lg px-2 py-0.5 text-[11px] font-semibold"
                :class="item.document?.final_pdf_path ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'"
              >
                {{ item.document?.final_pdf_path ? 'PDF Resmi Siap' : 'PDF Belum Terkonversi' }}
              </span>
              <span
                v-if="(item.document?.current_revision || 1) > 1"
                class="rounded-lg bg-sky-50 px-2 py-0.5 text-[11px] font-medium text-sky-700"
              >
                Revisi v{{ item.document.current_revision }}
              </span>
            </div>

            <h3 class="text-sm font-semibold text-slate-900">
              {{ item.facility?.name || '-' }} — {{ item.date }} ({{ formatTime(item.start_time) }} - {{ formatTime(item.end_time) }} WIB)
            </h3>

            <p class="text-xs text-slate-600">
              Pemohon: <strong class="text-slate-800">{{ item.organization?.name || '-' }}</strong>
              <template v-if="item.owner_organization">
                → Pemilik Jadwal: <strong class="text-slate-800">{{ item.owner_organization.name }}</strong>
              </template>
            </p>

            <p class="text-xs text-slate-700">{{ item.reason }}</p>

            <div class="flex flex-wrap items-center gap-3 pt-1 text-[11px] text-slate-500">
              <span v-if="item.letter_date">Tanggal Surat: {{ item.letter_date }}</span>
              <span v-if="item.document?.template">
                Template: {{ item.document.template.name }} (v{{ item.document.template.version }})
              </span>
              <span v-if="item.document?.final_pdf_sha256" class="font-mono">
                SHA-256: {{ item.document.final_pdf_sha256.slice(0, 12) }}…
              </span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 md:shrink-0">
            <button
              v-if="item.document?.final_pdf_path"
              type="button"
              class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
              :disabled="downloadingId === item.id"
              @click="$emit('download-pdf', item, true)"
            >
              Pratinjau / Cetak PDF
            </button>
            <button
              v-if="item.document?.final_pdf_path"
              type="button"
              class="rounded-xl border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
              :disabled="downloadingId === item.id"
              @click="$emit('download-pdf', item, false)"
            >
              Unduh PDF
            </button>
            <button
              v-if="!item.document?.final_pdf_path && isMpk"
              type="button"
              class="rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-50"
              :disabled="regeneratingId === item.id"
              @click="$emit('regenerate-pdf', item)"
            >
              {{ regeneratingId === item.id ? 'Memproses...' : 'Generate PDF' }}
            </button>
            <button
              v-if="item.document?.merged_docx_path"
              type="button"
              class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50"
              :disabled="downloadingId === item.id"
              @click="$emit('download-docx', item)"
            >
              Unduh DOCX
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup>
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

function workflowLabel(type) {
  if (type === 'inter_eskul') return 'Antar-Ekskul (3 TTD)'
  if (type === 'mpk_borrow') return 'MPK Meminjam (2 TTD)'
  return 'Slot Kosong'
}

function formatTime(time) {
  return String(time || '').slice(0, 5)
}
</script>

