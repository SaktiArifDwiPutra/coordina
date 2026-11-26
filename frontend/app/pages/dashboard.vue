<script setup>
definePageMeta({
  middleware: ['auth', 'role'],
  roles: ['admin_mpk', 'owner_eskul'],
})

import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useApi } from '~/composables/useApi'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import NotificationsBell from '~/components/dashboard/NotificationsBell.vue'
import ScheduleBoard from '~/components/dashboard/ScheduleBoard.vue'
import BorrowRequestForm from '~/components/dashboard/BorrowRequestForm.vue'
import MyRequestsPanel from '~/components/dashboard/MyRequestsPanel.vue'
import OwnerInboxPanel from '~/components/dashboard/OwnerInboxPanel.vue'
import MpkWorkbenchPanel from '~/components/dashboard/MpkWorkbenchPanel.vue'
import MpkMasterDataPanel from '~/components/dashboard/MpkMasterDataPanel.vue'
import OrganizationProfilePanel from '~/components/dashboard/OrganizationProfilePanel.vue'
import TemplateManagerPanel from '~/components/dashboard/TemplateManagerPanel.vue'
import DocumentArchivePanel from '~/components/dashboard/DocumentArchivePanel.vue'

const auth = useAuthStore()
const { apiFetch, extractErrorMessage } = useApi()

const pageLoading = ref(true)
let autoSyncInterval = null

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

const alertModal = ref({ isOpen: false, title: '', message: '', type: 'info' })
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
const deleteModal = ref({ isOpen: false, userId: null, userName: '', isSubmitting: false })
const decisionModal = ref({
  isOpen: false,
  mode: '', // 'screen_reject' | 'owner_approve' | 'owner_reject' | 'request_correction'
  request: null,
  reason: '',
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
  confirm_signature: true,
  isSubmitting: false,
})

const isMpk = computed(() => auth.user?.role === 'admin_mpk')

const incomingOwnerRequests = computed(() =>
  borrowRequests.value.filter(
    (r) =>
      r.owner_organization_id === auth.user?.organization_id &&
      (r.status === 'pending_owner' || (r.status === 'owner_approved' && r.document?.document_status === 'waiting_owner_signature'))
  )
)

const mySubmittedRequests = computed(() =>
  borrowRequests.value.filter(
    (r) => r.organization_id === auth.user?.organization_id || r.user_id === auth.user?.id
  )
)

function showAlert(title, message, type = 'info') {
  alertModal.value = { isOpen: true, title, message, type }
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

async function fetchOrganizationProfile() {
  try {
    const res = await apiFetch('/api/organization/profile')
    orgProfile.value = res.data
  } catch (error) {
    console.error(error)
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
  const tasks = [fetchFacilities(silent), fetchBorrowRequests(silent), fetchArchive(silent), fetchNotifications(), fetchOrganizationProfile()]
  if (isMpk.value) {
    tasks.push(fetchEskulUsers(silent), fetchTemplates())
  }
  await Promise.all(tasks)
}

async function handleSubmitBorrowRequest(payload, resetForm) {
  isSubmittingRequest.value = true
  try {
    const res = await apiFetch('/api/borrow-requests', { method: 'POST', body: payload })
    showAlert('Berhasil!', res.message, 'success')
    resetForm()
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengirim pengajuan.'), 'error')
  } finally {
    isSubmittingRequest.value = false
  }
}

async function handleScreenAccept(req) {
  if (screeningId.value === req.id) return
  if (!orgProfile.value?.is_complete_for_signing) {
    showAlert('Profil Belum Lengkap', 'Lengkapi profil dan gambar tanda tangan MPK terlebih dahulu.', 'error')
    return
  }

  screeningId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/screen`, {
      method: 'POST',
      body: { decision: 'accept', confirm_signature: true },
    })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal memproses screening.'), 'error')
  } finally {
    screeningId.value = null
  }
}

async function handleSignDocument(req) {
  if (signingId.value === req.id) return
  if (!orgProfile.value?.is_complete_for_signing) {
    showAlert('Profil Belum Lengkap', 'Lengkapi profil dan gambar tanda tangan organisasi terlebih dahulu sebelum menandatangani.', 'error')
    return
  }

  signingId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/sign`, { method: 'POST' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal membubuhkan tanda tangan.'), 'error')
  } finally {
    signingId.value = null
  }
}

function openDecisionModal(mode, req) {
  if (mode === 'owner_approve' && !orgProfile.value?.is_complete_for_signing) {
    showAlert('Profil Belum Lengkap', 'Lengkapi profil dan gambar tanda tangan organisasi Anda terlebih dahulu.', 'error')
    return
  }
  decisionModal.value = { isOpen: true, mode, request: req, reason: '', isSubmitting: false }
}

async function submitDecisionModal() {
  const { mode, request, reason } = decisionModal.value
  if ((mode === 'screen_reject' || mode === 'owner_reject' || mode === 'request_correction') && !reason.trim()) {
    showAlert('Catatan Wajib', 'Harap tuliskan alasan atau catatan revisi.', 'error')
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
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal memproses keputusan.'), 'error')
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
    confirm_signature: Boolean(orgProfile.value?.is_complete_for_signing),
    isSubmitting: false,
  }
}

async function submitReviseModal() {
  const modal = reviseModal.value
  if (!modal.facility_id || !modal.date || !modal.start_time || !modal.end_time || !modal.reason.trim()) {
    showAlert('Data Belum Lengkap', 'Lengkapi seluruh data revisi permohonan terlebih dahulu.', 'error')
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
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengirim revisi permohonan.'), 'error')
  } finally {
    modal.isSubmitting = false
  }
}

async function handleSaveProfile(payload) {
  isSavingProfile.value = true
  try {
    const res = await apiFetch('/api/organization/profile', { method: 'PUT', body: payload })
    orgProfile.value = res.data
    showAlert('Berhasil!', res.message, 'success')
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal menyimpan profil organisasi.'), 'error')
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
    showAlert('Berhasil!', res.message, 'success')
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengunggah aset organisasi.'), 'error')
  } finally {
    isUploadingAssets.value = false
  }
}

async function handleUploadTemplate(formData, resetFile) {
  isUploadingTemplate.value = true
  try {
    const res = await apiFetch('/api/document-templates', { method: 'POST', body: formData })
    resetFile()
    showAlert('Berhasil!', res.message, 'success')
    await fetchTemplates()
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengunggah master template.'), 'error')
  } finally {
    isUploadingTemplate.value = false
  }
}

async function handleGenerateDefaultTemplate() {
  isGeneratingDefaultTemplate.value = true
  try {
    const res = await apiFetch('/api/document-templates/generate-default', { method: 'POST' })
    showAlert('Berhasil!', res.message, 'success')
    await fetchTemplates()
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal membuat template default.'), 'error')
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
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengunduh template.'), 'error')
  }
}

async function handleFinalizeRequest(req) {
  if (finalizingId.value === req.id) return
  finalizingId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/finalize`, { method: 'POST' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mencatat keputusan akhir.'), 'error')
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
        showAlert('Pop-up Diblokir', 'Browser memblokir jendela pratinjau. File PDF resmi langsung diunduh ke perangkat Anda.', 'info')
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
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengunduh dokumen PDF resmi.'), 'error')
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
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengunduh dokumen DOCX resmi.'), 'error')
  } finally {
    downloadingDocumentId.value = null
  }
}

async function handleRegeneratePdf(req) {
  regeneratingDocumentId.value = req.id
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/regenerate-pdf`, { method: 'POST' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal memproses PDF resmi.'), 'error')
  } finally {
    regeneratingDocumentId.value = null
  }
}

async function handleCreateFacility(payload, resetForm) {
  isCreatingFacility.value = true
  try {
    const res = await apiFetch('/api/facilities', { method: 'POST', body: payload })
    showAlert('Berhasil!', res.message, 'success')
    resetForm()
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal menambahkan fasilitas.'), 'error')
  } finally {
    isCreatingFacility.value = false
  }
}

async function handleDeleteFacility(id) {
  if (!confirm('Apakah Anda yakin ingin menghapus fasilitas ini beserta seluruh jadwal di dalamnya?')) return
  try {
    const res = await apiFetch(`/api/facilities/${id}`, { method: 'DELETE' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal menghapus fasilitas.'), 'error')
  }
}

async function handleCreateSchedule(payload, resetForm) {
  isCreatingSchedule.value = true
  try {
    const res = await apiFetch('/api/fixed-schedules', { method: 'POST', body: payload })
    showAlert('Berhasil!', res.message, 'success')
    resetForm()
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mendaftarkan jadwal tetap.'), 'error')
  } finally {
    isCreatingSchedule.value = false
  }
}

async function handleDeleteSchedule(id) {
  if (!confirm('Hapus hak milik jadwal tetap ini?')) return
  try {
    const res = await apiFetch(`/api/fixed-schedules/${id}`, { method: 'DELETE' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal menghapus jadwal.'), 'error')
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
      showAlert('Berhasil!', res.message, 'success')
    }
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal membuat akun.'), 'error')
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
    showAlert('Salin Manual', text, 'info')
  }
}

async function handleToggleUserActive(userId) {
  togglingUserId.value = userId
  try {
    const res = await apiFetch(`/api/users/${userId}/toggle-active`, { method: 'PATCH' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mengubah status akun.'), 'error')
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
    showAlert('Berhasil!', res.message, 'success')
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
      showAlert('Berhasil!', res.message, 'success')
    }
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mereset password.'), 'error')
  } finally {
    passwordModal.value.isSubmitting = false
  }
}

async function executeDeleteUser() {
  deleteModal.value.isSubmitting = true
  try {
    const res = await apiFetch(`/api/users/${deleteModal.value.userId}`, { method: 'DELETE' })
    deleteModal.value.isOpen = false
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal menghapus akun.'), 'error')
  } finally {
    deleteModal.value.isSubmitting = false
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
})
</script>

<template>
  <div v-if="pageLoading" class="min-h-screen flex flex-col items-center justify-center bg-zinc-50">
    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-zinc-900 mb-4"></div>
    <p class="text-zinc-500 font-medium">Memeriksa hak akses ruang kerja...</p>
  </div>

  <div v-else class="min-h-screen bg-zinc-50 p-4 sm:p-8 relative">
    <!-- MODALS -->
    <div v-if="alertModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <h3 :class="['text-lg font-bold', alertModal.type === 'error' ? 'text-red-600' : 'text-zinc-900']">{{ alertModal.title }}</h3>
        <p class="text-zinc-600 text-sm leading-relaxed">{{ alertModal.message }}</p>
        <div class="flex justify-end pt-2">
          <Button @click="alertModal.isOpen = false" :class="alertModal.type === 'error' ? 'bg-red-600 hover:bg-red-700' : 'bg-zinc-900'">Mengerti</Button>
        </div>
      </div>
    </div>

    <div v-if="passwordModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <h3 class="text-lg font-bold text-zinc-900">Reset Password</h3>
        <p class="text-sm text-zinc-500">Akun: <span class="font-semibold text-zinc-900">{{ passwordModal.userName }}</span></p>
        <div class="space-y-2 pt-2">
          <div class="flex items-center justify-between gap-2">
            <Label>Password Baru</Label>
            <button type="button" class="text-xs font-semibold text-indigo-600 hover:underline" @click="generateModalTempPassword">
              Generate Acak
            </button>
          </div>
          <Input type="text" v-model="passwordModal.newPassword" placeholder="Kosongkan untuk generate otomatis" />
        </div>
        <label class="flex items-center gap-2 text-xs text-zinc-700 cursor-pointer">
          <input v-model="passwordModal.mustChangePassword" type="checkbox" class="rounded border-zinc-300" />
          Wajibkan ganti password saat login berikutnya
        </label>
        <div class="flex justify-end gap-2 pt-4">
          <Button variant="outline" @click="passwordModal.isOpen = false">Batal</Button>
          <Button @click="submitPasswordReset" :disabled="passwordModal.isSubmitting" class="bg-indigo-600 text-white">
            {{ passwordModal.isSubmitting ? 'Menyimpan...' : 'Simpan & Cabut Sesi' }}
          </Button>
        </div>
      </div>
    </div>

    <div v-if="handoverModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-zinc-900">{{ handoverModal.title }}</h3>
        <p class="text-xs text-zinc-500">
          Salin dan serahkan kredensial ini secara aman kepada pengurus baru organisasi <strong>{{ handoverModal.userName }}</strong>.
        </p>
        <div class="rounded-xl bg-zinc-50 border border-zinc-200 p-4 space-y-2 text-sm font-mono">
          <div><span class="text-zinc-500">Email:</span> {{ handoverModal.email }}</div>
          <div><span class="text-zinc-500">Password:</span> <strong>{{ handoverModal.password }}</strong></div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="copyHandoverCredentials">
            {{ handoverModal.copied ? 'Tersalin!' : 'Salin Kredensial' }}
          </Button>
          <Button class="bg-zinc-900 text-white" @click="handoverModal.isOpen = false">Selesai</Button>
        </div>
      </div>
    </div>

    <div v-if="changePasswordModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-zinc-900">
          {{ changePasswordModal.forced ? 'Wajib Ganti Password Sementara' : 'Ganti Password Akun' }}
        </h3>
        <p class="text-xs text-zinc-500">
          {{
            changePasswordModal.forced
              ? 'Untuk keamanan serah terima organisasi, Anda wajib mengganti password sementara sebelum mengakses dashboard.'
              : 'Perbarui password akun Anda secara berkala.'
          }}
        </p>
        <div class="space-y-3">
          <div class="space-y-1">
            <Label>Password Saat Ini</Label>
            <Input v-model="changePasswordModal.current_password" type="password" placeholder="Masukkan password saat ini" />
          </div>
          <div class="space-y-1">
            <Label>Password Baru (Minimal 8 Karakter)</Label>
            <Input v-model="changePasswordModal.new_password" type="password" placeholder="Password baru..." />
          </div>
          <div class="space-y-1">
            <Label>Konfirmasi Password Baru</Label>
            <Input v-model="changePasswordModal.new_password_confirmation" type="password" placeholder="Ulangi password baru..." />
          </div>
          <p v-if="changePasswordModal.error" class="text-xs text-red-600 font-medium">{{ changePasswordModal.error }}</p>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button v-if="!changePasswordModal.forced" variant="outline" @click="changePasswordModal.isOpen = false">Batal</Button>
          <Button v-else variant="outline" class="text-red-600 border-red-200" @click="auth.logout()">Keluar</Button>
          <Button :disabled="changePasswordModal.isSubmitting" class="bg-indigo-600 text-white" @click="submitChangePassword">
            {{ changePasswordModal.isSubmitting ? 'Menyimpan...' : 'Simpan Password Baru' }}
          </Button>
        </div>
      </div>
    </div>

    <div v-if="deleteModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <h3 class="text-lg font-bold text-red-600">Hapus Akun?</h3>
        <p class="text-zinc-600 text-sm">Hapus permanen akun <span class="font-bold text-zinc-900">{{ deleteModal.userName }}</span>?</p>
        <div class="flex justify-end gap-2 pt-4">
          <Button variant="outline" @click="deleteModal.isOpen = false">Batal</Button>
          <Button @click="executeDeleteUser" :disabled="deleteModal.isSubmitting" class="bg-red-600 text-white">Ya, Hapus</Button>
        </div>
      </div>
    </div>

    <div v-if="decisionModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-bold text-zinc-900">
          {{
            decisionModal.mode === 'owner_approve'
              ? 'Izinkan Peminjaman Jadwal?'
              : decisionModal.mode === 'request_correction'
                ? 'Minta Revisi Dokumen'
                : 'Tolak Permohonan'
          }}
        </h3>
        <p class="text-zinc-600 text-sm">
          {{ decisionModal.request?.organization?.name }} · {{ decisionModal.request?.facility?.name }} ({{ decisionModal.request?.date }})
        </p>
        <div v-if="decisionModal.mode !== 'owner_approve'" class="space-y-2">
          <Label>
            {{ decisionModal.mode === 'request_correction' ? 'Catatan Revisi untuk Pemohon (Wajib)' : 'Alasan Penolakan (Wajib)' }}
          </Label>
          <textarea
            v-model="decisionModal.reason"
            rows="3"
            placeholder="Tuliskan alasan atau instruksi perbaikan secara jelas..."
            class="w-full rounded-md border border-zinc-200 p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-zinc-900"
          />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="decisionModal.isOpen = false">Batal</Button>
          <Button
            @click="submitDecisionModal"
            :disabled="decisionModal.isSubmitting"
            :class="
              decisionModal.mode === 'owner_approve'
                ? 'bg-green-600 text-white'
                : decisionModal.mode === 'request_correction'
                  ? 'bg-amber-600 text-white'
                  : 'bg-red-600 text-white'
            "
          >
            {{ decisionModal.isSubmitting ? 'Memproses...' : 'Konfirmasi' }}
          </Button>
        </div>
      </div>
    </div>

    <div v-if="reviseModal.isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4">
        <h3 class="text-lg font-bold text-zinc-900">Ajukan Revisi Permohonan</h3>
        <p class="text-xs text-zinc-500">
          Mengirim revisi akan memperbarui versi dokumen dan mengulang siklus tanda tangan pada revisi terbaru.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="sm:col-span-2 space-y-1">
            <Label>Fasilitas</Label>
            <select
              v-model="reviseModal.facility_id"
              class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm"
            >
              <option v-for="f in facilities" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
            </select>
          </div>
          <div class="space-y-1">
            <Label>Tanggal</Label>
            <Input v-model="reviseModal.date" type="date" />
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label>Mulai</Label>
              <Input v-model="reviseModal.start_time" type="time" />
            </div>
            <div class="space-y-1">
              <Label>Selesai</Label>
              <Input v-model="reviseModal.end_time" type="time" />
            </div>
          </div>
          <div class="sm:col-span-2 space-y-1">
            <Label>Alasan / Keperluan</Label>
            <textarea
              v-model="reviseModal.reason"
              rows="3"
              class="w-full rounded-md border border-zinc-200 p-2.5 text-sm"
            />
          </div>
          <label class="sm:col-span-2 flex items-center gap-2 text-xs text-zinc-700">
            <input v-model="reviseModal.confirm_signature" type="checkbox" class="rounded border-zinc-300" />
            Bubuhkan ulang tanda tangan tahap 1 pada revisi baru
          </label>
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="reviseModal.isOpen = false">Batal</Button>
          <Button :disabled="reviseModal.isSubmitting" class="bg-amber-600 text-white" @click="submitReviseModal">
            {{ reviseModal.isSubmitting ? 'Mengirim...' : 'Kirim Revisi' }}
          </Button>
        </div>
      </div>
    </div>

    <div class="max-w-6xl mx-auto space-y-10">
      <!-- HEADER PROFILE -->
      <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-zinc-200">
        <div>
          <h1 class="text-2xl font-bold text-zinc-900">Dashboard Coordina</h1>
          <p class="text-zinc-500">
            Selamat datang, {{ auth.user?.name || 'User' }}!
            <span class="font-semibold text-indigo-600">
              ({{ isMpk ? 'Admin MPK' : (auth.user?.organization?.name || 'Eskul') }})
            </span>
          </p>
        </div>
        <div class="flex items-center gap-3">
          <NotificationsBell
            :notifications="notifications"
            :unread-count="unreadCount"
            @mark-read="handleMarkNotificationRead"
          />
          <button
            @click="openVoluntaryChangePassword"
            class="px-3 py-2 bg-zinc-100 text-zinc-700 hover:bg-zinc-200 font-medium rounded-lg transition-colors text-sm"
          >
            Ganti Password
          </button>
          <button
            @click="auth.logout()"
            class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 font-medium rounded-lg transition-colors text-sm"
          >
            Keluar
          </button>
        </div>
      </div>

      <ScheduleBoard
        :facilities="facilities"
        :loading="loading"
        :is-mpk="isMpk"
        @delete-facility="handleDeleteFacility"
        @delete-schedule="handleDeleteSchedule"
      />

      <!-- ESKUL WORKSPACE (Workflow A + Incoming Owner Inbox) -->
      <div v-if="!isMpk" class="space-y-10">
        <OwnerInboxPanel
          :requests="incomingOwnerRequests"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :signing-id="signingId"
          @approve="(req) => openDecisionModal('owner_approve', req)"
          @reject="(req) => openDecisionModal('owner_reject', req)"
          @sign="handleSignDocument"
        />

        <BorrowRequestForm
          :facilities="facilities"
          :submitting="isSubmittingRequest"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          title="Ajukan Peminjaman Fasilitas"
          subtitle="Pengajuan akan melalui screening MPK sebelum diteruskan ke pemilik jadwal tetap."
          @submit="handleSubmitBorrowRequest"
        />

        <MyRequestsPanel
          :requests="mySubmittedRequests"
          :loading="loadingRequests"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :downloading-id="downloadingDocumentId"
          :signing-id="signingId"
          @revise="openReviseModal"
          @sign="handleSignDocument"
          @download-pdf="handleDownloadPdf"
          @download-docx="handleDownloadDocx"
        />

        <DocumentArchivePanel
          :items="archiveItems"
          :loading="loadingArchive"
          :is-mpk="false"
          :downloading-id="downloadingDocumentId"
          @refresh="fetchArchive(false)"
          @download-pdf="handleDownloadPdf"
          @download-docx="handleDownloadDocx"
        />

        <OrganizationProfilePanel
          :profile="orgProfile"
          :saving="isSavingProfile"
          :uploading="isUploadingAssets"
          @save-profile="handleSaveProfile"
          @upload-assets="handleUploadAssets"
        />
      </div>

      <!-- MPK WORKSPACE (Screening, Final Recording, Workflow B, Master Data) -->
      <div v-else class="space-y-12">
        <MpkWorkbenchPanel
          :requests="borrowRequests"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :screening-id="screeningId"
          :finalizing-id="finalizingId"
          :signing-id="signingId"
          :regenerating-id="regeneratingDocumentId"
          @screen-accept="handleScreenAccept"
          @screen-reject="(req) => openDecisionModal('screen_reject', req)"
          @request-correction="(req) => openDecisionModal('request_correction', req)"
          @finalize="handleFinalizeRequest"
          @sign="handleSignDocument"
          @regenerate-pdf="handleRegeneratePdf"
        />

        <BorrowRequestForm
          :facilities="facilities"
          :submitting="isSubmittingRequest"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          title="Ajukan Peminjaman oleh MPK (Kebutuhan Sekolah)"
          subtitle="Permintaan langsung dikirim ke organisasi pemilik jadwal tanpa tahap screening terpisah."
          @submit="handleSubmitBorrowRequest"
        />

        <MyRequestsPanel
          :requests="mySubmittedRequests"
          :loading="loadingRequests"
          :profile-complete="Boolean(orgProfile?.is_complete_for_signing)"
          :downloading-id="downloadingDocumentId"
          :signing-id="signingId"
          @revise="openReviseModal"
          @sign="handleSignDocument"
          @download-pdf="handleDownloadPdf"
          @download-docx="handleDownloadDocx"
        />

        <DocumentArchivePanel
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
          :templates="templates"
          :required-placeholders="requiredPlaceholders"
          :uploading="isUploadingTemplate"
          :generating-default="isGeneratingDefaultTemplate"
          @upload-template="handleUploadTemplate"
          @generate-default="handleGenerateDefaultTemplate"
          @download-template="handleDownloadTemplate"
        />

        <OrganizationProfilePanel
          :profile="orgProfile"
          :saving="isSavingProfile"
          :uploading="isUploadingAssets"
          @save-profile="handleSaveProfile"
          @upload-assets="handleUploadAssets"
        />

        <MpkMasterDataPanel
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
          @delete-user="(id, name) => (deleteModal = { isOpen: true, userId: id, userName: name, isSubmitting: false })"
        />
      </div>
    </div>
  </div>
</template>
