/** 商户状态 */
export type MerchantStatus = 'pending' | 'approved' | 'rejected' | 'frozen'

/** 商户等级 */
export type MerchantTier = 'standard' | 'silver' | 'gold' | 'diamond'

/** 站点状态 */
export type StoreStatus = 'active' | 'inactive' | 'suspended'

/** 结算状态 */
export type SettlementStatus = 'pending' | 'approved' | 'rejected' | 'paid'

/** 风险等级 */
export type RiskLevel = 'low' | 'medium' | 'high' | 'critical'

/** 商户信息 */
export interface Merchant {
  id: number
  company_name: string
  contact_name: string
  email: string
  phone: string
  status: MerchantStatus
  tier: MerchantTier
  store_count: number
  business_license?: string
  address?: string
  country?: string
  reviewer_name?: string
  review_reason?: string
  reviewed_at?: string
  created_at: string
  updated_at: string
}

/** 站点信息 */
export interface Store {
  id: number
  merchant_id: number
  name: string
  domain: string
  status: StoreStatus
  category: string
  market: string
  language: string
  currency: string
  created_at: string
  updated_at: string
}

/** 结算记录 */
export interface Settlement {
  id: number
  merchant_id: number
  period_start: string
  period_end: string
  total_amount: number
  commission: number
  commission_rate: number
  net_amount: number
  status: SettlementStatus
  auditor_name?: string
  audit_remark?: string
  audited_at?: string
  created_at: string
}

/** 风控数据 */
export interface RiskProfile {
  merchant_id: number
  risk_score: number
  risk_level: RiskLevel
  daily_limit: number
  monthly_limit: number
  daily_used: number
  monthly_used: number
  flags: string[]
  last_checked_at: string
}

/** 操作日志 */
export interface OperationLog {
  id: number
  operator: string
  action: string
  description: string
  ip?: string
  created_at: string
}

/** 商户列表查询参数 */
export interface MerchantListParams {
  page: number
  per_page: number
  search?: string
  status?: MerchantStatus | ''
  tier?: MerchantTier | ''
}

/** 创建商户参数 */
export interface CreateMerchantPayload {
  company_name: string
  contact_name: string
  email: string
  phone: string
  tier?: MerchantTier
  address?: string
  country?: string
}

/** 审核商户参数 */
export interface ReviewMerchantPayload {
  action: 'approve' | 'reject'
  reason?: string
}

/** 创建站点参数（4步向导） */
export interface CreateStorePayload {
  name: string
  domain: string
  market: string
  language: string
  currency: string
  category: string
}

/** 结算审核参数 */
export interface AuditSettlementPayload {
  action: 'approve' | 'reject'
  remark?: string
}

/** 结算记录查询参数 */
export interface SettlementListParams {
  page: number
  per_page: number
  status?: SettlementStatus | ''
}

/** 操作日志查询参数 */
export interface LogListParams {
  page: number
  per_page: number
}
