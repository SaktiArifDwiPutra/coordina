<script setup lang="ts">
import { ref } from 'vue'
import { Building2, Calendar, Clock, PlusCircle, Trash2, CalendarPlus, CheckCircle2, ArrowRightLeft } from 'lucide-vue-next'
import StatusBadge from '~/components/StatusBadge.vue'
import { formatTimeRange, getWeekdayFromDate } from '~/lib/workflow'
import type { BorrowRequestItem, DemoRole, Facility, FixedSchedule, OrganizationProfile, WeekdayKey } from '~/types/coordina'

const props = defineProps<{
  role: DemoRole
  facilities: Facility[]
  fixedSchedules: FixedSchedule[]
  requests: BorrowRequestItem[]
  organizations: OrganizationProfile[]
}>()

const emit = defineEmits<{
  (e: 'borrow-now', facilityId: number): void
  (e: 'add-facility', payload: { name: string; type: 'Indoor' | 'Outdoor'; description: string }): void
  (e: 'add-schedule', payload: { facilityId: number; organizationId: number; day: WeekdayKey; startTime: string; endTime: string }): void
  (e: 'delete-schedule', scheduleId: number): void
}>()

const days: Array<{ key: WeekdayKey; label: string }> = [
  { key: 'Monday', label: 'Senin' },
  { key: 'Tuesday', label: 'Selasa' },
  { key: 'Wednesday', label: 'Rabu' },
  { key: 'Thursday', label: 'Kamis' },
  { key: 'Friday', label: 'Jumat' },
  { key: 'Saturday', label: 'Sabtu' },
  { key: 'Sunday', label: 'Minggu' },
]

const activeDay = ref<WeekdayKey>('Friday')
const showMpkManage = ref(false)
const newFacility = ref({ name: '', type: 'Indoor' as 'Indoor' | 'Outdoor', description: '' })
const newSchedule = ref({
  facilityId: 1,
  organizationId: 2,
  day: 'Monday' as WeekdayKey,
  startTime: '15:30',
  endTime: '17:30',
})

function orgName(orgId: number | null) {
  if (!orgId) return '-'
  return props.organizations.find((o) => o.id === orgId)?.name || '-'
}

function schedulesFor(facilityId: number, day: WeekdayKey) {
  return props.fixedSchedules.filter((s) => s.facilityId === facilityId && s.day === day)
}

function approvedOverridesFor(facilityId: number, day: WeekdayKey, start: string, end: string) {
  return props.requests.filter(
    (r) =>
      r.facilityId === facilityId &&
      r.status === 'approved' &&
      getWeekdayFromDate(r.date) === day &&
      r.startTime < end &&
      r.endTime > start
  )
}

function approvedFreeBookingsFor(facilityId: number, day: WeekdayKey) {
  return props.requests.filter(
    (r) =>
      r.facilityId === facilityId &&
      r.status === 'approved' &&
      !r.ownerOrgId &&
      getWeekdayFromDate(r.date) === day
  )
}

function hasAnyActivity(facilityId: number, day: WeekdayKey) {
  return schedulesFor(facilityId, day).length > 0 || approvedFreeBookingsFor(facilityId, day).length > 0
}

function submitFacility() {
  if (!newFacility.value.name.trim()) return
  emit('add-facility', { ...newFacility.value, name: newFacility.value.name.trim(), description: newFacility.value.description.trim() })
  newFacility.value = { name: '', type: 'Indoor', description: '' }
}

function submitSchedule() {
  if (!newSchedule.value.startTime || !newSchedule.value.endTime || newSchedule.value.endTime <= newSchedule.value.startTime) return
  emit('add-schedule', { ...newSchedule.value })
}
</script>

<template>
  <section class="space-y-5">
    <div class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-5 space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h2 class="text-lg font-bold text-[#0F172A]">Jadwal Fasilitas &amp; Slot Waktu</h2>
          <p class="text-sm text-[#475569] mt-0.5">
            Lihat jadwal rutin pemilik dan slot yang tersedia. Klik <strong>Pinjam Sekarang</strong> untuk diarahkan ke formulir permohonan resmi.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            v-if="role === 'mpk'"
            type="button"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-[#DCE4EE] bg-[#F8FAFC] hover:bg-[#EFF6FF] text-xs font-semibold text-[#1E3A5F]"
            @click="showMpkManage = !showMpkManage"
          >
            <CalendarPlus class="w-4 h-4" aria-hidden="true" />
            <span>{{ showMpkManage ? 'Tutup Kelola Jadwal MPK' : 'Kelola Fasilitas & Jadwal (MPK)' }}</span>
          </button>
        </div>
      </div>

      <div class="grid grid-cols-4 sm:flex sm:flex-wrap gap-1.5 bg-[#F4F7FA] p-1 rounded-xl border border-[#DCE4EE]" role="tablist" aria-label="Pilih hari">
        <button
          v-for="d in days"
          :key="d.key"
          type="button"
          role="tab"
          :aria-selected="activeDay === d.key"
          :class="[
            'px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors',
            activeDay === d.key ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-[#475569] hover:bg-white hover:text-[#0F172A]',
          ]"
          @click="activeDay = d.key"
        >
          {{ d.label }}
        </button>
      </div>

      <!-- MPK-ONLY SCHEDULE & FACILITY MANAGEMENT -->
      <div v-if="role === 'mpk' && showMpkManage" class="grid grid-cols-1 lg:grid-cols-2 gap-4 pt-3 border-t border-[#DCE4EE]">
        <form @submit.prevent="submitFacility" class="rounded-xl bg-[#F8FAFC] border border-[#DCE4EE] p-4 space-y-3">
          <h3 class="text-sm font-bold text-[#0F172A]">Tambah Fasilitas Baru (Simulasi MPK)</h3>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
            <input v-model="newFacility.name" type="text" placeholder="Nama fasilitas…" required class="sm:col-span-2 h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
            <select v-model="newFacility.type" class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
              <option value="Indoor">Indoor</option>
              <option value="Outdoor">Outdoor</option>
            </select>
          </div>
          <input v-model="newFacility.description" type="text" placeholder="Keterangan singkat…" class="w-full h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
          <button type="submit" class="px-4 py-2 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-xs font-semibold">
            Simpan Fasilitas
          </button>
        </form>

        <form @submit.prevent="submitSchedule" class="rounded-xl bg-[#F8FAFC] border border-[#DCE4EE] p-4 space-y-3">
          <h3 class="text-sm font-bold text-[#0F172A]">Tetapkan Hak Jadwal Rutin Ekskul</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <select v-model.number="newSchedule.facilityId" class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
              <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
            <select v-model.number="newSchedule.organizationId" class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
              <option v-for="o in organizations.filter((x) => !x.isMpk)" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
            <select v-model="newSchedule.day" class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-3 text-sm">
              <option v-for="d in days" :key="d.key" :value="d.key">{{ d.label }}</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
              <input v-model="newSchedule.startTime" type="time" required class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-2 text-sm tabular-nums">
              <input v-model="newSchedule.endTime" type="time" required class="h-10 rounded-xl border border-[#DCE4EE] bg-white px-2 text-sm tabular-nums">
            </div>
          </div>
          <button type="submit" class="px-4 py-2 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-xs font-semibold">
            Kunci Jadwal Rutin
          </button>
        </form>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <article
        v-for="fac in facilities"
        :key="fac.id"
        class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm overflow-hidden flex flex-col"
      >
        <div class="p-5 border-b border-[#DCE4EE] flex items-start justify-between gap-3">
          <div class="space-y-1">
            <h3 class="text-base font-bold text-[#0F172A] flex items-center gap-2">
              <Building2 class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
              <span>{{ fac.name }}</span>
            </h3>
            <p class="text-xs text-[#475569]">{{ fac.description || `Area ${fac.type}` }}</p>
          </div>
          <div class="flex flex-col items-end gap-1.5 shrink-0">
            <span class="px-2.5 py-0.5 rounded-md bg-[#F8FAFC] border border-[#DCE4EE] text-xs font-semibold text-[#475569]">
              {{ fac.type }}
            </span>
            <StatusBadge
              :label="hasAnyActivity(fac.id, activeDay) ? 'Terjadwal' : 'Slot Tersedia'"
              :tone="hasAnyActivity(fac.id, activeDay) ? 'blue' : 'emerald'"
              :icon="hasAnyActivity(fac.id, activeDay) ? 'clock' : 'check'"
            />
          </div>
        </div>

        <div class="p-5 bg-[#F8FAFC] flex-1 flex flex-col justify-between gap-4">
          <div class="space-y-2.5">
            <template v-for="fs in schedulesFor(fac.id, activeDay)" :key="fs.id">
              <div
                v-for="ov in approvedOverridesFor(fac.id, activeDay, fs.startTime, fs.endTime)"
                :key="'ov-' + ov.id"
                class="rounded-xl border border-[#FDE68A] bg-[#FFFBEB] p-3.5 space-y-1"
              >
                <div class="flex items-center justify-between text-xs font-bold text-[#B45309]">
                  <span class="inline-flex items-center gap-1">
                    <ArrowRightLeft class="w-3.5 h-3.5" aria-hidden="true" />
                    Dipakai Sementara
                  </span>
                  <span class="tabular-nums">{{ ov.date }}</span>
                </div>
                <p class="text-sm font-bold text-[#0F172A] tabular-nums">{{ formatTimeRange(ov.startTime, ov.endTime) }}</p>
                <p class="text-xs text-[#475569]">
                  Peminjam: <strong class="text-[#0F172A]">{{ orgName(ov.requesterOrgId) }}</strong> (Izin dari {{ orgName(fs.organizationId) }})
                </p>
              </div>

              <div class="rounded-xl border border-[#DCE4EE] bg-white p-3.5 flex items-center justify-between gap-2">
                <div>
                  <p class="text-sm font-bold text-[#0F172A] flex items-center gap-1.5 tabular-nums">
                    <Clock class="w-3.5 h-3.5 text-[#1E3A5F]" aria-hidden="true" />
                    <span>{{ formatTimeRange(fs.startTime, fs.endTime) }}</span>
                  </p>
                  <p class="text-xs text-[#475569] mt-0.5">
                    Pemilik jadwal rutin: <strong class="text-[#0F172A]">{{ orgName(fs.organizationId) }}</strong>
                  </p>
                </div>
                <button
                  v-if="role === 'mpk'"
                  type="button"
                  :aria-label="`Hapus jadwal ${orgName(fs.organizationId)}`"
                  class="p-2 rounded-lg text-[#BE123C] hover:bg-[#FFF1F2]"
                  @click="emit('delete-schedule', fs.id)"
                >
                  <Trash2 class="w-4 h-4" aria-hidden="true" />
                </button>
              </div>
            </template>

            <div
              v-for="fb in approvedFreeBookingsFor(fac.id, activeDay)"
              :key="'fb-' + fb.id"
              class="rounded-xl border border-[#A7F3D0] bg-[#ECFDF5] p-3.5 space-y-1"
            >
              <div class="flex items-center justify-between text-xs font-bold text-[#15803D]">
                <span class="inline-flex items-center gap-1">
                  <CheckCircle2 class="w-3.5 h-3.5" aria-hidden="true" />
                  Peminjaman Slot Kosong
                </span>
                <span class="tabular-nums">{{ fb.date }}</span>
              </div>
              <p class="text-sm font-bold text-[#0F172A] tabular-nums">{{ formatTimeRange(fb.startTime, fb.endTime) }}</p>
              <p class="text-xs text-[#475569]">Pemakai: <strong class="text-[#0F172A]">{{ orgName(fb.requesterOrgId) }}</strong></p>
            </div>

            <div
              v-if="!hasAnyActivity(fac.id, activeDay)"
              class="rounded-xl border border-dashed border-[#A7F3D0] bg-[#ECFDF5]/50 p-4 text-center space-y-1"
            >
              <Calendar class="w-5 h-5 text-[#15803D] mx-auto" aria-hidden="true" />
              <p class="text-sm font-semibold text-[#15803D]">Slot Kosong Sepanjang Hari</p>
              <p class="text-xs text-[#475569]">Tidak ada jadwal rutin ekskul lain pada hari ini.</p>
            </div>
          </div>

          <button
            type="button"
            class="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold transition-colors"
            @click="emit('borrow-now', fac.id)"
          >
            <PlusCircle class="w-4 h-4" aria-hidden="true" />
            <span>Pinjam Sekarang</span>
          </button>
        </div>
      </article>
    </div>
  </section>
</template>

