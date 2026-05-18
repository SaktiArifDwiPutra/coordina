export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  devtools: { enabled: true },

  css: ['./app/assets/css/tailwind.css'],

  modules: ['shadcn-nuxt', '@pinia/nuxt', '@nuxtjs/tailwindcss'],

  runtimeConfig: {
    public: {
      apiUrl: process.env.NUXT_PUBLIC_API_URL
    }
  },

  shadcn: {
    prefix: '',
    componentDir: './app/components/ui'
  }
})