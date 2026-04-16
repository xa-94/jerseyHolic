import { get, post, put } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

export interface SystemSetting {
  key: string
  value: string
  type: 'string' | 'number' | 'boolean' | 'json'
  description?: string
}

export interface Role {
  id: number
  name: string
  display_name: string
  permissions: string[]
  created_at: string
}

export interface Permission {
  id: number
  name: string
  display_name: string
  module: string
}

export interface OperationLog {
  id: number
  user_id: number
  user_name: string
  action: string
  module: string
  resource_id?: number
  ip: string
  user_agent: string
  created_at: string
}

/** 获取系统配置 */
export function getSettings(): Promise<ApiResponse<Record<string, string>>> {
  return get<Record<string, string>>('/settings')
}

/** 更新系统配置 */
export function updateSettings(data: Record<string, string>): Promise<ApiResponse<null>> {
  return post<null>('/settings', data)
}

/** 获取角色列表 */
export function getRoleList(): Promise<ApiResponse<Role[]>> {
  return get<Role[]>('/settings/roles')
}

/** 创建角色 */
export function createRole(data: Omit<Role, 'id' | 'created_at'>): Promise<ApiResponse<Role>> {
  return post<Role>('/settings/roles', data as unknown as Record<string, unknown>)
}

/** 更新角色权限 */
export function updateRolePermissions(roleId: number, permissions: string[]): Promise<ApiResponse<Role>> {
  return put<Role>(`/settings/roles/${roleId}/permissions`, { permissions })
}

/** 获取所有权限列表 */
export function getPermissionList(): Promise<ApiResponse<Permission[]>> {
  return get<Permission[]>('/settings/permissions')
}

/** 获取操作日志 */
export function getOperationLogs(params: Partial<PaginationParams> & {
  keyword?: string
  module?: string
  date_from?: string
  date_to?: string
}): Promise<ApiResponse<PaginatedData<OperationLog>>> {
  return get<PaginatedData<OperationLog>>('/settings/logs', params as unknown as Record<string, unknown>)
}
