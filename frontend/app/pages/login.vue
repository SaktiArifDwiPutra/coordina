<script setup>
import { ref } from 'vue'
import { Eye, EyeOff, FileCheck2, LogIn, ShieldCheck } from 'lucide-vue-next'
import { Card } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Button } from '~/components/ui/button'
import { useAuthStore } from '~/stores/auth'

definePageMeta({
  middleware: ['guest'],
})

const auth = useAuthStore()
const form = ref({
  email: '',
  password: '',
})
const showPassword = ref(false)
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.login(form.value)
    navigateTo('/dashboard')
  } catch (err) {
    errorMsg.value =
      err?.data?.message ||
      err?.response?._data?.message ||
      err?.message ||
      'Login gagal. Periksa kembali email dan password Anda.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-background flex flex-col justify-center py-10 px-4">
    <div class="w-full max-w-md mx-auto space-y-5">
      <div class="flex flex-col items-center text-center space-y-2.5">
        <div class="h-12 w-12 rounded-2xl bg-[#1E3A5F] text-white flex items-center justify-center shadow-sm">
          <FileCheck2 class="w-6 h-6" aria-hidden="true" />
        </div>
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-slate-900">Coordina</h1>
          <p class="text-sm text-slate-600 mt-0.5">Sistem Peminjaman Fasilitas &amp; Surat Izin SMKN 4 Padalarang</p>
        </div>
      </div>

      <Card class="p-6 sm:p-8 shadow-md border-border bg-white rounded-2xl space-y-6">
        <div class="space-y-1 border-b border-border pb-4">
          <h2 class="text-lg font-bold text-slate-900">Masuk ke Ruang Kerja</h2>
          <p class="text-xs text-slate-600 leading-relaxed">
            Gunakan akun resmi <strong>MPK Komisi 2</strong> atau akun organisasi <strong>Ekstrakurikuler</strong> Anda.
          </p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div class="space-y-1.5">
            <Label for="email">Email Organisasi</Label>
            <Input
              id="email"
              v-model="form.email"
              name="email"
              type="email"
              autocomplete="email"
              spellcheck="false"
              autocapitalize="none"
              enterkeyhint="next"
              placeholder="Contoh: futsal@coordina.com…"
              class="h-11 rounded-xl"
              required
            />
          </div>

          <div class="space-y-1.5">
            <Label for="password">Password</Label>
            <div class="relative">
              <Input
                id="password"
                v-model="form.password"
                name="password"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="current-password"
                enterkeyhint="done"
                placeholder="Masukkan password Anda…"
                class="h-11 rounded-xl pr-11"
                required
              />
              <button
                type="button"
                :aria-pressed="showPassword"
                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                @click="showPassword = !showPassword"
              >
                <component :is="showPassword ? EyeOff : Eye" class="w-4 h-4" aria-hidden="true" />
              </button>
            </div>
          </div>

          <div
            v-if="errorMsg"
            role="alert"
            class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-sm text-rose-800 font-medium"
          >
            {{ errorMsg }}
          </div>

          <Button
            :disabled="loading"
            type="submit"
            class="w-full h-11 rounded-xl bg-[#1E3A5F] hover:bg-[#152943] text-white text-sm font-semibold"
          >
            <LogIn class="w-4 h-4" aria-hidden="true" />
            <span>{{ loading ? 'Sedang masuk…' : 'Masuk ke Coordina' }}</span>
          </Button>
        </form>

        <div class="pt-3 border-t border-border flex items-start gap-2 text-xs text-slate-500">
          <ShieldCheck class="w-4 h-4 text-[#1E3A5F] shrink-0 mt-0.5" aria-hidden="true" />
          <span>Lupa password akun ekskul? Hubungi pengurus MPK Komisi 2 untuk melakukan reset password sementara.</span>
        </div>
      </Card>
    </div>
  </div>
</template>
