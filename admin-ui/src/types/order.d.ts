/** 订单相关类型定义 */

/** 订单商品项 */
export interface OrderItem {
  id: number
  product_id: number
  product_name: string
  safe_name?: string
  sku: string
  sku_code?: string
  quantity: number
  price: number
  subtotal: number
  image?: string
  options?: string
}

/** 收货/账单地址 */
export interface OrderAddress {
  id?: number
  firstname: string
  lastname: string
  company?: string
  phone: string
  email?: string
  country: string
  country_code?: string
  state: string
  city: string
  address1: string
  address2?: string
  postcode: string
  address_type?: 'shipping' | 'billing'
}

/** 订单历史记录 */
export interface OrderHistory {
  id: number
  comment: string
  operator?: string
  operator_id?: number
  notify_customer?: boolean
  created_at: string
}

/** 订单费用明细 */
export interface OrderTotal {
  code: string
  title: string
  value: number
  sort_order?: number
}

/** 订单主体 */
export interface Order {
  id: number
  order_no: string
  customer_name: string
  customer_email: string
  customer_phone?: string
  domain: string
  currency: string
  price: number
  total: number
  shipping_fee: number
  tax_amount: number
  discount_amount: number
  pay_status: number
  pay_status_label: string
  shipment_status: number
  shipment_status_label: string
  refund_status: number
  refund_status_label?: string
  dispute_status: number
  dispute_status_label?: string
  pay_type: string
  pay_type_label?: string
  is_zw: boolean
  is_diy: boolean
  is_wpz: boolean
  remark?: string
  items_count?: number
  created_at: string
  updated_at: string
  // 关联
  items?: OrderItem[]
  shipping_address?: OrderAddress
  billing_address?: OrderAddress
  histories?: OrderHistory[]
  totals?: OrderTotal[]
  customer?: {
    id: number
    firstname: string
    lastname: string
    email: string
  }
}

/** 订单列表查询参数 */
export interface OrderListParams {
  page?: number
  per_page?: number
  keyword?: string
  pay_status?: number | ''
  shipment_status?: number | ''
  refund_status?: number | ''
  date_from?: string
  date_to?: string
  domain?: string
  pay_type?: string
  sku_type?: string
  sort_field?: string
  sort_dir?: 'asc' | 'desc'
}

/** 更新支付/发货状态请求体 */
export interface UpdateStatusPayload {
  status: number
  remark?: string
}

/** 退款请求体 */
export interface RefundPayload {
  type: 'full' | 'partial'
  amount?: number
  reason: string
}

/** 添加历史备注请求体 */
export interface AddHistoryPayload {
  comment: string
  notify_customer?: boolean
}
