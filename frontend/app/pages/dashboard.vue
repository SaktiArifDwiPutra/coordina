<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import {
  ClipboardList,
  CalendarDays,
  Send,
  FolderArchive,
  Users,
  Settings,
  KeyRound,
  LogOut,
  FileCheck2,
  ShieldCheck,
  CheckCircle2,
  AlertTriangle,
  Info,
  X,
  Copy,
  Check,
} from 'lucide-vue-next'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import AppDialog from '~/components/ui/dialog/AppDialog.vue'
import NotificationsBell from '~/components/dashboard/NotificationsBell.vue'
import RequestsMasterDetail from '~/components/dashboard/RequestsMasterDetail.vue'
import ScheduleBoard from '~/components/dashboard/ScheduleBoard.vue'
import BorrowRequestForm from '~/components/dashboard/BorrowRequestForm.vue'
import MpkMasterDataPanel from '~/components/dashboard/MpkMasterDataPanel.vue'
import OrganizationProfilePanel from '~/components/dashboard/OrganizationProfilePanel.vue'
import TemplateManagerPanel from '~/components/dashboard/TemplateManagerPanel.vue'
import DocumentArchivePanel from '~/components/dashboard/DocumentArchivePanel.vue'

definePageMeta({
  middleware: ['auth', 'role'],
  roles: ['admin_mpk', 'owner_eskul'],
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { apiFetch, extractErrorMessage } = useApi()

const pageLoading = ref(true)
let autoSyncInterval = null
let toastTimer = null

const facilities = ref([])
const organizations = ref([])
const borrowRequests = ref([])
const eskulUsers = ref([])
const notifications = ref([])
const unreadCount = ref(0)
const orgProfile = ref(null)
const templates = ref([])
const requiredPlaceholders = ref([])
const archiveItems = ref([])

const loading = ref(true)
const loadingRequests = ref(true)
const loadingArchive = ref(false)
const loadingUsers = ref(false)
const loadingProfile = ref(false)
const orgProfileError = ref('')
const arsipSubTab = ref('archive') // 'archive' | 'templates'
const downloadingDocumentId = ref(null)
const regeneratingDocumentId = ref(null)
const screeningId = ref(null)
const finalizingId = ref(null)
const signingId = ref(null)
const togglingUserId = ref(null)

const isSubmittingRequest = ref(false)
const isCreatingFacility = ref(false)
const isCreatingSchedule = ref(false)
const isCreatingUser = ref(false)
const isSavingProfile = ref(false)
const isUploadingAssets = ref(false)
const isUploadingTemplate = ref(false)
const isGeneratingDefaultTemplate = ref(false)

const preselectedFacilityId = ref('')
const activeTab = ref('')

// Non-blocking accessible feedback banner / toast
const statusToast = ref({ open: false, title: '', message: '', type: 'info' })

// Real interactive dialogs (native <dialog> via AppDialog)
const passwordModal = ref({
  isOpen: false,
  userId: null,
  userName: '',
  userEmail: '',
  newPassword: '',
  mustChangePassword: true,
  isSubmitting: false,
})
const handoverModal = ref({
  isOpen: false,
  title: '',
  userName: '',
  email: '',
  password: '',
  copied: false,
})
const changePasswordModal = ref({
  isOpen: false,
  forced: false,
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
  error: '',
  isSubmitting: false,
})
const deleteModal = ref({
  isOpen: false,
  kind: 'user', // 'user' | 'facility' | 'schedule'
  targetId: null,
  title: '',
  description: '',
  isSubmitting: false,
})
const decisionModal = ref({
  isOpen: false,
  mode: '', // 'screen_reject' | 'owner_approve' | 'owner_reject' | 'request_correction'
  request: null,
  reason: '',
  error: '',
  isSubmitting: false,
})
const reviseModal = ref({
  isOpen: false,
  request: null,
  facility_id: '',
  date: '',
  start_time: '',
  end_time: '',
  reason: '',
  error: '',
  confirm_signature: true,
  isSubmitting: false,
})

const isMpk = computed(() => auth.user?.role === 'admin_mpk')

const incomingOwnerCount = computed(() =>
  borrowRequests.value.filter(
    (r) =>
      r.owner_organization_id === auth.user?.organization_id &&
      (r.status === 'pending_owner' || (r.status === 'owner_approved' && r.document?.document_status === 'waiting_owner_signature'))
  ).length
)

const myRevisionNeededCount = computed(() =>
  borrowRequests.value.filter(
    (r) =>
      (r.organization_id === auth.user?.organization_id || r.user_id === auth.user?.id) &&
      r.document?.document_status === 'needs_correction'
  ).length
)

const mpkActionCount = computed(() =>
  borrowRequests.value.filter(
    (r) => r.status === 'pending_mpk_screening' || ['owner_approved', 'owner_rejected'].includes(r.status)
  ).length
)

const roleTabs = computed(() => {
  if (isMpk.value) {
    return [
      { id: 'meja-kerja', label: 'Meja Kerja MPK', icon: ClipboardList, badge: mpkActionCount.value },
      { id: 'jadwal', label: 'Jadwal Fasilitas', icon: CalendarDays, badge: 0 },
      { id: 'pinjam-mpk', label: 'Peminjaman Resmi MPK', icon: Send, badge: 0 },
      { id: 'arsip-template', label: 'Arsip & Template', icon: FolderArchive, badge: 0 },
      { id: 'master-data', label: 'Data Master & Akun', icon: Users, badge: 0 },
      { id: 'pengaturan', label: 'Pengaturan', icon: Settings, badge: 0 },
    ]
  }
  return [
    { id: 'permohonan', label: 'Permohonan & Izin', icon: ClipboardList, badge: incomingOwnerCount.value + myRevisionNeededCount.value },
    { id: 'jadwal', label: 'Jadwal Fasilitas', icon: CalendarDays, badge: 0 },
    { id: 'ajukan', label: 'Ajukan Peminjaman', icon: Send, badge: 0 },
    { id: 'arsip', label: 'Arsip Surat', icon: FolderArchive, badge: 0 },
    { id: 'pengaturan', label: 'Pengaturan', icon: Settings, badge: 0 },
  ]
})

function setActiveTab(tabId) {
  activeTab.value = tabId
  router.replace({ query: { ...route.query, tab: tabId } })
}

watch(
  [isMpk, () => route.query.tab],
  ([mpk, queryTab]) => {
    const validIds = roleTabs.value.map((t) => t.id)
    if (typeof queryTab === 'string' && validIds.includes(queryTab)) {
      activeTab.value = queryTab
    } else if (!validIds.includes(activeTab.value)) {
      activeTab.value = mpk ? 'meja-kerja' : 'permohonan'
    }
  },
  { immediate: true }
)

const myVisibleRequests = computed(() => {
  if (isMpk.value) return borrowRequests.value
  return borrowRequests.value.filter(
    (r) =>
      r.owner_organization_id === auth.user?.organization_id ||
      r.organization_id === auth.user?.organization_id ||
      r.user_id === auth.user?.id
  )
})

function showFeedback(title, message, type = 'info') {
  if (toastTimer) clearTimeout(toastTimer)
  statusToast.value = { open: true, title, message, type }
  if (type !== 'error') {
    toastTimer = setTimeout(() => {
      statusToast.value.open = false
    }, 6000)
  }
}

function goToProfileTab() {
  setActiveTab('pengaturan')
}

function goToBorrowTab(facilityId = '') {
  if (facilityId) {
    preselectedFacilityId.value = Number(facilityId)
    showFeedback(
      'Fasilitas Dipilih',
      'Silakan lengkapi tanggal, jam, alasan, dan konfirmasi tanda tangan resmi sesuai prosedur pengajuan.',
      'info'
    )
  }
  setActiveTab(isMpk.value ? 'pinjam-mpk' : 'ajukan')
}

async function fetchFacilities(silent = false) {
  if (!silent) loading.value = true
  try {
    const res = await apiFetch('/api/facilities')
    facilities.value = res.data
  } catch (error) {
    console.error(error)
  } finally {
    if (!silent) loading.value = false
  }
}

async function fetchBorrowRequests(silent = false) {
  if (!silent) loadingRequests.value = true
  try {
    const res = await apiFetch('/api/borrow-requests')
    borrowRequests.value = res.data
  } catch (error) {
    console.error(error)
  } finally {
    if (!silent) loadingRequests.value = false
  }
}

async function fetchNotifications() {
  try {
    const res = await apiFetch('/api/notifications')
    notifications.value = res.data
    unreadCount.value = res.unread_count
  } catch (error) {
    console.error(error)
  }
}

async function fetchEskulUsers(silent = false) {
  if (!silent) loadingUsers.value = true
  try {
    const res = await apiFetch('/api/users')
    eskulUsers.value = res.data
  } catch (error) {
    console.error(error)
  } finally {
    if (!silent) loadingUsers.value = false
  }
}

async function fetchOrganizations() {
  try {
    const res = await apiFetch('/api/organizations')
    organizations.value = res.data
  } catch (error) {
    console.error(error)
  }
}

async function fetchOrganizationProfile(silent = true) {
  if (!silent) loadingProfile.value = true
  orgProfileError.value = ''
  try {
    const res = await apiFetch('/api/organization/profile')
    orgProfile.value = res.data
  } catch (error) {
    orgProfileError.value = extractErrorMessage(error, 'Gagal memuat profil organisasi.')
    console.error(error)
  } finally {
    if (!silent) loadingProfile.value = false
  }
}

async function fetchTemplates() {
  try {
    const res = await apiFetch('/api/document-templates')
    templates.value = res.data
    requiredPlaceholders.value = res.required_placeholders || []
  } catch (error) {
    console.error(error)
  }
}

async function fetchArchive(silent = false) {
  if (!silent) loadingArchive.value = true
  try {
    const res = await apiFetch('/api/documents/archive')
    archiveItems.value = res.data || []
  } catch (error) {
    console.error(error)
  } finally {
    if (!silent) loadingArchive.value = false
  }
}

async function refreshAllData(silent = true) {
  const tasks = [
    fetchFacilities(silent),
    fetchBorrowRequests(silent),
    fetchArchive(silent),
    fetchNotifications(),
    fetchOrganizationProfile(),
  ]
  if (isMpk.value) {
    tasks.push(fetchEskulUsers(silent), fetchTemplates())
  }
  await Promise.all(tasks)
}

async function handleSubmitBorrowRequest(payload, resetForm) {
  isSubmittingRequest.value = true
  try {
    const res = await apiFetch('/api/borrow-requests', { method: 'POST', body: payload })
    showFeedback('Pengajuan Terkirim', res.message, 'success')
    resetForm()
    preselectedFacilityId.value = ''
    await refreshAllData(true)
    setActiveTab(isMpk.value ? 'meja-kerja' : 'permohonan')
  } catch (error) {
    showFeedback('Gagal Mengirim Pengajuan', extractErrorMessage(error, 'Gagal mengirim pengajuan.'), 'error')
  } finally {
    isSubmittingRequest.value = false
  }
}

async function handleScreenAccept(req) {
  if (screeningId.value === req.id) return
  if (!orgProfile.value?.is_complete_for_signing) {
    showFeedback('Profil Belum Lengkap', 'Lengkapi profil dan gambar tanda tangan MPK terlebih dahulu.', 'error')
    return
  }

  screeningId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/screen`, {
      method: 'POST',
      body: { decision: 'accept', confirm_signature: true },
    })
    showFeedback('Screening Disimpan', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Memproses Screening', extractErrorMessage(error, 'Gagal memproses screening.'), 'error')
  } finally {
    screeningId.value = null
  }
}

async function handleSignDocument(req) {
  if (signingId.value === req.id) return
  if (!orgProfile.value?.is_complete_for_signing) {
    showFeedback('Profil Belum Lengkap', 'Lengkapi profil dan gambar tanda tangan organisasi terlebih dahulu.', 'error')
    return
  }

  signingId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/sign`, { method: 'POST' })
    showFeedback('Tanda Tangan Tersimpan', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Membubuhkan TTD', extractErrorMessage(error, 'Gagal membubuhkan tanda tangan.'), 'error')
  } finally {
    signingId.value = null
  }
}

function openDecisionModal(mode, req) {
  if (mode === 'owner_approve' && !orgProfile.value?.is_complete_for_signing) {
    showFeedback('Profil Belum Lengkap', 'Lengkapi profil dan gambar tanda tangan organisasi Anda terlebih dahulu.', 'error')
    return
  }
  decisionModal.value = { isOpen: true, mode, request: req, reason: '', error: '', isSubmitting: false }
}

async function submitDecisionModal() {
  const { mode, request, reason } = decisionModal.value
  decisionModal.value.error = ''
  if ((mode === 'screen_reject' || mode === 'owner_reject' || mode === 'request_correction') && !reason.trim()) {
    decisionModal.value.error = 'Harap tuliskan alasan atau catatan revisi sebelum melanjutkan.'
    return
  }

  decisionModal.value.isSubmitting = true
  try {
    let res
    if (mode === 'screen_reject') {
      res = await apiFetch(`/api/borrow-requests/${request.id}/screen`, {
        method: 'POST',
        body: { decision: 'reject', rejection_reason: reason.trim() },
      })
    } else if (mode === 'request_correction') {
      res = await apiFetch(`/api/borrow-requests/${request.id}/request-correction`, {
        method: 'POST',
        body: { reason: reason.trim() },
      })
    } else {
      res = await apiFetch(`/api/borrow-requests/${request.id}/owner-decision`, {
        method: 'POST',
        body: {
          decision: mode === 'owner_approve' ? 'approve' : 'reject',
          rejection_reason: mode === 'owner_reject' ? reason.trim() : null,
          confirm_signature: mode === 'owner_approve',
        },
      })
    }
    decisionModal.value.isOpen = false
    showFeedback('Keputusan Disimpan', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    decisionModal.value.error = extractErrorMessage(error, 'Gagal memproses keputusan.')
  } finally {
    decisionModal.value.isSubmitting = false
  }
}

function openReviseModal(req) {
  reviseModal.value = {
    isOpen: true,
    request: req,
    facility_id: String(req.facility_id),
    date: req.date,
    start_time: String(req.start_time || '').substring(0, 5),
    end_time: String(req.end_time || '').substring(0, 5),
    reason: req.reason || '',
    error: '',
    confirm_signature: Boolean(orgProfile.value?.is_complete_for_signing),
    isSubmitting: false,
  }
}

async function submitReviseModal() {
  const modal = reviseModal.value
  modal.error = ''
  if (!modal.facility_id || !modal.date || !modal.start_time || !modal.end_time || !modal.reason.trim()) {
    modal.error = 'Lengkapi seluruh data revisi permohonan terlebih dahulu.'
    return
  }

  modal.isSubmitting = true
  try {
    const res = await apiFetch(`/api/borrow-requests/${modal.request.id}/revise`, {
      method: 'POST',
      body: {
        facility_id: Number(modal.facility_id),
        date: modal.date,
        start_time: modal.start_time,
        end_time: modal.end_time,
        reason: modal.reason.trim(),
        confirm_signature: Boolean(modal.confirm_signature),
      },
    })
    reviseModal.value.isOpen = false
    showFeedback('Revisi Terkirim', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    modal.error = extractErrorMessage(error, 'Gagal mengirim revisi permohonan.')
  } finally {
    modal.isSubmitting = false
  }
}

async function handleSaveProfile(payload) {
  isSavingProfile.value = true
  try {
    const res = await apiFetch('/api/organization/profile', { method: 'PUT', body: payload })
    orgProfile.value = res.data
    showFeedback('Profil Tersimpan', res.message, 'success')
  } catch (error) {
    showFeedback('Gagal Menyimpan Profil', extractErrorMessage(error, 'Gagal menyimpan profil organisasi.'), 'error')
  } finally {
    isSavingProfile.value = false
  }
}

async function handleUploadAssets(formData, resetFiles) {
  isUploadingAssets.value = true
  try {
    const res = await apiFetch('/api/organization/assets', { method: 'POST', body: formData })
    orgProfile.value = res.data
    resetFiles()
    showFeedback('Aset Diperbarui', res.message, 'success')
  } catch (error) {
    showFeedback('Gagal Mengunggah Aset', extractErrorMessage(error, 'Gagal mengunggah aset organisasi.'), 'error')
  } finally {
    isUploadingAssets.value = false
  }
}

async function handleUploadTemplate(formData, resetFile) {
  isUploadingTemplate.value = true
  try {
    const res = await apiFetch('/api/document-templates', { method: 'POST', body: formData })
    resetFile()
    showFeedback('Template Diaktifkan', res.message, 'success')
    await fetchTemplates()
  } catch (error) {
    showFeedback('Gagal Mengunggah Template', extractErrorMessage(error, 'Gagal mengunggah master template.'), 'error')
  } finally {
    isUploadingTemplate.value = false
  }
}

async function handleGenerateDefaultTemplate() {
  isGeneratingDefaultTemplate.value = true
  try {
    const res = await apiFetch('/api/document-templates/generate-default', { method: 'POST' })
    showFeedback('Template Default Dibuat', res.message, 'success')
    await fetchTemplates()
  } catch (error) {
    showFeedback('Gagal Membuat Template', extractErrorMessage(error, 'Gagal membuat template default.'), 'error')
  } finally {
    isGeneratingDefaultTemplate.value = false
  }
}

async function handleDownloadTemplate(tpl) {
  try {
    const blob = await apiFetch(`/api/document-templates/${tpl.id}/download`, { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `template-v${tpl.version}.docx`
    a.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    showFeedback('Gagal Mengunduh Template', extractErrorMessage(error, 'Gagal mengunduh template.'), 'error')
  }
}

async function handleFinalizeRequest(req) {
  if (finalizingId.value === req.id) return
  finalizingId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/finalize`, { method: 'POST' })
    showFeedback('Keputusan Akhir Tercatat', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Mencatat Keputusan', extractErrorMessage(error, 'Gagal mencatat keputusan akhir.'), 'error')
  } finally {
    finalizingId.value = null
  }
}

async function handleDownloadPdf(req, inline = false) {
  downloadingDocumentId.value = req.id
  try {
    const suffix = inline ? '?inline=1' : ''
    const blob = await apiFetch(`/api/borrow-requests/${req.id}/document/pdf${suffix}`, { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    if (inline) {
      const previewWin = window.open(url, '_blank', 'noopener')
      if (!previewWin) {
        const a = document.createElement('a')
        a.href = url
        a.download = `surat-izin-${req.id}.pdf`
        a.click()
        showFeedback('Pop-up Diblokir', 'Browser memblokir jendela pratinjau. File PDF resmi langsung diunduh ke perangkat Anda.', 'info')
      }
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } else {
      const a = document.createElement('a')
      a.href = url
      a.download = `surat-izin-${req.id}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    }
  } catch (error) {
    showFeedback('Gagal Mengunduh PDF', extractErrorMessage(error, 'Gagal mengunduh dokumen PDF resmi.'), 'error')
  } finally {
    downloadingDocumentId.value = null
  }
}

async function handleDownloadDocx(req) {
  downloadingDocumentId.value = req.id
  try {
    const blob = await apiFetch(`/api/borrow-requests/${req.id}/document/docx`, { responseType: 'blob' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `surat-izin-${req.id}.docx`
    a.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    showFeedback('Gagal Mengunduh DOCX', extractErrorMessage(error, 'Gagal mengunduh dokumen DOCX resmi.'), 'error')
  } finally {
    downloadingDocumentId.value = null
  }
}

async function handleRegeneratePdf(req) {
  regeneratingDocumentId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/regenerate-pdf`, { method: 'POST' })
    showFeedback('PDF Diproses', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Memproses PDF', extractErrorMessage(error, 'Gagal memproses PDF resmi.'), 'error')
  } finally {
    regeneratingDocumentId.value = null
  }
}

async function handleCreateFacility(payload, resetForm) {
  isCreatingFacility.value = true
  try {
    const res = await apiFetch('/api/facilities', { method: 'POST', body: payload })
    showFeedback('Fasilitas Ditambahkan', res.message, 'success')
    resetForm()
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Menambah Fasilitas', extractErrorMessage(error, 'Gagal menambahkan fasilitas.'), 'error')
  } finally {
    isCreatingFacility.value = false
  }
}

function openDeleteConfirmation(kind, id, name = '') {
  if (kind === 'facility') {
    deleteModal.value = {
      isOpen: true,
      kind: 'facility',
      targetId: id,
      title: 'Hapus Fasilitas?',
      description: 'Menghapus fasilitas akan menghapus seluruh jadwal tetap di dalamnya secara permanen.',
      isSubmitting: false,
    }
  } else if (kind === 'schedule') {
    deleteModal.value = {
      isOpen: true,
      kind: 'schedule',
      targetId: id,
      title: 'Hapus Jadwal Tetap?',
      description: 'Hak milik jadwal tetap ini akan dilepas dan slot menjadi terbuka.',
      isSubmitting: false,
    }
  } else {
    deleteModal.value = {
      isOpen: true,
      kind: 'user',
      targetId: id,
      title: 'Hapus Akun Ekskul?',
      description: `Hapus permanen akun ${name}? Riwayat surat organisasi tetap terjaga di arsip.`,
      isSubmitting: false,
    }
  }
}

async function executeDeleteConfirmed() {
  const { kind, targetId } = deleteModal.value
  deleteModal.value.isSubmitting = true
  try {
    const endpoint =
      kind === 'facility'
        ? `/api/facilities/${targetId}`
        : kind === 'schedule'
          ? `/api/fixed-schedules/${targetId}`
          : `/api/users/${targetId}`
    const res = await apiFetch(endpoint, { method: 'DELETE' })
    deleteModal.value.isOpen = false
    showFeedback('Data Dihapus', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Menghapus', extractErrorMessage(error, 'Gagal menghapus data.'), 'error')
  } finally {
    deleteModal.value.isSubmitting = false
  }
}

async function handleCreateSchedule(payload, resetForm) {
  isCreatingSchedule.value = true
  try {
    const res = await apiFetch('/api/fixed-schedules', { method: 'POST', body: payload })
    showFeedback('Jadwal Tetap Tersimpan', res.message, 'success')
    resetForm()
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Mendaftarkan Jadwal', extractErrorMessage(error, 'Gagal mendaftarkan jadwal tetap.'), 'error')
  } finally {
    isCreatingSchedule.value = false
  }
}

async function handleCreateUser(payload, resetForm) {
  isCreatingUser.value = true
  try {
    const res = await apiFetch('/api/users', { method: 'POST', body: payload })
    resetForm()
    if (res.generated_password) {
      handoverModal.value = {
        isOpen: true,
        title: 'Kredensial Akun Eskul Siap Serah Terima',
        userName: res.data?.name || payload.name,
        email: res.data?.email || payload.email,
        password: res.generated_password,
        copied: false,
      }
    } else {
      showFeedback('Akun Dibuat', res.message, 'success')
    }
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Membuat Akun', extractErrorMessage(error, 'Gagal membuat akun.'), 'error')
  } finally {
    isCreatingUser.value = false
  }
}

function generateModalTempPassword() {
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
  passwordModal.value.newPassword = chars.join('')
}

async function copyHandoverCredentials() {
  const { email, password } = handoverModal.value
  const text = `Email: ${email}\nPassword Sementara: ${password}`
  try {
    await navigator.clipboard.writeText(text)
    handoverModal.value.copied = true
  } catch {
    showFeedback('Salin Kredensial Manual', text, 'info')
  }
}

async function handleToggleUserActive(userId) {
  togglingUserId.value = userId
  try {
    const res = await apiFetch(`/api/users/${userId}/toggle-active`, { method: 'PATCH' })
    showFeedback('Status Akun Diperbarui', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Mengubah Status', extractErrorMessage(error, 'Gagal mengubah status akun.'), 'error')
  } finally {
    togglingUserId.value = null
  }
}

function openVoluntaryChangePassword() {
  changePasswordModal.value = {
    isOpen: true,
    forced: false,
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
    error: '',
    isSubmitting: false,
  }
}

async function submitChangePassword() {
  const m = changePasswordModal.value
  m.error = ''
  if (!m.current_password || !m.new_password || m.new_password !== m.new_password_confirmation) {
    m.error = 'Pastikan password saat ini diisi dan konfirmasi password baru cocok.'
    return
  }

  m.isSubmitting = true
  try {
    const res = await apiFetch('/api/change-password', {
      method: 'POST',
      body: {
        current_password: m.current_password,
        new_password: m.new_password,
        new_password_confirmation: m.new_password_confirmation,
      },
    })
    auth.user = res.user
    m.isOpen = false
    m.forced = false
    showFeedback('Password Diperbarui', res.message, 'success')
    await refreshAllData(false)
  } catch (error) {
    m.error = extractErrorMessage(error, 'Gagal mengganti password.')
  } finally {
    m.isSubmitting = false
  }
}

watch(
  () => auth.user?.must_change_password,
  (mustChange) => {
    if (mustChange) {
      changePasswordModal.value = {
        isOpen: true,
        forced: true,
        current_password: '',
        new_password: '',
        new_password_confirmation: '',
        error: '',
        isSubmitting: false,
      }
    }
  },
  { immediate: true }
)

async function submitPasswordReset() {
  passwordModal.value.isSubmitting = true
  try {
    const trimmed = passwordModal.value.newPassword.trim()
    const res = await apiFetch(`/api/users/${passwordModal.value.userId}/reset-password`, {
      method: 'PATCH',
      body: {
        password: trimmed || null,
        generate_password: !trimmed,
        must_change_password: Boolean(passwordModal.value.mustChangePassword),
      },
    })
    const targetName = passwordModal.value.userName
    const targetEmail = passwordModal.value.userEmail
    passwordModal.value.isOpen = false
    if (res.generated_password) {
      handoverModal.value = {
        isOpen: true,
        title: 'Password Sementara Baru Siap Serah Terima',
        userName: targetName,
        email: targetEmail || res.data?.email || '',
        password: res.generated_password,
        copied: false,
      }
    } else {
      showFeedback('Password Direset', res.message, 'success')
    }
    await refreshAllData(true)
  } catch (error) {
    showFeedback('Gagal Reset Password', extractErrorMessage(error, 'Gagal mereset password.'), 'error')
  } finally {
    passwordModal.value.isSubmitting = false
  }
}

async function handleMarkNotificationRead(id) {
  try {
    await apiFetch(`/api/notifications/${id}/read`, { method: 'PATCH' })
    await fetchNotifications()
  } catch (error) {
    console.error(error)
  }
}

onMounted(async () => {
  pageLoading.value = true
  if (!auth.user) await auth.fetchUser()
  if (!auth.token || !auth.user) return navigateTo('/login')

  if (auth.user.must_change_password) {
    changePasswordModal.value.isOpen = true
    changePasswordModal.value.forced = true
    pageLoading.value = false
    return
  }

  if (isMpk.value) {
    fetchOrganizations()
  }
  await refreshAllData(false)
  pageLoading.value = false

  let isRefreshing = false
  autoSyncInterval = setInterval(async () => {
    if (isRefreshing) return
    isRefreshing = true
    try {
      await refreshAllData(true)
    } finally {
      isRefreshing = false
    }
  }, 30000)
})

onUnmounted(() => {
  if (autoSyncInterval) clearInterval(autoSyncInterval)
  if (toastTimer) clearTimeout(toastTimer)
})
</script>

<template>
  <div v-if="pageLoading" class="min-h-screen flex flex-col items-center justify-center bg-background px-4">
    <div class="animate-spin rounded-full h-11 w-11 border-b-2 border-[#1E3A5F] mb-4" aria-hidden="true" />
    <p class="text-sm text-slate-600 font-semibold">Memeriksa hak akses ruang kerja Coordina…</p>
  </div>

  <div v-else class="min-h-screen bg-background flex flex-col">
    <!-- NATIVE ACCESSIBLE DIALOGS -->
    <AppDialog
      :open="passwordModal.isOpen"
      title="Reset Password Akun"
      :description="`Akun target: ${passwordModal.userName}`"
      @update:open="(v) => (passwordModal.isOpen = v)"
    >
      <div class="space-y-4">
        <div class="space-y-1.5">
          <div class="flex items-center justify-between gap-2">
            <Label for="modal-new-password">Password Baru</Label>
            <button type="button" class="text-xs font-semibold text-[#1E3A5F] hover:underline" @click="generateModalTempPassword">
              Generate Acak
            </button>
          </div>
          <Input id="modal-new-password" v-model="passwordModal.newPassword" type="text" placeholder="Kosongkan untuk generate otomatis…" class="h-11 rounded-xl" />
        </div>
        <label class="flex items-center gap-2.5 text-xs text-slate-700 cursor-pointer">
          <input v-model="passwordModal.mustChangePassword" type="checkbox" class="rounded border-slate-300 h-4 w-4 accent-[#1E3A5F]" />
          <span>Wajibkan ganti password saat login berikutnya</span>
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" @click="passwordModal.isOpen = false">Batal</Button>
          <Button type="button" :disabled="passwordModal.isSubmitting" class="bg-[#1E3A5F] hover:bg-[#152943] text-white" @click="submitPasswordReset">
            {{ passwordModal.isSubmitting ? 'Menyimpan…' : 'Simpan & Cabut Sesi' }}
          </Button>
        </div>
      </div>
    </AppDialog>

    <AppDialog
      :open="handoverModal.isOpen"
      :title="handoverModal.title"
      :description="`Salin dan serahkan kredensial ini secara aman kepada pengurus organisasi ${handoverModal.userName}.`"
      max-width-class="max-w-lg"
      @update:open="(v) => (handoverModal.isOpen = v)"
    >
      <div class="space-y-4">
        <div class="rounded-xl bg-slate-50 border border-border p-4 space-y-2 text-sm tabular-nums">
          <div><span class="text-slate-500">Email:</span> <strong class="text-slate-900">{{ handoverModal.email }}</strong></div>
          <div><span class="text-slate-500">Password Sementara:</span> <strong class="text-slate-900">{{ handoverModal.password }}</strong></div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" @click="copyHandoverCredentials">
            <component :is="handoverModal.copied ? Check : Copy" class="w-4 h-4" aria-hidden="true" />
            <span>{{ handoverModal.copied ? 'Tersalin' : 'Salin Kredensial' }}</span>
          </Button>
          <Button type="button" class="bg-[#1E3A5F] hover:bg-[#152943] text-white" @click="handoverModal.isOpen = false">Selesai</Button>
        </div>
      </div>
    </AppDialog>

    <AppDialog
      :open="changePasswordModal.isOpen"
      :persistent="changePasswordModal.forced"
      :title="changePasswordModal.forced ? 'Wajib Ganti Password Sementara' : 'Ganti Password Akun'"
      :description="
        changePasswordModal.forced
          ? 'Untuk keamanan serah terima organisasi, Anda wajib mengganti password sementara sebelum mengakses dashboard.'
          : 'Perbarui password akun Anda secara berkala.'
      "
      @update:open="(v) => !changePasswordModal.forced && (changePasswordModal.isOpen = v)"
    >
      <form @submit.prevent="submitChangePassword" class="space-y-3.5">
        <div class="space-y-1.5">
          <Label for="cp-current">Password Saat Ini</Label>
          <Input id="cp-current" v-model="changePasswordModal.current_password" type="password" autocomplete="current-password" placeholder="Masukkan password saat ini…" class="h-11 rounded-xl" />
        </div>
        <div class="space-y-1.5">
          <Label for="cp-new">Password Baru (Minimal 8 Karakter)</Label>
          <Input id="cp-new" v-model="changePasswordModal.new_password" type="password" autocomplete="new-password" placeholder="Password baru…" class="h-11 rounded-xl" />
        </div>
        <div class="space-y-1.5">
          <Label for="cp-confirm">Konfirmasi Password Baru</Label>
          <Input id="cp-confirm" v-model="changePasswordModal.new_password_confirmation" type="password" autocomplete="new-password" placeholder="Ulangi password baru…" class="h-11 rounded-xl" />
        </div>
        <p v-if="changePasswordModal.error" role="alert" class="text-xs text-rose-700 font-semibold">{{ changePasswordModal.error }}</p>
        <div class="flex justify-end gap-2 pt-2">
          <Button v-if="!changePasswordModal.forced" type="button" variant="outline" @click="changePasswordModal.isOpen = false">Batal</Button>
          <Button v-else type="button" variant="outline" class="text-rose-700 border-rose-200" @click="auth.logout()">Keluar</Button>
          <Button type="submit" :disabled="changePasswordModal.isSubmitting" class="bg-[#1E3A5F] hover:bg-[#152943] text-white">
            {{ changePasswordModal.isSubmitting ? 'Menyimpan…' : 'Simpan Password Baru' }}
          </Button>
        </div>
      </form>
    </AppDialog>

    <AppDialog
      :open="deleteModal.isOpen"
      :title="deleteModal.title"
      :description="deleteModal.description"
      @update:open="(v) => (deleteModal.isOpen = v)"
    >
      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" variant="outline" autofocus @click="deleteModal.isOpen = false">Batal</Button>
        <Button type="button" :disabled="deleteModal.isSubmitting" class="bg-rose-700 hover:bg-rose-800 text-white" @click="executeDeleteConfirmed">
          {{ deleteModal.isSubmitting ? 'Menghapus…' : 'Ya, Hapus Permanen' }}
        </Button>
      </div>
    </AppDialog>

    <AppDialog
      :open="decisionModal.isOpen"
      :title="
        decisionModal.mode === 'owner_approve'
          ? 'Izinkan Peminjaman Jadwal?'
          : decisionModal.mode === 'request_correction'
            ? 'Minta Perbaikan Berkas'
            : 'Tolak Permohonan'
      "
      :description="`${decisionModal.request?.organization?.name || ''} · ${decisionModal.request?.facility?.name || ''} (${decisionModal.request?.date || ''})`"
      max-width-class="max-w-lg"
      @update:open="(v) => (decisionModal.isOpen = v)"
    >
      <div class="space-y-4">
        <p v-if="decisionModal.mode === 'owner_approve'" class="text-sm text-slate-600 leading-relaxed">
          Dengan menekan tombol konfirmasi, Anda menyetujui peminjaman jadwal organisasi Anda sekaligus membubuhkan tanda tangan resmi Ketua pada surat perizinan.
        </p>
        <div v-else class="space-y-1.5">
          <Label for="decision-reason">
            {{ decisionModal.mode === 'request_correction' ? 'Catatan Revisi untuk Pemohon (Wajib)' : 'Alasan Penolakan (Wajib)' }}
          </Label>
          <textarea
            id="decision-reason"
            v-model="decisionModal.reason"
            rows="3"
            placeholder="Tuliskan alasan atau instruksi perbaikan secara jelas…"
            class="w-full rounded-xl border border-input bg-white p-3 text-sm text-slate-900"
          />
        </div>
        <p v-if="decisionModal.error" role="alert" class="text-xs text-rose-700 font-semibold">{{ decisionModal.error }}</p>
        <div class="flex justify-end gap-2 pt-1">
          <Button type="button" variant="outline" @click="decisionModal.isOpen = false">Batal</Button>
          <Button
            type="button"
            :disabled="decisionModal.isSubmitting"
            :class="
              decisionModal.mode === 'owner_approve'
                ? 'bg-emerald-700 hover:bg-emerald-800 text-white'
                : decisionModal.mode === 'request_correction'
                  ? 'bg-amber-600 hover:bg-amber-700 text-white'
                  : 'bg-rose-700 hover:bg-rose-800 text-white'
            "
            @click="submitDecisionModal"
          >
            {{ decisionModal.isSubmitting ? 'Memproses…' : 'Konfirmasi Keputusan' }}
          </Button>
        </div>
      </div>
    </AppDialog>

    <AppDialog
      :open="reviseModal.isOpen"
      title="Ajukan Revisi Permohonan"
      description="Mengirim revisi akan memperbarui versi dokumen dan mengulang siklus tanda tangan pada revisi terbaru."
      max-width-class="max-w-lg"
      @update:open="(v) => (reviseModal.isOpen = v)"
    >
      <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="sm:col-span-2 space-y-1.5">
            <Label for="rev-facility">Fasilitas</Label>
            <select id="rev-facility" v-model="reviseModal.facility_id" class="w-full h-10 rounded-xl border border-input bg-white px-3 text-sm">
              <option v-for="f in facilities" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <Label for="rev-date">Tanggal</Label>
            <Input id="rev-date" v-model="reviseModal.date" type="date" class="h-10 rounded-xl tabular-nums" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1.5">
              <Label for="rev-start">Mulai</Label>
              <Input id="rev-start" v-model="reviseModal.start_time" type="time" class="h-10 rounded-xl tabular-nums" />
            </div>
            <div class="space-y-1.5">
              <Label for="rev-end">Selesai</Label>
              <Input id="rev-end" v-model="reviseModal.end_time" type="time" class="h-10 rounded-xl tabular-nums" />
            </div>
          </div>
          <div class="sm:col-span-2 space-y-1.5">
            <Label for="rev-reason">Alasan / Keperluan</Label>
            <textarea id="rev-reason" v-model="reviseModal.reason" rows="3" class="w-full rounded-xl border border-input bg-white p-3 text-sm" />
          </div>
          <label class="sm:col-span-2 flex items-center gap-2.5 text-xs text-slate-700 cursor-pointer">
            <input v-model="reviseModal.confirm_signature" type="checkbox" class="rounded border-slate-300 h-4 w-4 accent-[#1E3A5F]" />
            <span>Bubuhkan ulang tanda tangan tahap 1 pada revisi baru</span>
          </label>
        </div>
        <p v-if="reviseModal.error" role="alert" class="text-xs text-rose-700 font-semibold">{{ reviseModal.error }}</p>
        <div class="flex justify-end gap-2 pt-1">
          <Button type="button" variant="outline" @click="reviseModal.isOpen = false">Batal</Button>
          <Button type="button" :disabled="reviseModal.isSubmitting" class="bg-amber-600 hover:bg-amber-700 text-white" @click="submitReviseModal">
            {{ reviseModal.isSubmitting ? 'Mengirim…' : 'Kirim Revisi' }}
          </Button>
        </div>
      </div>
    </AppDialog>

    <!-- INSTITUTIONAL TOP HEADER + ROLE-SPECIFIC NAVIGATION -->
    <header class="bg-gradient-to-r from-[#1E3A5F] to-[#162E4D] text-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <div class="h-10 w-10 rounded-xl bg-white/15 border border-white/20 flex items-center justify-center shrink-0">
            <FileCheck2 class="w-5 h-5 text-white" aria-hidden="true" />
          </div>
          <div class="min-w-0">
            <h1 class="text-lg font-bold text-white leading-tight truncate">Coordina</h1>
            <p class="text-xs text-blue-100/90 truncate">
              SMKN 4 Padalarang · {{ auth.user?.name || 'Pengguna' }}
            </p>
          </div>
        </div>

        <NotificationsBell
          :notifications="notifications"
          :unread-count="unreadCount"
          @mark-read="handleMarkNotificationRead"
        />
      </div>

      <!-- ROLE-SCOPED NAVIGATION BAR -->
      <nav aria-label="Menu Utama Workspace" class="bg-[#E9EFF6] border-t border-white/10 border-b border-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-2 flex items-center gap-1.5 overflow-x-auto" role="tablist">
          <button
            v-for="tab in roleTabs"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="activeTab === tab.id"
            :class="[
              'whitespace-nowrap inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border transition-colors',
              activeTab === tab.id
                ? 'bg-white text-[#1E3A5F] border-border shadow-sm'
                : 'bg-transparent text-slate-700 border-transparent hover:bg-white/60 hover:text-slate-900',
            ]"
            @click="setActiveTab(tab.id)"
          >
            <component :is="tab.icon" class="w-4 h-4 shrink-0" aria-hidden="true" />
            <span>{{ tab.label }}</span>
            <span
              v-if="tab.badge > 0"
              :class="[
                'px-2 py-0.5 rounded-full text-xs font-bold tabular-nums leading-none',
                activeTab === tab.id ? 'bg-[#1E3A5F] text-white' : 'bg-amber-100 text-amber-900',
              ]"
            >
              {{ tab.badge }}
            </span>
          </button>
        </div>
      </nav>
    </header>

    <!-- MAIN CONTENT REGION -->
    <main class="max-w-7xl w-full mx-auto px-4 sm:px-6 py-6 flex-1 space-y-5">
      <!-- Accessible live status banner -->
      <div
        v-if="statusToast.open"
        :role="statusToast.type === 'error' ? 'alert' : 'status'"
        :aria-live="statusToast.type === 'error' ? 'assertive' : 'polite'"
        :class="[
          'rounded-2xl border px-4 py-3.5 flex items-start justify-between gap-3 shadow-sm',
          statusToast.type === 'error'
            ? 'bg-rose-50 border-rose-300 text-rose-950'
            : statusToast.type === 'success'
              ? 'bg-emerald-50 border-emerald-300 text-emerald-950'
              : 'bg-blue-50 border-blue-200 text-slate-900',
        ]"
      >
        <div class="flex items-start gap-2.5 text-sm">
          <component
            :is="statusToast.type === 'error' ? AlertTriangle : statusToast.type === 'success' ? CheckCircle2 : Info"
            class="w-5 h-5 shrink-0 mt-0.5"
            aria-hidden="true"
          />
          <div>
            <p class="font-bold">{{ statusToast.title }}</p>
            <p class="text-xs sm:text-sm mt-0.5">{{ statusToast.message }}</p>
          </div>
        </div>
        <button
          type="button"
          aria-label="Tutup pesan status"
          class="rounded-lg p-1 text-slate-600 hover:bg-black/5 shrink-0"
          @click="statusToast.open = false"
        >
          <X class="w-4 h-4" aria-hidden="true" />
        </button>
      </div>

      <!-- ADMIN MPK WORKSPACE (Strictly MPK-scoped menus & actions) -->
      <template v-if="isMpk">
        <RequestsMasterDetail
          v-if="activeTab === 'meja-kerja'"
          :requests="myVisibleRequests"
          :loading="loadingRequests"
          :is-mpk="true"
          :user-org-id="auth.user?.organization_id"
          :user-id="auth.user?.id"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :screening-id="screeningId"
          :finalizing-id="finalizingId"
          :signing-id="signingId"
          :downloading-id="downloadingDocumentId"
          :regenerating-id="regeneratingDocumentId"
          @screen-accept="handleScreenAccept"
          @screen-reject="(req) => openDecisionModal('screen_reject', req)"
          @request-correction="(req) => openDecisionModal('request_correction', req)"
          @finalize="handleFinalizeRequest"
          @sign="handleSignDocument"
          @regenerate-pdf="handleRegeneratePdf"
          @download-pdf="handleDownloadPdf"
          @download-docx="handleDownloadDocx"
          @go-profile="goToProfileTab"
          @go-apply="goToBorrowTab()"
        />

        <ScheduleBoard
          v-else-if="activeTab === 'jadwal'"
          :facilities="facilities"
          :loading="loading"
          :is-mpk="true"
          @delete-facility="(id) => openDeleteConfirmation('facility', id)"
          @delete-schedule="(id) => openDeleteConfirmation('schedule', id)"
          @borrow-facility="(facilityId) => goToBorrowTab(facilityId)"
        />

        <BorrowRequestForm
          v-else-if="activeTab === 'pinjam-mpk'"
          :facilities="facilities"
          :submitting="isSubmittingRequest"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :is-mpk="true"
          :user-org-id="auth.user?.organization_id"
          :preselected-facility-id="preselectedFacilityId"
          title="Ajukan Peminjaman Resmi MPK (Kebutuhan Sekolah)"
          subtitle="Permintaan langsung dikirim ke organisasi pemilik jadwal tanpa tahap screening terpisah (2 tanda tangan)."
          @submit="handleSubmitBorrowRequest"
          @go-profile="goToProfileTab"
        />

        <div v-else-if="activeTab === 'arsip-template'" class="space-y-5">
          <div class="bg-white rounded-2xl border border-border shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h2 class="text-base font-bold text-slate-900">Arsip Surat &amp; Master Template</h2>
              <p class="text-xs text-slate-600 mt-0.5">Pilih antara daftar arsip surat resmi atau pengelolaan template DOCX.</p>
            </div>
            <div class="grid grid-cols-2 sm:inline-flex gap-1.5 bg-slate-100 p-1 rounded-xl border border-border" role="tablist">
              <button
                type="button"
                role="tab"
                :aria-selected="arsipSubTab === 'archive'"
                :class="[
                  'px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors',
                  arsipSubTab === 'archive' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-slate-700 hover:bg-white',
                ]"
                @click="arsipSubTab = 'archive'"
              >
                Arsip Surat ({{ archiveItems.length }})
              </button>
              <button
                type="button"
                role="tab"
                :aria-selected="arsipSubTab === 'templates'"
                :class="[
                  'px-3.5 py-2 rounded-lg text-xs font-semibold transition-colors',
                  arsipSubTab === 'templates' ? 'bg-[#1E3A5F] text-white shadow-sm' : 'text-slate-700 hover:bg-white',
                ]"
                @click="arsipSubTab = 'templates'"
              >
                Template DOCX ({{ templates.length }})
              </button>
            </div>
          </div>

          <DocumentArchivePanel
            v-if="arsipSubTab === 'archive'"
            :items="archiveItems"
            :loading="loadingArchive"
            :is-mpk="true"
            :downloading-id="downloadingDocumentId"
            :regenerating-id="regeneratingDocumentId"
            @refresh="fetchArchive(false)"
            @download-pdf="handleDownloadPdf"
            @download-docx="handleDownloadDocx"
            @regenerate-pdf="handleRegeneratePdf"
          />
          <TemplateManagerPanel
            v-else
            :templates="templates"
            :required-placeholders="requiredPlaceholders"
            :uploading="isUploadingTemplate"
            :generating-default="isGeneratingDefaultTemplate"
            @upload-template="handleUploadTemplate"
            @generate-default="handleGenerateDefaultTemplate"
            @download-template="handleDownloadTemplate"
          />
        </div>

        <MpkMasterDataPanel
          v-else-if="activeTab === 'master-data'"
          :facilities="facilities"
          :organizations="organizations"
          :eskul-users="eskulUsers"
          :loading-users="loadingUsers"
          :creating-facility="isCreatingFacility"
          :creating-schedule="isCreatingSchedule"
          :creating-user="isCreatingUser"
          :toggling-user-id="togglingUserId"
          @create-facility="handleCreateFacility"
          @create-schedule="handleCreateSchedule"
          @create-user="handleCreateUser"
          @reset-password="(id, name, email) => (passwordModal = { isOpen: true, userId: id, userName: name, userEmail: email || '', newPassword: '', mustChangePassword: true, isSubmitting: false })"
          @toggle-active="handleToggleUserActive"
          @delete-user="(id, name) => openDeleteConfirmation('user', id, name)"
        />
      </template>

      <!-- OWNER ESKUL WORKSPACE (Strictly Eskul-scoped menus & actions) -->
      <template v-else>
        <RequestsMasterDetail
          v-if="activeTab === 'permohonan'"
          :requests="myVisibleRequests"
          :loading="loadingRequests"
          :is-mpk="false"
          :user-org-id="auth.user?.organization_id"
          :user-id="auth.user?.id"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :signing-id="signingId"
          :downloading-id="downloadingDocumentId"
          @approve="(req) => openDecisionModal('owner_approve', req)"
          @reject="(req) => openDecisionModal('owner_reject', req)"
          @sign="handleSignDocument"
          @revise="openReviseModal"
          @download-pdf="handleDownloadPdf"
          @download-docx="handleDownloadDocx"
          @go-profile="goToProfileTab"
          @go-apply="goToBorrowTab()"
        />

        <ScheduleBoard
          v-else-if="activeTab === 'jadwal'"
          :facilities="facilities"
          :loading="loading"
          :is-mpk="false"
          @borrow-facility="(facilityId) => goToBorrowTab(facilityId)"
        />

        <BorrowRequestForm
          v-else-if="activeTab === 'ajukan'"
          :facilities="facilities"
          :submitting="isSubmittingRequest"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :is-mpk="false"
          :user-org-id="auth.user?.organization_id"
          :preselected-facility-id="preselectedFacilityId"
          title="Ajukan Peminjaman Fasilitas"
          subtitle="Pengajuan akan melalui pemeriksaan MPK sebelum diteruskan ke pemilik jadwal tetap (atau langsung diteruskan pada slot kosong)."
          @submit="handleSubmitBorrowRequest"
          @go-profile="goToProfileTab"
        />

        <DocumentArchivePanel
          v-else-if="activeTab === 'arsip'"
          :items="archiveItems"
          :loading="loadingArchive"
          :is-mpk="false"
          :downloading-id="downloadingDocumentId"
          @refresh="fetchArchive(false)"
          @download-pdf="handleDownloadPdf"
          @download-docx="handleDownloadDocx"
        />
      </template>

      <!-- DEDICATED SETTINGS TAB (Logo, TTD, Change Password & Logout for both roles) -->
      <div v-if="activeTab === 'pengaturan'" class="max-w-5xl mx-auto space-y-6">
        <OrganizationProfilePanel
          :profile="orgProfile"
          :loading-profile="loadingProfile"
          :profile-error="orgProfileError"
          :saving="isSavingProfile"
          :uploading="isUploadingAssets"
          @save-profile="handleSaveProfile"
          @upload-assets="handleUploadAssets"
          @retry-profile="fetchOrganizationProfile(false)"
        />

        <section class="bg-white rounded-2xl border border-border shadow-sm p-6 space-y-4">
          <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-xl bg-slate-100 text-[#1E3A5F] flex items-center justify-center shrink-0">
              <ShieldCheck class="w-5 h-5" aria-hidden="true" />
            </div>
            <div>
              <h3 class="text-base font-bold text-slate-900">Keamanan &amp; Sesi Akun</h3>
              <p class="text-sm text-slate-600 mt-0.5">
                Kelola kata sandi dan akhiri sesi login Anda di perangkat ini.
              </p>
            </div>
          </div>

          <div class="rounded-xl bg-slate-50 border border-border p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
              <span class="text-xs font-semibold text-slate-500 block">Nama Akun</span>
              <strong class="text-slate-900">{{ auth.user?.name || '-' }}</strong>
            </div>
            <div>
              <span class="text-xs font-semibold text-slate-500 block">Email Terdaftar</span>
              <strong class="text-slate-900 break-all">{{ auth.user?.email || '-' }}</strong>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
            <Button
              type="button"
              variant="outline"
              class="h-11 rounded-xl border-slate-300 text-slate-800 hover:bg-slate-50"
              @click="openVoluntaryChangePassword"
            >
              <KeyRound class="w-4 h-4" aria-hidden="true" />
              <span>Ganti Password</span>
            </Button>

            <Button
              type="button"
              variant="outline"
              class="h-11 rounded-xl border-rose-300 text-rose-700 hover:bg-rose-50"
              @click="auth.logout()"
            >
              <LogOut class="w-4 h-4" aria-hidden="true" />
              <span>Keluar dari Akun</span>
            </Button>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>
