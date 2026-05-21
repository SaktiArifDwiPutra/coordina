<script setup>
import { Card } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { Button } from '~/components/ui/button'
import { useAuthStore } from '~/stores/auth'

const config = useRuntimeConfig()

const { data, error, pending } = await useFetch(
  `${config.public.apiUrl}/api/test`
)

console.log(data.value)
console.log(error.value)

const auth = useAuthStore()
const form = ref({
  email: '',
  password: ''
})
const loading = ref(false)
const errorMsg = ref('')

async function handleLogin() {
  loading.value = true
  errorMsg.value = ''
  try {
    await auth.login(form.value)
    // Jika berhasil, arahkan ke dashboard
    navigateTo('/dashboard')
  } catch (err) {
  console.log(err)

  errorMsg.value =
    err?.data?.message ||
    err?.message ||
    'Login gagal'
}
}
</script>

<template>
  <div>
    <p v-if="pending">Loading...</p>

    <pre v-else-if="data">
      {{ data }}
    </pre>

    <pre v-else-if="error">
      {{ error }}
    </pre>
  </div>

  <div class="min-h-screen flex items-center justify-center bg-zinc-50 px-4">
    <Card class="w-full max-w-md p-6 shadow-lg border-zinc-200">
      <div class="space-y-2 text-center mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-zinc-900">Coordina</h1>
        <p class="text-zinc-500">Sistem Peminjaman Fasilitas SMKN 4</p>
      </div>

      <form @submit.prevent="handleLogin" class="space-y-4">
        <div class="space-y-2">
          <Label for="email">Email</Label>
          <Input 
            id="email" 
            v-model="form.email" 
            type="email" 
            placeholder="admin@mpk.com" 
            required 
          />
        </div>
        
        <div class="space-y-2">
          <Label for="password">Password</Label>
          <Input 
            id="password" 
            v-model="form.password" 
            type="password" 
            required 
          />
        </div>

        <div v-if="errorMsg" class="text-sm text-red-500 font-medium">
          {{ errorMsg }}
        </div>

        <Button :disabled="loading" type="submit" class="w-full bg-zinc-900 text-white">
          {{ loading ? 'Sedang masuk...' : 'Masuk' }}
        </Button>
      </form>
    </Card>
  </div>
</template>