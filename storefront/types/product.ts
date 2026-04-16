export interface ProductImage {
  id: number
  url: string
  alt?: string
  sort_order?: number
}

export interface ProductSku {
  id: number
  sku_code: string
  price: number
  original_price?: number
  stock: number
  attributes: Record<string, string> // { size: 'XL', color: 'Red' }
}

export interface ProductDescription {
  locale: string
  name: string
  description: string
  short_description?: string
}

export interface Category {
  id: number
  name: string
  slug: string
  description?: string
  image?: string
  parent_id?: number | null
  children?: Category[]
  product_count?: number
}

export interface Product {
  id: number
  name: string
  slug: string
  short_description?: string
  description: string
  price: number
  original_price?: number
  images: ProductImage[]
  thumbnail?: string
  skus?: ProductSku[]
  category_id?: number
  category?: Category
  is_featured?: boolean
  status: 'active' | 'inactive'
  stock?: number
  sold_count?: number
  rating?: number
  review_count?: number
  attributes?: Record<string, string[]>
  descriptions?: ProductDescription[]
  related_products?: Product[]
  created_at?: string
}

export interface ProductListParams {
  page?: number
  per_page?: number
  keyword?: string
  category_id?: number
  sort?: 'latest' | 'price_asc' | 'price_desc' | 'best_seller'
  price_min?: number
  price_max?: number
  featured?: boolean
}

export interface PaginatedProducts {
  list: Product[]
  total: number
  page: number
  per_page: number
}
