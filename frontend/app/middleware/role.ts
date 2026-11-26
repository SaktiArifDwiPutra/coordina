import { useAuthStore } from '~/stores/auth'

const ALLOWED_MVP_ROLES = ['admin_mpk', 'owner_eskul']

export default defineNuxtRouteMiddleware((to) => {
  if (import.meta.server) return

  const auth = useAuthStore()
  const role = auth.user?.role

  if (!role || !ALLOWED_MVP_ROLES.includes(role)) {
    return navigateTo('/login')
  }

  const requiredRoles = to.meta?.roles as string[] | undefined
  if (Array.isArray(requiredRoles) && requiredRoles.length > 0 && !requiredRoles.includes(role)) {
    return navigateTo('/dashboard')
  }
})

