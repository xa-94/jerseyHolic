import { post, get } from './request'
import type { ApiResponse } from '@/types/api'
import type { MerchantUserInfo, MerchantLoginResult } from '@/types/merchant'

/** 登录 */
export function loginApi(data: {
  email: string
  password: string
  remember_me?: boolean
}): Promise<ApiResponse<MerchantLoginResult>> {
  return post<MerchantLoginResult>('/auth/login', data as unknown as Record<string, unknown>)
}

/** 登出 */
export function logoutApi(): Promise<ApiResponse<null>> {
  return post<null>('/auth/logout')
}

/** 获取当前登录用户信息 */
export function getUserInfoApi(): Promise<ApiResponse<MerchantUserInfo>> {
  return get<MerchantUserInfo>('/auth/me')
}
