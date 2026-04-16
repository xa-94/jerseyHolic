import { get, post } from './request'
import type { ApiResponse, LoginParams, LoginResult, UserInfo } from '@/types/api'

/** 登录 */
export function login(data: LoginParams): Promise<ApiResponse<LoginResult>> {
  return post<LoginResult>('/auth/login', data as unknown as Record<string, unknown>)
}

/** 登出 */
export function logout(): Promise<ApiResponse<null>> {
  return post<null>('/auth/logout')
}

/** 获取当前用户信息 */
export function getMe(): Promise<ApiResponse<UserInfo>> {
  return get<UserInfo>('/auth/me')
}

/** 修改密码 */
export function changePassword(data: {
  current_password: string
  new_password: string
  new_password_confirmation: string
}): Promise<ApiResponse<null>> {
  return post<null>('/auth/change-password', data as unknown as Record<string, unknown>)
}
