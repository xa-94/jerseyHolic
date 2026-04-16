<script setup lang="ts">
import type { Category, ProductListParams } from '~/types/product'

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()
const router = useRouter()
const { apiFetch } = useApi()

const categoryId = computed(() => route.params.id as string)

// 获取分类信息
const { data: category, error: categoryError } = await useAsyncData(
  `category-detail-${categoryId.value}`,
  () => apiFetch<Category>(`/categories/${categoryId.value}`)
)

if (categoryError.value || !category.value) {
  throw createError({ statusCode: 404, message: 'Category not found' })
}

// 分页 & 筛选参数
const currentPage = computed(() => Number(route.query.page) || 1)
const currentSort = computed(() => (route.query.sort as string) || 'latest')
const currentPriceMin = computed(() => route.query.price_min ? Number(route.query.price_min) : undefined)
const currentPriceMax = computed(() => route.query.price_max ? Number(route.query.price_max) : undefined)

const fetchParams = computed<ProductListParams>(() => ({
  page: currentPage.value,
  per_page: 20,
  sort: currentSort.value as ProductListParams['sort'],
  category_id: Number(categoryId.value),
  price_min: currentPriceMin.value,
  price_max: currentPriceMax.value,
}))

// 获取该分类的商品（SSR）
const { data: productsData, pending } = await useAsyncData(
  () => `category-${categoryId.value}-products-${JSON.stringify(fetchParams.value)}`,
  () => apiFetch<{ list: any[]; total: number; page: number; per_page: number }>(
    `/products/category/${categoryId.value}`,
    { params: fetchParams.value }
  ).catch(() => ({ list: [], total: 0, page: 1, per_page: 20 })),
  { watch: [fetchParams] }
)

const products = computed(() => productsData.value?.list ?? [])
const total = computed(() => productsData.value?.total ?? 0)

// SEO
useHead({
  title: `${category.value?.name} Jerseys - JerseyHolic`,
  meta: [
    { name: 'description', content: category.value?.description ?? `Shop ${category.value?.name} jerseys at JerseyHolic. Premium quality, worldwide shipping.` },
    { property: 'og:title', content: `${category.value?.name} - JerseyHolic` },
    { property: 'og:type', content: 'website' },
  ],
})

// 排序选项
const sortOptions = [
  { value: 'latest', label: 'Latest' },
  { value: 'price_asc', label: 'Price ↑' },
  { value: 'price_desc', label: 'Price ↓' },
  { value: 'best_seller', label: 'Best Seller' },
]

function updateQuery(params: Record<string, any>) {
  router.push({
    path: localePath(`/categories/${categoryId.value}`),
    query: { ...route.query, ...params, page: undefined }
  })
}

function handlePageChange(page: number) {
  router.push({
    path: localePath(`/categories/${categoryId.value}`),
    query: { ...route.query, page }
  })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <!-- 面包屑 -->
    <nav class="text-sm text-gray-500 mb-4">
      <NuxtLink :to="localePath('/')" class="hover:text-accent">Home</NuxtLink>
      <span class="mx-2">/</span>
      <NuxtLink :to="localePath('/products')" class="hover:text-accent">Products</NuxtLink>
      <span class="mx-2">/</span>
      <span class="text-gray-800">{{ category?.name }}</span>
    </nav>

    <!-- 分类标题 & 描述 -->
    <div class="mb-8">
      <div class="flex items-center gap-4 mb-3">
        <div v-if="category?.image" class="w-14 h-14 rounded-full overflow-hidden bg-gray-100 shrink-0">
          <img :src="category.image" :alt="category.name" class="w-full h-full object-cover"/>
        </div>
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900">{{ category?.name }}</h1>
          <p v-if="category?.description" class="text-gray-500 mt-1 text-sm">{{ category.description }}</p>
        </div>
      </div>
      <span class="text-sm text-gray-400">{{ total }} products</span>
    </div>

    <!-- 工具栏：排序 -->
    <div class="flex items-center justify-between mb-5 border-b border-gray-100 pb-4">
      <div class="text-sm text-gray-500">
        Showing <strong>{{ products.length }}</strong> of <strong>{{ total }}</strong>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-500 hidden sm:inline">Sort by:</span>
        <select
          :value="currentSort"
          @change="updateQuery({ sort: ($event.target as HTMLSelectElement).value })"
          class="border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-accent"
        >
          <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>
    </div>

    <!-- 商品网格 -->
    <div v-if="pending" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 md:gap-6">
      <div v-for="i in 12" :key="i" class="rounded-xl overflow-hidden">
        <div class="aspect-square bg-gray-200 animate-pulse"/>
        <div class="p-3 space-y-2">
          <div class="h-4 bg-gray-200 rounded animate-pulse"/>
          <div class="h-4 bg-gray-200 rounded w-2/3 animate-pulse"/>
        </div>
      </div>
    </div>

    <div v-else-if="products.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 md:gap-6">
      <ProductCard v-for="product in products" :key="product.id" :product="product" />
    </div>

    <div v-else class="text-center py-20 text-gray-400">
      <p class="text-5xl mb-4">📦</p>
      <p class="text-lg font-medium text-gray-600">{{ t('common.no_results') }}</p>
      <p class="text-sm mt-1">No products in this category yet</p>
      <NuxtLink :to="localePath('/products')" class="mt-4 inline-block text-accent hover:underline text-sm">
        Browse all products
      </NuxtLink>
    </div>

    <!-- 分页 -->
    <CommonPagination
      :total="total"
      :page="currentPage"
      :per-page="20"
      @change="handlePageChange"
    />

    <!-- 子分类导航 -->
    <div v-if="category?.children?.length" class="mt-12 border-t border-gray-100 pt-8">
      <h2 class="text-lg font-semibold text-gray-800 mb-5">Sub-Categories</h2>
      <div class="flex flex-wrap gap-3">
        <NuxtLink
          v-for="sub in category.children"
          :key="sub.id"
          :to="localePath(`/categories/${sub.id}`)"
          class="px-4 py-2 rounded-full border border-gray-200 text-sm hover:border-accent hover:text-accent transition"
        >
          {{ sub.name }}
        </NuxtLink>
      </div>
    </div>
  </div>
</template>
