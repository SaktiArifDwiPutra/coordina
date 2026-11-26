<script setup>
import { ref } from 'vue'
import { Building2, CalendarPlus, UserPlus, KeyRound, Trash2, Users } from 'lucide-vue-next'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import StatusBadge from '~/components/dashboard/StatusBadge.vue'

defineProps({
  facilities: { type: Array, default: () => [] },
  organizations: { type: Array, default: () => [] },
  eskulUsers: { type: Array, default: () => [] },
  loadingUsers: { type: Boolean, default: false },
  creatingFacility: { type: Boolean, default: false },
  creatingSchedule: { type: Boolean, default: false },
  creatingUser: { type: Boolean, default: false },
  togglingUserId: { type: Number, default: null },
})

const emit = defineEmits([
  'create-facility',
  'create-schedule',
  'create-user',
  'reset-password',
  'toggle-active',
  'delete-user',
])

const activeSection = ref('accounts') // 'accounts' | 'facilities'

const facilityForm = ref({ name: '', type: '', description: '' })
const scheduleForm = ref({ facility_id: '', organization_id: '', day: 'Monday', start_time: '', end_time: '' })
const newUserForm = ref({
  name: '',
  email: '',
  password: '',
  generate_password: false,
  must_change_password: true,
  organization_id: '',
})

const facNameId = useId()
const facTypeId = useId()
const facDescId = useId()
const schFacId = useId()
const schOrgId = useId()
const schDayId = useId()
const schStartId = useId()
const schEndId = useId()

function generateClientTempPassword() {
  const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ'
  const lower = 'abcdefghijkmnopqrstuvwxyz'
  const digits = '23456789'
  const symbols = '!@#$%&*?'
  const all = upper + lower + digits + symbols
  const pick = (pool) => pool[Math.floor(Math.random() * pool.length)]
  const chars = [pick(upper), pick(lower), pick(digits), pick(symbols)]
  while (chars.length < 12) chars.push(pick(all))
  for (let i = chars.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[chars[i], chars[j]] = [chars[j], chars[i]]
  }
  newUserForm.value.password = chars.join('')
  newUserForm.value.generate_password = false
}

function submitFacility() {
  emit('create-facility', { ...facilityForm.value }, () => {
    facilityForm.value = { name: '', type: '', description: '' }
  })
}

function submitSchedule() {
  emit('create-schedule', { ...scheduleForm.value }, () => {
    scheduleForm.value = { facility_id: '', organization_id: '', day: 'Monday', start_time: '', end_time: '' }
  })
}

function submitUser() {
  const payload = {
    ...newUserForm.value,
    generate_password: !newUserForm.value.password.trim(),
  }
  emit('create-user', payload, () => {
    newUserForm.value = {
      name: '',
      email: '',
      password: '',
      generate_password: false,
      must_change_password: true,
      organization_id: '',
    }
  })
}
</script>

<template>
  <section class="space-y-6">
    <!-- Clean 2-pill sub-navigation so forms never overwhelm on one screen -->
    <div class="bg-white rounded-2xl border border-border shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Data Master &amp; Akun Ekskul</h2>
        <p class="text-sm text-slate-600 mt-0.5">Kelola akun organisasi ekskul, fasilitas sekolah, dan hak milik jadwal rutin.</p>
      </div>
      <div class="grid grid-cols-2 sm:inline-flex gap-1.5 bg-slate-100 p-1 rounded-xl border border-border" role="tablist" aria-label="Kategori data master">
        <button
          type="button"
          role="tab"
          :aria-selected="activeSection === 'accounts'"
          :class="[
            'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors',
            activeSection === 'accounts' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-slate-700 hover:bg-white',
          ]"
          @click="activeSection = 'accounts'"
        >
          <Users class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>Akun Ekskul ({{ eskulUsers.length }})</span>
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeSection === 'facilities'"
          :class="[
            'inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors',
            activeSection === 'facilities' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-slate-700 hover:bg-white',
          ]"
          @click="activeSection = 'facilities'"
        >
          <Building2 class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>Fasilitas &amp; Jadwal</span>
        </button>
      </div>
    </div>

    <!-- SECTION 1: EXTRACURRICULAR ACCOUNTS (Responsive cards on mobile, clean table on md+) -->
    <div v-if="activeSection === 'accounts'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <Card class="border-border shadow-sm p-6 bg-white rounded-2xl lg:col-span-1">
        <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
          <UserPlus class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
          <span>Buat Akun Ekskul Baru</span>
        </h3>
        <form @submit.prevent="submitUser" class="space-y-4">
          <div class="space-y-1.5">
            <Label for="new_org">Organisasi / Ekskul</Label>
            <select id="new_org" v-model="newUserForm.organization_id" required class="flex h-11 w-full rounded-xl border border-input bg-white px-3 py-2 text-sm">
              <option value="" disabled>Pilih ekskul…</option>
              <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <Label for="new_name">Nama Tampilan Akun</Label>
            <Input id="new_name" v-model="newUserForm.name" type="text" placeholder="Contoh: Official Basket…" class="h-11 rounded-xl" required />
          </div>
          <div class="space-y-1.5">
            <Label for="new_email">Email Dinas</Label>
            <Input id="new_email" v-model="newUserForm.email" type="email" autocomplete="off" spellcheck="false" placeholder="Contoh: basket@coordina.com…" class="h-11 rounded-xl" required />
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center justify-between gap-2">
              <Label for="new_password">Password Sementara</Label>
              <button type="button" class="text-xs font-semibold text-[#1E3A5F] hover:underline" @click="generateClientTempPassword">
                Generate Acak
              </button>
            </div>
            <Input
              id="new_password"
              v-model="newUserForm.password"
              type="text"
              minlength="6"
              autocomplete="off"
              placeholder="Kosongkan untuk dibuat otomatis…"
              class="h-11 rounded-xl"
            />
          </div>
          <label class="flex items-center gap-2.5 text-xs text-slate-700 cursor-pointer">
            <input v-model="newUserForm.must_change_password" type="checkbox" class="rounded border-slate-300 h-4 w-4 accent-[#1E3A5F]" />
            <span>Wajibkan ganti password saat login pertama</span>
          </label>
          <Button :disabled="creatingUser" type="submit" class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white font-semibold">
            {{ creatingUser ? 'Membuat Akun…' : 'Buat Akun & Tampilkan Kredensial' }}
          </Button>
        </form>
      </Card>

      <Card class="border-border shadow-sm overflow-hidden bg-white rounded-2xl lg:col-span-2">
        <div class="px-5 sm:px-6 py-4 border-b border-border flex items-center justify-between gap-2">
          <div>
            <h3 class="text-base font-bold text-slate-900">Daftar Akun Ekskul</h3>
            <p class="text-xs text-slate-500 mt-0.5">Kelola status aktif, reset password sementara, dan serah terima pengurus.</p>
          </div>
          <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-lg tabular-nums shrink-0">
            {{ eskulUsers.length }} Akun
          </span>
        </div>

        <div v-if="loadingUsers" class="p-8 text-center text-sm text-slate-500">Memuat data akun…</div>

        <div v-else-if="eskulUsers.length === 0" class="p-8 text-center text-sm text-slate-500">
          Belum ada akun eskul yang terdaftar.
        </div>

        <template v-else>
          <!-- MOBILE CARD LIST (< md): fits cleanly without any horizontal scrolling -->
          <div class="md:hidden divide-y divide-border">
            <article v-for="user in eskulUsers" :key="'mob-' + user.id" class="p-4 space-y-3">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-0.5">
                  <h4 class="text-sm font-bold text-slate-900 break-words">{{ user.name }}</h4>
                  <p v-if="user.organization?.name" class="text-xs font-semibold text-[#1E3A5F]">
                    {{ user.organization.name }}
                  </p>
                  <p class="text-xs text-slate-600 break-all">{{ user.email }}</p>
                </div>
                <div class="flex flex-col items-end gap-1 shrink-0">
                  <StatusBadge
                    :label="user.is_active !== false ? 'Aktif' : 'Nonaktif'"
                    :tone="user.is_active !== false ? 'emerald' : 'rose'"
                    :icon="user.is_active !== false ? 'check' : 'x'"
                  />
                  <StatusBadge
                    v-if="user.must_change_password"
                    label="Ganti Password"
                    tone="amber"
                    icon="alert"
                  />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-1">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full"
                  :disabled="togglingUserId === user.id"
                  @click="emit('toggle-active', user.id)"
                >
                  {{ togglingUserId === user.id ? 'Memproses…' : user.is_active !== false ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  class="w-full"
                  @click="emit('reset-password', user.id, user.name, user.email)"
                >
                  <KeyRound class="w-3.5 h-3.5" aria-hidden="true" />
                  <span>Reset Password</span>
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  class="col-span-2 w-full border-rose-200 text-rose-700 hover:bg-rose-50"
                  @click="emit('delete-user', user.id, user.name)"
                >
                  <Trash2 class="w-3.5 h-3.5" aria-hidden="true" />
                  <span>Hapus Akun</span>
                </Button>
              </div>
            </article>
          </div>

          <!-- DESKTOP TABLE (md+) -->
          <div class="hidden md:block">
            <table class="w-full text-sm text-left">
              <thead class="bg-slate-50 border-b border-border text-slate-600 text-xs">
                <tr>
                  <th class="px-5 py-3.5 font-semibold">Nama / Organisasi</th>
                  <th class="px-5 py-3.5 font-semibold">Status Akun</th>
                  <th class="px-5 py-3.5 font-semibold text-right">Aksi Keamanan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border bg-white">
                <tr v-for="user in eskulUsers" :key="user.id" class="hover:bg-slate-50/70 transition-colors">
                  <td class="px-5 py-4 min-w-0">
                    <p class="font-bold text-slate-900">{{ user.name }}</p>
                    <p v-if="user.organization?.name" class="text-xs text-slate-600">{{ user.organization.name }}</p>
                    <p class="text-xs text-slate-500 break-all">{{ user.email }}</p>
                  </td>
                  <td class="px-5 py-4 space-y-1.5">
                    <StatusBadge
                      :label="user.is_active !== false ? 'Aktif' : 'Nonaktif'"
                      :tone="user.is_active !== false ? 'emerald' : 'rose'"
                      :icon="user.is_active !== false ? 'check' : 'x'"
                    />
                    <div v-if="user.must_change_password">
                      <StatusBadge label="Wajib Ganti Password" tone="amber" icon="alert" />
                    </div>
                  </td>
                  <td class="px-5 py-4 text-right">
                    <div class="inline-flex flex-wrap justify-end gap-1.5">
                      <Button
                        variant="outline"
                        size="sm"
                        :disabled="togglingUserId === user.id"
                        @click="emit('toggle-active', user.id)"
                      >
                        {{ togglingUserId === user.id ? 'Memproses…' : user.is_active !== false ? 'Nonaktifkan' : 'Aktifkan' }}
                      </Button>
                      <Button variant="outline" size="sm" @click="emit('reset-password', user.id, user.name, user.email)">
                        <KeyRound class="w-3.5 h-3.5" aria-hidden="true" />
                        <span>Reset Password</span>
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        class="border-rose-200 text-rose-700 hover:bg-rose-50"
                        @click="emit('delete-user', user.id, user.name)"
                      >
                        <Trash2 class="w-3.5 h-3.5" aria-hidden="true" />
                        <span>Hapus</span>
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </Card>
    </div>

    <!-- SECTION 2: FACILITIES & FIXED SCHEDULES -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card class="border-border shadow-sm p-6 bg-white rounded-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
          <Building2 class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
          <span>Tambah Fasilitas Baru</span>
        </h3>
        <form @submit.prevent="submitFacility" class="space-y-4">
          <div class="space-y-1.5">
            <Label :for="facNameId">Nama Tempat</Label>
            <Input :id="facNameId" v-model="facilityForm.name" placeholder="Contoh: Aula Atas, Studio Musik…" class="h-11 rounded-xl" required />
          </div>
          <div class="space-y-1.5">
            <Label :for="facTypeId">Kategori / Tipe</Label>
            <Input :id="facTypeId" v-model="facilityForm.type" placeholder="Contoh: Indoor, Outdoor…" class="h-11 rounded-xl" required />
          </div>
          <div class="space-y-1.5">
            <Label :for="facDescId">Keterangan Tambahan</Label>
            <Input :id="facDescId" v-model="facilityForm.description" placeholder="Opsional…" class="h-11 rounded-xl" />
          </div>
          <Button :disabled="creatingFacility" type="submit" class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white font-semibold">
            {{ creatingFacility ? 'Menambahkan…' : 'Simpan Fasilitas' }}
          </Button>
        </form>
      </Card>

      <Card class="border-border shadow-sm p-6 bg-white rounded-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
          <CalendarPlus class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
          <span>Daftarkan Hak Milik Jadwal Tetap</span>
        </h3>
        <form @submit.prevent="submitSchedule" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <Label :for="schFacId">Fasilitas</Label>
              <select :id="schFacId" v-model="scheduleForm.facility_id" required class="flex h-11 w-full border border-input rounded-xl px-3 text-sm bg-white">
                <option value="" disabled>Pilih fasilitas…</option>
                <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label :for="schOrgId">Ekskul Pemilik</Label>
              <select :id="schOrgId" v-model="scheduleForm.organization_id" required class="flex h-11 w-full border border-input rounded-xl px-3 text-sm bg-white">
                <option value="" disabled>Pilih ekskul…</option>
                <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label :for="schDayId">Hari Hak Akses</Label>
              <select :id="schDayId" v-model="scheduleForm.day" required class="flex h-11 w-full border border-input rounded-xl px-3 text-sm bg-white">
                <option value="Monday">Senin</option>
                <option value="Tuesday">Selasa</option>
                <option value="Wednesday">Rabu</option>
                <option value="Thursday">Kamis</option>
                <option value="Friday">Jumat</option>
                <option value="Saturday">Sabtu</option>
                <option value="Sunday">Minggu</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <Label :for="schStartId">Jam Mulai</Label>
              <Input :id="schStartId" v-model="scheduleForm.start_time" type="time" class="h-11 rounded-xl tabular-nums" required />
            </div>
          </div>
          <div class="space-y-1.5">
            <Label :for="schEndId">Jam Selesai</Label>
            <Input :id="schEndId" v-model="scheduleForm.end_time" type="time" class="h-11 rounded-xl tabular-nums" required />
          </div>
          <Button :disabled="creatingSchedule" type="submit" class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white font-semibold">
            {{ creatingSchedule ? 'Mendaftarkan…' : 'Kunci Jadwal Tetap' }}
          </Button>
        </form>
      </Card>
    </div>
  </section>
</template>
