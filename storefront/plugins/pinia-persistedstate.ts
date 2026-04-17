import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'

/**
 * pinia-persistedstate plugin
 * 为 Pinia store 提供本地持久化能力（cart、tenant 等 store 使用 persist 选项）
 * 注意：SSR 环境下 storage 为 undefined 时自动跳过持久化
 */
export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.$pinia.use(piniaPluginPersistedstate)
})
