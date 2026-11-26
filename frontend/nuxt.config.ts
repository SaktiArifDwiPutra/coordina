export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  devtools: { enabled: true },

  app: {
    head: {
      htmlAttrs: { lang: 'id' },
      title: 'Coordina — Sistem Peminjaman Fasilitas SMKN 4 Padalarang',
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Lexend:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap',
        },
      ],
    },
  },

  css: ['./app/assets/css/tailwind.css'],

  modules: ['shadcn-nuxt', '@pinia/nuxt', '@nuxtjs/tailwindcss'],

  tailwindcss: {
    cssPath: '~/assets/css/tailwind.css',
  },

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