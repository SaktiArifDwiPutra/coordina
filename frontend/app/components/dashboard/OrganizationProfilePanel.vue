<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { Save, Upload, ImageIcon, PenTool, AlertTriangle, RefreshCw } from 'lucide-vue-next'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import StatusBadge from '~/components/dashboard/StatusBadge.vue'
import { useApi } from '~/composables/useApi'

const MAX_ASSET_BYTES = 2 * 1024 * 1024

const props = defineProps({
  profile: { type: Object, default: null },
  loadingProfile: { type: Boolean, default: false },
  profileError: { type: String, default: '' },
  saving: { type: Boolean, default: false },
  uploading: { type: Boolean, default: false },
})

const emit = defineEmits(['save-profile', 'upload-assets', 'retry-profile'])
const { apiFetch } = useApi()

const form = ref({
  advisor_name: '',
  chairperson_name: '',
  chairperson_title: '',
  chairperson_id_number: '',
})

const logoFile = ref(null)
const signatureFile = ref(null)
const logoInputRef = ref(null)
const signatureInputRef = ref(null)
const fileError = ref('')
const logoPreviewUrl = ref('')
const signaturePreviewUrl = ref('')
const pendingLogoUrl = ref('')
const pendingSignatureUrl = ref('')

const chairNameId = useId()
const chairTitleId = useId()
const chairNimId = useId()
const advisorId = useId()
const logoUploadId = useId()
const sigUploadId = useId()

async function loadAssetPreviews(profile) {
  if (logoPreviewUrl.value) URL.revokeObjectURL(logoPreviewUrl.value)
  if (signaturePreviewUrl.value) URL.revokeObjectURL(signaturePreviewUrl.value)
  logoPreviewUrl.value = ''
  signaturePreviewUrl.value = ''

  if (!profile?.id) return

  if (profile.has_logo) {
    try {
      const blob = await apiFetch(`/api/organizations/${profile.id}/logo`, { responseType: 'blob' })
      logoPreviewUrl.value = URL.createObjectURL(blob)
    } catch {}
  }

  if (profile.has_signature) {
    try {
      const blob = await apiFetch(`/api/organizations/${profile.id}/signature`, { responseType: 'blob' })
      signaturePreviewUrl.value = URL.createObjectURL(blob)
    } catch {}
  }
}

watch(
  () => props.profile,
  (val) => {
    if (!val) return
    form.value = {
      advisor_name: val.advisor_name || '',
      chairperson_name: val.chairperson_name || '',
      chairperson_title: val.chairperson_title || '',
      chairperson_id_number: val.chairperson_id_number || '',
    }
    loadAssetPreviews(val)
  },
  { immediate: true }
)

onUnmounted(() => {
  if (logoPreviewUrl.value) URL.revokeObjectURL(logoPreviewUrl.value)
  if (signaturePreviewUrl.value) URL.revokeObjectURL(signaturePreviewUrl.value)
  if (pendingLogoUrl.value) URL.revokeObjectURL(pendingLogoUrl.value)
  if (pendingSignatureUrl.value) URL.revokeObjectURL(pendingSignatureUrl.value)
})

function onFileSelected(type, event) {
  fileError.value = ''
  const selected = event.target.files?.[0] || null
  if (selected && selected.size > MAX_ASSET_BYTES) {
    fileError.value = `Ukuran file ${selected.name} melebihi batas maksimal 2MB.`
    event.target.value = ''
    if (type === 'logo') {
      logoFile.value = null
      if (pendingLogoUrl.value) URL.revokeObjectURL(pendingLogoUrl.value)
      pendingLogoUrl.value = ''
    } else {
      signatureFile.value = null
      if (pendingSignatureUrl.value) URL.revokeObjectURL(pendingSignatureUrl.value)
      pendingSignatureUrl.value = ''
    }
    return
  }

  if (type === 'logo') {
    if (pendingLogoUrl.value) URL.revokeObjectURL(pendingLogoUrl.value)
    logoFile.value = selected
    pendingLogoUrl.value = selected ? URL.createObjectURL(selected) : ''
  } else {
    if (pendingSignatureUrl.value) URL.revokeObjectURL(pendingSignatureUrl.value)
    signatureFile.value = selected
    pendingSignatureUrl.value = selected ? URL.createObjectURL(selected) : ''
  }
}

function handleSaveProfile() {
  emit('save-profile', { ...form.value })
}

function handleUploadAssets() {
  if (!logoFile.value && !signatureFile.value) return
  fileError.value = ''
  const fd = new FormData()
  if (logoFile.value) fd.append('logo', logoFile.value)
  if (signatureFile.value) fd.append('signature_image', signatureFile.value)
  emit('upload-assets', fd, () => {
    logoFile.value = null
    signatureFile.value = null
    if (pendingLogoUrl.value) URL.revokeObjectURL(pendingLogoUrl.value)
    if (pendingSignatureUrl.value) URL.revokeObjectURL(pendingSignatureUrl.value)
    pendingLogoUrl.value = ''
    pendingSignatureUrl.value = ''
    if (logoInputRef.value) logoInputRef.value.value = ''
    if (signatureInputRef.value) signatureInputRef.value.value = ''
  })
}
</script>

<template>
  <section id="organization-profile" class="space-y-5">
    <div v-if="loadingProfile && !profile" class="bg-white rounded-2xl border border-border shadow-sm p-8 text-center text-sm text-slate-600">
      Memuat profil organisasi dan tanda tangan…
    </div>

    <div
      v-else-if="!profile"
      class="bg-white rounded-2xl border border-amber-300 shadow-sm p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
      role="status"
    >
      <div class="flex items-start gap-3">
        <AlertTriangle class="w-5 h-5 text-amber-700 shrink-0 mt-0.5" aria-hidden="true" />
        <div class="space-y-1">
          <h3 class="text-base font-bold text-slate-900">Profil Organisasi Belum Tersedia</h3>
          <p class="text-sm text-slate-600 leading-relaxed">
            {{ profileError || 'Akun Anda belum terhubung ke profil organisasi atau gagal memuat data. Coba muat ulang halaman ini.' }}
          </p>
        </div>
      </div>
      <Button type="button" variant="outline" class="shrink-0" @click="emit('retry-profile')">
        <RefreshCw class="w-4 h-4" aria-hidden="true" />
        <span>Muat Ulang Profil</span>
      </Button>
    </div>

    <template v-else>
      <div class="bg-white rounded-2xl border border-border shadow-sm p-5 sm:p-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-slate-900">
            Profil, Logo &amp; Tanda Tangan {{ profile.name ? `(${profile.name})` : 'Organisasi' }}
          </h2>
          <p class="text-sm text-slate-600 mt-0.5">
            Digunakan otomatis untuk kop surat dan lembar pengesahan resmi pada dokumen PDF.
          </p>
        </div>
        <StatusBadge
          :label="profile.is_complete_for_signing ? 'Siap Menandatangani Dokumen' : 'Profil Tanda Tangan Belum Lengkap'"
          :tone="profile.is_complete_for_signing ? 'emerald' : 'amber'"
          :icon="profile.is_complete_for_signing ? 'check' : 'alert'"
        />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card class="border-border shadow-sm p-6 bg-white rounded-2xl">
          <h3 class="text-base font-bold text-slate-900 mb-4">1. Identitas Ketua / Penanggung Jawab</h3>
          <form @submit.prevent="handleSaveProfile" class="space-y-4">
            <div class="space-y-1.5">
              <Label :for="chairNameId">Nama Lengkap Ketua</Label>
              <Input :id="chairNameId" v-model="form.chairperson_name" name="chairperson_name" placeholder="Contoh: Raka Pratama…" class="h-11 rounded-xl" required />
            </div>
            <div class="space-y-1.5">
              <Label :for="chairTitleId">Jabatan pada Surat</Label>
              <Input
                :id="chairTitleId"
                v-model="form.chairperson_title"
                name="chairperson_title"
                :placeholder="profile.is_mpk ? 'Ketua Komisi 2 MPK' : `Ketua ${profile.name}`"
                class="h-11 rounded-xl"
                required
              />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="space-y-1.5">
                <Label :for="chairNimId">NIS / Nomor Identitas</Label>
                <Input :id="chairNimId" v-model="form.chairperson_id_number" name="chairperson_id_number" placeholder="Opsional…" class="h-11 rounded-xl tabular-nums" />
              </div>
              <div class="space-y-1.5">
                <Label :for="advisorId">Nama Pembina</Label>
                <Input :id="advisorId" v-model="form.advisor_name" name="advisor_name" placeholder="Nama pembina…" class="h-11 rounded-xl" />
              </div>
            </div>
            <Button :disabled="saving" type="submit" class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white font-semibold">
              <Save class="w-4 h-4" aria-hidden="true" />
              <span>{{ saving ? 'Menyimpan…' : 'Simpan Identitas Ketua' }}</span>
            </Button>
          </form>
        </Card>

        <Card class="border-border shadow-sm p-6 bg-white rounded-2xl space-y-5">
          <h3 class="text-base font-bold text-slate-900">2. Logo Organisasi &amp; Gambar Tanda Tangan</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="border border-dashed border-slate-300 bg-slate-50 rounded-xl p-4 text-center">
              <p class="text-xs font-semibold text-slate-600 mb-2 flex items-center justify-center gap-1">
                <ImageIcon class="w-3.5 h-3.5" aria-hidden="true" />
                <span>{{ pendingLogoUrl ? 'Pratinjau Logo Baru' : 'Logo Saat Ini' }}</span>
              </p>
              <img
                v-if="pendingLogoUrl || logoPreviewUrl"
                :src="pendingLogoUrl || logoPreviewUrl"
                alt="Logo organisasi"
                class="h-16 mx-auto object-contain"
                width="64"
                height="64"
              />
              <p v-else class="text-xs text-slate-500 py-5">Belum ada logo</p>
            </div>
            <div class="border border-dashed border-slate-300 bg-slate-50 rounded-xl p-4 text-center">
              <p class="text-xs font-semibold text-slate-600 mb-2 flex items-center justify-center gap-1">
                <PenTool class="w-3.5 h-3.5" aria-hidden="true" />
                <span>{{ pendingSignatureUrl ? 'Pratinjau TTD Baru' : 'Tanda Tangan Saat Ini' }}</span>
              </p>
              <img
                v-if="pendingSignatureUrl || signaturePreviewUrl"
                :src="pendingSignatureUrl || signaturePreviewUrl"
                alt="Tanda tangan"
                class="h-16 mx-auto object-contain"
                width="120"
                height="64"
              />
              <p v-else class="text-xs text-slate-500 py-5">Belum ada gambar TTD</p>
            </div>
          </div>

          <form @submit.prevent="handleUploadAssets" class="space-y-4">
            <div class="space-y-1.5">
              <Label :for="logoUploadId">Unggah Logo Baru (PNG/JPG, maks 2MB)</Label>
              <input
                :id="logoUploadId"
                ref="logoInputRef"
                type="file"
                accept=".png,.jpg,.jpeg"
                class="text-sm block w-full text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border file:border-slate-300 file:bg-slate-50 file:text-xs file:font-semibold hover:file:bg-slate-100"
                @change="(e) => onFileSelected('logo', e)"
              >
            </div>
            <div class="space-y-1.5">
              <Label :for="sigUploadId">Unggah Gambar Tanda Tangan (PNG transparan disarankan, maks 2MB)</Label>
              <input
                :id="sigUploadId"
                ref="signatureInputRef"
                type="file"
                accept=".png,.jpg,.jpeg"
                class="text-sm block w-full text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border file:border-slate-300 file:bg-slate-50 file:text-xs file:font-semibold hover:file:bg-slate-100"
                @change="(e) => onFileSelected('signature', e)"
              >
            </div>
            <p v-if="fileError" role="alert" class="text-xs text-rose-700 font-semibold">{{ fileError }}</p>
            <Button
              :disabled="uploading || (!logoFile && !signatureFile)"
              type="submit"
              class="w-full h-11 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white font-semibold"
            >
              <Upload class="w-4 h-4" aria-hidden="true" />
              <span>{{ uploading ? 'Mengunggah…' : 'Unggah Logo & Tanda Tangan' }}</span>
            </Button>
          </form>
        </Card>
      </div>
    </template>
  </section>
</template>
