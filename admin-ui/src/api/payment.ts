import { get, post, put, del } from './request'
import type { ApiResponse, PaginationParams, PaginatedData } from '@/types/api'

export interface PaymentAccount {
  id: number
  name: string
  type: 'stripe' | 'paypal' | 'alipay' | 'wechat' | 'crypto'
  account_id: string
  is_active: boolean
  is_default: boolean
  currency: string
  created_at: string
}

export interface PaymentTransaction {
  id: number
  order_no: string
  amount: number
  currency: string
  status: 'pending' | 'success' | 'failed' | 'refunded'
  payment_method: string
  transaction_id: string
  created_at: string
}

export interface PaymentAccountForm {
  name: string
  type: PaymentAccount['type']
  account_id: string
  secret_key?: string
  is_active?: boolean
  is_default?: boolean
  currency: string
}

/** 获取支付账号列表 */
export function getPaymentAccountList(params?: Partial<PaginationParams>): Promise<ApiResponse<PaginatedData<PaymentAccount>>> {
  return get<PaginatedData<PaymentAccount>>('/payment/accounts', params as unknown as Record<string, unknown>)
}

/** 获取支付账号详情 */
export function getPaymentAccountById(id: number): Promise<ApiResponse<PaymentAccount>> {
  return get<PaymentAccount>(`/payment/accounts/${id}`)
}

/** 创建支付账号 */
export function createPaymentAccount(data: PaymentAccountForm): Promise<ApiResponse<PaymentAccount>> {
  return post<PaymentAccount>('/payment/accounts', data as unknown as Record<string, unknown>)
}

/** 更新支付账号 */
export function updatePaymentAccount(id: number, data: Partial<PaymentAccountForm>): Promise<ApiResponse<PaymentAccount>> {
  return put<PaymentAccount>(`/payment/accounts/${id}`, data as unknown as Record<string, unknown>)
}

/** 删除支付账号 */
export function deletePaymentAccount(id: number): Promise<ApiResponse<null>> {
  return del<null>(`/payment/accounts/${id}`)
}

/** 获取交易记录列表 */
export function getTransactionList(params: Partial<PaginationParams> & {
  keyword?: string
  status?: string
  date_from?: string
  date_to?: string
}): Promise<ApiResponse<PaginatedData<PaymentTransaction>>> {
  return get<PaginatedData<PaymentTransaction>>('/payment/transactions', params as unknown as Record<string, unknown>)
}
