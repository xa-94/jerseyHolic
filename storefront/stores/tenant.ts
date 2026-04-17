import { defineStore } from 'pinia'
import type { StoreConfig, StoreTheme } from '~/types/tenant'

export const useTenantStore = defineStore('tenant', {
  state: () => ({
    storeConfig: null as StoreConfig | null,
    isLoaded: false,
    isLoading: false,
  }),

  getters: {
    languages: (state): string[] => state.storeConfig?.languages ?? [],
    defaultLanguage: (state): string => state.storeConfig?.default_language ?? 'en',
    currencies: (state): string[] => state.storeConfig?.currencies ?? [],
    defaultCurrency: (state): string => state.storeConfig?.default_currency ?? 'USD',
    theme: (state): StoreTheme => state.storeConfig?.theme ?? { primary_color: '#1a73e8', logo_url: '' },
    isRtl: (state): boolean => state.storeConfig?.rtl ?? false,
    storeId: (state): number | null => state.storeConfig?.id ?? null,
    storeName: (state): string => state.storeConfig?.name ?? 'JerseyHolic',
    storeStatus: (state): string => state.storeConfig?.status ?? 'active',
  },

  actions: {
    async fetchStoreConfig() {
      if (this.isLoading) return
      this.isLoading = true

      try {
        const config = useRuntimeConfig()
        const response = await $fetch<{ code: number; message: string; data: StoreConfig }>(
          '/store/config',
          {
            baseURL: config.public.apiBase as string,
            headers: { Accept: 'application/json' },
          },
        )

        if (response.code === 0 && response.data) {
          this.storeConfig = response.data
          this.isLoaded = true
        }
      }
      catch (error) {
        // 加载失败时使用默认配置，避免阻塞页面渲染
        console.warn('[TenantStore] Failed to fetch store config, using defaults.', error)
        this.isLoaded = true // 标记为已尝试加载，避免无限重试
      }
      finally {
        this.isLoading = false
      }
    },

    clearConfig() {
      this.storeConfig = null
      this.isLoaded = false
      this.isLoading = false
    },
  },

  persist: {
    storage: import.meta.client ? sessionStorage : undefined,
    pick: ['storeConfig', 'isLoaded'],
  },
})
