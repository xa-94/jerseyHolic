/**
 * 订单管理 API
 */
import { get } from './request'
import service from './request'
import type { ApiResponse } from '@/types/api'
import type { AxiosResponse } from 'axios'

// ─── 类型定义 ───────────────────────────────────────────────────────────────

/** 订单状态 */
export type OrderStatus = 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled' | 'refunded'

/** 支付状态 */
export type PaymentStatus = 'unpaid' | 'paid' | 'refunded'

/** 订单商品行 */
export interface OrderItem {
  product_name: string
  sku: string
  quantity: number
  price: number
  image_url?: string
}

/** 收货地址 */
export interface ShippingAddress {
  name: string
  address_line1: string
  city: string
  state: string
  country: string
  postal_code: string
  phone: string
}

/** 订单（完整，含商品行） */
export interface Order {
  id: number
  order_no: string
  store_id: number
  store_name: string
  customer_name: string
  customer_email: string
  items: OrderItem[]
  subtotal: number
  shipping_fee: number
  total_amount: number
  currency: string
  status: OrderStatus
  payment_method: string
  payment_status: PaymentStatus
  shipping_address: ShippingAddress
  tracking_number?: string
  carrier?: string
  created_at: string
  updated_at: string
}

/** 订单列表查询参数 */
export interface OrderListParams {
  page?: number
  per_page?: number
  store_id?: number | ''
  status?: OrderStatus | ''
  date_from?: string
  date_to?: string
  search?: string
}

/** 订单列表分页响应 */
export interface OrderPaginatedData {
  data: Order[]
  meta: {
    current_page: number
    total: number
    per_page: number
  }
}

/** 导出订单参数 */
export interface ExportOrderParams {
  store_id?: number | ''
  status?: OrderStatus | ''
  date_from?: string
  date_to?: string
  search?: string
}

// ─── API 函数 ──────────────────────────────────────────────────────────────

/** 获取订单列表（分页 + 筛选） */
export function getOrderList(params?: OrderListParams): Promise<ApiResponse<OrderPaginatedData>> {
  return get('/orders', params as Record<string, unknown>)
}

/** 获取订单详情 */
export function getOrderDetail(id: number): Promise<ApiResponse<Order>> {
  return get(`/orders/${id}`)
}

/**
 * 导出订单 CSV
 * 返回 Blob，由调用方触发浏览器下载
 */
export function exportOrders(params?: ExportOrderParams): Promise<Blob> {
  return service
    .post('/orders/export', params ?? {}, { responseType: 'blob' })
    .then((res: AxiosResponse<Blob>) => res.data)
}
