<script setup lang="ts">
import type { Category, ProductListParams } from '~/types/product'

const { t } = useI18n()
const localePath = useLocalePath()
const route = useRoute()
const router = useRouter()

// 从 URL query 初始化参数
const currentPage = computed(() => Number(route.query.page) || 1)
const currentSort = computed(() => (route.query.sort as string) || 'latest')
const currentCategory = computed(() => route.query.category_id ? Number(route.query.category_id) : undefined)
const currentKeyword = computed(() => (route.query.keyword as string) || '')
const currentPriceMin = computed(() => route.query.price_min ? Number(route.query.price_min) : undefined)
const currentPriceMax = computed(() => route.query.price_max ? Number(route.query.price_max) : undefined)

const filterValue = ref({
  categoryId: currentCategory.value,
  priceMin: currentPriceMin.value,
  priceMax: currentPriceMax.value,
  sort: currentSort.value,
})

// 获取分类
const { apiFetch } = useApi()
const { data: categoriesData } = await useAsyncData('products-page-categories', () =>
  apiFetch<Category[]>('/categories').catch(() => [] as Category[])
)
const categories = computed(() => categoriesData.value ?? [])

// 构建参数
const fetchParams = computed<ProductListParams>(() => ({
  page: currentPage.value,
  per_page: 20,
  sort: currentSort.value as ProductListParams['sort'],
  category_id: currentCategory.value,
  keyword: currentKeyword.value || undefined,
  price_min: currentPriceMin.value,
  price_max: currentPriceMax.value,
}))

// 获取商品数据（SSR）
const { data: productsData, pending, refresh } = await useAsyncData(
  () => `products-list-${JSON.stringify(fetchParams.value)}`,
  () => apiFetch<{ list: any[]; total: number; page: number; per_page: number }>(
    '/products', { params: fetchParams.value }
  ).catch(() => ({ list: [], total: 0, page: 1, per_page: 20 })),
  { watch: [fetchParams] }
)

const products = computed(() => productsData.value?.list ?? [])
const total = computed(() => productsData.value?.total ?? 0)

// SEO
const pageTitle = computed(() => {
  if (currentKeyword.value) return `Search: "${currentKeyword.value}" - JerseyHolic`
  return `All Jerseys - JerseyHolic`
})

useHead({
  title: pageTitle,
  meta: [
    { name: 'description', content: 'Browse our complete collection of premium sports jerseys. Filter by category, price, and more.' },
  ],
})

// 更新 URL query
function updateQuery(params: Record<string, any>) {
  router.push({
    path: localePath('/products'),
    query: {
      ...route.query,
      ...params,
      page: undefined, // 筛选时重置页码
    }
  })
}

function handleFilterChange(val: typeof filterValue.value) {
  filterValue.value = val
  updateQuery({
    category_id: val.categoryId || undefined,
    price_min: val.priceMin || undefined,
    price_max: val.priceMax || undefined,
    sort: val.sort || undefined,
  })
}

function handlePageChange(page: number) {
  router.push({ path: localePath('/products'), query: { ...route.query, page } })
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// 移动端筛选面板
const showFilter = ref(false)

// 排序选项
const sortOptions = [
  { value: 'latest', label: 'Latest' },
  { value: 'price_asc', label: 'Price ↑' },
  { value: 'price_desc', label: 'Price ↓' },
  { value: 'best_seller', label: 'Best Seller' },
]
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <!-- 面包屑 + 标题 -->
    <div class="mb-6">
      <nav class="text-sm text-gray-500 mb-2">
        <NuxtLink :to="localePath('/')" class="hover:text-accent">Home</NuxtLink>
        <span class="mx-2">/</span>
        <span class="text-gray-800">Products</span>
      </nav>
      <div class="flex items-center justify-between flex-wrap gap-3">
        <h1 class="text-2xl font-bold text-gray-900">
          <span v-if="currentKeyword">Results for "{{ currentKeyword }}"</span>
          <span v-else>All Jerseys</span>
        </h1>
        <span class="text-sm text-gray-500">{{ total }} products</span>
      </div>
    </div>

    <div class="flex gap-8">
      <!-- ======== 左侧筛选（桌面端） ======== -->
      <div class="hidden lg:block w-56 shrink-0">
        <ProductFilter
          :categories="categories"
          :model-value="filterValue"
          @update:model-value="handleFilterChange"
        />
      </div>

      <!-- ======== 右侧主内容 ======== -->
      <div class="flex-1 min-w-0">
        <!-- 工具栏：排序 + 移动端筛选按钮 -->
        <div class="flex items-center justify-between mb-5 gap-3">
          <!-- 移动端筛选按钮 -->
          <button
            class="lg:hidden flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 text-sm hover:border-accent transition"
            @click="showFilter = true"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            Filters
          </button>

          <!-- 桌面端排序 -->
          <div class="hidden lg:flex items-center gap-2 ms-auto">
            <span class="text-sm text-gray-500">Sort by:</span>
            <select
              :value="currentSort"
              @change="updateQuery({ sort: ($event.target as HTMLSelectElement).value })"
              class="border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-accent"
            >
              <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>

          <!-- 移动端排序 -->
          <select
            :value="currentSort"
            @change="updateQuery({ sort: ($event.target as HTMLSelectElement).value })"
            class="lg:hidden border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-accent ms-auto"
          >
            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>

        <!-- 商品网格 -->
        <div v-if="pending" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
          <div v-for="i in 12" :key="i" class="rounded-xl overflow-hidden">
            <div class="aspect-square bg-gray-200 animate-pulse"/>
            <div class="p-3 space-y-2">
              <div class="h-4 bg-gray-200 rounded animate-pulse"/>
              <div class="h-4 bg-gray-200 rounded w-2/3 animate-pulse"/>
            </div>
          </div>
        </div>

        <div v-else-if="products.length > 0" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-4">
          <ProductCard v-for="product in products" :key="product.id" :product="product" />
        </div>

        <div v-else class="text-center py-20 text-gray-400">
          <p class="text-5xl mb-4">🔍</p>
          <p class="text-lg font-medium text-gray-600">{{ t('common.no_results') }}</p>
          <p class="text-sm mt-1">Try different keywords or remove filters</p>
          <NuxtLink :to="localePath('/products')" class="mt-4 inline-block text-accent hover:underline text-sm">Clear all filters</NuxtLink>
        </div>

        <!-- 分页 -->
        <CommonPagination
          :total="total"
          :page="currentPage"
          :per-page="fetchParams.per_page ?? 20"
          @change="handlePageChange"
        />
      </div>
    </div>

    <!-- ======== 移动端筛选抽屉 ======== -->
    <Teleport to="body">
      <Transition name="drawer">
        <div v-if="showFilter" class="fixed inset-0 z-50 flex">
          <div class="fixed inset-0 bg-black/40" @click="showFilter = false"/>
          <div class="relative ms-auto w-72 max-w-full h-full bg-white overflow-y-auto p-5 shadow-xl">
            <div class="flex items-center justify-between mb-5">
              <h3 class="font-bold text-gray-900">Filters</h3>
              <button @click="showFilter = false" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <ProductFilter
              :categories="categories"
              :model-value="filterValue"
              @update:model-value="(v) => { handleFilterChange(v); showFilter = false }"
            />
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
  transition: opacity 0.25s ease;
}
.drawer-enter-active .relative,
.drawer-leave-active .relative {
  transition: transform 0.25s ease;
}
.drawer-enter-from,
.drawer-leave-to {
  opacity: 0;
}
</style>
