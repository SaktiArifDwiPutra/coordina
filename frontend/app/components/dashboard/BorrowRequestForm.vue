<script setup>
import { ref, computed, watch } from 'vue'
import { AlertTriangle, CheckCircle2, Info, Send } from 'lucide-vue-next'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'

const props = defineProps({
  facilities: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  profileComplete: { type: Boolean, default: false },
  isMpk: { type: Boolean, default: false },
  userOrgId: { type: Number, default: null },
  preselectedFacilityId: { type: [Number, String], default: '' },
  title: { type: String, default: 'Ajukan Peminjaman Fasilitas' },
  subtitle: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'go-profile'])

const form = ref({
  facility_id: '',
  date: '',
  start_time: '',
  end_time: '',
  reason: '',
  confirm_signature: props.profileComplete,
})

watch(
  () => props.preselectedFacilityId,
  (val) => {
    if (val) {
      form.value.facility_id = Number(val)
    }
  },
  { immediate: true }
)

watch(
  () => props.profileComplete,
  (complete) => {
    form.value.confirm_signature = Boolean(complete)
  }
)

const validationError = ref('')
const facilityIdField = useId()
const dateField = useId()
const startField = useId()
const endField = useId()
const reasonField = useId()
const signField = useId()

const detectedProcedure = computed(() => {
  if (!form.value.facility_id || !form.value.date || !form.value.start_time || !form.value.end_time) {
    return null
  }
  const facility = props.facilities.find((f) => Number(f.id) === Number(form.value.facility_id))
  if (!facility) return null

  const [y, m, d] = String(form.value.date).split('-').map(Number)
  const dayName = new Date(y, (m || 1) - 1, d || 1).toLocaleDateString('en-US', { weekday: 'long' })
  const reqStart = `${form.value.start_time}:00`
  const reqEnd = `${form.value.end_time}:00`

  const conflictingSchedule = (facility.fixed_schedules || []).find((fs) => {
    if (fs.day !== dayName) return false
    if (props.userOrgId && Number(fs.organization_id) === Number(props.userOrgId)) return false
    return fs.start_time < reqEnd && fs.end_time > reqStart
  })

  if (conflictingSchedule) {
    const ownerName = conflictingSchedule.organization?.name || 'Organisasi Pemilik'
    if (props.isMpk) {
      return {
        tone: 'blue',
        title: `Alur Resmi MPK · Jadwal Milik ${ownerName}`,
        text: `Surat dikirim langsung ke ${ownerName} untuk keputusan & tanda tangan pemilik (2 tanda tangan, tanpa tahap screening terpisah).`,
      }
    }
    return {
      tone: 'amber',
      title: `Bertepatan dengan Jadwal Rutin ${ownerName}`,
      text: `Pengajuan akan melewati 4 tahap resmi: TTD Anda → Pemeriksaan & TTD MPK → Keputusan & TTD ${ownerName} → Verifikasi PDF MPK.`,
    }
  }

  return {
    tone: 'emerald',
    title: 'Slot Kosong (Tidak Ada Pemilik Jadwal Tetap)',
    text: props.isMpk
      ? 'Tidak ada jadwal rutin yang bertabrakan. Surat izin resmi akan langsung diterbitkan setelah Anda konfirmasi tanda tangan MPK.'
      : 'Tidak ada jadwal rutin ekskul lain di jam ini. Pengajuan cukup melalui Pemeriksaan & TTD MPK (2 tanda tangan).',
  }
})

function handleSubmit() {
  validationError.value = ''
  if (!props.profileComplete) {
    validationError.value = 'Lengkapi profil dan gambar tanda tangan organisasi Anda terlebih dahulu sebelum mengirim pengajuan.'
    return
  }

  if (!form.value.confirm_signature) {
    validationError.value = 'Anda harus mencentang konfirmasi pembubuhan tanda tangan resmi organisasi.'
    return
  }

  if (form.value.start_time && form.value.end_time && form.value.end_time <= form.value.start_time) {
    validationError.value = 'Jam selesai harus lebih akhir daripada jam mulai.'
    return
  }

  emit('submit', { ...form.value }, () => {
    form.value = {
      facility_id: '',
      date: '',
      start_time: '',
      end_time: '',
      reason: '',
      confirm_signature: props.profileComplete,
    }
  })
}
</script>

<template>
  <section class="max-w-3xl mx-auto">
    <Card class="border-border shadow-sm p-6 sm:p-8 bg-white rounded-2xl space-y-6">
      <div class="pb-4 border-b border-border">
        <h2 class="text-xl font-bold text-slate-900">{{ title }}</h2>
        <p v-if="subtitle" class="text-sm text-slate-600 mt-1 leading-relaxed">{{ subtitle }}</p>
      </div>

      <div
        v-if="!profileComplete"
        class="p-4 rounded-xl bg-amber-50 border border-amber-300 text-sm text-amber-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        role="status"
      >
        <div class="flex items-start gap-2.5">
          <AlertTriangle class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
          <span>
            <strong>Profil tanda tangan belum lengkap.</strong> Lengkapi nama ketua, jabatan, dan gambar tanda tangan sebelum mengajukan surat peminjaman.
          </span>
        </div>
        <Button type="button" size="sm" variant="outline" class="border-amber-400 bg-white shrink-0" @click="emit('go-profile')">
          Lengkapi di Pengaturan
        </Button>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-6">
        <div class="space-y-4">
          <h3 class="text-sm font-bold text-[#1E3A5F]">1. Tentukan Tempat, Tanggal, dan Jam</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <Label :for="facilityIdField">Fasilitas yang Dipinjam</Label>
              <select
                :id="facilityIdField"
                v-model="form.facility_id"
                name="facility_id"
                required
                class="flex h-11 w-full rounded-xl border border-input bg-white px-3.5 text-sm text-slate-900"
              >
                <option value="" disabled>Pilih fasilitas…</option>
                <option v-for="fac in facilities" :key="fac.id" :value="fac.id">{{ fac.name }} ({{ fac.type }})</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label :for="dateField">Tanggal Pemakaian</Label>
              <Input :id="dateField" v-model="form.date" name="date" type="date" class="h-11 rounded-xl tabular-nums" required />
            </div>
            <div class="space-y-1.5">
              <Label :for="startField">Jam Mulai</Label>
              <Input :id="startField" v-model="form.start_time" name="start_time" type="time" class="h-11 rounded-xl tabular-nums" required />
            </div>
            <div class="space-y-1.5">
              <Label :for="endField">Jam Selesai</Label>
              <Input :id="endField" v-model="form.end_time" name="end_time" type="time" class="h-11 rounded-xl tabular-nums" required />
            </div>
          </div>

          <div
            v-if="detectedProcedure"
            :class="[
              'rounded-xl border p-4 text-sm flex items-start gap-2.5',
              detectedProcedure.tone === 'emerald'
                ? 'bg-emerald-50 border-emerald-200 text-emerald-950'
                : detectedProcedure.tone === 'amber'
                  ? 'bg-amber-50 border-amber-300 text-amber-950'
                  : 'bg-blue-50 border-blue-200 text-slate-900',
            ]"
            aria-live="polite"
          >
            <component
              :is="detectedProcedure.tone === 'emerald' ? CheckCircle2 : detectedProcedure.tone === 'amber' ? AlertTriangle : Info"
              class="w-5 h-5 shrink-0 mt-0.5"
              aria-hidden="true"
            />
            <div>
              <p class="font-bold">{{ detectedProcedure.title }}</p>
              <p class="text-xs mt-0.5 leading-relaxed">{{ detectedProcedure.text }}</p>
            </div>
          </div>
        </div>

        <div class="space-y-2 pt-4 border-t border-border">
          <h3 class="text-sm font-bold text-[#1E3A5F]">2. Tuliskan Alasan Peminjaman</h3>
          <Label :for="reasonField">Keperluan Resmi Kegiatan</Label>
          <textarea
            :id="reasonField"
            v-model="form.reason"
            name="reason"
            rows="3"
            placeholder="Contoh: Latihan gabungan atau kegiatan resmi sekolah…"
            required
            class="w-full rounded-xl border border-input bg-white p-3.5 text-sm text-slate-900 placeholder:text-slate-400"
          />
        </div>

        <div class="space-y-3 pt-4 border-t border-border">
          <h3 class="text-sm font-bold text-[#1E3A5F]">3. Konfirmasi Tanda Tangan Resmi (Tahap 1)</h3>
          <label
            :for="signField"
            :class="[
              'flex items-start gap-3 p-4 rounded-xl border text-sm leading-relaxed',
              profileComplete ? 'bg-slate-50 border-border text-slate-800 cursor-pointer' : 'bg-slate-100 border-border text-slate-400 cursor-not-allowed',
            ]"
          >
            <input
              :id="signField"
              v-model="form.confirm_signature"
              type="checkbox"
              :disabled="!profileComplete"
              class="mt-1 h-4 w-4 accent-[#1E3A5F]"
            >
            <span>
              <strong>Konfirmasi &amp; Bubuhkan Tanda Tangan Organisasi (Tahap 1):</strong>
              Saya menyatakan pengajuan ini resmi atas nama organisasi dan mengizinkan pembubuhan tanda tangan ketua ke surat perizinan.
            </span>
          </label>
        </div>

        <p v-if="validationError" role="alert" class="text-sm text-rose-700 font-semibold">
          {{ validationError }}
        </p>

        <Button
          :disabled="submitting || !profileComplete || !form.confirm_signature"
          type="submit"
          class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold"
        >
          <Send class="w-4 h-4" aria-hidden="true" />
          <span>{{ submitting ? 'Mengirim Pengajuan…' : 'Kirim Pengajuan Resmi' }}</span>
        </Button>
      </form>
    </Card>
  </section>
</template>
