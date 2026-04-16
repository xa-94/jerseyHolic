import { get, post, put } from './request'
import type { ApiResponse, PaginatedData } from '@/types/api'
import type {
  Order,
  OrderListParams,
  UpdateStatusPayload,
  RefundPayload,
  AddHistoryPayload,
} from '@/types/order'

// 重新导出类型以便其他模块直接从此文件导入
export type { Order, OrderListParams, UpdateStatusPayload, RefundPayload, AddHistoryPayload }

/** 获取订单列表 */
export function getOrderList(
  params: OrderListParams,
): Promise<ApiResponse<PaginatedData<Order>>> {
  return get<PaginatedData<Order>>(
    '/admin/orders',
    params as unknown as Record<string, unknown>,
  )
}

/** 获取订单详情 */
export function getOrderDetail(id: number): Promise<ApiResponse<Order>> {
  return get<Order>(`/admin/orders/${id}`)
}

/** 更新支付状态 */
export function updatePayStatus(
  id: number,
  data: UpdateStatusPayload,
): Promise<ApiResponse<Order>> {
  return put<Order>(
    `/admin/orders/${id}/pay-status`,
    data as unknown as Record<string, unknown>,
  )
}

/** 更新发货状态 */
export function updateShipStatus(
  id: number,
  data: UpdateStatusPayload,
): Promise<ApiResponse<Order>> {
  return put<Order>(
    `/admin/orders/${id}/ship-status`,
    data as unknown as Record<string, unknown>,
  )
}

/** 提交退款 */
export function refundOrder(
  id: number,
  data: RefundPayload,
): Promise<ApiResponse<null>> {
  return post<null>(
    `/admin/orders/${id}/refund`,
    data as unknown as Record<string, unknown>,
  )
}

/** 添加订单历史备注 */
export function addOrderHistory(
  id: number,
  data: AddHistoryPayload,
): Promise<ApiResponse<null>> {
  return post<null>(
    `/admin/orders/${id}/history`,
    data as unknown as Record<string, unknown>,
  )
}

/** 导出订单（返回 blob URL 下载） */
export function exportOrders(
  params: Omit<OrderListParams, 'page' | 'per_page'>,
): Promise<ApiResponse<{ url: string }>> {
  return get<{ url: string }>(
    '/admin/orders/export',
    params as unknown as Record<string, unknown>,
  )
}
