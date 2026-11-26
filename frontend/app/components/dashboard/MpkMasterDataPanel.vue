<script setup>
import { ref } from 'vue'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'

defineProps({
  facilities: { type: Array, default: () => [] },
  organizations: { type: Array, default: () => [] },
  eskulUsers: { type: Array, default: () => [] },
  loadingUsers: { type: Boolean, default: false },
  creatingFacility: { type: Boolean, default: false },
  creatingSchedule: { type: Boolean, default: false },
  creatingUser: { type: Boolean, default: false },
})

const emit = defineEmits([
  'create-facility',
  'create-schedule',
  'create-user',
  'reset-password',
  'delete-user',
])

const facilityForm = ref({ name: '', type: '', description: '' })
const scheduleForm = ref({ facility_id: '', organization_id: '', day: 'Monday', start_time: '', end_time: '' })
const newUserForm = ref({ name: '', email: '', password: '', organization_id: '' })

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
  emit('create-user', { ...newUserForm.value }, () => {
    newUserForm.value = { name: '', email: '', password: '', organization_id: '' }
  })
}
</script>

<template>
  <div class="space-y-10">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <Card class="border-zinc-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-zinc-900 mb-4">Tambah Fasilitas Baru</h3>
        <form @submit.prevent="submitFacility" class="space-y-4">
          <div class="space-y-2"><Label>Nama Tempat</Label><Input v-model="facilityForm.name" placeholder="Contoh: Aula Atas, Studio Musik" required /></div>
          <div class="space-y-2"><Label>Kategori / Tipe</Label><Input v-model="facilityForm.type" placeholder="Contoh: Indoor, Outdoor" required /></div>
          <div class="space-y-2"><Label>Keterangan Tambahan</Label><Input v-model="facilityForm.description" placeholder="Opsional..." /></div>
          <Button :disabled="creatingFacility" type="submit" class="w-full bg-zinc-900 text-white">
            {{ creatingFacility ? 'Menambahkan...' : 'Simpan Fasilitas' }}
          </Button>
        </form>
      </Card>

      <Card class="border-zinc-200 shadow-sm p-6">
        <h3 class="text-lg font-bold text-zinc-900 mb-4">Daftarkan Hak Milik Jadwal Tetap</h3>
        <form @submit.prevent="submitSchedule" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>Fasilitas</Label>
              <select v-model="scheduleForm.facility_id" required class="flex h-10 w-full border border-zinc-200 rounded-md px-3 text-sm bg-white">
                <option value="" disabled>Pilih...</option>
                <option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <Label>Eskul Pemilik</Label>
              <select v-model="scheduleForm.organization_id" required class="flex h-10 w-full border border-zinc-200 rounded-md px-3 text-sm bg-white">
                <option value="" disabled>Pilih...</option>
                <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
              </select>
            </div>
            <div class="space-y-2">
              <Label>Hari Hak Akses</Label>
              <select v-model="scheduleForm.day" required class="flex h-10 w-full border border-zinc-200 rounded-md px-3 text-sm bg-white">
                <option value="Monday">Senin</option>
                <option value="Tuesday">Selasa</option>
                <option value="Wednesday">Rabu</option>
                <option value="Thursday">Kamis</option>
                <option value="Friday">Jumat</option>
                <option value="Saturday">Sabtu</option>
                <option value="Sunday">Minggu</option>
              </select>
            </div>
            <div class="space-y-2"><Label>Jam Mulai</Label><Input v-model="scheduleForm.start_time" type="time" required /></div>
          </div>
          <div class="space-y-2"><Label>Jam Selesai</Label><Input v-model="scheduleForm.end_time" type="time" required /></div>
          <Button :disabled="creatingSchedule" type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white">
            {{ creatingSchedule ? 'Mendaftarkan...' : 'Kunci Jadwal Tetap' }}
          </Button>
        </form>
      </Card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="lg:col-span-1">
        <h2 class="text-xl font-bold text-zinc-900 mb-4">Buat Akun Eskul</h2>
        <Card class="border-zinc-200 shadow-sm p-6">
          <form @submit.prevent="submitUser" class="space-y-4">
            <div class="space-y-2">
              <Label for="new_org">Pilih Organisasi / Eskul</Label>
              <select id="new_org" v-model="newUserForm.organization_id" required class="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm">
                <option value="" disabled>Pilih Eskul...</option>
                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
              </select>
            </div>
            <div class="space-y-2"><Label for="new_name">Nama Tampilan Akun</Label><Input id="new_name" v-model="newUserForm.name" type="text" placeholder="Contoh: Official Basket" required /></div>
            <div class="space-y-2"><Label for="new_email">Email Dinas</Label><Input id="new_email" v-model="newUserForm.email" type="email" placeholder="Contoh: basket@coordina.com" required /></div>
            <div class="space-y-2"><Label for="new_password">Password Awal</Label><Input id="new_password" v-model="newUserForm.password" type="password" minlength="6" placeholder="Minimal 6 karakter" required /></div>
            <Button :disabled="creatingUser" type="submit" class="w-full bg-zinc-900 text-white">Buat Akun</Button>
          </form>
        </Card>
      </div>

      <div class="lg:col-span-2">
        <h2 class="text-xl font-bold text-zinc-900 mb-4">Daftar Akun Eskul Aktif</h2>
        <Card class="border-zinc-200 shadow-sm p-0 overflow-hidden">
          <div v-if="loadingUsers" class="p-6 text-zinc-500">Memuat data akun...</div>
          <table v-else class="w-full text-sm text-left">
            <thead class="bg-zinc-50 border-b border-zinc-200 text-zinc-600 uppercase text-xs">
              <tr>
                <th class="px-6 py-4 font-semibold">Nama / Organisasi</th>
                <th class="px-6 py-4 font-semibold">Email Login</th>
                <th class="px-6 py-4 font-semibold text-right">Aksi Keamanan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 bg-white">
              <tr v-for="user in eskulUsers" :key="user.id" class="hover:bg-zinc-50/50 transition-colors">
                <td class="px-6 py-4 font-medium text-zinc-900">
                  {{ user.name }}
                  <span v-if="user.organization?.name" class="block text-xs text-zinc-500 font-normal">{{ user.organization.name }}</span>
                </td>
                <td class="px-6 py-4 text-zinc-600">{{ user.email }}</td>
                <td class="px-6 py-4 text-right space-x-2">
                  <Button @click="emit('reset-password', user.id, user.name)" variant="outline" size="sm">Reset Password</Button>
                  <Button @click="emit('delete-user', user.id, user.name)" variant="outline" size="sm" class="border-red-200 text-red-600 hover:bg-red-50">Hapus</Button>
                </td>
              </tr>
              <tr v-if="eskulUsers.length === 0">
                <td colspan="3" class="px-6 py-8 text-center text-zinc-500">Belum ada akun eskul yang terdaftar.</td>
              </tr>
            </tbody>
          </table>
        </Card>
      </div>
    </div>
  </div>
</template>

