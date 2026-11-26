<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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

const loading = ref(true)
const loadingRequests = ref(true)
const loadingUsers = ref(false)

const isSubmittingRequest = ref(false)
const isCreatingFacility = ref(false)
const isCreatingSchedule = ref(false)
const isCreatingUser = ref(false)

const alertModal = ref({ isOpen: false, title: '', message: '', type: 'info' })
const passwordModal = ref({ isOpen: false, userId: null, userName: '', newPassword: '', isSubmitting: false })
const deleteModal = ref({ isOpen: false, userId: null, userName: '', isSubmitting: false })
const decisionModal = ref({
  isOpen: false,
  mode: '', // 'screen_reject' | 'owner_approve' | 'owner_reject'
  request: null,
  reason: '',
  isSubmitting: false,
})

const isMpk = computed(() => auth.user?.role === 'admin_mpk')

const incomingOwnerRequests = computed(() =>
  borrowRequests.value.filter(
    (r) => r.owner_organization_id === auth.user?.organization_id && r.status === 'pending_owner'
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

async function refreshAllData(silent = true) {
  const tasks = [fetchFacilities(silent), fetchBorrowRequests(silent), fetchNotifications()]
  if (isMpk.value) {
    tasks.push(fetchEskulUsers(silent))
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
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/screen`, {
      method: 'POST',
      body: { decision: 'accept' },
    })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal memproses screening.'), 'error')
  }
}

function openDecisionModal(mode, req) {
  decisionModal.value = { isOpen: true, mode, request: req, reason: '', isSubmitting: false }
}

async function submitDecisionModal() {
  const { mode, request, reason } = decisionModal.value
  if ((mode === 'screen_reject' || mode === 'owner_reject') && !reason.trim()) {
    showAlert('Alasan Wajib', 'Harap tuliskan alasan penolakan.', 'error')
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
    } else {
      res = await apiFetch(`/api/borrow-requests/${request.id}/owner-decision`, {
        method: 'POST',
        body: {
          decision: mode === 'owner_approve' ? 'approve' : 'reject',
          rejection_reason: mode === 'owner_reject' ? reason.trim() : null,
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

async function handleFinalizeRequest(req) {
  try {
    const res = await apiFetch(`/api/borrow-requests/${req.id}/finalize`, { method: 'POST' })
    showAlert('Berhasil!', res.message, 'success')
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal mencatat keputusan akhir.'), 'error')
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
    showAlert('Berhasil!', res.message, 'success')
    resetForm()
    await refreshAllData(true)
  } catch (error) {
    showAlert('Gagal!', extractErrorMessage(error, 'Gagal membuat akun.'), 'error')
  } finally {
    isCreatingUser.value = false
  }
}

async function submitPasswordReset() {
  passwordModal.value.isSubmitting = true
  try {
    const res = await apiFetch(`/api/users/${passwordModal.value.userId}/reset-password`, {
      method: 'PATCH',
      body: { password: passwordModal.value.newPassword },
    })
    passwordModal.value.isOpen = false
    showAlert('Berhasil!', res.message, 'success')
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
        <div class="space-y-2 pt-2"><Label>Password Baru</Label><Input type="password" v-model="passwordModal.newPassword" placeholder="Minimal 6 karakter..." /></div>
        <div class="flex justify-end gap-2 pt-4">
          <Button variant="outline" @click="passwordModal.isOpen = false">Batal</Button>
          <Button @click="submitPasswordReset" :disabled="passwordModal.isSubmitting" class="bg-indigo-600 text-white">Simpan</Button>
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
          {{ decisionModal.mode === 'owner_approve' ? 'Izinkan Peminjaman Jadwal?' : 'Tolak Permohonan' }}
        </h3>
        <p class="text-zinc-600 text-sm">
          {{ decisionModal.request?.organization?.name }} · {{ decisionModal.request?.facility?.name }} ({{ decisionModal.request?.date }})
        </p>
        <div v-if="decisionModal.mode !== 'owner_approve'" class="space-y-2">
          <Label>Alasan Penolakan (Wajib)</Label>
          <textarea
            v-model="decisionModal.reason"
            rows="3"
            placeholder="Jelaskan alasan penolakan secara jelas..."
            class="w-full rounded-md border border-zinc-200 p-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-zinc-900"
          />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <Button variant="outline" @click="decisionModal.isOpen = false">Batal</Button>
          <Button
            @click="submitDecisionModal"
            :disabled="decisionModal.isSubmitting"
            :class="decisionModal.mode === 'owner_approve' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
          >
            {{ decisionModal.isSubmitting ? 'Memproses...' : 'Konfirmasi' }}
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
          @approve="(req) => openDecisionModal('owner_approve', req)"
          @reject="(req) => openDecisionModal('owner_reject', req)"
        />

        <BorrowRequestForm
          :facilities="facilities"
          :submitting="isSubmittingRequest"
          title="Ajukan Peminjaman Fasilitas"
          subtitle="Pengajuan akan melalui screening MPK sebelum diteruskan ke pemilik jadwal tetap."
          @submit="handleSubmitBorrowRequest"
        />

        <MyRequestsPanel :requests="mySubmittedRequests" :loading="loadingRequests" />
      </div>

      <!-- MPK WORKSPACE (Screening, Final Recording, Workflow B, Master Data) -->
      <div v-else class="space-y-12">
        <MpkWorkbenchPanel
          :requests="borrowRequests"
          @screen-accept="handleScreenAccept"
          @screen-reject="(req) => openDecisionModal('screen_reject', req)"
          @finalize="handleFinalizeRequest"
        />

        <BorrowRequestForm
          :facilities="facilities"
          :submitting="isSubmittingRequest"
          title="Ajukan Peminjaman oleh MPK (Kebutuhan Sekolah)"
          subtitle="Permintaan langsung dikirim ke organisasi pemilik jadwal tanpa tahap screening terpisah."
          @submit="handleSubmitBorrowRequest"
        />

        <MyRequestsPanel :requests="mySubmittedRequests" :loading="loadingRequests" />

        <MpkMasterDataPanel
          :facilities="facilities"
          :organizations="organizations"
          :eskul-users="eskulUsers"
          :loading-users="loadingUsers"
          :creating-facility="isCreatingFacility"
          :creating-schedule="isCreatingSchedule"
          :creating-user="isCreatingUser"
          @create-facility="handleCreateFacility"
          @create-schedule="handleCreateSchedule"
          @create-user="handleCreateUser"
          @reset-password="(id, name) => (passwordModal = { isOpen: true, userId: id, userName: name, newPassword: '', isSubmitting: false })"
          @delete-user="(id, name) => (deleteModal = { isOpen: true, userId: id, userName: name, isSubmitting: false })"
        />
      </div>
    </div>
  </div>
</template>
