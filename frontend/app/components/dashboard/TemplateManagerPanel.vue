<script setup>
import { ref } from 'vue'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { Input } from '~/components/ui/input'
import { Button } from '~/components/ui/button'

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
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div>
        <h2 class="text-xl font-bold text-zinc-900">Master Template Surat DOCX</h2>
        <p class="text-sm text-zinc-500">Satu template master DOCX digunakan dinamis untuk seluruh organisasi dan tidak mengubah arsip PDF lama.</p>
      </div>
      <Button :disabled="generatingDefault" variant="outline" @click="emit('generate-default')">
        {{ generatingDefault ? 'Membuat Template...' : 'Buat Template Default SMKN 4 Padalarang' }}
      </Button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <Card class="border-zinc-200 shadow-sm p-6">
        <h3 class="text-base font-bold text-zinc-900 mb-3">Unggah / Ganti Master Template (.docx)</h3>
        <form @submit.prevent="submitUpload" class="space-y-4">
          <div class="space-y-2">
            <Label>Nama Template</Label>
            <Input v-model="name" required />
          </div>
          <div class="space-y-2">
            <Label>Cakupan Alur Surat</Label>
            <select
              v-model="workflowScope"
              class="flex h-10 w-full rounded-md border border-zinc-200 bg-white px-3 text-sm"
            >
              <option value="shared">Semua Alur (Shared — Workflow A, B &amp; Slot Kosong)</option>
              <option value="workflow_a">Khusus Workflow A (Antar-Ekskul 3 TTD)</option>
              <option value="workflow_b">Khusus Workflow B (MPK Meminjam 2 TTD)</option>
            </select>
          </div>
          <div class="space-y-2">
            <Label>File DOCX (Maks 5MB)</Label>
            <input ref="templateInputRef" type="file" accept=".docx" required class="text-xs block w-full" @change="onFileSelected" />
          </div>
          <p v-if="fileError" class="text-xs text-red-600 font-medium">{{ fileError }}</p>
          <Button :disabled="uploading || !file" type="submit" class="w-full bg-zinc-900 text-white">
            {{ uploading ? 'Memvalidasi & Mengunggah...' : 'Aktifkan Master Template' }}
          </Button>
        </form>
        <div v-if="requiredPlaceholders.length" class="mt-4 pt-3 border-t border-zinc-100">
          <p class="text-xs font-semibold text-zinc-700 mb-1">Placeholder Wajib pada DOCX:</p>
          <p class="text-[11px] text-zinc-500 break-words leading-relaxed">
            {{ requiredPlaceholders.map((p) => '${' + p + '}').join(', ') }}
          </p>
        </div>
      </Card>

      <Card class="border-zinc-200 shadow-sm p-6">
        <h3 class="text-base font-bold text-zinc-900 mb-3">Riwayat Versi Template</h3>
        <div v-if="templates.length === 0" class="text-sm text-zinc-500 py-4">
          Belum ada master template yang diunggah. Klik "Buat Template Default SMKN 4 Padalarang" atau unggah file DOCX.
        </div>
        <div v-else class="space-y-3 max-h-64 overflow-y-auto">
          <div
            v-for="tpl in templates"
            :key="tpl.id"
            class="p-3 rounded-lg border border-zinc-200 flex justify-between items-center gap-3"
          >
            <div>
              <p class="text-sm font-semibold text-zinc-900">
                {{ tpl.name }} <span class="text-xs text-zinc-500">(v{{ tpl.version }})</span>
              </p>
              <div class="flex flex-wrap items-center gap-1.5 mt-1">
                <span
                  :class="[
                    'text-[11px] px-2 py-0.5 rounded font-medium inline-block',
                    tpl.is_active ? 'bg-green-100 text-green-800' : 'bg-zinc-100 text-zinc-600',
                  ]"
                >
                  {{ tpl.is_active ? 'Aktif' : 'Arsip Lama' }}
                </span>
                <span class="text-[11px] px-2 py-0.5 rounded font-medium bg-indigo-50 text-indigo-700 inline-block">
                  {{ scopeLabel(tpl.workflow_scope) }}
                </span>
              </div>
            </div>
            <Button size="sm" variant="outline" @click="emit('download-template', tpl)">Unduh DOCX</Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
