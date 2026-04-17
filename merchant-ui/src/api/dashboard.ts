import { get } from './request'
import type { ApiResponse } from '@/types/api'

/** 趋势数据点 */
export interface TrendItem {
  date: string
  sales: number
  orders: number
}

/** 站点销售占比 */
export interface StoreDistributionItem {
  store_name: string
  sales: number
  percentage: number
}

/** 仪表盘统计数据 */
export interface DashboardStats {
  today_sales: number
  today_orders: number
  pending_orders: number
  available_balance: number
  trends: TrendItem[]
  store_distribution: StoreDistributionItem[]
}

/** 获取仪表盘统计数据 */
export function getDashboardStats(params?: { store_id?: number }): Promise<ApiResponse<DashboardStats>> {
  return get<DashboardStats>('/dashboard/stats', params as Record<string, unknown>)
}
