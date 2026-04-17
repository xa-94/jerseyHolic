/**
 * 商户相关类型定义
 */

/** 商户登录用户信息（后端 /auth/me 和 /auth/login 返回） */
export interface MerchantUserInfo {
  id: number
  name: string
  email: string
  merchant_id: number
  role: string
  permissions: string[]
  stores: StoreInfo[]
}

/** 登录响应数据 */
export interface MerchantLoginResult {
  token: string
  expires_in: number
  user: MerchantUserInfo
}

/** 商户信息 */
export interface MerchantInfo {
  id: number
  name: string
  email: string
  status: 'active' | 'inactive' | 'suspended'
  created_at: string
  updated_at: string
}

/** 站点信息 */
export interface StoreInfo {
  id: number
  merchant_id: number
  name: string
  domain: string
  language: string
  currency: string
  status: 'active' | 'inactive'
  created_at: string
  updated_at: string
}

/** 商品（商户视角） */
export interface MerchantProduct {
  id: number
  store_id: number
  sku: string
  name: string
  safe_name?: string
  price: number
  stock: number
  status: 'active' | 'inactive' | 'draft'
  synced_at?: string
  created_at: string
  updated_at: string
}

/** 订单（商户视角） */
export interface MerchantOrder {
  id: number
  order_no: string
  store_id: number
  store_name: string
  customer_name: string
  customer_email: string
  total_amount: number
  currency: string
  status: 'pending' | 'paid' | 'shipped' | 'completed' | 'cancelled' | 'refunded'
  payment_status: 'unpaid' | 'paid' | 'refunded'
  shipping_status: 'pending' | 'processing' | 'shipped' | 'delivered'
  created_at: string
  updated_at: string
}

/** 结算记录 */
export interface SettlementRecord {
  id: number
  merchant_id: number
  period_start: string
  period_end: string
  total_orders: number
  total_amount: number
  commission: number
  net_amount: number
  currency: string
  status: 'pending' | 'processing' | 'completed'
  settled_at?: string
  created_at: string
}

/** 商品同步请求 */
export interface ProductSyncRequest {
  store_id: number
  source: 'manual' | 'import'
  product_ids?: number[]
}

/** 商品同步结果 */
export interface ProductSyncResult {
  total: number
  success: number
  failed: number
  errors: Array<{
    sku: string
    message: string
  }>
}
