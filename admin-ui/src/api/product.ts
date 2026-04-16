import { get, post, put, del } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

export interface Product {
  id: number
  name: string
  safe_name: string
  sku: string
  sku_prefix?: string
  price: number
  special_price?: number
  effective_price?: number
  stock: number
  quantity?: number
  status: 'active' | 'inactive' | 'draft'
  category_id: number
  category_name?: string
  sort_order?: number
  images: string[]
  image?: string
  description: string
  mapping_type?: 'exact' | 'sku_prefix' | 'default'
  created_at: string
  updated_at: string
}

export interface ProductListParams extends PaginationParams {
  keyword?: string
  status?: string
  category_id?: number | ''
  min_price?: number | ''
  max_price?: number | ''
  sort_by?: string
  sort_order_dir?: string
}

export interface ProductForm {
  name: string
  safe_name?: string
  sku: string
  price: number
  stock: number
  status: 'active' | 'inactive' | 'draft'
  category_id: number
  images?: string[]
  description?: string
}

/** 获取商品列表 */
export function getProductList(params: ProductListParams): Promise<ApiResponse<PaginatedData<Product>>> {
  return get<PaginatedData<Product>>('/products', params as unknown as Record<string, unknown>)
}

/** 获取商品详情 */
export function getProductById(id: number): Promise<ApiResponse<Product>> {
  return get<Product>(`/products/${id}`)
}

/** 创建商品 */
export function createProduct(data: ProductForm): Promise<ApiResponse<Product>> {
  return post<Product>('/products', data as unknown as Record<string, unknown>)
}

/** 更新商品 */
export function updateProduct(id: number, data: Partial<ProductForm>): Promise<ApiResponse<Product>> {
  return put<Product>(`/products/${id}`, data as unknown as Record<string, unknown>)
}

/** 删除商品 */
export function deleteProduct(id: number): Promise<ApiResponse<null>> {
  return del<null>(`/products/${id}`)
}

/** 切换商品状态 */
export function toggleProductStatus(id: number): Promise<ApiResponse<Product>> {
  return post<Product>(`/products/${id}/toggle-status`, {})
}

/** 更新库存 */
export function updateProductStock(id: number, quantity: number): Promise<ApiResponse<Product>> {
  return post<Product>(`/products/${id}/stock`, { quantity })
}

/** 批量删除商品 */
export function batchDeleteProducts(ids: number[]): Promise<ApiResponse<null>> {
  return post<null>('/products/bulk-delete', { ids })
}

/** 批量更新商品状态 */
export function batchUpdateProductStatus(ids: number[], status: string): Promise<ApiResponse<null>> {
  return post<null>('/products/bulk-status', { ids, status })
}

/** 获取映射列表 */
export function getProductMappingList(params?: Record<string, unknown>): Promise<ApiResponse<PaginatedData<Product>>> {
  return get<PaginatedData<Product>>('/products', { ...params, with_mapping: 1 } as unknown as Record<string, unknown>)
}

/** 更新商品映射 */
export function updateProductMapping(id: number, data: { safe_name: string; mapping_type: string }): Promise<ApiResponse<Product>> {
  return put<Product>(`/products/${id}`, data as unknown as Record<string, unknown>)
}
