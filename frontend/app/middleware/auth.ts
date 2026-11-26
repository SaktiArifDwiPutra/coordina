import { useAuthStore } from '~/stores/auth'

export default defineNuxtRouteMiddleware(async () => {
  if (import.meta.server) return

  const auth = useAuthStore()
  if (!auth.user) {
    await auth.fetchUser()
  }

  if (!auth.token || !auth.user) {
    return navigateTo('/login')
  }
})

