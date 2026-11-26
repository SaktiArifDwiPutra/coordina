<script setup>
import { ref, onMounted } from 'vue'
import { Card, CardHeader, CardTitle, CardContent } from '~/components/ui/card'
import { Button } from '~/components/ui/button'

const props = defineProps({
  facilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  isMpk: { type: Boolean, default: false },
})

const emit = defineEmits(['delete-facility', 'delete-schedule'])

const days = [
  { key: 'Monday', label: 'Senin' },
  { key: 'Tuesday', label: 'Selasa' },
  { key: 'Wednesday', label: 'Rabu' },
  { key: 'Thursday', label: 'Kamis' },
  { key: 'Friday', label: 'Jumat' },
  { key: 'Saturday', label: 'Sabtu' },
  { key: 'Sunday', label: 'Minggu' },
]

const activeDay = ref('Monday')

onMounted(() => {
  const today = new Date().toLocaleDateString('en-US', { weekday: 'long' })
  if (days.some((d) => d.key === today)) {
    activeDay.value = today
  }
})

function getDayName(dateString) {
  const [year, month, day] = dateString.split('-')
  return new Date(year, month - 1, day).toLocaleDateString('en-US', { weekday: 'long' })
}

function getOverridesForSchedule(facility, day, startTime, endTime) {
  if (!facility.borrow_requests) return []
  return facility.borrow_requests.filter((req) => {
    return getDayName(req.date) === day && req.start_time < endTime && req.end_time > startTime
  })
}

function getPureBorrowRequests(facility, day) {
  if (!facility.borrow_requests) return []
  return facility.borrow_requests.filter((req) => {
    return getDayName(req.date) === day && !req.owner_organization_id
  })
}

function hasAnySchedule(facility, day) {
  const fixed = facility.fixed_schedules?.filter((s) => s.day === day) || []
  const overrides = facility.borrow_requests?.filter((req) => getDayName(req.date) === day) || []
  return fixed.length > 0 || overrides.length > 0
}
</script>

<template>
  <div>
    <h2 class="text-xl font-bold text-zinc-900 mb-4">Daftar Fasilitas & Jadwal</h2>
    <div class="flex space-x-6 mb-6 border-b border-zinc-200 overflow-x-auto scrollbar-hide">
      <button
        v-for="d in days"
        :key="d.key"
        @click="activeDay = d.key"
        :class="[
          'whitespace-nowrap pb-3 text-sm font-medium transition-colors relative top-[1px]',
          activeDay === d.key
            ? 'border-b-2 border-indigo-600 text-indigo-900'
            : 'text-zinc-500 hover:text-zinc-800 border-b-2 border-transparent',
        ]"
      >
        {{ d.label }}
      </button>
    </div>

    <div v-if="loading" class="text-zinc-500">Memuat data fasilitas...</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card v-for="facility in facilities" :key="facility.id" class="border-zinc-200 shadow-sm rounded-xl overflow-hidden flex flex-col">
        <CardHeader class="bg-white pb-3 border-b border-zinc-100 flex flex-row justify-between items-start gap-2">
          <div>
            <CardTitle class="text-lg">{{ facility.name }}</CardTitle>
            <span class="text-xs px-2 py-0.5 bg-zinc-100 text-zinc-600 rounded-md mt-1 inline-block">{{ facility.type }}</span>
            <p v-if="facility.description" class="text-xs text-zinc-500 mt-1">{{ facility.description }}</p>
          </div>
          <Button
            v-if="isMpk"
            @click="emit('delete-facility', facility.id)"
            variant="outline"
            size="sm"
            class="text-red-600 border-red-100 hover:bg-red-50 p-2 h-8 shrink-0"
          >
            Hapus
          </Button>
        </CardHeader>
        <CardContent class="pt-4 bg-zinc-50/50 flex-1 space-y-3">
          <template v-for="fs in facility.fixed_schedules?.filter((s) => s.day === activeDay)" :key="'fs-' + fs.id">
            <div v-if="getOverridesForSchedule(facility, activeDay, fs.start_time, fs.end_time).length > 0" class="space-y-2">
              <div class="text-xs text-zinc-400 line-through">
                {{ fs.start_time.substring(0, 5) }} - {{ fs.end_time.substring(0, 5) }} ({{ fs.organization?.name }})
              </div>
              <div
                v-for="override in getOverridesForSchedule(facility, activeDay, fs.start_time, fs.end_time)"
                :key="'over-' + override.id"
                class="p-3 bg-amber-50 border border-amber-200 rounded-lg shadow-sm"
              >
                <div class="flex justify-between items-center text-xs font-bold text-amber-800 mb-1">
                  <span>🔄 Diambil Alih Sementara</span>
                  <span class="px-1.5 py-0.5 bg-amber-100 rounded">{{ override.date }}</span>
                </div>
                <div class="text-sm font-bold text-zinc-900">
                  {{ override.start_time.substring(0, 5) }} - {{ override.end_time.substring(0, 5) }} | {{ override.organization?.name }}
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-zinc-700 bg-white p-3 rounded-lg border border-zinc-200 shadow-sm flex justify-between items-center">
              <div>
                <span class="font-bold text-zinc-900">{{ fs.start_time.substring(0, 5) }} - {{ fs.end_time.substring(0, 5) }}</span><br />
                <span class="text-xs text-zinc-500">Pemilik: {{ fs.organization?.name }}</span>
              </div>
              <button
                v-if="isMpk"
                @click="emit('delete-schedule', fs.id)"
                class="text-red-500 hover:text-red-700 text-xs font-medium"
              >
                Hapus
              </button>
            </div>
          </template>

          <template v-for="req in getPureBorrowRequests(facility, activeDay)" :key="'req-' + req.id">
            <div class="p-3 bg-green-50 border border-green-200 rounded-lg shadow-sm">
              <div class="flex justify-between items-center text-xs font-bold text-green-800 mb-1">
                <span>✅ Peminjaman Ekstra</span>
                <span class="px-1.5 py-0.5 bg-green-100 rounded">{{ req.date }}</span>
              </div>
              <div class="text-sm font-bold text-zinc-900">
                {{ req.start_time.substring(0, 5) }} - {{ req.end_time.substring(0, 5) }} | {{ req.organization?.name }}
              </div>
            </div>
          </template>

          <div
            v-if="!hasAnySchedule(facility, activeDay)"
            class="text-sm text-zinc-400 text-center py-4 border-2 border-dashed border-zinc-200 rounded-lg"
          >
            Bebas Digunakan
          </div>
        </CardContent>
      </Card>
    </div>
  </div>
</template>

