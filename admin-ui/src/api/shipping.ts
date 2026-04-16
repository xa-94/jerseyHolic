import { get, post, put, del } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

export interface ShippingProvider {
  id: number
  name: string
  code: string
  tracking_url: string
  is_active: boolean
  created_at: string
}

export interface Shipment {
  id: number
  order_id: number
  order_no: string
  provider_id: number
  provider_name: string
  tracking_no: string
  status: 'pending' | 'shipped' | 'in_transit' | 'delivered' | 'failed'
  shipped_at?: string
  delivered_at?: string
  created_at: string
}

export interface ShipmentForm {
  order_id: number
  provider_id: number
  tracking_no: string
}

export interface ShippingProviderForm {
  name: string
  code: string
  tracking_url: string
  is_active?: boolean
}

/** 获取发货列表 */
export function getShipmentList(params: Partial<PaginationParams> & {
  keyword?: string
  status?: string
}): Promise<ApiResponse<PaginatedData<Shipment>>> {
  return get<PaginatedData<Shipment>>('/shipping/shipments', params as unknown as Record<string, unknown>)
}

/** 创建发货记录 */
export function createShipment(data: ShipmentForm): Promise<ApiResponse<Shipment>> {
  return post<Shipment>('/shipping/shipments', data as unknown as Record<string, unknown>)
}

/** 获取物流商列表 */
export function getProviderList(params?: Partial<PaginationParams>): Promise<ApiResponse<PaginatedData<ShippingProvider>>> {
  return get<PaginatedData<ShippingProvider>>('/shipping/providers', params as unknown as Record<string, unknown>)
}

/** 获取物流商详情 */
export function getProviderById(id: number): Promise<ApiResponse<ShippingProvider>> {
  return get<ShippingProvider>(`/shipping/providers/${id}`)
}

/** 创建物流商 */
export function createProvider(data: ShippingProviderForm): Promise<ApiResponse<ShippingProvider>> {
  return post<ShippingProvider>('/shipping/providers', data as unknown as Record<string, unknown>)
}

/** 更新物流商 */
export function updateProvider(id: number, data: Partial<ShippingProviderForm>): Promise<ApiResponse<ShippingProvider>> {
  return put<ShippingProvider>(`/shipping/providers/${id}`, data as unknown as Record<string, unknown>)
}

/** 删除物流商 */
export function deleteProvider(id: number): Promise<ApiResponse<null>> {
  return del<null>(`/shipping/providers/${id}`)
}
