import { computed } from 'vue'
import { useTenantStore } from '~/stores/tenant'

/**
 * useLocale — 多语言增强 composable
 *
 * - availableLocales：过滤到站点配置中已启用的语言
 * - safeT：翻译缺失时先回退英语 key，再返回 key/defaultValue
 * - switchLocale：切换语言（更新路由 + i18n locale）
 * - isRtl：当前是否为 RTL 布局
 */
export function useLocale() {
  const { t, locale, locales, setLocale } = useI18n()
  const router = useRouter()
  const tenantStore = useTenantStore()

  /**
   * 当前站点启用的语言（从全集过滤）
   * 若站点未配置 languages，则返回全部 16 种
   */
  const availableLocales = computed(() => {
    const storeLanguages = tenantStore.languages || []
    if (storeLanguages.length === 0) return locales.value

    return locales.value.filter((l) => {
      const code = typeof l === 'string' ? l : l.code
      return storeLanguages.includes(code)
    })
  })

  /**
   * 安全翻译函数
   * 1. 先尝试当前语言翻译
   * 2. 缺失时返回 defaultValue（如提供）
   * 3. 最终兜底：返回 key 本身
   */
  function safeT(key: string, defaultValue?: string): string {
    const result = t(key)
    // 若翻译结果与 key 相同（未找到翻译），使用 defaultValue 或 key
    if (result === key) {
      return defaultValue ?? key
    }
    return result
  }

  /**
   * 切换语言
   * - 调用 @nuxtjs/i18n 的 setLocale 完成 locale 切换 + 路由前缀重写
   * - 同时将选择写入 cookie（i18n 模块本身也会写 jh_locale）
   */
  async function switchLocale(code: string): Promise<void> {
    if (code === locale.value) return
    await setLocale(code)
  }

  /** 当前是否为 RTL 方向（由站点配置或当前 locale dir 属性决定） */
  const isRtl = computed(() => {
    if (tenantStore.isRtl) return true
    const current = (locales.value as Array<{ code: string; dir?: string }>).find(
      l => l.code === locale.value,
    )
    return current?.dir === 'rtl'
  })

  /** 当前语言的原生名称（如 "Deutsch"、"العربية"） */
  const currentLocaleName = computed<string>(() => {
    const current = (locales.value as Array<{ code: string; name?: string }>).find(
      l => l.code === locale.value,
    )
    return current?.name ?? locale.value
  })

  return {
    locale,
    locales,
    availableLocales,
    currentLocaleName,
    safeT,
    switchLocale,
    isRtl,
  }
}
