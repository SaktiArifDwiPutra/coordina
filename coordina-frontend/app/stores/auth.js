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

      try {
        const userData = await $fetch('http://127.0.0.1:8000/api/user', {
          method: 'GET',
          headers: {
            'Accept': 'application/json',
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
      try {
        const data = await $fetch('http://localhost:8000/api/login', {
          method: 'POST',
          // 👇 INI BAGIAN YANG DITAMBAHKAN 👇
          headers: {
            'Accept': 'application/json'
          },
          // 👆 👆 👆 👆 👆 👆 👆 👆 👆 👆 👆 👆
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