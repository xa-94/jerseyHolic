import { get, post, put, del } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

export interface Category {
  id: number
  name: string
  slug: string
  parent_id: number | null
  parent_name?: string
  image?: string
  sort_order: number
  status: 'active' | 'inactive'
  products_count?: number
  children?: Category[]
  descriptions?: Array<{ locale: string; name: string }>
  created_at: string
}

export interface CategoryForm {
  name: string
  slug?: string
  parent_id?: number | null
  sort_order?: number
  status?: 'active' | 'inactive'
}

/** 获取分类列表（支持分页） */
export function getCategoryList(params?: Partial<PaginationParams> & { keyword?: string }): Promise<ApiResponse<PaginatedData<Category>>> {
  return get<PaginatedData<Category>>('/categories', params as unknown as Record<string, unknown>)
}

/** 获取分类树（无分页，用于下拉选择） */
export function getCategoryTree(): Promise<ApiResponse<Category[]>> {
  return get<Category[]>('/categories/tree')
}

/** 获取分类详情 */
export function getCategoryById(id: number): Promise<ApiResponse<Category>> {
  return get<Category>(`/categories/${id}`)
}

/** 创建分类 */
export function createCategory(data: CategoryForm): Promise<ApiResponse<Category>> {
  return post<Category>('/categories', data as unknown as Record<string, unknown>)
}

/** 更新分类 */
export function updateCategory(id: number, data: Partial<CategoryForm>): Promise<ApiResponse<Category>> {
  return put<Category>(`/categories/${id}`, data as unknown as Record<string, unknown>)
}

/** 删除分类 */
export function deleteCategory(id: number): Promise<ApiResponse<null>> {
  return del<null>(`/categories/${id}`)
}

/** 分类排序 */
export function reorderCategories(items: Array<{ id: number; sort_order: number; parent_id?: number | null }>): Promise<ApiResponse<null>> {
  return post<null>('/categories/reorder', { items } as unknown as Record<string, unknown>)
}
