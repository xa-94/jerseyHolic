export default defineNuxtConfig({
  devtools: { enabled: true },
  ssr: true,

  modules: [
    '@nuxtjs/tailwindcss',
    '@nuxtjs/i18n',
    '@pinia/nuxt',
  ],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api/v1',
    },
  },

  i18n: {
    locales: [
      { code: 'en', name: 'English', file: 'en.json', dir: 'ltr' },
      { code: 'de', name: 'Deutsch', file: 'de.json', dir: 'ltr' },
      { code: 'fr', name: 'Français', file: 'fr.json', dir: 'ltr' },
      { code: 'es', name: 'Español', file: 'es.json', dir: 'ltr' },
      { code: 'it', name: 'Italiano', file: 'it.json', dir: 'ltr' },
      { code: 'ja', name: '日本語', file: 'ja.json', dir: 'ltr' },
      { code: 'ko', name: '한국어', file: 'ko.json', dir: 'ltr' },
      { code: 'pt-BR', name: 'Português (BR)', file: 'pt-BR.json', dir: 'ltr' },
      { code: 'pt-PT', name: 'Português (PT)', file: 'pt-PT.json', dir: 'ltr' },
      { code: 'nl', name: 'Nederlands', file: 'nl.json', dir: 'ltr' },
      { code: 'pl', name: 'Polski', file: 'pl.json', dir: 'ltr' },
      { code: 'sv', name: 'Svenska', file: 'sv.json', dir: 'ltr' },
      { code: 'da', name: 'Dansk', file: 'da.json', dir: 'ltr' },
      { code: 'ar', name: 'العربية', file: 'ar.json', dir: 'rtl' },
      { code: 'tr', name: 'Türkçe', file: 'tr.json', dir: 'ltr' },
      { code: 'el', name: 'Ελληνικά', file: 'el.json', dir: 'ltr' },
    ],
    defaultLocale: 'en',
    lazy: true,
    langDir: 'locales',
    strategy: 'prefix_except_default',
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'jh_locale',
      redirectOn: 'root',
    },
  },

  app: {
    head: {
      title: 'JerseyHolic - Premium Sports Jerseys',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Shop premium sports jerseys at JerseyHolic. Free worldwide shipping.' },
      ],
      link: [
        { rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' },
      ],
    },
  },

  compatibilityDate: '2024-07-01',
})
