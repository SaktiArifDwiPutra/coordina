<script setup>
import { ref, watch, onUnmounted } from 'vue'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import { useApi } from '~/composables/useApi'

const MAX_ASSET_BYTES = 2 * 1024 * 1024

const props = defineProps({
  profile: { type: Object, default: null },
  saving: { type: Boolean, default: false },
  uploading: { type: Boolean, default: false },
})

const emit = defineEmits(['save-profile', 'upload-assets'])
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
})

function onFileSelected(type, event) {
  fileError.value = ''
  const selected = event.target.files?.[0] || null
  if (selected && selected.size > MAX_ASSET_BYTES) {
    fileError.value = `Ukuran file ${selected.name} melebihi batas maksimal 2MB.`
    event.target.value = ''
    if (type === 'logo') logoFile.value = null
    else signatureFile.value = null
    return
  }

  if (type === 'logo') logoFile.value = selected
  else signatureFile.value = selected
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
    if (logoInputRef.value) logoInputRef.value.value = ''
    if (signatureInputRef.value) signatureInputRef.value.value = ''
  })
}
</script>

<template>
  <div v-if="profile" id="organization-profile">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
      <div>
        <h2 class="text-xl font-bold text-zinc-900">Profil & Tanda Tangan Organisasi</h2>
        <p class="text-sm text-zinc-500">Digunakan untuk kop surat dinamis dan pembubuhan tanda tangan persetujuan resmi.</p>
      </div>
      <span
        :class="[
          'px-3 py-1 text-xs font-semibold rounded-full',
          profile.is_complete_for_signing ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800',
        ]"
      >
        {{ profile.is_complete_for_signing ? 'Siap Menandatangani Dokumen' : 'Profil Tanda Tangan Belum Lengkap' }}
      </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card class="border-zinc-200 shadow-sm p-6">
        <h3 class="text-base font-bold text-zinc-900 mb-4">Identitas Ketua / Penanggung Jawab</h3>
        <form @submit.prevent="handleSaveProfile" class="space-y-4">
          <div class="space-y-2">
            <Label>Nama Lengkap Ketua</Label>
            <Input v-model="form.chairperson_name" placeholder="Contoh: Raka Pratama" required />
          </div>
          <div class="space-y-2">
            <Label>Jabatan pada Surat</Label>
            <Input
              v-model="form.chairperson_title"
              :placeholder="profile.is_mpk ? 'Ketua Komisi 2 MPK' : `Ketua ${profile.name}`"
              required
            />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-2">
              <Label>NIS / Nomor Identitas</Label>
              <Input v-model="form.chairperson_id_number" placeholder="Opsional..." />
            </div>
            <div class="space-y-2">
              <Label>Nama Pembina</Label>
              <Input v-model="form.advisor_name" placeholder="Nama pembina..." />
            </div>
          </div>
          <Button :disabled="saving" type="submit" class="w-full bg-zinc-900 text-white">
            {{ saving ? 'Menyimpan...' : 'Simpan Identitas Ketua' }}
          </Button>
        </form>
      </Card>

      <Card class="border-zinc-200 shadow-sm p-6 space-y-4">
        <h3 class="text-base font-bold text-zinc-900">Logo Organisasi & Gambar Tanda Tangan</h3>
        <div class="grid grid-cols-2 gap-4">
          <div class="border border-dashed border-zinc-200 rounded-lg p-3 text-center">
            <p class="text-xs font-medium text-zinc-600 mb-2">Logo Saat Ini</p>
            <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Logo organisasi" class="h-16 mx-auto object-contain" />
            <p v-else class="text-xs text-zinc-400 py-5">Belum ada logo</p>
          </div>
          <div class="border border-dashed border-zinc-200 rounded-lg p-3 text-center">
            <p class="text-xs font-medium text-zinc-600 mb-2">Tanda Tangan Saat Ini</p>
            <img v-if="signaturePreviewUrl" :src="signaturePreviewUrl" alt="Tanda tangan" class="h-16 mx-auto object-contain" />
            <p v-else class="text-xs text-zinc-400 py-5">Belum ada TTD</p>
          </div>
        </div>

        <form @submit.prevent="handleUploadAssets" class="space-y-3">
          <div class="space-y-1">
            <Label>Unggah Logo Baru (PNG/JPG, maks 2MB)</Label>
            <input ref="logoInputRef" type="file" accept=".png,.jpg,.jpeg" class="text-xs block w-full" @change="(e) => onFileSelected('logo', e)" />
          </div>
          <div class="space-y-1">
            <Label>Unggah Gambar Tanda Tangan (PNG transparan disarankan, maks 2MB)</Label>
            <input ref="signatureInputRef" type="file" accept=".png,.jpg,.jpeg" class="text-xs block w-full" @change="(e) => onFileSelected('signature', e)" />
          </div>
          <p v-if="fileError" class="text-xs text-red-600 font-medium">{{ fileError }}</p>
          <Button :disabled="uploading || (!logoFile && !signatureFile)" type="submit" class="w-full bg-indigo-600 text-white">
            {{ uploading ? 'Mengunggah...' : 'Unggah Aset Terpilih' }}
          </Button>
        </form>
      </Card>
    </div>
  </div>
</template>
