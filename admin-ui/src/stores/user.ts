import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { UserInfo, LoginParams, LoginResult } from '@/types/api'
import { post, get } from '@/api/request'
import { setToken, removeToken, getToken } from '@/utils/auth'

export const useUserStore = defineStore('user', () => {
  const token = ref<string | null>(getToken())
  const userInfo = ref<UserInfo | null>(null)

  /** 登录 */
  async function login(params: LoginParams): Promise<void> {
    const res = await post<LoginResult>('/auth/login', params as unknown as Record<string, unknown>)
    token.value = res.data.token
    userInfo.value = res.data.user
    setToken(res.data.token)
  }

  /** 获取用户信息 */
  async function fetchUserInfo(): Promise<UserInfo> {
    const res = await get<UserInfo>('/auth/me')
    userInfo.value = res.data
    return res.data
  }

  /** 登出 */
  async function logout(): Promise<void> {
    try {
      await post('/auth/logout')
    } finally {
      token.value = null
      userInfo.value = null
      removeToken()
    }
  }

  /** 重置状态 */
  function resetState(): void {
    token.value = null
    userInfo.value = null
    removeToken()
  }

  return {
    token,
    userInfo,
    login,
    fetchUserInfo,
    logout,
    resetState,
  }
})
