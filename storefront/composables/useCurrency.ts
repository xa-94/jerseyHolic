import { ref, computed } from 'vue'
import { useTenantStore } from '~/stores/tenant'

/**
 * 货币代码 → 货币符号映射表
 */
const CURRENCY_SYMBOLS: Record<string, string> = {
  USD: '$',
  EUR: '€',
  GBP: '£',
  JPY: '¥',
  KRW: '₩',
  BRL: 'R$',
  AUD: 'A$',
  CAD: 'C$',
  SEK: 'kr',
  DKK: 'kr',
  NOK: 'kr',
  PLN: 'zł',
  TRY: '₺',
  SAR: '﷼',
  AED: 'د.إ',
  CHF: 'Fr',
  CNY: '¥',
  HKD: 'HK$',
  SGD: 'S$',
  MXN: 'MX$',
}

/**
 * 货币小数位配置（默认 2 位，特殊货币 0 位）
 */
const CURRENCY_DECIMALS: Record<string, number> = {
  JPY: 0,
  KRW: 0,
  SAR: 0,
}

/**
 * useCurrency — 货币格式化与切换 composable
 *
 * - 当前选中货币默认取站点主货币（defaultCurrency）
 * - formatPrice 使用 Intl.NumberFormat，SSR/Node.js 兼容
 * - setCurrency 切换后写入 cookie 以便刷新后保持
 */
export function useCurrency() {
  const tenantStore = useTenantStore()

  // 初始货币：优先读 cookie，再取站点默认，最后兜底 USD
  const cookieCurrency = useCookie<string>('jh_currency', { maxAge: 60 * 60 * 24 * 365 })

  const currentCurrency = ref<string>(
    cookieCurrency.value || tenantStore.defaultCurrency || 'USD',
  )

  // 站点支持的货币列表
  const availableCurrencies = computed<string[]>(() => tenantStore.currencies)

  /**
   * 格式化价格
   * @param amount  原始金额（number）
   * @param currency 货币代码（可选，默认使用 currentCurrency）
   * @returns 格式化后的字符串，如 "$ 29.99"、"€ 24.90"
   */
  function formatPrice(amount: number, currency?: string): string {
    const code = currency || currentCurrency.value || 'USD'
    const decimals = CURRENCY_DECIMALS[code] ?? 2

    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      }).format(amount)
    }
    catch {
      // Intl 不支持该货币时，手动拼接符号
      const symbol = getCurrencySymbol(code)
      return `${symbol}${amount.toFixed(decimals)}`
    }
  }

  /**
   * 获取货币符号
   * @param currency 货币代码（可选，默认使用 currentCurrency）
   */
  function getCurrencySymbol(currency?: string): string {
    const code = currency || currentCurrency.value || 'USD'
    return CURRENCY_SYMBOLS[code] || code
  }

  /**
   * 切换当前货币
   * @param code 目标货币代码（如 'EUR'）
   */
  function setCurrency(code: string): void {
    currentCurrency.value = code
    cookieCurrency.value = code
  }

  /**
   * 获取货币显示标签（符号 + 代码，如 "$ USD"）
   */
  function getCurrencyLabel(currency?: string): string {
    const code = currency || currentCurrency.value || 'USD'
    return `${getCurrencySymbol(code)} ${code}`
  }

  return {
    currentCurrency,
    availableCurrencies,
    formatPrice,
    getCurrencySymbol,
    getCurrencyLabel,
    setCurrency,
  }
}
