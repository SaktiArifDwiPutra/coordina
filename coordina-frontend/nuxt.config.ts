export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  devtools: { enabled: true },

  css: ['./app/assets/css/tailwind.css'],

  modules: ['shadcn-nuxt', '@pinia/nuxt', '@nuxtjs/tailwindcss'],

  shadcn: {
    prefix: '',
    componentDir: './app/components/ui'
  }
})