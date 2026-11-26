<script setup>
import { Card } from '~/components/ui/card'
import { Button } from '~/components/ui/button'

defineProps({
  requests: { type: Array, default: () => [] },
  profileComplete: { type: Boolean, default: false },
  signingId: { type: Number, default: null },
})

const emit = defineEmits(['approve', 'reject', 'sign'])

function scrollToProfile() {
  document.getElementById('organization-profile')?.scrollIntoView({ behavior: 'smooth' })
}
</script>

<template>
  <div v-if="requests.length > 0" class="space-y-4">
    <h2 class="text-xl font-bold text-zinc-900 flex items-center gap-2">
      <span>🔔</span> Permohonan Izin Masuk
    </h2>

    <div
      v-if="!profileComplete"
      class="p-3.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
    >
      <span>
        <strong>Profil tanda tangan belum lengkap.</strong> Lengkapi nama ketua, jabatan, dan gambar tanda tangan sebelum menyetujui & menandatangani izin.
      </span>
      <button type="button" class="font-semibold underline shrink-0 text-left" @click="scrollToProfile">
        Lengkapi Profil Organisasi →
      </button>
    </div>

    <div class="space-y-4">
      <Card v-for="req in requests" :key="'inbox-' + req.id" class="border-amber-200 bg-amber-50 shadow-sm p-5">
        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-bold text-lg text-zinc-900">
                {{ req.organization?.name }}
                <span class="text-zinc-600 font-normal text-sm">meminta izin memakai jadwal Anda di</span>
                {{ req.facility?.name }}
              </h3>
              <span
                v-if="req.workflow_type === 'mpk_borrow'"
                class="text-xs px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 font-semibold"
              >
                Permintaan MPK
              </span>
            </div>
            <p class="text-sm text-zinc-700 mt-1">
              📅 {{ req.date }} | ⏰ {{ req.start_time.substring(0, 5) }} - {{ req.end_time.substring(0, 5) }}
            </p>
            <p class="text-sm text-zinc-700 mt-1">📝 Alasan: "{{ req.reason }}"</p>
          </div>
          <div class="flex flex-wrap gap-2 shrink-0">
            <template v-if="req.status === 'pending_owner'">
              <Button :disabled="!profileComplete || signingId === req.id" @click="emit('approve', req)" class="bg-green-600 hover:bg-green-700 text-white">
                Izinkan & TTD
              </Button>
              <Button @click="emit('reject', req)" variant="outline" class="text-red-600 border-red-200 hover:bg-red-50">Tolak</Button>
            </template>
            <Button
              v-else-if="req.status === 'owner_approved' && req.document?.document_status === 'waiting_owner_signature'"
              :disabled="!profileComplete || signingId === req.id"
              @click="emit('sign', req)"
              class="bg-indigo-600 hover:bg-indigo-700 text-white"
            >
              {{ signingId === req.id ? 'Menyimpan...' : 'Bubuhkan TTD Pemilik' }}
            </Button>
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>
