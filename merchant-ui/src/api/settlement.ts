/**
 * 结算中心 API
 */
import { get } from './request'
import type { ApiResponse } from '@/types/api'

// ─── 类型定义 ───────────────────────────────────────────────────────────────

/** 结算状态 */
export type SettlementStatus = 'pending' | 'processing' | 'completed' | 'rejected'

/** 各站点结算明细 */
export interface StoreSettlementDetail {
  store_id: number
  store_name: string
  sales: number
  commission: number
  net: number
}

/** 结算记录 */
export interface Settlement {
  id: number
  period_start: string
  period_end: string
  total_sales: number
  total_commission: number
  commission_rate: number
  total_refunds: number
  net_amount: number
  adjustment: number
  status: SettlementStatus
  store_details: StoreSettlementDetail[]
  paid_at?: string
  bank_info?: string
  created_at: string
}

/** 结算摘要 */
export interface SettlementSummary {
  available_balance: number
  this_month_estimated_commission: number
  total_settled: number
  pending_amount: number
}

/** 结算列表查询参数 */
export interface SettlementListParams {
  page?: number
  per_page?: number
  status?: SettlementStatus | ''
  date_from?: string
  date_to?: string
}

/** 结算列表分页响应 */
export interface SettlementPaginatedData {
  data: Settlement[]
  meta: {
    current_page: number
    total: number
    per_page: number
  }
}

// ─── API 函数 ──────────────────────────────────────────────────────────────

/** 获取结算记录列表（分页 + 筛选） */
export function getSettlementList(params?: SettlementListParams): Promise<ApiResponse<SettlementPaginatedData>> {
  return get('/settlements', params as Record<string, unknown>)
}

/** 获取结算详情 */
export function getSettlementDetail(id: number): Promise<ApiResponse<Settlement>> {
  return get(`/settlements/${id}`)
}

/** 获取结算摘要（余额 / 佣金 / 已结算 / 待结算） */
export function getSettlementSummary(): Promise<ApiResponse<SettlementSummary>> {
  return get('/settlements/summary')
}
