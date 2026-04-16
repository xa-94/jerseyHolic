import type { Product, Category, ProductListParams, PaginatedProducts } from '~/types/product'

export function useProducts(params: ProductListParams = {}) {
  const { apiFetch } = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `products-${JSON.stringify(params)}-${locale.value}`,
    () => apiFetch<PaginatedProducts>('/products', { params }),
    { watch: [() => ({ ...params })] }
  )
}

export function useProduct(id: number | string) {
  const { apiFetch } = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `product-${id}-${locale.value}`,
    () => apiFetch<Product>(`/products/${id}`)
  )
}

export function useCategories() {
  const { apiFetch } = useApi()

  return useAsyncData(
    'categories',
    () => apiFetch<Category[]>('/categories'),
    { default: () => [] as Category[] }
  )
}

export function useFeaturedProducts(limit = 8) {
  const { apiFetch } = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `featured-products-${locale.value}`,
    () => apiFetch<PaginatedProducts>('/products', {
      params: { featured: true, per_page: limit, sort: 'best_seller' }
    }),
    { default: () => ({ list: [], total: 0, page: 1, per_page: limit } as PaginatedProducts) }
  )
}

export function useLatestProducts(limit = 8) {
  const { apiFetch } = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `latest-products-${locale.value}`,
    () => apiFetch<PaginatedProducts>('/products', {
      params: { per_page: limit, sort: 'latest' }
    }),
    { default: () => ({ list: [], total: 0, page: 1, per_page: limit } as PaginatedProducts) }
  )
}

export function useCategoryProducts(categoryId: number | string, params: ProductListParams = {}) {
  const { apiFetch } = useApi()
  const { locale } = useI18n()

  return useAsyncData(
    `category-${categoryId}-products-${JSON.stringify(params)}-${locale.value}`,
    () => apiFetch<PaginatedProducts>(`/products/category/${categoryId}`, { params }),
    { watch: [() => ({ ...params })] }
  )
}

export function useCategory(id: number | string) {
  const { apiFetch } = useApi()

  return useAsyncData(
    `category-${id}`,
    () => apiFetch<Category>(`/categories/${id}`)
  )
}
