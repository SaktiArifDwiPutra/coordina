<script setup lang="ts">
import { ref, computed } from 'vue'
import { FileCheck2, FileText } from 'lucide-vue-next'
import StatusBadge from '~/components/StatusBadge.vue'
import { formatDateId, formatTimeRange, workflowTitle } from '~/lib/workflow'
import type { BorrowRequestItem, Facility, OrganizationProfile } from '~/types/coordina'

const props = defineProps<{
  documents: BorrowRequestItem[]
  organizations: OrganizationProfile[]
  facilities: Facility[]
}>()

const emit = defineEmits<{
  (e: 'preview', req: BorrowRequestItem): void
}>()

const searchQuery = ref('')
const workflowFilter = ref<'all' | 'inter_eskul' | 'mpk_borrow' | 'free_slot'>('all')
const searchId = useId()
const selectId = useId()

function orgName(id: number | null) {
  if (!id) return null
  return props.organizations.find((o) => o.id === id)?.name || '-'
}

function facilityName(id: number) {
  return props.facilities.find((f) => f.id === id)?.name || '-'
}

const filtered = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  return props.documents.filter((doc) => {
    if (workflowFilter.value !== 'all' && doc.workflowType !== workflowFilter.value) return false
    if (!q) return true
    const text = [doc.letterNumber, orgName(doc.requesterOrgId), orgName(doc.ownerOrgId), facilityName(doc.facilityId), doc.reason]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()
    return text.includes(q)
  })
})
</script>

<template>
  <section class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-5 sm:p-6 space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-[#DCE4EE]">
      <div>
        <h2 class="text-lg font-bold text-[#0F172A]">Arsip Surat Resmi</h2>
        <p class="text-sm text-[#475569] mt-0.5">
          Kumpulan surat izin yang telah disetujui lengkap dengan nomor surat dan pratinjau dokumen siap cetak.
        </p>
      </div>
      <search class="flex flex-wrap items-center gap-2.5">
        <label :for="searchId" class="sr-only">Cari arsip surat</label>
        <input
          :id="searchId"
          v-model="searchQuery"
          type="search"
          placeholder="Cari nomor surat, fasilitas, atau ekskul…"
          class="h-10 rounded-xl border border-[#DCE4EE] bg-[#F8FAFC] px-3.5 text-sm w-full sm:w-64"
        >
        <label :for="selectId" class="sr-only">Filter alur surat</label>
        <select :id="selectId" v-model="workflowFilter" class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
          <option value="all">Semua Alur Surat</option>
          <option value="inter_eskul">Alur A (3 TTD)</option>
          <option value="mpk_borrow">Alur B MPK (2 TTD)</option>
          <option value="free_slot">Slot Kosong</option>
        </select>
      </search>
    </div>

    <div v-if="filtered.length === 0" class="py-10 text-center space-y-2">
      <FileCheck2 class="w-8 h-8 text-[#475569] mx-auto" aria-hidden="true" />
      <p class="text-sm font-medium text-[#475569]">Belum ada surat resmi yang cocok dengan filter Anda.</p>
    </div>

    <div v-else class="space-y-3">
      <article
        v-for="doc in filtered"
        :key="doc.id"
        class="rounded-xl border border-[#DCE4EE] bg-[#F8FAFC] p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4"
      >
        <div class="space-y-1.5 min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-2.5 py-0.5 rounded-lg bg-[#EFF6FF] border border-[#BFDBFE] text-xs font-bold text-[#1E3A5F] tabular-nums">
              {{ doc.letterNumber }}
            </span>
            <StatusBadge label="Disetujui" tone="emerald" icon="check" />
            <span class="px-2.5 py-0.5 rounded-lg bg-white border border-[#DCE4EE] text-xs font-semibold text-[#475569]">
              {{ workflowTitle(doc) }}
            </span>
          </div>

          <h3 class="text-base font-bold text-[#0F172A]">
            {{ facilityName(doc.facilityId) }} ·
            <span class="font-semibold text-[#475569] tabular-nums">
              {{ formatDateId(doc.date) }} ({{ formatTimeRange(doc.startTime, doc.endTime) }})
            </span>
          </h3>

          <p class="text-sm text-[#475569]">
            Pemohon: <strong class="text-[#0F172A]">{{ orgName(doc.requesterOrgId) }}</strong>
            <template v-if="doc.ownerOrgId">
              · Pemilik Jadwal: <strong class="text-[#0F172A]">{{ orgName(doc.ownerOrgId) }}</strong>
            </template>
          </p>
          <p class="text-xs text-[#475569]">{{ doc.reason }}</p>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold shrink-0"
          @click="emit('preview', doc)"
        >
          <FileText class="w-4 h-4" aria-hidden="true" />
          <span>Pratinjau &amp; Cetak Surat</span>
        </button>
      </article>
    </div>
  </section>
</template>

