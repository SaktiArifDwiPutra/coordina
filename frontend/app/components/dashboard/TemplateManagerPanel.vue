<script setup>
import { ref } from 'vue'
import { Download, Sparkles, Upload } from 'lucide-vue-next'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'
import StatusBadge from '~/components/dashboard/StatusBadge.vue'

const MAX_TEMPLATE_BYTES = 5 * 1024 * 1024

defineProps({
  templates: { type: Array, default: () => [] },
  requiredPlaceholders: { type: Array, default: () => [] },
  uploading: { type: Boolean, default: false },
  generatingDefault: { type: Boolean, default: false },
})

const emit = defineEmits(['upload-template', 'generate-default', 'download-template'])

const name = ref('Master Surat Perizinan MPK SMKN 4')
const workflowScope = ref('shared')
const file = ref(null)
const templateInputRef = ref(null)
const fileError = ref('')
const showPlaceholders = ref(false)

const tplNameId = useId()
const tplScopeId = useId()
const tplFileId = useId()

function scopeLabel(scope) {
  if (scope === 'workflow_a') return 'Workflow A (3 TTD)'
  if (scope === 'workflow_b') return 'Workflow B (2 TTD)'
  return 'Semua Alur (Shared)'
}

function onFileSelected(event) {
  fileError.value = ''
  const selected = event.target.files?.[0] || null
  if (selected && selected.size > MAX_TEMPLATE_BYTES) {
    fileError.value = `Ukuran file ${selected.name} melebihi batas maksimal 5MB.`
    event.target.value = ''
    file.value = null
    return
  }
  file.value = selected
}

function submitUpload() {
  if (!file.value) return
  fileError.value = ''
  const fd = new FormData()
  fd.append('name', name.value)
  fd.append('workflow_scope', workflowScope.value)
  fd.append('template', file.value)
  emit('upload-template', fd, () => {
    file.value = null
    workflowScope.value = 'shared'
    if (templateInputRef.value) templateInputRef.value.value = ''
  })
}
</script>

<template>
  <section class="space-y-5">
    <div class="bg-white rounded-2xl border border-border shadow-sm p-5 sm:p-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="text-lg font-bold text-slate-900">Master Template Surat DOCX</h2>
        <p class="text-sm text-slate-600 mt-0.5">
          Template master DOCX digunakan dinamis untuk surat baru dan tidak mengubah arsip PDF lama yang sudah final.
        </p>
      </div>
      <Button :disabled="generatingDefault" type="button" variant="outline" @click="emit('generate-default')">
        <Sparkles class="w-4 h-4" aria-hidden="true" />
        <span>{{ generatingDefault ? 'Membuat Template…' : 'Buat Template Default SMKN 4 Padalarang' }}</span>
      </Button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card class="border-border shadow-sm p-6 bg-white rounded-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-4">Unggah / Ganti Master Template (.docx)</h3>
        <form @submit.prevent="submitUpload" class="space-y-4">
          <div class="space-y-1.5">
            <Label :for="tplNameId">Nama Template</Label>
            <Input :id="tplNameId" v-model="name" class="h-11 rounded-xl" required />
          </div>
          <div class="space-y-1.5">
            <Label :for="tplScopeId">Cakupan Alur Surat</Label>
            <select
              :id="tplScopeId"
              v-model="workflowScope"
              class="flex h-11 w-full rounded-xl border border-input bg-white px-3.5 text-sm text-slate-900"
            >
              <option value="shared">Semua Alur (Workflow A, B &amp; Slot Kosong)</option>
              <option value="workflow_a">Khusus Workflow A (Antar-Ekskul 3 TTD)</option>
              <option value="workflow_b">Khusus Workflow B (Peminjaman MPK 2 TTD)</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <Label :for="tplFileId">File DOCX (Maks 5MB)</Label>
            <input
              :id="tplFileId"
              ref="templateInputRef"
              type="file"
              accept=".docx"
              required
              class="text-sm block w-full text-slate-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border file:border-slate-300 file:bg-slate-50 file:text-xs file:font-semibold hover:file:bg-slate-100"
              @change="onFileSelected"
            >
          </div>
          <p v-if="fileError" role="alert" class="text-xs text-rose-700 font-semibold">{{ fileError }}</p>
          <Button :disabled="uploading || !file" type="submit" class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white font-semibold">
            <Upload class="w-4 h-4" aria-hidden="true" />
            <span>{{ uploading ? 'Memvalidasi & Mengunggah…' : 'Aktifkan Master Template' }}</span>
          </Button>
        </form>
        <div v-if="requiredPlaceholders.length" class="mt-5 pt-4 border-t border-border space-y-2">
          <button
            type="button"
            :aria-expanded="showPlaceholders"
            class="text-xs font-semibold text-[#1E3A5F] hover:underline"
            @click="showPlaceholders = !showPlaceholders"
          >
            {{ showPlaceholders ? 'Sembunyikan daftar kode placeholder DOCX' : `Lihat ${requiredPlaceholders.length} kode placeholder wajib DOCX` }}
          </button>
          <p v-if="showPlaceholders" class="text-xs text-slate-600 break-words leading-relaxed rounded-xl bg-slate-50 border border-border p-3">
            {{ requiredPlaceholders.map((p) => '${' + p + '}').join(', ') }}
          </p>
        </div>
      </Card>

      <Card class="border-border shadow-sm p-6 bg-white rounded-2xl">
        <h3 class="text-base font-bold text-slate-900 mb-4">Riwayat Versi Template</h3>
        <div v-if="templates.length === 0" class="text-sm text-slate-600 py-4 leading-relaxed">
          Belum ada master template yang diunggah. Klik tombol "Buat Template Default SMKN 4 Padalarang" atau unggah file DOCX.
        </div>
        <div v-else class="space-y-3 max-h-80 overflow-y-auto pr-1">
          <div
            v-for="tpl in templates"
            :key="tpl.id"
            class="p-3.5 rounded-xl border border-border bg-slate-50/70 flex justify-between items-center gap-3"
          >
            <div class="space-y-1 min-w-0">
              <p class="text-sm font-bold text-slate-900 truncate">
                {{ tpl.name }} <span class="text-xs font-medium text-slate-500">(v{{ tpl.version }})</span>
              </p>
              <div class="flex flex-wrap items-center gap-1.5">
                <StatusBadge
                  :label="tpl.is_active ? 'Aktif' : 'Arsip Lama'"
                  :tone="tpl.is_active ? 'emerald' : 'slate'"
                  :icon="tpl.is_active ? 'check' : 'clock'"
                />
                <span class="text-xs px-2.5 py-0.5 rounded-md font-medium bg-blue-50 text-[#1E3A5F]">
                  {{ scopeLabel(tpl.workflow_scope) }}
                </span>
              </div>
            </div>
            <Button type="button" size="sm" variant="outline" class="shrink-0" @click="emit('download-template', tpl)">
              <Download class="w-4 h-4" aria-hidden="true" />
              <span>Unduh</span>
            </Button>
          </div>
        </div>
      </Card>
    </div>
  </section>
</template>
