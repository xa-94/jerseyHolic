import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { RouteRecordRaw } from 'vue-router'

export const usePermissionStore = defineStore('permission', () => {
  /** 用户权限列表 */
  const permissions = ref<string[]>([])

  /** 动态路由（后续由后端返回菜单生成） */
  const dynamicRoutes = ref<RouteRecordRaw[]>([])

  /** 菜单是否已生成 */
  const isRoutesGenerated = ref(false)

  /** 设置权限列表 */
  function setPermissions(perms: string[]): void {
    permissions.value = perms
  }

  /** 检查是否有某个权限 */
  function hasPermission(permission: string): boolean {
    // 超级管理员拥有所有权限
    if (permissions.value.includes('*')) return true
    return permissions.value.includes(permission)
  }

  /** 生成动态路由（后续对接后端菜单） */
  async function generateRoutes(): Promise<RouteRecordRaw[]> {
    // TODO: 从后端获取菜单配置，生成动态路由
    isRoutesGenerated.value = true
    return dynamicRoutes.value
  }

  /** 重置 */
  function resetPermissions(): void {
    permissions.value = []
    dynamicRoutes.value = []
    isRoutesGenerated.value = false
  }

  return {
    permissions,
    dynamicRoutes,
    isRoutesGenerated,
    setPermissions,
    hasPermission,
    generateRoutes,
    resetPermissions,
  }
})
