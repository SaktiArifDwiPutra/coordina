export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',

  ssr: false,

  devtools: { enabled: false },

  app: {
    head: {
      htmlAttrs: { lang: 'id' },
      title: 'Coordina Demo — Simulasi Sistem Peminjaman Fasilitas SMKN 4',
      meta: [
        {
          name: 'description',
          content: 'Demo interaktif frontend-only sistem koordinasi jadwal dan surat izin peminjaman fasilitas ekstrakurikuler Coordina.',
        },
      ],
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

  css: ['~/assets/css/tailwind.css'],

  modules: ['@pinia/nuxt', '@nuxtjs/tailwindcss'],

  tailwindcss: {
    cssPath: '~/assets/css/tailwind.css',
  },
})

