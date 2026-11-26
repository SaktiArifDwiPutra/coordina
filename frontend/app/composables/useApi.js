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
      const status = error?.status || error?.response?.status
      const code = error?.data?.code || error?.response?._data?.code

      if (status === 401 || (status === 403 && code === 'ACCOUNT_INACTIVE')) {
        auth.user = null
        auth.token = null
        if (process.client) {
          localStorage.removeItem('auth_token')
        }
        navigateTo('/login')
      } else if (status === 403 && code === 'MUST_CHANGE_PASSWORD' && auth.user) {
        auth.user = { ...auth.user, must_change_password: true }
      }
      throw error
    }
  }

  return { apiFetch, extractErrorMessage }
}

