/** 分类描述（多语言） */
export interface CategoryDescription {
  locale: string
  name: string
  description?: string
  seo_title?: string
  seo_description?: string
  seo_keywords?: string
}

/** 分类详情 */
export interface Category {
  id: number
  parent_id: number | null
  parent_name?: string
  slug: string
  image?: string
  status: 'active' | 'inactive'
  sort_order: number
  products_count?: number
  children?: Category[]
  descriptions?: CategoryDescription[]
  name: string
  created_at: string
  updated_at?: string
}

/** 分类树节点 */
export interface CategoryTreeNode extends Category {
  children?: CategoryTreeNode[]
  label?: string
  value?: number
}

/** 分类表单数据 */
export interface CategoryFormData {
  parent_id: number | null
  slug?: string
  image?: string
  status: 'active' | 'inactive'
  sort_order: number
  descriptions: CategoryDescription[]
}

/** 排序请求项 */
export interface ReorderItem {
  id: number
  sort_order: number
  parent_id?: number | null
}
