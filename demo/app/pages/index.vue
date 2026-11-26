<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  ClipboardList,
  CalendarDays,
  Send,
  FolderArchive,
  UserCheck,
  FileCheck2,
  RotateCcw,
  CheckCircle2,
  AlertTriangle,
  Info,
  X,
} from 'lucide-vue-next'
import { useDemoStore } from '~/stores/demoStore'
import AppDialog from '~/components/AppDialog.vue'
import LetterPreviewDialog from '~/components/LetterPreviewDialog.vue'
import RequestsMasterDetail from '~/components/RequestsMasterDetail.vue'
import ScheduleBoard from '~/components/ScheduleBoard.vue'
import BorrowRequestForm from '~/components/BorrowRequestForm.vue'
import DocumentArchivePanel from '~/components/DocumentArchivePanel.vue'
import OrganizationProfilePanel from '~/components/OrganizationProfilePanel.vue'
import type { BorrowRequestItem, DemoRole, OrganizationProfile, WeekdayKey } from '~/types/coordina'

type TabId = 'tugas' | 'jadwal' | 'ajukan' | 'arsip' | 'profil'

const store = useDemoStore()
const activeTab = ref<TabId>('tugas')
const selectedRequestId = ref<number | null>(101)
const preselectedFacilityId = ref<number | null>(null)
const previewLetter = ref<BorrowRequestItem | null>(null)
const previewOpen = ref(false)

const toast = ref<{ open: boolean; tone: 'emerald' | 'rose' | 'blue'; title: string; message: string }>({
  open: false,
  tone: 'blue',
  title: '',
  message: '',
})
let toastTimeout: ReturnType<typeof setTimeout> | null = null

const rejectDialog = ref<{
  open: boolean
  mode: 'screen' | 'owner'
  request: BorrowRequestItem | null
  reason: string
  error: string
}>({
  open: false,
  mode: 'screen',
  request: null,
  reason: '',
  error: '',
})

const navTabs = computed(() => [
  { id: 'tugas' as TabId, label: 'Tugas & Persetujuan', icon: ClipboardList, badge: store.activeActionCount },
  { id: 'jadwal' as TabId, label: 'Jadwal Fasilitas', icon: CalendarDays, badge: 0 },
  { id: 'ajukan' as TabId, label: 'Ajukan Peminjaman', icon: Send, badge: 0 },
  { id: 'arsip' as TabId, label: 'Arsip Surat Resmi', icon: FolderArchive, badge: 0 },
  { id: 'profil' as TabId, label: 'Profil & Tanda Tangan', icon: UserCheck, badge: 0 },
])

function notify(title: string, message: string, tone: 'emerald' | 'rose' | 'blue' = 'blue') {
  if (toastTimeout) clearTimeout(toastTimeout)
  toast.value = { open: true, tone, title, message }
  if (tone !== 'rose') {
    toastTimeout = setTimeout(() => {
      toast.value.open = false
    }, 5500)
  }
}

function handleSwitchRole(nextRole: DemoRole) {
  store.switchRole(nextRole)
  notify(
    nextRole === 'mpk' ? 'Mode Pengurus MPK' : `Mode Pengurus ${store.activeOrganization.name}`,
    nextRole === 'mpk'
      ? 'Menampilkan antrean pemeriksaan, verifikasi akhir, pengajuan MPK (Alur B), dan manajemen jadwal.'
      : `Menampilkan permohonan dan arsip yang melibatkan ${store.activeOrganization.name}.`,
    'blue'
  )
}

function handleSwitchEskul(event: Event) {
  const id = Number((event.target as HTMLSelectElement).value)
  store.switchEskulOrg(id)
  notify(
    `Organisasi Aktif: ${store.activeOrganization.name}`,
    'Tampilan kini menyimulasikan sudut pandang organisasi ekskul tersebut.',
    'blue'
  )
}

function handleBorrowNow(facilityId: number) {
  preselectedFacilityId.value = facilityId
  activeTab.value = 'ajukan'
  notify(
    'Formulir Pengajuan Siap',
    'Fasilitas terpilih otomatis. Silakan lengkapi waktu, alasan, dan konfirmasi tanda tangan resmi.',
    'blue'
  )
}

function handleSubmitRequest(payload: {
  requesterOrgId: number
  facilityId: number
  date: string
  startTime: string
  endTime: string
  reason: string
  confirmSignature: boolean
}) {
  const res = store.submitRequest(payload)
  if (!res.ok) {
    notify('Pengajuan Tidak Dapat Dikirim', res.message, 'rose')
    return
  }
  if (res.created) selectedRequestId.value = res.created.id
  activeTab.value = 'tugas'
  notify('Pengajuan Tercatat', res.message, 'emerald')
}

function handleScreenAccept(req: BorrowRequestItem) {
  const res = store.screenRequest(req.id, 'accept')
  notify(res.ok ? 'Pemeriksaan MPK Berhasil' : 'Gagal', res.message, res.ok ? 'emerald' : 'rose')
}

function openRejectModal(mode: 'screen' | 'owner', req: BorrowRequestItem) {
  rejectDialog.value = { open: true, mode, request: req, reason: '', error: '' }
}

function submitRejectModal() {
  const dlg = rejectDialog.value
  dlg.error = ''
  if (!dlg.reason.trim()) {
    dlg.error = 'Alasan penolakan wajib ditulis agar pemohon mengetahui alasannya.'
    return
  }
  if (!dlg.request) return
  const res =
    dlg.mode === 'screen'
      ? store.screenRequest(dlg.request.id, 'reject', dlg.reason)
      : store.ownerDecision(dlg.request.id, 'reject', dlg.reason)
  if (!res.ok) {
    dlg.error = res.message
    return
  }
  dlg.open = false
  notify('Penolakan Tercatat', res.message, 'blue')
}

function handleOwnerApprove(req: BorrowRequestItem) {
  const res = store.ownerDecision(req.id, 'approve')
  notify(res.ok ? 'Izin Jadwal Disetujui' : 'Tidak Dapat Menyetujui', res.message, res.ok ? 'emerald' : 'rose')
}

function handleFinalize(req: BorrowRequestItem) {
  const res = store.finalizeAndPublish(req.id)
  notify(res.ok ? 'Proses Akhir Selesai' : 'Gagal', res.message, res.ok ? 'emerald' : 'rose')
}

function openPreview(req: BorrowRequestItem) {
  previewLetter.value = req
  previewOpen.value = true
}

function handleSaveProfile(orgId: number, updates: Partial<OrganizationProfile>) {
  store.updateOrganizationProfile(orgId, updates)
  notify('Profil Demo Diperbarui', 'Perubahan identitas atau aset tanda tangan tersimpan di sesi demo.', 'emerald')
}

function handleCreateAccount(payload: { name: string; accountEmail: string; chairpersonName: string }) {
  store.createEskulAccount(payload)
  notify('Akun Ekskul Ditambahkan', `${payload.name} ditambahkan ke daftar simulasi.`, 'emerald')
}

function handleToggleAccount(orgId: number) {
  store.toggleAccountActive(orgId)
  notify('Status Akun Diperbarui', 'Status aktif akun organisasi berhasil diubah.', 'blue')
}

function handleAddFacility(payload: { name: string; type: 'Indoor' | 'Outdoor'; description: string }) {
  store.addFacility(payload)
  notify('Fasilitas Ditambahkan', `${payload.name} kini tersedia pada jadwal fasilitas.`, 'emerald')
}

function handleAddSchedule(payload: {
  facilityId: number
  organizationId: number
  day: WeekdayKey
  startTime: string
  endTime: string
}) {
  store.addFixedSchedule(payload)
  notify('Jadwal Rutin Dikunci', 'Jadwal tetap baru berhasil ditambahkan pada hari tersebut.', 'emerald')
}

function handleDeleteSchedule(scheduleId: number) {
  store.deleteFixedSchedule(scheduleId)
  notify('Jadwal Dihapus', 'Hak jadwal rutin tersebut telah dilepas.', 'blue')
}

function handleResetDemo() {
  store.resetDemoData()
  selectedRequestId.value = 101
  activeTab.value = 'tugas'
  notify('Data Demo Direset', 'Seluruh contoh pengajuan, jadwal, dan profil dikembalikan ke kondisi awal.', 'blue')
}

onMounted(() => {
  store.initFromStorage()
})

onUnmounted(() => {
  if (toastTimeout) clearTimeout(toastTimeout)
})
</script>

<template>
  <div class="min-h-screen bg-[#F4F7FA] text-[#0F172A] flex flex-col">
    <!-- REJECTION REASON DIALOG -->
    <AppDialog
      :open="rejectDialog.open"
      :title="rejectDialog.mode === 'screen' ? 'Tolak pada Pemeriksaan Awal MPK' : 'Tolak Peminjaman Jadwal'"
      description="Setiap penolakan wajib disertai alasan tertulis agar dapat dibaca oleh organisasi pemohon."
      @update:open="(v) => (rejectDialog.open = v)"
    >
      <form @submit.prevent="submitRejectModal" class="space-y-4">
        <div class="space-y-1.5">
          <label for="reject-reason" class="block text-sm font-semibold text-[#0F172A]">Alasan Penolakan (Wajib)</label>
          <textarea
            id="reject-reason"
            v-model="rejectDialog.reason"
            rows="3"
            placeholder="Contoh: Jadwal bentrok dengan persiapan lomba atau informasi kegiatan belum jelas…"
            class="w-full rounded-xl border border-[#DCE4EE] p-3 text-sm text-[#0F172A]"
          />
        </div>
        <p v-if="rejectDialog.error" role="alert" class="text-xs font-semibold text-[#BE123C]">{{ rejectDialog.error }}</p>
        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl border border-[#DCE4EE] text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC]"
            @click="rejectDialog.open = false"
          >
            Batal
          </button>
          <button
            type="submit"
            class="px-4 py-2 rounded-xl bg-[#BE123C] hover:bg-rose-800 text-white text-sm font-semibold"
          >
            Kirim Penolakan
          </button>
        </div>
      </form>
    </AppDialog>

    <!-- SIMULATED PRINTABLE LETTER PREVIEW -->
    <LetterPreviewDialog
      :open="previewOpen"
      :request="previewLetter"
      :organizations="store.organizations"
      :facilities="store.facilities"
      @update:open="(v) => (previewOpen = v)"
    />

    <!-- INSTITUTIONAL HEADER + ROLE SWITCHER -->
    <header class="bg-[#1E3A5F] text-white shadow-sm no-print">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5 min-w-0">
          <div class="h-10 w-10 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0">
            <FileCheck2 class="w-5 h-5 text-white" aria-hidden="true" />
          </div>
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="text-lg sm:text-xl font-bold text-white leading-tight">Coordina</h1>
              <span class="px-2 py-0.5 rounded-md bg-white/15 border border-white/20 text-xs font-semibold text-blue-100">
                Simulasi Demo
              </span>
            </div>
            <p class="text-xs sm:text-sm text-blue-100/90 truncate">
              SMKN 4 Padalarang · {{ store.activeOrganization.name }}
            </p>
          </div>
        </div>

        <!-- PRESENTER ROLE & ORGANIZATION SWITCHER -->
        <div class="flex flex-wrap items-center gap-2.5">
          <div class="inline-flex rounded-xl bg-[#152943] p-1 border border-white/15" role="group" aria-label="Pilih Peran Simulasi">
            <button
              type="button"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                store.role === 'mpk' ? 'bg-white text-[#1E3A5F] shadow-sm' : 'text-blue-100 hover:text-white',
              ]"
              @click="handleSwitchRole('mpk')"
            >
              Pengurus MPK
            </button>
            <button
              type="button"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors',
                store.role === 'eskul' ? 'bg-white text-[#1E3A5F] shadow-sm' : 'text-blue-100 hover:text-white',
              ]"
              @click="handleSwitchRole('eskul')"
            >
              Pengurus Ekskul
            </button>
          </div>

          <div v-if="store.role === 'eskul'" class="flex items-center">
            <label for="demo-eskul-org" class="sr-only">Pilih Organisasi Ekskul Simulasi</label>
            <select
              id="demo-eskul-org"
              :value="store.activeEskulOrgId"
              class="h-9 rounded-xl border border-white/20 bg-[#152943] px-3 text-xs font-semibold text-white"
              @change="handleSwitchEskul"
            >
              <option v-for="org in store.eskulOrganizations" :key="org.id" :value="org.id">
                Akun: {{ org.name }}
              </option>
            </select>
          </div>

          <button
            type="button"
            class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-white/20 bg-white/10 hover:bg-white/20 text-xs font-semibold text-white"
            title="Kembalikan seluruh data demo ke kondisi awal"
            @click="handleResetDemo"
          >
            <RotateCcw class="w-3.5 h-3.5" aria-hidden="true" />
            <span>Reset Demo</span>
          </button>
        </div>
      </div>

      <!-- 5 APPROVED NAVIGATION SECTIONS -->
      <nav aria-label="Navigasi Utama Demo" class="bg-[#F8FAFC] border-t border-white/10 border-b border-[#DCE4EE]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2 flex items-center gap-1.5 overflow-x-auto" role="tablist">
          <button
            v-for="tab in navTabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :class="[
              'whitespace-nowrap inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold border transition-colors',
              activeTab === tab.id
                ? 'bg-white text-[#1E3A5F] border-[#DCE4EE] shadow-sm'
                : 'bg-transparent text-[#475569] border-transparent hover:bg-white/80 hover:text-[#0F172A]',
            ]"
            @click="activeTab = tab.id"
          >
            <component :is="tab.icon" class="w-4 h-4 shrink-0" aria-hidden="true" />
            <span>{{ tab.label }}</span>
            <span
              v-if="tab.badge > 0"
              :class="[
                'px-2 py-0.5 rounded-full text-xs font-bold tabular-nums leading-none',
                activeTab === tab.id ? 'bg-[#1E3A5F] text-white' : 'bg-[#FFFBEB] text-[#B45309] border border-[#FDE68A]',
              ]"
            >
              {{ tab.badge }}
            </span>
          </button>
        </div>
      </nav>
    </header>

    <!-- WORKSPACE -->
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 flex-1 space-y-5 no-print">
      <!-- Live Feedback Toast -->
      <div
        v-if="toast.open"
        :role="toast.tone === 'rose' ? 'alert' : 'status'"
        :aria-live="toast.tone === 'rose' ? 'assertive' : 'polite'"
        :class="[
          'rounded-2xl border px-4 py-3.5 flex items-start justify-between gap-3 shadow-sm',
          toast.tone === 'emerald'
            ? 'bg-[#ECFDF5] border-[#A7F3D0] text-[#15803D]'
            : toast.tone === 'rose'
              ? 'bg-[#FFF1F2] border-[#FECDD3] text-[#BE123C]'
              : 'bg-[#EFF6FF] border-[#BFDBFE] text-[#1E3A5F]',
        ]"
      >
        <div class="flex items-start gap-2.5 text-sm">
          <component
            :is="toast.tone === 'emerald' ? CheckCircle2 : toast.tone === 'rose' ? AlertTriangle : Info"
            class="w-5 h-5 shrink-0 mt-0.5"
            aria-hidden="true"
          />
          <div>
            <p class="font-bold">{{ toast.title }}</p>
            <p class="text-xs sm:text-sm mt-0.5">{{ toast.message }}</p>
          </div>
        </div>
        <button
          type="button"
          aria-label="Tutup pemberitahuan"
          class="rounded-lg p-1 hover:bg-black/5 shrink-0"
          @click="toast.open = false"
        >
          <X class="w-4 h-4" aria-hidden="true" />
        </button>
      </div>

      <RequestsMasterDetail
        v-if="activeTab === 'tugas'"
        v-model:selected-id="selectedRequestId"
        :requests="store.visibleRequests"
        :role="store.role"
        :active-eskul-org-id="store.activeEskulOrgId"
        :organizations="store.organizations"
        :facilities="store.facilities"
        @screen-accept="handleScreenAccept"
        @screen-reject="(r) => openRejectModal('screen', r)"
        @owner-approve="handleOwnerApprove"
        @owner-reject="(r) => openRejectModal('owner', r)"
        @finalize="handleFinalize"
        @preview-letter="openPreview"
        @go-apply="activeTab = 'ajukan'"
      />

      <ScheduleBoard
        v-else-if="activeTab === 'jadwal'"
        :role="store.role"
        :facilities="store.facilities"
        :fixed-schedules="store.fixedSchedules"
        :requests="store.requests"
        :organizations="store.organizations"
        @borrow-now="handleBorrowNow"
        @add-facility="handleAddFacility"
        @add-schedule="handleAddSchedule"
        @delete-schedule="handleDeleteSchedule"
      />

      <BorrowRequestForm
        v-else-if="activeTab === 'ajukan'"
        :role="store.role"
        :active-org="store.activeOrganization"
        :organizations="store.organizations"
        :facilities="store.facilities"
        :fixed-schedules="store.fixedSchedules"
        :requests="store.requests"
        :preselected-facility-id="preselectedFacilityId"
        @submit="handleSubmitRequest"
        @go-profile="activeTab = 'profil'"
      />

      <DocumentArchivePanel
        v-else-if="activeTab === 'arsip'"
        :documents="store.archivedDocuments"
        :organizations="store.organizations"
        :facilities="store.facilities"
        @preview="openPreview"
      />

      <OrganizationProfilePanel
        v-else-if="activeTab === 'profil'"
        :role="store.role"
        :active-org="store.activeOrganization"
        :organizations="store.organizations"
        @save-profile="handleSaveProfile"
        @create-account="handleCreateAccount"
        @toggle-active="handleToggleAccount"
      />
    </main>
  </div>
</template>

