import { get, post, put, del } from './request'
import type { ApiResponse, PaginatedData, PaginationParams } from '@/types/api'

/** 子账号信息 */
export interface MerchantUser {
  id: number
  name: string
  email: string
  role: string
  role_id?: number
  status: 'active' | 'inactive'
  store_ids?: number[]
  created_at: string
  updated_at?: string
}

/** 角色信息 */
export interface Role {
  id: number
  name: string
  display_name: string
}

/** 创建/更新用户请求体 */
export interface UserFormData {
  name: string
  email: string
  password?: string
  role_id: number
  store_ids?: number[]
  status?: 'active' | 'inactive'
}

/** 用户列表查询参数 */
export interface UserListParams extends Partial<PaginationParams> {
  keyword?: string
  role?: string
  status?: string
}

/** 获取子账号列表 */
export function getUserList(params?: UserListParams): Promise<ApiResponse<PaginatedData<MerchantUser>>> {
  return get<PaginatedData<MerchantUser>>('/users', params as Record<string, unknown>)
}

/** 获取用户详情 */
export function getUserDetail(id: number): Promise<ApiResponse<MerchantUser>> {
  return get<MerchantUser>(`/users/${id}`)
}

/** 创建子账号 */
export function createUser(data: UserFormData): Promise<ApiResponse<MerchantUser>> {
  return post<MerchantUser>('/users', data as Record<string, unknown>)
}

/** 更新子账号 */
export function updateUser(id: number, data: Partial<UserFormData>): Promise<ApiResponse<MerchantUser>> {
  return put<MerchantUser>(`/users/${id}`, data as Record<string, unknown>)
}

/** 删除子账号 */
export function deleteUser(id: number): Promise<ApiResponse<null>> {
  return del<null>(`/users/${id}`)
}

/** 获取可用角色列表 */
export function getRoleList(): Promise<ApiResponse<{ data: Role[] }>> {
  return get<{ data: Role[] }>('/roles')
}
