import { toRefs } from 'vue'
import { useTenantStore } from '~/stores/tenant'
import type { StoreTheme } from '~/types/tenant'

export function useStore() {
  const tenantStore = useTenantStore()

  /**
   * 确保站点配置已加载（如未加载则自动请求）
   */
  async function ensureLoaded(): Promise<void> {
    if (!tenantStore.isLoaded && !tenantStore.isLoading) {
      await tenantStore.fetchStoreConfig()
    }
  }

  /**
   * 检查该站点是否支持指定语言代码
   */
  function isLanguageSupported(code: string): boolean {
    if (!tenantStore.languages.length) return true // 未加载时全部放行
    return tenantStore.languages.includes(code)
  }

  /**
   * 获取当前站点主题配置
   */
  function getTheme(): StoreTheme {
    return tenantStore.theme
  }

  /**
   * 获取站点 Logo URL（优先使用 API 返回的 logo_url）
   */
  function getLogoUrl(): string {
    const logoUrl = tenantStore.theme.logo_url
    if (!logoUrl) return '/images/logo.png'

    // 如果是相对路径，拼接 API base 域名
    if (logoUrl.startsWith('http')) return logoUrl

    const config = useRuntimeConfig()
    const apiBase = (config.public.apiBase as string) || ''
    // apiBase 例如 http://localhost:8000/api/v1 → 取 origin
    try {
      const origin = new URL(apiBase).origin
      return `${origin}${logoUrl}`
    }
    catch {
      return logoUrl
    }
  }

  return {
    ensureLoaded,
    isLanguageSupported,
    getTheme,
    getLogoUrl,
    ...toRefs(tenantStore.$state),
    // 透传 getters
    languages: computed(() => tenantStore.languages),
    defaultLanguage: computed(() => tenantStore.defaultLanguage),
    currencies: computed(() => tenantStore.currencies),
    defaultCurrency: computed(() => tenantStore.defaultCurrency),
    theme: computed(() => tenantStore.theme),
    isRtl: computed(() => tenantStore.isRtl),
    storeId: computed(() => tenantStore.storeId),
    storeName: computed(() => tenantStore.storeName),
  }
}
