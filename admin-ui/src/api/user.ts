import { get, post, put, del } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

export interface AdminUser {
  id: number
  name: string
  email: string
  avatar?: string
  roles: string[]
  status: 'active' | 'inactive'
  last_login_at?: string
  created_at: string
}

export interface Merchant {
  id: number
  name: string
  email: string
  shop_name: string
  status: 'active' | 'inactive' | 'pending'
  balance: number
  created_at: string
}

export interface Customer {
  id: number
  name: string
  email: string
  phone?: string
  status: 'active' | 'inactive'
  order_count: number
  total_spent: number
  created_at: string
}

export interface UserListParams extends PaginationParams {
  keyword?: string
  status?: string
}

/** 获取管理员列表 */
export function getAdminList(params: UserListParams): Promise<ApiResponse<PaginatedData<AdminUser>>> {
  return get<PaginatedData<AdminUser>>('/users/admins', params as unknown as Record<string, unknown>)
}

/** 创建管理员 */
export function createAdmin(data: Partial<AdminUser> & { password: string }): Promise<ApiResponse<AdminUser>> {
  return post<AdminUser>('/users/admins', data as unknown as Record<string, unknown>)
}

/** 更新管理员 */
export function updateAdmin(id: number, data: Partial<AdminUser>): Promise<ApiResponse<AdminUser>> {
  return put<AdminUser>(`/users/admins/${id}`, data as unknown as Record<string, unknown>)
}

/** 删除管理员 */
export function deleteAdmin(id: number): Promise<ApiResponse<null>> {
  return del<null>(`/users/admins/${id}`)
}

/** 获取商户列表 */
export function getMerchantList(params: UserListParams): Promise<ApiResponse<PaginatedData<Merchant>>> {
  return get<PaginatedData<Merchant>>('/users/merchants', params as unknown as Record<string, unknown>)
}

/** 更新商户状态 */
export function updateMerchantStatus(id: number, status: string): Promise<ApiResponse<Merchant>> {
  return put<Merchant>(`/users/merchants/${id}/status`, { status })
}

/** 获取买家列表 */
export function getCustomerList(params: UserListParams): Promise<ApiResponse<PaginatedData<Customer>>> {
  return get<PaginatedData<Customer>>('/users/customers', params as unknown as Record<string, unknown>)
}

/** 获取买家详情 */
export function getCustomerById(id: number): Promise<ApiResponse<Customer>> {
  return get<Customer>(`/users/customers/${id}`)
}
