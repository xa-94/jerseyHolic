import type { Config } from 'tailwindcss'

export default {
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './composables/**/*.{js,ts}',
    './plugins/**/*.{js,ts}',
    './app.vue',
  ],
  theme: {
    extend: {
      colors: {
        primary: { DEFAULT: '#1a1a2e', light: '#16213e', dark: '#0f3460' },
        accent: { DEFAULT: '#e94560', light: '#ff6b6b' },
      },
    },
  },
  plugins: [
    require('tailwindcss-rtl'),
  ],
} satisfies Config
