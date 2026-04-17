import { get, post, put } from './request'
import type { ApiResponse, PaginatedData } from '@/types/api'
import type {
  Merchant,
  Store,
  Settlement,
  RiskProfile,
  OperationLog,
  MerchantListParams,
  CreateMerchantPayload,
  ReviewMerchantPayload,
  CreateStorePayload,
  AuditSettlementPayload,
  SettlementListParams,
  LogListParams,
} from '@/types/merchant'

// 重新导出类型以便其他模块直接从此文件导入
export type {
  Merchant,
  Store,
  Settlement,
  RiskProfile,
  OperationLog,
  MerchantListParams,
  CreateMerchantPayload,
  ReviewMerchantPayload,
  CreateStorePayload,
  AuditSettlementPayload,
  SettlementListParams,
  LogListParams,
}

/** 获取商户列表 */
export function getMerchants(
  params: MerchantListParams,
): Promise<ApiResponse<PaginatedData<Merchant>>> {
  return get<PaginatedData<Merchant>>(
    '/merchants',
    params as unknown as Record<string, unknown>,
  )
}

/** 获取商户详情 */
export function getMerchantDetail(id: number): Promise<ApiResponse<Merchant>> {
  return get<Merchant>(`/merchants/${id}`)
}

/** 创建商户 */
export function createMerchant(
  data: CreateMerchantPayload,
): Promise<ApiResponse<Merchant>> {
  return post<Merchant>('/merchants', data as unknown as Record<string, unknown>)
}

/** 更新商户 */
export function updateMerchant(
  id: number,
  data: Partial<CreateMerchantPayload>,
): Promise<ApiResponse<Merchant>> {
  return put<Merchant>(`/merchants/${id}`, data as unknown as Record<string, unknown>)
}

/** 审核商户（approve / reject） */
export function reviewMerchant(
  id: number,
  data: ReviewMerchantPayload,
): Promise<ApiResponse<Merchant>> {
  return post<Merchant>(
    `/merchants/${id}/review`,
    data as unknown as Record<string, unknown>,
  )
}

/** 获取商户下站点列表 */
export function getMerchantStores(
  merchantId: number,
): Promise<ApiResponse<Store[]>> {
  return get<Store[]>(`/merchants/${merchantId}/stores`)
}

/** 创建站点（4步向导数据） */
export function createStore(
  merchantId: number,
  data: CreateStorePayload,
): Promise<ApiResponse<Store>> {
  return post<Store>(
    `/merchants/${merchantId}/stores`,
    data as unknown as Record<string, unknown>,
  )
}

/** 获取商户结算记录 */
export function getMerchantSettlements(
  merchantId: number,
  params: SettlementListParams,
): Promise<ApiResponse<PaginatedData<Settlement>>> {
  return get<PaginatedData<Settlement>>(
    `/merchants/${merchantId}/settlements`,
    params as unknown as Record<string, unknown>,
  )
}

/** 结算审核（approve / reject） */
export function auditSettlement(
  settlementId: number,
  data: AuditSettlementPayload,
): Promise<ApiResponse<Settlement>> {
  return post<Settlement>(
    `/settlements/${settlementId}/audit`,
    data as unknown as Record<string, unknown>,
  )
}

/** 获取商户风控数据 */
export function getMerchantRiskProfile(
  merchantId: number,
): Promise<ApiResponse<RiskProfile>> {
  return get<RiskProfile>(`/merchants/${merchantId}/risk-profile`)
}

/** 获取商户操作日志 */
export function getMerchantLogs(
  merchantId: number,
  params: LogListParams,
): Promise<ApiResponse<PaginatedData<OperationLog>>> {
  return get<PaginatedData<OperationLog>>(
    `/merchants/${merchantId}/operation-logs`,
    params as unknown as Record<string, unknown>,
  )
}
