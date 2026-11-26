<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { AlertTriangle, CheckCircle2, Info, Send } from 'lucide-vue-next'
import { detectScheduleConflict, isOrgProfileComplete } from '~/lib/workflow'
import type { BorrowRequestItem, DemoRole, Facility, FixedSchedule, OrganizationProfile } from '~/types/coordina'

const props = defineProps<{
  role: DemoRole
  activeOrg: OrganizationProfile
  organizations: OrganizationProfile[]
  facilities: Facility[]
  fixedSchedules: FixedSchedule[]
  requests: BorrowRequestItem[]
  preselectedFacilityId: number | null
}>()

const emit = defineEmits<{
  (
    e: 'submit',
    payload: {
      requesterOrgId: number
      facilityId: number
      date: string
      startTime: string
      endTime: string
      reason: string
      confirmSignature: boolean
    }
  ): void
  (e: 'go-profile'): void
}>()

const requesterId = ref<number>(props.activeOrg.id)
const facilityId = ref<number>(props.preselectedFacilityId || props.facilities[0]?.id || 1)
const date = ref('2026-11-27')
const startTime = ref('15:30')
const endTime = ref('17:00')
const reason = ref('')
const confirmSignature = ref(true)
const errorText = ref('')

watch(
  () => props.activeOrg.id,
  (id) => {
    requesterId.value = id
  }
)

watch(
  () => props.preselectedFacilityId,
  (val) => {
    if (val) facilityId.value = val
  }
)

const selectedRequester = computed(
  () => props.organizations.find((o) => o.id === requesterId.value) || props.activeOrg
)

const profileComplete = computed(() => isOrgProfileComplete(selectedRequester.value))

const conflictPreview = computed(() => {
  if (!facilityId.value || !date.value || !startTime.value || !endTime.value || endTime.value <= startTime.value) {
    return null
  }
  return detectScheduleConflict({
    facilityId: facilityId.value,
    requesterOrgId: requesterId.value,
    date: date.value,
    startTime: startTime.value,
    endTime: endTime.value,
    fixedSchedules: props.fixedSchedules,
    requests: props.requests,
    organizations: props.organizations,
  })
})

function onSubmit() {
  errorText.value = ''
  if (!profileComplete.value) {
    errorText.value = `Profil & tanda tangan ${selectedRequester.value.name} belum lengkap. Lengkapi terlebih dahulu di tab Profil & Tanda Tangan.`
    return
  }
  if (!startTime.value || !endTime.value || endTime.value <= startTime.value) {
    errorText.value = 'Jam selesai harus lebih akhir daripada jam mulai.'
    return
  }
  if (!reason.value.trim()) {
    errorText.value = 'Alasan keperluan wajib diisi.'
    return
  }
  if (conflictPreview.value?.blockedByBooking) {
    errorText.value = conflictPreview.value.noticeText
    return
  }
  emit('submit', {
    requesterOrgId: requesterId.value,
    facilityId: facilityId.value,
    date: date.value,
    startTime: startTime.value,
    endTime: endTime.value,
    reason: reason.value.trim(),
    confirmSignature: confirmSignature.value,
  })
  reason.value = ''
}

const fFacilityId = useId()
const fDateId = useId()
const fStartId = useId()
const fEndId = useId()
const fReasonId = useId()
const fSignId = useId()
</script>

<template>
  <section class="max-w-3xl mx-auto bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-6 sm:p-8 space-y-6">
    <div class="pb-4 border-b border-[#DCE4EE]">
      <h2 class="text-xl font-bold text-[#0F172A]">
        {{ role === 'mpk' ? 'Ajukan Peminjaman Resmi MPK (Alur B)' : 'Ajukan Peminjaman Fasilitas (Alur A / Slot Kosong)' }}
      </h2>
      <p class="text-sm text-[#475569] mt-1 leading-relaxed">
        {{
          role === 'mpk'
            ? 'Permintaan MPK dikirim langsung ke organisasi pemilik jadwal untuk persetujuan tanpa pemeriksaan ulang (total 2 blok tanda tangan).'
            : 'Permohonan akan otomatis mendeteksi jadwal ekskul pemilik, diperiksa MPK terlebih dahulu, lalu dimintakan izin pemilik.'
        }}
      </p>
    </div>

    <div
      v-if="!profileComplete"
      class="rounded-xl border border-[#FDE68A] bg-[#FFFBEB] p-4 text-sm text-[#B45309] flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      role="status"
    >
      <div class="flex items-start gap-2.5">
        <AlertTriangle class="w-5 h-5 shrink-0 mt-0.5" aria-hidden="true" />
        <span>
          <strong>Profil {{ selectedRequester.name }} belum lengkap.</strong> Lengkapi ketua, logo, dan tanda tangan sebelum mengajukan surat resmi.
        </span>
      </div>
      <button
        type="button"
        class="px-3.5 py-2 rounded-xl border border-[#FDE68A] bg-white text-xs font-bold text-[#0F172A] shrink-0"
        @click="emit('go-profile')"
      >
        Lengkapi Profil
      </button>
    </div>

    <form @submit.prevent="onSubmit" class="space-y-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-[#F8FAFC] border border-[#DCE4EE] p-4 text-sm">
        <div>
          <span class="text-xs font-semibold text-[#475569] block">Organisasi Pemohon</span>
          <strong class="text-[#0F172A]">{{ selectedRequester.name }}</strong>
        </div>
        <div>
          <span class="text-xs font-semibold text-[#475569] block">Ekskul Pemilik Jadwal Terdampak</span>
          <strong class="text-[#0F172A]">
            {{ conflictPreview?.overlappingOwnerOrg?.name || 'Otomatis dideteksi dari tanggal & jam' }}
          </strong>
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="text-sm font-bold text-[#1E3A5F]">1. Pilih Tempat, Tanggal &amp; Waktu</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1.5">
            <label :for="fFacilityId" class="block text-sm font-semibold text-[#0F172A]">Fasilitas</label>
            <select :id="fFacilityId" v-model.number="facilityId" class="w-full h-11 rounded-xl border border-[#DCE4EE] bg-white px-3.5 text-sm">
              <option v-for="fac in facilities" :key="fac.id" :value="fac.id">{{ fac.name }} ({{ fac.type }})</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label :for="fDateId" class="block text-sm font-semibold text-[#0F172A]">Tanggal Pemakaian</label>
            <input :id="fDateId" v-model="date" type="date" required class="w-full h-11 rounded-xl border border-[#DCE4EE] bg-white px-3.5 text-sm tabular-nums">
          </div>
          <div class="space-y-1.5">
            <label :for="fStartId" class="block text-sm font-semibold text-[#0F172A]">Jam Mulai</label>
            <input :id="fStartId" v-model="startTime" type="time" required class="w-full h-11 rounded-xl border border-[#DCE4EE] bg-white px-3.5 text-sm tabular-nums">
          </div>
          <div class="space-y-1.5">
            <label :for="fEndId" class="block text-sm font-semibold text-[#0F172A]">Jam Selesai</label>
            <input :id="fEndId" v-model="endTime" type="time" required class="w-full h-11 rounded-xl border border-[#DCE4EE] bg-white px-3.5 text-sm tabular-nums">
          </div>
        </div>

        <!-- LIVE CONFLICT DETECTION NOTICE -->
        <div
          v-if="conflictPreview"
          aria-live="polite"
          :class="[
            'rounded-xl border p-4 text-sm flex items-start gap-2.5',
            conflictPreview.noticeTone === 'rose'
              ? 'bg-[#FFF1F2] border-[#FECDD3] text-[#BE123C]'
              : conflictPreview.noticeTone === 'amber'
                ? 'bg-[#FFFBEB] border-[#FDE68A] text-[#B45309]'
                : conflictPreview.noticeTone === 'emerald'
                  ? 'bg-[#ECFDF5] border-[#A7F3D0] text-[#15803D]'
                  : 'bg-[#EFF6FF] border-[#BFDBFE] text-[#1E3A5F]',
          ]"
        >
          <component
            :is="conflictPreview.noticeTone === 'emerald' ? CheckCircle2 : conflictPreview.noticeTone === 'rose' || conflictPreview.noticeTone === 'amber' ? AlertTriangle : Info"
            class="w-5 h-5 shrink-0 mt-0.5"
            aria-hidden="true"
          />
          <div>
            <p class="font-bold">{{ conflictPreview.noticeTitle }}</p>
            <p class="text-xs mt-0.5 leading-relaxed">{{ conflictPreview.noticeText }}</p>
          </div>
        </div>
      </div>

      <div class="space-y-2 pt-4 border-t border-[#DCE4EE]">
        <h3 class="text-sm font-bold text-[#1E3A5F]">2. Alasan Peminjaman</h3>
        <label :for="fReasonId" class="block text-sm font-semibold text-[#0F172A]">Keperluan Kegiatan</label>
        <textarea
          :id="fReasonId"
          v-model="reason"
          rows="3"
          required
          placeholder="Contoh: Latihan gabungan atau kegiatan resmi sekolah…"
          class="w-full rounded-xl border border-[#DCE4EE] bg-white p-3.5 text-sm text-[#0F172A]"
        />
      </div>

      <div class="space-y-3 pt-4 border-t border-[#DCE4EE]">
        <h3 class="text-sm font-bold text-[#1E3A5F]">3. Tanda Tangan Simulasi Pemohon (Tahap 1)</h3>
        <label
          :for="fSignId"
          class="flex items-start gap-3 rounded-xl border border-[#DCE4EE] bg-[#F8FAFC] p-4 text-sm text-[#0F172A] cursor-pointer"
        >
          <input :id="fSignId" v-model="confirmSignature" type="checkbox" class="mt-1 h-4 w-4 accent-[#1E3A5F]">
          <span>
            <strong>Konfirmasi Tanda Tangan Simulasi:</strong> Pasang gambar tanda tangan <strong>{{ selectedRequester.chairpersonName }}</strong> pada surat peminjaman (simulasi demo).
          </span>
        </label>
      </div>

      <p v-if="errorText" role="alert" class="text-sm font-semibold text-[#BE123C]">{{ errorText }}</p>

      <button
        type="submit"
        :disabled="Boolean(conflictPreview?.blockedByBooking) || !profileComplete || !confirmSignature"
        class="w-full h-11 inline-flex items-center justify-center gap-2 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] disabled:opacity-50 text-white text-sm font-semibold shadow-sm transition-colors"
      >
        <Send class="w-4 h-4" aria-hidden="true" />
        <span>Kirim Pengajuan Resmi</span>
      </button>
    </form>
  </section>
</template>

