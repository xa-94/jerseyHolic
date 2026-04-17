import { get } from './request'
import type { ApiResponse } from '@/types/api'

/** 站点详情（含更多字段） */
export interface StoreDetail {
  id: number
  merchant_id?: number
  name: string
  domain: string
  status: 'active' | 'inactive'
  category?: string
  market?: string
  language: string
  currency: string
  created_at?: string
  updated_at?: string
}

/** 获取当前商户的站点列表 */
export function getStoreList(): Promise<ApiResponse<{ data: StoreDetail[] }>> {
  return get<{ data: StoreDetail[] }>('/stores')
}
