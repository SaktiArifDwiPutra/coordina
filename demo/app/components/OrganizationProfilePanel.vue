<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { CheckCircle2, AlertTriangle, Save, Sparkles, Upload, Users, UserPlus } from 'lucide-vue-next'
import StatusBadge from '~/components/StatusBadge.vue'
import { createSvgBadgeDataUrl, createSvgSignatureDataUrl } from '~/data/mockData'
import { isOrgProfileComplete } from '~/lib/workflow'
import type { DemoRole, OrganizationProfile } from '~/types/coordina'

const props = defineProps<{
  role: DemoRole
  activeOrg: OrganizationProfile
  organizations: OrganizationProfile[]
}>()

const emit = defineEmits<{
  (e: 'save-profile', orgId: number, updates: Partial<OrganizationProfile>): void
  (e: 'create-account', payload: { name: string; accountEmail: string; chairpersonName: string }): void
  (e: 'toggle-active', orgId: number): void
}>()

const form = ref({
  chairpersonName: '',
  chairpersonTitle: '',
  chairpersonIdNumber: '',
  advisorName: '',
})

const newOrg = ref({ name: '', accountEmail: '', chairpersonName: '' })

watch(
  () => props.activeOrg,
  (org) => {
    form.value = {
      chairpersonName: org.chairpersonName,
      chairpersonTitle: org.chairpersonTitle,
      chairpersonIdNumber: org.chairpersonIdNumber,
      advisorName: org.advisorName,
    }
  },
  { immediate: true, deep: true }
)

const checklist = computed(() => [
  {
    label: 'Identitas Ketua Organisasi',
    done: Boolean(props.activeOrg.chairpersonName.trim() && props.activeOrg.chairpersonTitle.trim()),
  },
  {
    label: 'Logo Resmi Organisasi',
    done: Boolean(props.activeOrg.logoDataUrl),
  },
  {
    label: 'Gambar Tanda Tangan Ketua',
    done: Boolean(props.activeOrg.signatureDataUrl),
  },
])

const isComplete = computed(() => isOrgProfileComplete(props.activeOrg))

function onSaveIdentity() {
  emit('save-profile', props.activeOrg.id, {
    chairpersonName: form.value.chairpersonName.trim(),
    chairpersonTitle: form.value.chairpersonTitle.trim(),
    chairpersonIdNumber: form.value.chairpersonIdNumber.trim(),
    advisorName: form.value.advisorName.trim(),
  })
}

function applyPresetLogo() {
  emit('save-profile', props.activeOrg.id, {
    logoDataUrl: createSvgBadgeDataUrl(props.activeOrg.shortCode, '#1E3A5F'),
  })
}

function applyPresetSignature() {
  emit('save-profile', props.activeOrg.id, {
    signatureDataUrl: createSvgSignatureDataUrl('#1E3A5F', 2),
  })
}

function onFileChange(kind: 'logo' | 'signature', event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    const result = typeof reader.result === 'string' ? reader.result : null
    if (!result) return
    if (kind === 'logo') emit('save-profile', props.activeOrg.id, { logoDataUrl: result })
    else emit('save-profile', props.activeOrg.id, { signatureDataUrl: result })
  }
  reader.readAsDataURL(file)
}

function onAddAccount() {
  if (!newOrg.value.name.trim() || !newOrg.value.accountEmail.trim() || !newOrg.value.chairpersonName.trim()) return
  emit('create-account', {
    name: newOrg.value.name.trim(),
    accountEmail: newOrg.value.accountEmail.trim(),
    chairpersonName: newOrg.value.chairpersonName.trim(),
  })
  newOrg.value = { name: '', accountEmail: '', chairpersonName: '' }
}

const pNameId = useId()
const pTitleId = useId()
const pNimId = useId()
const pAdvId = useId()
</script>

<template>
  <div class="space-y-6">
    <!-- TOP PROFILE SUMMARY + 3-ITEM COMPLETENESS CHECKLIST -->
    <section class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-5 sm:p-6 space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-[#0F172A]">Profil &amp; Tanda Tangan · {{ activeOrg.name }}</h2>
          <p class="text-sm text-[#475569] mt-0.5">
            Lengkapi ketiga unsur di bawah agar organisasi dapat menandatangani surat izin dalam demo.
          </p>
        </div>
        <StatusBadge
          :label="isComplete ? 'Siap Menandatangani Surat' : 'Profil Belum Lengkap'"
          :tone="isComplete ? 'emerald' : 'amber'"
          :icon="isComplete ? 'check' : 'clock'"
        />
      </div>

      <ul class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <li
          v-for="item in checklist"
          :key="item.label"
          :class="[
            'rounded-xl border p-3.5 flex items-center gap-2.5 text-sm font-semibold',
            item.done ? 'border-[#A7F3D0] bg-[#ECFDF5]/60 text-[#15803D]' : 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
          ]"
        >
          <component :is="item.done ? CheckCircle2 : AlertTriangle" class="w-4 h-4 shrink-0" aria-hidden="true" />
          <span>{{ item.label }}</span>
        </li>
      </ul>
    </section>

    <!-- IDENTITY FORM + LOGO / SIGNATURE SIMULATOR -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <section class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-6">
        <h3 class="text-base font-bold text-[#0F172A] mb-4">1. Identitas Ketua Organisasi</h3>
        <form @submit.prevent="onSaveIdentity" class="space-y-4">
          <div class="space-y-1.5">
            <label :for="pNameId" class="block text-sm font-semibold text-[#0F172A]">Nama Lengkap Ketua</label>
            <input :id="pNameId" v-model="form.chairpersonName" type="text" required class="w-full h-11 rounded-xl border border-[#DCE4EE] px-3.5 text-sm">
          </div>
          <div class="space-y-1.5">
            <label :for="pTitleId" class="block text-sm font-semibold text-[#0F172A]">Jabatan pada Surat</label>
            <input :id="pTitleId" v-model="form.chairpersonTitle" type="text" :placeholder="`Ketua ${activeOrg.name}`" required class="w-full h-11 rounded-xl border border-[#DCE4EE] px-3.5 text-sm">
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label :for="pNimId" class="block text-sm font-semibold text-[#0F172A]">NIS / Nomor Identitas</label>
              <input :id="pNimId" v-model="form.chairpersonIdNumber" type="text" class="w-full h-11 rounded-xl border border-[#DCE4EE] px-3.5 text-sm tabular-nums">
            </div>
            <div class="space-y-1.5">
              <label :for="pAdvId" class="block text-sm font-semibold text-[#0F172A]">Nama Pembina</label>
              <input :id="pAdvId" v-model="form.advisorName" type="text" class="w-full h-11 rounded-xl border border-[#DCE4EE] px-3.5 text-sm">
            </div>
          </div>
          <button type="submit" class="w-full h-11 inline-flex items-center justify-center gap-2 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold">
            <Save class="w-4 h-4" aria-hidden="true" />
            <span>Simpan Identitas (Simulasi)</span>
          </button>
        </form>
      </section>

      <section class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-6 space-y-5">
        <h3 class="text-base font-bold text-[#0F172A]">2. Logo &amp; Gambar Tanda Tangan (Simulasi)</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="rounded-xl border border-dashed border-[#DCE4EE] bg-[#F8FAFC] p-4 text-center space-y-2.5">
            <p class="text-xs font-semibold text-[#475569]">Logo Organisasi</p>
            <div class="h-16 flex items-center justify-center">
              <img v-if="activeOrg.logoDataUrl" :src="activeOrg.logoDataUrl" :alt="`Logo ${activeOrg.name}`" class="h-16 object-contain">
              <span v-else class="text-xs text-[#475569]">Belum ada logo</span>
            </div>
            <div class="flex flex-col gap-1.5">
              <button
                type="button"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#EFF6FF] text-[#1E3A5F] text-xs font-semibold hover:bg-blue-100"
                @click="applyPresetLogo"
              >
                <Sparkles class="w-3.5 h-3.5" aria-hidden="true" />
                <span>Pakai Logo Contoh</span>
              </button>
              <label class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#DCE4EE] bg-white text-xs font-semibold text-[#0F172A] cursor-pointer hover:bg-[#F8FAFC]">
                <Upload class="w-3.5 h-3.5" aria-hidden="true" />
                <span>Unggah Gambar</span>
                <input type="file" accept="image/*" class="sr-only" @change="(e) => onFileChange('logo', e)">
              </label>
            </div>
          </div>

          <div class="rounded-xl border border-dashed border-[#DCE4EE] bg-[#F8FAFC] p-4 text-center space-y-2.5">
            <p class="text-xs font-semibold text-[#475569]">Tanda Tangan Ketua</p>
            <div class="h-16 flex items-center justify-center">
              <img v-if="activeOrg.signatureDataUrl" :src="activeOrg.signatureDataUrl" :alt="`Tanda tangan ${activeOrg.chairpersonName}`" class="h-12 object-contain">
              <span v-else class="text-xs text-[#475569]">Belum ada tanda tangan</span>
            </div>
            <div class="flex flex-col gap-1.5">
              <button
                type="button"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#EFF6FF] text-[#1E3A5F] text-xs font-semibold hover:bg-blue-100"
                @click="applyPresetSignature"
              >
                <Sparkles class="w-3.5 h-3.5" aria-hidden="true" />
                <span>Pakai TTD Contoh</span>
              </button>
              <label class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-[#DCE4EE] bg-white text-xs font-semibold text-[#0F172A] cursor-pointer hover:bg-[#F8FAFC]">
                <Upload class="w-3.5 h-3.5" aria-hidden="true" />
                <span>Unggah Gambar</span>
                <input type="file" accept="image/*" class="sr-only" @change="(e) => onFileChange('signature', e)">
              </label>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- MPK-ONLY ORGANIZATION ACCOUNT MANAGEMENT INTERFACE -->
    <section v-if="role === 'mpk'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <div class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm p-6 lg:col-span-1 space-y-4">
        <h3 class="text-base font-bold text-[#0F172A] flex items-center gap-2">
          <UserPlus class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
          <span>Tambah Akun Ekskul (MPK)</span>
        </h3>
        <form @submit.prevent="onAddAccount" class="space-y-3">
          <input v-model="newOrg.name" type="text" placeholder="Nama ekskul (mis: PMR SMKN 4)…" required class="w-full h-10 rounded-xl border border-[#DCE4EE] px-3 text-sm">
          <input v-model="newOrg.accountEmail" type="email" placeholder="Email akun (mis: pmr@smkn4.demo)…" required class="w-full h-10 rounded-xl border border-[#DCE4EE] px-3 text-sm">
          <input v-model="newOrg.chairpersonName" type="text" placeholder="Nama ketua organisasi…" required class="w-full h-10 rounded-xl border border-[#DCE4EE] px-3 text-sm">
          <button type="submit" class="w-full h-10 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold">
            Tambah Organisasi Ekskul
          </button>
        </form>
      </div>

      <div class="bg-white rounded-2xl border border-[#DCE4EE] shadow-sm overflow-hidden lg:col-span-2">
        <div class="px-5 py-4 border-b border-[#DCE4EE] flex items-center justify-between gap-2">
          <h3 class="text-base font-bold text-[#0F172A] flex items-center gap-2">
            <Users class="w-4 h-4 text-[#1E3A5F]" aria-hidden="true" />
            <span>Daftar Akun Organisasi Ekskul</span>
          </h3>
          <span class="text-xs font-semibold text-[#475569] tabular-nums">
            {{ organizations.filter((o) => !o.isMpk).length }} Ekskul
          </span>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-[#DCE4EE]">
          <div v-for="org in organizations.filter((o) => !o.isMpk)" :key="'mob-' + org.id" class="p-4 space-y-2.5">
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="text-sm font-bold text-[#0F172A]">{{ org.name }}</p>
                <p class="text-xs text-[#475569] break-all">{{ org.accountEmail }}</p>
                <p class="text-xs text-[#475569]">Ketua: {{ org.chairpersonName || '-' }}</p>
              </div>
              <StatusBadge
                :label="org.accountActive ? 'Aktif' : 'Nonaktif'"
                :tone="org.accountActive ? 'emerald' : 'rose'"
                :icon="org.accountActive ? 'check' : 'x'"
              />
            </div>
            <div class="flex items-center justify-between gap-2">
              <StatusBadge
                :label="isOrgProfileComplete(org) ? 'Profil TTD Lengkap' : 'Profil Belum Lengkap'"
                :tone="isOrgProfileComplete(org) ? 'blue' : 'amber'"
                :icon="isOrgProfileComplete(org) ? 'check' : 'clock'"
              />
              <button
                type="button"
                class="px-3 py-1.5 rounded-lg border border-[#DCE4EE] text-xs font-semibold text-[#0F172A]"
                @click="emit('toggle-active', org.id)"
              >
                {{ org.accountActive ? 'Nonaktifkan' : 'Aktifkan' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Desktop table -->
        <div class="hidden md:block">
          <table class="w-full text-sm text-left">
            <thead class="bg-[#F8FAFC] border-b border-[#DCE4EE] text-xs text-[#475569]">
              <tr>
                <th class="px-5 py-3 font-semibold">Organisasi &amp; Ketua</th>
                <th class="px-5 py-3 font-semibold">Kelengkapan Profil</th>
                <th class="px-5 py-3 font-semibold text-right">Status Akun</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#DCE4EE]">
              <tr v-for="org in organizations.filter((o) => !o.isMpk)" :key="org.id">
                <td class="px-5 py-3.5">
                  <p class="font-bold text-[#0F172A]">{{ org.name }}</p>
                  <p class="text-xs text-[#475569]">{{ org.chairpersonName || '-' }} · {{ org.accountEmail }}</p>
                </td>
                <td class="px-5 py-3.5">
                  <StatusBadge
                    :label="isOrgProfileComplete(org) ? 'Profil TTD Lengkap' : 'Belum Lengkap'"
                    :tone="isOrgProfileComplete(org) ? 'emerald' : 'amber'"
                    :icon="isOrgProfileComplete(org) ? 'check' : 'clock'"
                  />
                </td>
                <td class="px-5 py-3.5 text-right">
                  <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border border-[#DCE4EE] hover:bg-[#F8FAFC] text-xs font-semibold text-[#0F172A]"
                    @click="emit('toggle-active', org.id)"
                  >
                    {{ org.accountActive ? 'Aktif (Nonaktifkan)' : 'Nonaktif (Aktifkan)' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>

