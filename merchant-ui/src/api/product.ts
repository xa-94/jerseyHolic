/**
 * 商品管理 & 同步管理 API
 */
import { get, post, put, del } from './request'
import type { ApiResponse, PaginatedData } from '@/types/api'

// ─── 类型定义 ───────────────────────────────────────────────────────────────

/** 商品状态 */
export type ProductStatus = 'active' | 'inactive' | 'draft'

/** 品类 L1 */
export interface CategoryL1 {
  id: number
  name: string
  slug: string
}

/** 品类 L2 */
export interface CategoryL2 {
  id: number
  l1_id: number
  name: string
  slug: string
}

/** SKU 变体 */
export interface ProductVariant {
  id?: number
  sku: string
  size?: string
  color?: string
  stock: number
  price: number
  cost_price?: number
}

/** 商品多语言翻译 */
export interface ProductTranslation {
  locale: string
  name: string
  description: string
}

/** 商品图片 */
export interface ProductImage {
  id?: number
  url: string
  sort: number
  is_main: boolean
}

/** 主商品库商品（详细） */
export interface MasterProduct {
  id: number
  sku: string
  name: string
  category_l1_id: number
  category_l1_name?: string
  category_l2_id: number
  category_l2_name?: string
  price: number
  cost_price?: number
  status: ProductStatus
  synced_stores_count: number
  variants: ProductVariant[]
  translations: ProductTranslation[]
  images: ProductImage[]
  created_at: string
  updated_at: string
}

/** 商品列表查询参数 */
export interface ProductListParams {
  page?: number
  per_page?: number
  search?: string
  category_l1?: number
  status?: ProductStatus | ''
}

/** 创建/更新商品数据 */
export interface ProductFormData {
  sku: string
  name: string
  category_l1_id: number
  category_l2_id: number
  price: number
  cost_price?: number
  status: ProductStatus
  variants: ProductVariant[]
  translations: ProductTranslation[]
  images: ProductImage[]
}

/** 批量更新状态 */
export interface BatchStatusData {
  ids: number[]
  status: ProductStatus
}

// ─── 同步类型 ────────────────────────────────────────────────────────────────

/** 同步价格策略 */
export type PricingStrategy = 'original' | 'multiplier' | 'fixed'

/** 同步字段选项 */
export type SyncField = 'name' | 'description' | 'price' | 'images' | 'variants'

/** 单商品同步请求 */
export interface SyncSingleRequest {
  master_product_id: number
  store_ids: number[]
  sync_fields?: SyncField[]
  pricing_strategy?: PricingStrategy
  pricing_value?: number
}

/** 批量同步请求 */
export interface SyncBatchRequest {
  master_product_ids: number[]
  store_ids: number[]
  options?: {
    sync_fields?: SyncField[]
    pricing_strategy?: PricingStrategy
    pricing_value?: number
  }
}

/** 同步状态 */
export type SyncStatus = 'success' | 'failed' | 'pending'

/** 同步日志 */
export interface SyncLog {
  id: number
  master_product_id: number
  product_name: string
  store_id: number
  store_name: string
  status: SyncStatus
  duration_ms?: number
  error_message?: string
  synced_at: string
  created_at: string
}

/** 同步日志查询参数 */
export interface SyncLogParams {
  page?: number
  per_page?: number
  status?: SyncStatus | ''
  store_id?: number | ''
  date_start?: string
  date_end?: string
}

/** 同步规则 */
export interface SyncRule {
  id: number
  name: string
  target_store_ids: number[]
  exclude_store_ids: number[]
  sync_fields: SyncField[]
  pricing_strategy: PricingStrategy
  pricing_value?: number
  status: 'active' | 'inactive'
  created_at: string
  updated_at: string
}

/** 同步规则表单数据 */
export interface SyncRuleFormData {
  name: string
  target_store_ids: number[]
  exclude_store_ids: number[]
  sync_fields: SyncField[]
  pricing_strategy: PricingStrategy
  pricing_value?: number
  status: 'active' | 'inactive'
}

// ─── 商品 CRUD API ─────────────────────────────────────────────────────────

/** 获取商品列表 */
export function getProductList(params?: ProductListParams): Promise<ApiResponse<PaginatedData<MasterProduct>>> {
  return get('/products', params as Record<string, unknown>)
}

/** 获取商品详情 */
export function getProductDetail(id: number): Promise<ApiResponse<MasterProduct>> {
  return get(`/products/${id}`)
}

/** 创建商品 */
export function createProduct(data: ProductFormData): Promise<ApiResponse<MasterProduct>> {
  return post('/products', data as unknown as Record<string, unknown>)
}

/** 更新商品 */
export function updateProduct(id: number, data: Partial<ProductFormData>): Promise<ApiResponse<MasterProduct>> {
  return put(`/products/${id}`, data as Record<string, unknown>)
}

/** 删除商品 */
export function deleteProduct(id: number): Promise<ApiResponse<null>> {
  return del(`/products/${id}`)
}

/** 批量更新商品状态 */
export function batchUpdateStatus(data: BatchStatusData): Promise<ApiResponse<{ updated: number }>> {
  return post('/products/batch-status', data as unknown as Record<string, unknown>)
}

// ─── 品类 API ──────────────────────────────────────────────────────────────

/** 获取 L1 品类列表 */
export function getCategoryL1List(): Promise<ApiResponse<CategoryL1[]>> {
  return get('/categories/l1')
}

/** 获取 L2 品类列表 */
export function getCategoryL2List(params: { l1_id: number }): Promise<ApiResponse<CategoryL2[]>> {
  return get('/categories/l2', params as Record<string, unknown>)
}

// ─── 同步 API ──────────────────────────────────────────────────────────────

/** 同步单个商品 */
export function syncSingleProduct(data: SyncSingleRequest): Promise<ApiResponse<{ job_id: string }>> {
  return post('/sync/single', data as unknown as Record<string, unknown>)
}

/** 批量同步商品 */
export function syncBatchProducts(data: SyncBatchRequest): Promise<ApiResponse<{ job_id: string; total: number }>> {
  return post('/sync/batch', data as unknown as Record<string, unknown>)
}

/** 获取同步日志列表 */
export function getSyncLogs(params?: SyncLogParams): Promise<ApiResponse<PaginatedData<SyncLog>>> {
  return get('/sync/logs', params as Record<string, unknown>)
}

/** 获取同步日志详情 */
export function getSyncLogDetail(id: number): Promise<ApiResponse<SyncLog>> {
  return get(`/sync/logs/${id}`)
}

// ─── 同步规则 API ──────────────────────────────────────────────────────────

/** 获取同步规则列表 */
export function getSyncRules(): Promise<ApiResponse<SyncRule[]>> {
  return get('/sync-rules')
}

/** 创建同步规则 */
export function createSyncRule(data: SyncRuleFormData): Promise<ApiResponse<SyncRule>> {
  return post('/sync-rules', data as unknown as Record<string, unknown>)
}

/** 更新同步规则 */
export function updateSyncRule(id: number, data: Partial<SyncRuleFormData>): Promise<ApiResponse<SyncRule>> {
  return put(`/sync-rules/${id}`, data as Record<string, unknown>)
}

/** 删除同步规则 */
export function deleteSyncRule(id: number): Promise<ApiResponse<null>> {
  return del(`/sync-rules/${id}`)
}
