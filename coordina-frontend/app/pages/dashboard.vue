<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '~/stores/auth'

const config = useRuntimeConfig()
const auth = useAuthStore()

/* =====================
   AUTH
===================== */
const authReady = ref(false)

/* =====================
   DATA
===================== */
const facilities = ref([])
const borrowRequests = ref([])
const eskulUsers = ref([])

/* =====================
   LOADING
===================== */
const loading = ref(false)

/* =====================
   FETCH GUARD (ANTI SPAM)
===================== */
let isFetching = false

/* =====================
   API CALLS
===================== */
async function fetchFacilities() {
  return $fetch(`${config.public.apiUrl}/api/facilities`, {
    headers: { Authorization: `Bearer ${auth.token}` }
  }).then(res => facilities.value = res.data)
}

async function fetchBorrowRequests() {
  return $fetch(`${config.public.apiUrl}/api/borrow-requests`, {
    headers: { Authorization: `Bearer ${auth.token}` }
  }).then(res => borrowRequests.value = res.data)
}

async function fetchEskulUsers() {
  if (!['admin', 'admin_mpk'].includes(auth.user?.role)) return

  return $fetch(`${config.public.apiUrl}/api/users`, {
    headers: { Authorization: `Bearer ${auth.token}` }
  }).then(res => eskulUsers.value = res.data)
}

/* =====================
   LOAD DATA (NON BLOCKING)
===================== */
function loadData() {
  if (isFetching) return
  isFetching = true

  Promise.allSettled([
    fetchFacilities(),
    fetchBorrowRequests(),
    fetchEskulUsers()
  ]).finally(() => {
    isFetching = false
  })
}

/* =====================
   AUTO SYNC (LIGHT ONLY)
===================== */
let interval = null

function startAutoSync() {
  interval = setInterval(() => {
    fetchBorrowRequests() // ONLY 1 API, NOT ALL
  }, 20000)
}

/* =====================
   INIT
===================== */
onMounted(async () => {
  if (!auth.user) await auth.fetchUser()

  if (!auth.token || !auth.user) {
    return navigateTo('/login')
  }

  authReady.value = true

  loadData()
  startAutoSync()
})

onUnmounted(() => {
  if (interval) clearInterval(interval)
})
</script>

<template>
  <div v-if="pageLoading" class="min-h-screen flex flex-col items-center justify-center bg-zinc-50">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-zinc-900 mb-4"></div>
    <p class="text-zinc-500 font-medium">Memeriksa hak akses ruang kerja...</p>
  </div>

  <div v-else class="min-h-screen bg-zinc-50 p-8 relative">
    
    <!-- === MODALS === -->
<div v-if="!authReady" class="min-h-screen flex items-center justify-center bg-zinc-50">
  <div class="text-center">
    <div class="animate-spin h-10 w-10 border-b-2 border-zinc-900 mx-auto mb-3"></div>
    <p class="text-zinc-500">Verifying session...</p>
  </div>
</div>

    <div v-if="passwordModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <h3 class="text-lg font-bold text-zinc-900">Reset Password</h3>
        <p class="text-sm text-zinc-500">Akun: <span class="font-semibold text-zinc-900">{{ passwordModal.userName }}</span></p>
        <div class="space-y-2 pt-2"><Label>Password Baru</Label><Input type="password" v-model="passwordModal.newPassword" placeholder="Minimal 6 karakter..." /></div>
        <div class="flex justify-end gap-2 pt-4"><Button variant="outline" @click="passwordModal.isOpen = false">Batal</Button><Button @click="submitPasswordReset" :disabled="passwordModal.isSubmitting" class="bg-indigo-600 text-white">Simpan</Button></div>
      </div>
    </div>

    <div v-if="deleteModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <h3 class="text-lg font-bold text-red-600">Hapus Akun?</h3>
        <p class="text-zinc-600 text-sm">Hapus permanen akun <span class="font-bold text-zinc-900">{{ deleteModal.userName }}</span>?</p>
        <div class="flex justify-end gap-2 pt-4"><Button variant="outline" @click="deleteModal.isOpen = false">Batal</Button><Button @click="executeDeleteUser" :disabled="deleteModal.isSubmitting" class="bg-red-600 text-white">Ya, Hapus</Button></div>
      </div>
    </div>

    <div v-if="approvalModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4 animate-in fade-in zoom-in-95 duration-200">
        <h3 class="text-lg font-bold text-zinc-900">{{ approvalModal.actionType === 'approved' ? 'Izinkan Peminjaman?' : 'Tolak Peminjaman?' }}</h3>
        <p class="text-zinc-600 text-sm">Apakah kamu yakin ingin memproses tindakan ini untuk <span class="font-bold">{{ approvalModal.orgName }}</span>?</p>
        <div class="flex justify-end gap-2 pt-4"><Button variant="outline" @click="approvalModal.isOpen = false">Batal</Button><Button @click="updateRequestStatus(approvalModal.requestId, approvalModal.actionType, true)" :disabled="approvalModal.isSubmitting" :class="approvalModal.actionType === 'approved' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'">Proses</Button></div>
      </div>
    </div>

    <div class="max-w-6xl mx-auto space-y-8">
      
      <!-- HEADER PROFILE -->
      <div class="flex justify-between items-center bg-white p-6 rounded-2xl shadow-sm border border-zinc-200">
        <div>
          <h1 class="text-2xl font-bold text-zinc-900">Dashboard Coordina</h1>
          <p class="text-zinc-500">Selamat datang, {{ auth.user?.name || 'User' }}! <span class="font-semibold text-indigo-600">({{ ['admin', 'admin_mpk'].includes(auth.user?.role) ? 'Admin MPK' : 'Eskul' }})</span></p>
        </div>
        <button @click="handleLogout" class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 font-medium rounded-lg transition-colors">Keluar</button>
      </div>

      <!-- DAFTAR FASILITAS DENGAN TAB JADWAL GLOBAL -->
      <div>
        <h2 class="text-xl font-bold text-zinc-900 mb-4">Daftar Fasilitas & Jadwal</h2>
        <div class="flex space-x-6 mb-6 border-b border-zinc-200 overflow-x-auto scrollbar-hide">
          <button v-for="d in days" :key="d.key" @click="activeDay = d.key" :class="['whitespace-nowrap pb-3 text-sm font-medium transition-colors relative top-[1px]', activeDay === d.key ? 'border-b-2 border-indigo-600 text-indigo-900' : 'text-zinc-500 hover:text-zinc-800 border-b-2 border-transparent']">{{ d.label }}</button>
        </div>

        <div v-if="loading" class="text-zinc-500">Memuat data fasilitas...</div>
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <Card v-for="facility in facilities" :key="facility.id" class="border-zinc-200 shadow-sm rounded-xl overflow-hidden flex flex-col">
            <CardHeader class="bg-white pb-3 border-b border-zinc-100 flex flex-row justify-between items-center">
              <div>
                <CardTitle class="text-lg">{{ facility.name }}</CardTitle>
                <span class="text-xs px-2 py-0.5 bg-zinc-100 text-zinc-600 rounded-md mt-1 inline-block">{{ facility.type }}</span>
              </div>
              <Button v-if="['admin', 'admin_mpk'].includes(auth.user?.role)" @click="deleteFacility(facility.id)" variant="outline" size="sm" class="text-red-600 border-red-100 hover:bg-red-50 p-2 h-8">Hapus</Button>
            </CardHeader>
            <CardContent class="pt-4 bg-zinc-50/50 flex-1 space-y-3">
              
              <!-- LOOPING JADWAL TETAP -->
              <template v-for="fs in facility.fixed_schedules?.filter(s => s.day === activeDay)" :key="'fs-'+fs.id">
                <div v-if="getOverridesForSchedule(facility, activeDay, fs.start_time, fs.end_time).length > 0">
                  <div class="text-xs text-zinc-400 line-through mb-1">{{ fs.start_time.substring(0,5) }} - {{ fs.end_time.substring(0,5) }} ({{ fs.organization?.name }})</div>
                  <div v-for="override in getOverridesForSchedule(facility, activeDay, fs.start_time, fs.end_time)" :key="'over-'+override.id" class="p-3 bg-amber-50 border border-amber-200 rounded-lg shadow-sm">
                    <div class="text-xs font-bold text-amber-800 mb-1 flex items-center gap-1"><span>🔄</span> Diambil Alih Sementara</div>
                    <div class="text-sm font-bold text-zinc-900">{{ override.start_time.substring(0,5) }} - {{ override.end_time.substring(0,5) }} | {{ override.organization?.name }}</div>
                  </div>
                </div>
                <div v-else class="text-sm text-zinc-700 bg-white p-3 rounded-lg border border-zinc-200 shadow-sm flex justify-between items-center">
                  <div>
                    <span class="font-bold text-zinc-900">{{ fs.start_time.substring(0,5) }} - {{ fs.end_time.substring(0,5) }}</span><br>
                    <span class="text-xs text-zinc-500">Pemilik: {{ fs.organization?.name }}</span>
                  </div>
                  <button v-if="['admin', 'admin_mpk'].includes(auth.user?.role)" @click="deleteFixedSchedule(fs.id)" class="text-red-500 hover:text-red-700 text-xs font-medium">Hapus</button>
                </div>
              </template>

              <!-- LOOPING PEMINJAMAN MURNI -->
              <template v-for="req in getPureBorrowRequests(facility, activeDay)" :key="'req-'+req.id">
                <div class="p-3 bg-green-50 border border-green-200 rounded-lg shadow-sm">
                  <div class="text-xs font-bold text-green-800 mb-1 flex items-center gap-1"><span>✅</span> Peminjaman Ekstra</div>
                  <div class="text-sm font-bold text-zinc-900">{{ req.start_time.substring(0,5) }} - {{ req.end_time.substring(0,5) }} | {{ req.organization?.name }}</div>
                </div>
              </template>

              <div v-if="!hasAnySchedule(facility, activeDay)" class="text-sm text-zinc-400 text-center py-4 border-2 border-dashed border-zinc-200 rounded-lg">Bebas Digunakan</div>
            </CardContent>
          </Card>
        </div>
      </div>

      <!-- TAMPILAN KHUSUS ESKUL -->
      <div v-if="!['admin', 'admin_mpk'].includes(auth.user?.role)" class="space-y-12 mt-12">
        <div v-if="borrowRequests.filter(r => r.owner_organization_id === auth.user?.organization_id && r.status === 'pending_owner').length > 0">
          <h2 class="text-xl font-bold text-zinc-900 mb-4 flex items-center gap-2"><span>🔔</span> Permohonan Izin Masuk</h2>
          <div class="space-y-4">
            <Card v-for="req in borrowRequests.filter(r => r.owner_organization_id === auth.user?.organization_id && r.status === 'pending_owner')" :key="'inbox-'+req.id" class="border-amber-200 bg-amber-50 shadow-sm p-5">
              <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                  <h3 class="font-bold text-lg text-zinc-900">{{ req.organization?.name }} <span class="text-zinc-600 font-normal text-sm">meminta izin memakai jadwal Anda di</span> {{ req.facility?.name }}</h3>
                  <p class="text-sm text-zinc-700 mt-1">📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0,5) }} - {{ req.end_time.substring(0,5) }}</p>
                  <p class="text-sm text-zinc-700 mt-1">📝 Alasan: "{{ req.reason }}"</p>
                </div>
                <div class="flex gap-2">
                  <Button @click="confirmApproval(req.id, 'approved', req.organization?.name, req.facility?.name)" class="bg-green-600 hover:bg-green-700 text-white">Izinkan</Button>
                  <Button @click="confirmApproval(req.id, 'rejected', req.organization?.name, req.facility?.name)" variant="outline" class="text-red-600 border-red-200 hover:bg-red-50">Tolak</Button>
                </div>
              </div>
            </Card>
          </div>
        </div>

        <div>
          <h2 class="text-xl font-bold text-zinc-900 mb-4">Ajukan Peminjaman</h2>
          <Card class="border-zinc-200 shadow-sm p-6 max-w-2xl">
            <form @submit.prevent="submitRequest" class="space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2"><Label>Fasilitas</Label><select v-model="form.facility_id" required class="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 text-sm"><option value="" disabled>Pilih Fasilitas...</option><option v-for="fac in facilities" :key="fac.id" :value="fac.id">{{ fac.name }}</option></select></div>
                <div class="space-y-2"><Label>Tanggal</Label><Input v-model="form.date" type="date" required /></div>
                <div class="space-y-2"><Label>Jam Mulai</Label><Input v-model="form.start_time" type="time" required /></div>
                <div class="space-y-2"><Label>Jam Selesai</Label><Input v-model="form.end_time" type="time" required /></div>
              </div>
              <div class="space-y-2"><Label>Alasan Keperluan</Label><Input v-model="form.reason" type="text" placeholder="Contoh: Latihan gabungan..." required /></div>
              <Button :disabled="isSubmitting" type="submit" class="w-full bg-zinc-900 text-white">Kirim Pengajuan</Button>
            </form>
          </Card>
        </div>
      </div>

      <!-- TAMPILAN KHUSUS ADMIN MPK -->
      <div v-if="['admin', 'admin_mpk'].includes(auth.user?.role)" class="space-y-12 mt-12">
        
        <!-- MEJA KERJA MPK -->
        <div>
          <h2 class="text-xl font-bold text-zinc-900 mb-4">Meja Kerja MPK (Persetujuan Jadwal)</h2>
          <div class="space-y-4">
            <Card v-for="req in borrowRequests" :key="req.id" class="border-zinc-200 shadow-sm p-5">
              <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                  <h3 class="font-bold text-lg text-zinc-900">{{ req.organization?.name }} <span class="text-zinc-500 font-normal text-sm">ingin meminjam</span> {{ req.facility?.name }}</h3>
                  <p class="text-sm text-zinc-600 mt-1">📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0,5) }} - {{ req.end_time.substring(0,5) }}</p>
                  <p class="text-sm text-zinc-600 mt-1">📝 Alasan: "{{ req.reason }}"</p>
                  <div v-if="req.owner_organization_id" class="mt-2 text-xs font-semibold px-2 py-1 bg-amber-100 text-amber-800 rounded-md inline-block">⚠️ Bertabrakan dengan jadwal: {{ req.owner_organization?.name }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                  <template v-if="req.status === 'pending_mpk'">
                    <Button v-if="req.owner_organization_id" @click="updateRequestStatus(req.id, 'pending_owner')" class="bg-blue-600 text-white">Teruskan ke Pemilik</Button>
                    <Button v-else @click="updateRequestStatus(req.id, 'approved')" class="bg-green-600 text-white">Setujui Langsung</Button>
                    <Button @click="updateRequestStatus(req.id, 'rejected')" variant="outline" class="text-red-600 border-red-200">Tolak</Button>
                  </template>
                  <span v-else-if="req.status === 'pending_owner'" class="px-3 py-1.5 bg-blue-50 text-blue-700 text-sm font-medium rounded-lg">Menunggu Izin Eskul</span>
                  <span v-else-if="req.status === 'approved'" class="px-3 py-1.5 bg-green-50 text-green-700 text-sm font-medium rounded-lg">Disetujui</span>
                  <span v-else-if="req.status === 'rejected'" class="px-3 py-1.5 bg-red-50 text-red-700 text-sm font-medium rounded-lg">Ditolak</span>
                </div>
              </div>
            </Card>
          </div>
        </div>

        <!-- PANEL BARU: MANAJEMEN FASILITAS & JADWAL (MPK) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
          
          <!-- FORM TAMBAH FASILITAS -->
          <Card class="border-zinc-200 shadow-sm p-6">
            <h3 class="text-lg font-bold text-zinc-900 mb-4">Tambah Fasilitas Baru</h3>
            <form @submit.prevent="createFacility" class="space-y-4">
              <div class="space-y-2"><Label>Nama Tempat</Label><Input v-model="facilityForm.name" placeholder="Contoh: Aula Atas, Studio Musik" required /></div>
              <div class="space-y-2"><Label>Kategori / Tipe</Label><Input v-model="facilityForm.type" placeholder="Contoh: Ruangan, Lapangan" required /></div>
              <div class="space-y-2"><Label>Keterangan Tambahan</Label><Input v-model="facilityForm.description" placeholder="Opsional..." /></div>
              <Button :disabled="isCreatingFacility" type="submit" class="w-full bg-zinc-900 text-white">{{ isCreatingFacility ? 'Menambahkan...' : 'Simpan Fasilitas' }}</Button>
            </form>
          </Card>

          <!-- FORM DAFTAR JADWAL TETAP -->
          <Card class="border-zinc-200 shadow-sm p-6">
            <h3 class="text-lg font-bold text-zinc-900 mb-4">Daftarkan Hak Milik Jadwal Tetap</h3>
            <form @submit.prevent="createFixedSchedule" class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label>Fasilitas</Label>
                  <select v-model="scheduleForm.facility_id" required class="flex h-10 w-full border border-zinc-200 rounded-md px-3 text-sm bg-white"><option value="" disabled>Pilih...</option><option v-for="f in facilities" :key="f.id" :value="f.id">{{ f.name }}</option></select>
                </div>
                <div class="space-y-2">
                  <Label>Eskul Pemilik</Label>
                  <select v-model="scheduleForm.organization_id" required class="flex h-10 w-full border border-zinc-200 rounded-md px-3 text-sm bg-white"><option value="" disabled>Pilih...</option><option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option></select>
                </div>
                <div class="space-y-2">
                  <Label>Hari Hak Akses</Label>
                  <select v-model="scheduleForm.day" required class="flex h-10 w-full border border-zinc-200 rounded-md px-3 text-sm bg-white"><option value="Monday">Senin</option><option value="Tuesday">Selasa</option><option value="Wednesday">Rabu</option><option value="Thursday">Kamis</option><option value="Friday">Jumat</option></select>
                </div>
                <div class="space-y-2"><Label>Jam Mulai</Label><Input v-model="scheduleForm.start_time" type="time" required /></div>
              </div>
              <div class="space-y-2"><Label>Jam Selesai</Label><Input v-model="scheduleForm.end_time" type="time" required /></div>
              <Button :disabled="isCreatingSchedule" type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white">{{ isCreatingSchedule ? 'Mendaftarkan...' : 'Kunci Jadwal Tetap' }}</Button>
            </form>
          </Card>

        </div>

        <!-- MANAJEMEN AKUN ESKUL -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div class="lg:col-span-1">
            <h2 class="text-xl font-bold text-zinc-900 mb-4">Buat Akun Eskul</h2>
            <Card class="border-zinc-200 shadow-sm p-6">
              <form @submit.prevent="createEskulUser" class="space-y-4">
                <div class="space-y-2">
                  <Label for="new_org">Pilih Organisasi / Eskul</Label>
                  <select id="new_org" v-model="newUserForm.organization_id" required class="flex h-10 w-full items-center justify-between rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm"><option value="" disabled>Pilih Eskul aslinya...</option><option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option></select>
                </div>
                <div class="space-y-2"><Label for="new_name">Nama Tampilan Akun</Label><Input id="new_name" v-model="newUserForm.name" type="text" placeholder="Contoh: Official Basket" required /></div>
                <div class="space-y-2"><Label for="new_email">Email Dinas</Label><Input id="new_email" v-model="newUserForm.email" type="email" placeholder="Contoh: basket@coordina.com" required /></div>
                <div class="space-y-2"><Label for="new_password">Password Awal</Label><Input id="new_password" v-model="newUserForm.password" type="password" minlength="6" placeholder="Minimal 6 karakter" required /></div>
                <Button :disabled="isCreatingUser" type="submit" class="w-full bg-zinc-900 text-white">Buat Akun</Button>
              </form>
            </Card>
          </div>

          <div class="lg:col-span-2">
            <h2 class="text-xl font-bold text-zinc-900 mb-4">Daftar Akun Eskul Aktif</h2>
            <Card class="border-zinc-200 shadow-sm p-0 overflow-hidden">
              <div v-if="loadingUsers" class="p-6 text-zinc-500">Memuat data akun...</div>
              <table v-else class="w-full text-sm text-left">
                <thead class="bg-zinc-50 border-b border-zinc-200 text-zinc-600 uppercase text-xs">
                  <tr><th class="px-6 py-4 font-semibold">Nama / Perwakilan</th><th class="px-6 py-4 font-semibold">Email Login</th><th class="px-6 py-4 font-semibold text-right">Aksi Keamanan</th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white">
                  <tr v-for="user in eskulUsers" :key="user.id" class="hover:bg-zinc-50/50 transition-colors">
                    <td class="px-6 py-4 font-medium text-zinc-900">{{ user.name }}</td>
                    <td class="px-6 py-4 text-zinc-600">{{ user.email }}</td>
                    <td class="px-6 py-4 text-right space-x-2">
                      <Button @click="openPasswordReset(user.id, user.name)" variant="outline" size="sm" class="border-zinc-300 text-zinc-700 hover:bg-zinc-100">Reset Password</Button>
                      <Button @click="confirmDeleteUser(user.id, user.name)" variant="outline" size="sm" class="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700">Hapus</Button>
                    </td>
                  </tr>
                  <tr v-if="eskulUsers.length === 0"><td colspan="3" class="px-6 py-8 text-center text-zinc-500">Belum ada akun eskul yang terdaftar.</td></tr>
                </tbody>
              </table>
            </Card>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>