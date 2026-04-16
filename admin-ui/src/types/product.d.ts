/** 商品描述（多语言） */
export interface ProductDescription {
  locale: string
  name: string
  description?: string
  seo_title?: string
  seo_description?: string
  seo_keywords?: string
}

/** 商品图片 */
export interface ProductImage {
  id: number
  url: string
  sort_order: number
  is_primary: boolean
}

/** 商品 SKU */
export interface ProductSku {
  id: number
  sku_code: string
  price: number
  special_price?: number
  quantity: number
  options?: Record<string, string>
}

/** 商品详情（完整） */
export interface Product {
  id: number
  model: string
  sku: string
  sku_prefix: string
  price: number
  special_price?: number
  special_price_start?: string
  special_price_end?: string
  effective_price: number
  quantity: number
  status: 'active' | 'inactive' | 'draft'
  category_id: number
  category_name?: string
  sort_order: number
  viewed: number
  safe_name?: string
  mapping_type?: 'exact' | 'sku_prefix' | 'default'
  image?: string
  descriptions: ProductDescription[]
  images: ProductImage[]
  skus: ProductSku[]
  created_at: string
  updated_at: string
}

/** 商品列表条目（简化版） */
export interface ProductListItem {
  id: number
  model: string
  sku: string
  sku_prefix: string
  price: number
  special_price?: number
  effective_price: number
  quantity: number
  status: 'active' | 'inactive' | 'draft'
  category_id: number
  category_name?: string
  sort_order: number
  safe_name?: string
  mapping_type?: 'exact' | 'sku_prefix' | 'default'
  image?: string
  name: string
  created_at: string
}

/** 商品列表搜索参数 */
export interface ProductListParams {
  keyword?: string
  category_id?: number | ''
  status?: string
  min_price?: number | ''
  max_price?: number | ''
  sort_by?: string
  sort_order?: 'asc' | 'desc'
  page: number
  per_page: number
}

/** 商品表单数据 */
export interface ProductFormData {
  category_id: number | null
  status: 'active' | 'inactive' | 'draft'
  sort_order: number
  price: number
  special_price?: number | null
  special_price_start?: string
  special_price_end?: string
  descriptions: ProductDescription[]
  images?: ProductImage[]
  skus?: ProductSku[]
}

/** SKU 颜色配置 */
export const SKU_PREFIX_COLORS: Record<string, string> = {
  hic: 'danger',
  WPZ: 'primary',
  DIY: 'success',
  NBL: 'info',
}
