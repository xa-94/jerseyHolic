import { useTenantStore } from '~/stores/tenant'

export function useApi() {
  const config = useRuntimeConfig()
  const { locale } = useI18n()
  const tenantStore = useTenantStore()

  async function apiFetch<T = any>(url: string, options: any = {}): Promise<T> {
    const headers: Record<string, string> = {
      'Accept-Language': locale.value,
      'Accept': 'application/json',
      ...(options.headers || {}),
    }

    // 注入站点 ID，让后端识别多租户上下文
    if (tenantStore.storeId) {
      headers['X-Store-Id'] = String(tenantStore.storeId)
    }

    // Add auth token if available (client-side)
    if (import.meta.client) {
      const token = localStorage.getItem('jh_token')
      if (token) {
        headers['Authorization'] = `Bearer ${token}`
      }
    }

    const response = await $fetch<{ code: number; message: string; data: T }>(url, {
      baseURL: config.public.apiBase as string,
      headers,
      ...options,
    })

    if (response.code !== 0) {
      throw new Error(response.message || 'Request failed')
    }

    return response.data
  }

  return { apiFetch }
}
