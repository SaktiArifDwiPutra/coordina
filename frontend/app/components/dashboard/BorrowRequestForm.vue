<script setup>
import { ref, watch } from 'vue'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'

const props = defineProps({
  facilities: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
  profileComplete: { type: Boolean, default: false },
  title: { type: String, default: 'Ajukan Peminjaman' },
  subtitle: { type: String, default: '' },
})

const emit = defineEmits(['submit'])

const form = ref({
  facility_id: '',
  date: '',
  start_time: '',
  end_time: '',
  reason: '',
  confirm_signature: props.profileComplete,
})

watch(
  () => props.profileComplete,
  (complete) => {
    form.value.confirm_signature = Boolean(complete)
  }
)

const validationError = ref('')

function scrollToProfile() {
  document.getElementById('organization-profile')?.scrollIntoView({ behavior: 'smooth' })
}

function handleSubmit() {
  validationError.value = ''
  if (!props.profileComplete) {
    validationError.value = 'Lengkapi profil & gambar tanda tangan organisasi Anda terlebih dahulu sebelum mengajukan peminjaman.'
    return
  }

  if (!form.value.confirm_signature) {
    validationError.value = 'Anda harus mengonfirmasi pembubuhan tanda tangan organisasi pada surat perizinan.'
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
  <div>
    <h2 class="text-xl font-bold text-zinc-900 mb-1">{{ title }}</h2>
    <p v-if="subtitle" class="text-sm text-zinc-500 mb-4">{{ subtitle }}</p>
    <Card class="border-zinc-200 shadow-sm p-6 max-w-2xl mt-3">
      <div
        v-if="!profileComplete"
        class="mb-4 p-3.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
      >
        <span>
          <strong>Profil tanda tangan belum lengkap.</strong> Lengkapi nama ketua, jabatan, dan gambar tanda tangan sebelum mengajukan surat peminjaman.
        </span>
        <button type="button" class="font-semibold underline shrink-0 text-left" @click="scrollToProfile">
          Lengkapi Profil Organisasi →
        </button>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-2">
            <Label>Fasilitas</Label>
            <select
              v-model="form.facility_id"
              required
              class="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 text-sm"
            >
              <option value="" disabled>Pilih Fasilitas...</option>
              <option v-for="fac in facilities" :key="fac.id" :value="fac.id">{{ fac.name }}</option>
            </select>
          </div>
          <div class="space-y-2">
            <Label>Tanggal</Label>
            <Input v-model="form.date" type="date" required />
          </div>
          <div class="space-y-2">
            <Label>Jam Mulai</Label>
            <Input v-model="form.start_time" type="time" required />
          </div>
          <div class="space-y-2">
            <Label>Jam Selesai</Label>
            <Input v-model="form.end_time" type="time" required />
          </div>
        </div>
        <div class="space-y-2">
          <Label>Alasan Keperluan</Label>
          <Input v-model="form.reason" type="text" placeholder="Contoh: Latihan gabungan / acara resmi sekolah..." required />
        </div>
        <label
          :class="[
            'flex items-start gap-2.5 p-3 rounded-lg border text-xs',
            profileComplete ? 'bg-zinc-50 border-zinc-200 text-zinc-700 cursor-pointer' : 'bg-zinc-100 border-zinc-200 text-zinc-400 cursor-not-allowed',
          ]"
        >
          <input v-model="form.confirm_signature" type="checkbox" :disabled="!profileComplete" class="mt-0.5" />
          <span>
            <strong>Konfirmasi & Bubuhkan Tanda Tangan Organisasi (Tahap 1):</strong>
            Saya menyatakan pengajuan ini resmi atas nama organisasi dan mengizinkan pembubuhan tanda tangan ketua ke surat perizinan.
          </span>
        </label>
        <p v-if="validationError" class="text-sm text-red-600 font-medium">{{ validationError }}</p>
        <Button :disabled="submitting || !profileComplete || !form.confirm_signature" type="submit" class="w-full bg-zinc-900 text-white">
          {{ submitting ? 'Mengirim...' : 'Kirim Pengajuan' }}
        </Button>
      </form>
    </Card>
  </div>
</template>
