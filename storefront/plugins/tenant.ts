import { useTenantStore } from '~/stores/tenant'

/**
 * tenant plugin — Nuxt 启动时初始化站点上下文
 *
 * 执行时机：
 *   - SSR 阶段（服务端每次请求执行一次）
 *   - 客户端首次导航（hydration 后执行一次）
 *
 * 职责：
 *   1. 调用 /api/v1/store/config 获取站点配置
 *   2. 根据站点默认语言设置 i18n locale
 *   3. 注入 HTML dir 属性（RTL 支持）
 *   4. 注入 CSS 主题变量
 */
export default defineNuxtPlugin(async (nuxtApp) => {
  const tenantStore = useTenantStore(nuxtApp.$pinia as any)

  // 仅在未加载时请求（避免 SSR hydration 重复请求）
  if (!tenantStore.isLoaded) {
    await tenantStore.fetchStoreConfig()
  }

  // 根据站点配置设置 i18n 默认语言
  const i18n = nuxtApp.$i18n as any
  if (i18n && tenantStore.defaultLanguage) {
    const currentLocale = i18n.locale?.value ?? i18n.locale
    if (currentLocale !== tenantStore.defaultLanguage) {
      try {
        await i18n.setLocale(tenantStore.defaultLanguage)
      }
      catch {
        // i18n 可能尚未初始化，忽略错误
      }
    }
  }

  // 注入 HTML dir 属性（RTL/LTR）
  if (tenantStore.isRtl) {
    useHead({ htmlAttrs: { dir: 'rtl' } })
  }
  else {
    useHead({ htmlAttrs: { dir: 'ltr' } })
  }

  // 注入主题 CSS 变量（仅客户端）
  if (import.meta.client && tenantStore.storeConfig) {
    const { primary_color } = tenantStore.theme
    if (primary_color) {
      document.documentElement.style.setProperty('--primary-color', primary_color)
    }
  }
})
