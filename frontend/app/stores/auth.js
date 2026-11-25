import { defineStore } from 'pinia'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
  }),
  actions: {
    // Fungsi baru untuk memulihkan ingatan Nuxt
    async fetchUser() {
      if (!process.client) return
      
      const savedToken = localStorage.getItem('auth_token')
      if (!savedToken) return

      const config = useRuntimeConfig()

      try {
        const userData = await $fetch(`${config.public.apiUrl}/api/user`, {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
            // TYPO DIPERBAIKI: sebelumnya Authorizatioan
            'Authorization': `Bearer ${savedToken}`
          }
        })
        
        // Pulihkan state Pinia
        this.user = userData
        this.token = savedToken
      } catch (error) {
        // Kalau tokennya ternyata sudah kedaluwarsa, paksa keluar
        this.logout()
      }
    },
    async login(loginData) {
      const config = useRuntimeConfig()
      
      try {
        const data = await $fetch(`${config.public.apiUrl}/api/login`, {
          method: 'POST',
          headers: {
            'Accept': 'application/json'
          },
          body: loginData
        })
        
        this.token = data.access_token
        this.user = data.user
        
        // Simpan token ke localStorage (biar ga ilang pas refresh)
        if (process.client) {
          localStorage.setItem('auth_token', data.access_token)
        }
        
        return true
      } catch (error) {
        throw error
      }
    },
    logout() {
      this.user = null
      this.token = null
      if (process.client) {
        localStorage.removeItem('auth_token')
      }
      navigateTo('/login')
    }
  }
})