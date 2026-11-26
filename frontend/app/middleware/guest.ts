import { useAuthStore } from '~/stores/auth'

export default defineNuxtRouteMiddleware(async () => {
  if (import.meta.server) return

  const auth = useAuthStore()
  if (!auth.user) {
    await auth.fetchUser()
  }

  if (auth.token && auth.user && ['admin_mpk', 'owner_eskul'].includes(auth.user.role)) {
    return navigateTo('/dashboard')
  }
})

