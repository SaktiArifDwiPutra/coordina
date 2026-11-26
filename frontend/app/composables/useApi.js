import { useAuthStore } from '~/stores/auth'

export function extractErrorMessage(error, fallback = 'Terjadi kesalahan.') {
  return error?.data?.message || error?.response?._data?.message || fallback
}

export function useApi() {
  const config = useRuntimeConfig()
  const auth = useAuthStore()

  async function apiFetch(path, options = {}) {
    const token = auth.token || (process.client ? localStorage.getItem('auth_token') : null)
    const headers = {
      Accept: 'application/json',
      ...(options.headers || {}),
    }

    if (token) {
      headers.Authorization = `Bearer ${token}`
    }

    try {
      return await $fetch(`${config.public.apiUrl}${path}`, {
        ...options,
        headers,
      })
    } catch (error) {
      if (error?.status === 401 || error?.response?.status === 401) {
        auth.user = null
        auth.token = null
        if (process.client) {
          localStorage.removeItem('auth_token')
        }
        navigateTo('/login')
      }
      throw error
    }
  }

  return { apiFetch, extractErrorMessage }
}

