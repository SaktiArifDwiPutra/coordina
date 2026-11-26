<script setup>
import { ref } from 'vue'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'

const props = defineProps({
  facilities: { type: Array, default: () => [] },
  submitting: { type: Boolean, default: false },
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
})

const validationError = ref('')

function handleSubmit() {
  validationError.value = ''
  if (form.value.start_time && form.value.end_time && form.value.end_time <= form.value.start_time) {
    validationError.value = 'Jam selesai harus lebih akhir daripada jam mulai.'
    return
  }

  emit('submit', { ...form.value }, () => {
    form.value = { facility_id: '', date: '', start_time: '', end_time: '', reason: '' }
  })
}
</script>

<template>
  <div>
    <h2 class="text-xl font-bold text-zinc-900 mb-1">{{ title }}</h2>
    <p v-if="subtitle" class="text-sm text-zinc-500 mb-4">{{ subtitle }}</p>
    <Card class="border-zinc-200 shadow-sm p-6 max-w-2xl mt-3">
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
        <p v-if="validationError" class="text-sm text-red-600 font-medium">{{ validationError }}</p>
        <Button :disabled="submitting" type="submit" class="w-full bg-zinc-900 text-white">
          {{ submitting ? 'Mengirim...' : 'Kirim Pengajuan' }}
        </Button>
      </form>
    </Card>
  </div>
</template>

