import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { MerchantUserInfo } from '@/types/merchant'
import { loginApi, logoutApi, getUserInfoApi } from '@/api/auth'
import { setToken, removeToken, getToken } from '@/utils/auth'
import router from '@/router'

export const useUserStore = defineStore('user', () => {
  // ─── State ─────────────────────────────────────────────────────────────────

  const token = ref<string>(getToken() || '')
  const userInfo = ref<MerchantUserInfo | null>(null)
  /** 当前选中的站点 ID */
  const currentStoreId = ref<number | null>(null)

  // ─── Getters ────────────────────────────────────────────────────────────────

  /** 是否已登录 */
  const isLoggedIn = computed(() => !!token.value)

  /** 用户名称 */
  const userName = computed(() => userInfo.value?.name ?? '')

  /** 商户 ID */
  const merchantId = computed(() => userInfo.value?.merchant_id ?? null)

  /** 商户下属站点列表 */
  const stores = computed(() => userInfo.value?.stores ?? [])

  /** 用户权限列表 */
  const permissions = computed(() => userInfo.value?.permissions ?? [])

  /** 用户角色 */
  const userRole = computed(() => userInfo.value?.role ?? '')

  // ─── Actions ────────────────────────────────────────────────────────────────

  /**
   * 登录 — 调用 loginApi → setToken → 更新 userInfo
   */
  async function login(data: {
    email: string
    password: string
    remember_me?: boolean
  }): Promise<void> {
    const res = await loginApi(data)
    token.value = res.data.token
    setToken(res.data.token)
    userInfo.value = res.data.user
    // 默认选中第一个站点
    if (res.data.user.stores.length > 0) {
      currentStoreId.value = res.data.user.stores[0].id
    }
  }

  /**
   * 获取当前用户信息（用于页面刷新后恢复状态）
   */
  async function fetchUserInfo(): Promise<MerchantUserInfo> {
    const res = await getUserInfoApi()
    userInfo.value = res.data
    if (!currentStoreId.value && res.data.stores.length > 0) {
      currentStoreId.value = res.data.stores[0].id
    }
    return res.data
  }

  /**
   * 登出 — 调用 logoutApi → removeToken → 清空状态 → 跳转登录页
   */
  async function logout(): Promise<void> {
    try {
      await logoutApi()
    } finally {
      token.value = ''
      userInfo.value = null
      currentStoreId.value = null
      removeToken()
      router.push('/login')
    }
  }

  /**
   * 设置当前操作的站点
   */
  function setCurrentStore(storeId: number): void {
    currentStoreId.value = storeId
  }

  /**
   * 重置全部状态（用于强制清除，如 Token 过期）
   */
  function resetState(): void {
    token.value = ''
    userInfo.value = null
    currentStoreId.value = null
    removeToken()
  }

  return {
    // state
    token,
    userInfo,
    currentStoreId,
    // getters
    isLoggedIn,
    userName,
    merchantId,
    stores,
    permissions,
    userRole,
    // actions
    login,
    fetchUserInfo,
    logout,
    setCurrentStore,
    resetState,
  }
})
