import { useTenantStore } from '~/stores/tenant'

/**
 * tenant middleware — 全局路由守卫
 *
 * 职责：
 *   1. 确保站点配置已加载（兜底，通常 plugin 已提前完成）
 *   2. 站点非 active 状态时跳转到维护页面
 */
export default defineNuxtRouteMiddleware(async (to) => {
  // 维护页面本身不检查，避免无限跳转
  if (to.path === '/maintenance') return

  const tenantStore = useTenantStore()

  // 兜底加载（正常情况下 plugin 已完成，这里防止 plugin 失败）
  if (!tenantStore.isLoaded && !tenantStore.isLoading) {
    await tenantStore.fetchStoreConfig()
  }

  // 站点状态非 active 时跳转维护页
  if (tenantStore.storeConfig && tenantStore.storeStatus !== 'active') {
    return navigateTo('/maintenance')
  }
})
