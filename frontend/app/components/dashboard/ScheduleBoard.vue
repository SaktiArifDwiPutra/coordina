<script setup>
import { ref, onMounted } from 'vue'
import { Calendar, Clock, Trash2, ArrowRightLeft, PlusCircle, CheckCircle2, Building2 } from 'lucide-vue-next'
import { Card, CardHeader, CardTitle, CardContent } from '~/components/ui/card'
import { Button } from '~/components/ui/button'
import StatusBadge from '~/components/dashboard/StatusBadge.vue'
import { formatTimeRange } from '~/lib/workflow'

const props = defineProps({
  facilities: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  isMpk: { type: Boolean, default: false },
})

const emit = defineEmits(['delete-facility', 'delete-schedule', 'borrow-facility'])

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
  <section class="space-y-5">
    <div class="bg-white rounded-2xl border border-border shadow-sm p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Jadwal Fasilitas &amp; Kepemilikan Harian</h2>
        <p class="text-sm text-slate-600 mt-0.5">
          Pilih hari untuk memeriksa ekskul pemilik jadwal tetap. Klik tombol peminjaman untuk diarahkan ke formulir pengajuan resmi.
        </p>
      </div>

      <div class="grid grid-cols-4 sm:flex sm:flex-wrap gap-1.5 bg-slate-100 p-1 rounded-xl border border-border" role="tablist" aria-label="Pilih hari">
        <button
          v-for="d in days"
          :key="d.key"
          type="button"
          role="tab"
          :aria-selected="activeDay === d.key"
          :class="[
            'whitespace-nowrap px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors',
            activeDay === d.key
              ? 'bg-[#1E3A5F] text-white shadow-sm'
              : 'text-slate-700 hover:bg-white hover:text-slate-900',
          ]"
          @click="activeDay = d.key"
        >
          {{ d.label }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="bg-white rounded-2xl border border-border p-8 text-center text-sm text-slate-500">
      Memuat daftar fasilitas dan jadwal…
    </div>

    <div v-else-if="facilities.length === 0" class="bg-white rounded-2xl border border-border p-8 text-center text-sm text-slate-500">
      Belum ada fasilitas yang didaftarkan oleh MPK.
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <Card
        v-for="facility in facilities"
        :key="facility.id"
        class="border-border shadow-sm rounded-2xl overflow-hidden flex flex-col bg-white"
      >
        <CardHeader class="bg-white pb-3.5 border-b border-border flex flex-row justify-between items-start gap-2">
          <div class="space-y-1 min-w-0">
            <CardTitle class="text-base font-bold text-slate-900 flex items-center gap-2">
              <Building2 class="w-4 h-4 text-[#1E3A5F] shrink-0" aria-hidden="true" />
              <span class="truncate">{{ facility.name }}</span>
            </CardTitle>
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-xs px-2.5 py-0.5 bg-slate-100 text-slate-700 rounded-md font-medium">
                {{ facility.type }}
              </span>
              <StatusBadge
                v-if="!hasAnySchedule(facility, activeDay)"
                label="Tersedia Bebas"
                tone="emerald"
                icon="check"
              />
            </div>
            <p v-if="facility.description" class="text-xs text-slate-500 pt-0.5">{{ facility.description }}</p>
          </div>
          <Button
            v-if="isMpk"
            type="button"
            variant="outline"
            size="sm"
            class="text-rose-700 border-rose-200 hover:bg-rose-50 shrink-0"
            :aria-label="`Hapus fasilitas ${facility.name}`"
            @click="emit('delete-facility', facility.id)"
          >
            <Trash2 class="w-3.5 h-3.5" aria-hidden="true" />
            <span>Hapus</span>
          </Button>
        </CardHeader>

        <CardContent class="pt-4 bg-slate-50/50 flex-1 flex flex-col justify-between gap-4">
          <div class="space-y-3">
            <template v-for="fs in facility.fixed_schedules?.filter((s) => s.day === activeDay)" :key="'fs-' + fs.id">
              <div v-if="getOverridesForSchedule(facility, activeDay, fs.start_time, fs.end_time).length > 0" class="space-y-2">
                <div class="text-xs text-slate-400 line-through tabular-nums">
                  {{ formatTimeRange(fs.start_time, fs.end_time) }} ({{ fs.organization?.name }})
                </div>
                <div
                  v-for="override in getOverridesForSchedule(facility, activeDay, fs.start_time, fs.end_time)"
                  :key="'over-' + override.id"
                  class="p-3.5 bg-amber-50 border border-amber-300 rounded-xl space-y-1.5"
                >
                  <div class="flex justify-between items-center gap-2">
                    <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-900">
                      <ArrowRightLeft class="w-3.5 h-3.5" aria-hidden="true" />
                      <span>Dipinjam Sementara</span>
                    </span>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-900 rounded text-xs font-semibold tabular-nums">
                      {{ override.date }}
                    </span>
                  </div>
                  <p class="text-sm font-bold text-slate-900 tabular-nums">
                    {{ formatTimeRange(override.start_time, override.end_time) }}
                  </p>
                  <p class="text-xs text-slate-700">
                    Pemakai: <strong>{{ override.organization?.name }}</strong>
                  </p>
                </div>
              </div>

              <div
                v-else
                class="text-sm bg-white p-3.5 rounded-xl border border-border flex justify-between items-center gap-2"
              >
                <div class="space-y-0.5 min-w-0">
                  <p class="font-bold text-slate-900 flex items-center gap-1.5 tabular-nums">
                    <Clock class="w-3.5 h-3.5 text-[#1E3A5F] shrink-0" aria-hidden="true" />
                    <span>{{ formatTimeRange(fs.start_time, fs.end_time) }}</span>
                  </p>
                  <p class="text-xs text-slate-600">
                    Pemilik jadwal tetap: <strong class="text-slate-900">{{ fs.organization?.name }}</strong>
                  </p>
                </div>
                <Button
                  v-if="isMpk"
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="text-rose-700 hover:bg-rose-50 hover:text-rose-800 text-xs shrink-0"
                  @click="emit('delete-schedule', fs.id)"
                >
                  Hapus Jadwal
                </Button>
              </div>
            </template>

            <template v-for="req in getPureBorrowRequests(facility, activeDay)" :key="'req-' + req.id">
              <div class="p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1">
                <div class="flex justify-between items-center gap-2">
                  <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-800">
                    <CheckCircle2 class="w-3.5 h-3.5" aria-hidden="true" />
                    <span>Peminjaman Disetujui</span>
                  </span>
                  <span class="px-2 py-0.5 bg-emerald-100 text-emerald-900 rounded text-xs font-semibold tabular-nums">
                    {{ req.date }}
                  </span>
                </div>
                <p class="text-sm font-bold text-slate-900 tabular-nums">
                  {{ formatTimeRange(req.start_time, req.end_time) }}
                </p>
                <p class="text-xs text-slate-700">Pemakai: <strong>{{ req.organization?.name }}</strong></p>
              </div>
            </template>

            <div
              v-if="!hasAnySchedule(facility, activeDay)"
              class="text-sm text-slate-600 text-center py-5 px-3 border border-dashed border-emerald-300 bg-emerald-50/40 rounded-xl space-y-1"
            >
              <Calendar class="w-5 h-5 text-emerald-700 mx-auto" aria-hidden="true" />
              <p class="font-semibold text-emerald-900">Tidak Ada Jadwal Rutin</p>
              <p class="text-xs text-slate-600">Dapat diajukan melalui prosedur peminjaman resmi.</p>
            </div>
          </div>

          <!-- Redirects cleanly to the official submission form without bypassing workflow -->
          <Button
            type="button"
            :variant="hasAnySchedule(facility, activeDay) ? 'outline' : 'default'"
            :class="[
              'w-full',
              !hasAnySchedule(facility, activeDay) ? 'bg-[#1E3A5F] hover:bg-[#152943] text-white' : 'border-slate-300 text-slate-800 hover:bg-slate-100',
            ]"
            @click="emit('borrow-facility', facility.id)"
          >
            <PlusCircle class="w-4 h-4" aria-hidden="true" />
            <span>Ajukan Peminjaman Resmi</span>
          </Button>
        </CardContent>
      </Card>
    </div>
  </section>
</template>
