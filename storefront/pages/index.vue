<script setup lang="ts">
import type { Category, Product } from '~/types/product'

const { t } = useI18n()
const localePath = useLocalePath()

// SEO
useHead({
  title: 'JerseyHolic - Premium Sports Jerseys Worldwide',
  meta: [
    { name: 'description', content: 'Shop premium sports jerseys at JerseyHolic. Authentic quality, worldwide shipping. Choose from thousands of team jerseys.' },
    { property: 'og:title', content: 'JerseyHolic - Premium Sports Jerseys' },
    { property: 'og:description', content: 'Shop premium sports jerseys at JerseyHolic.' },
    { property: 'og:type', content: 'website' },
  ],
})

// 并行获取数据（SSR）
const { apiFetch } = useApi()

const [{ data: categoriesData }, { data: featuredData }, { data: latestData }] = await Promise.all([
  useAsyncData('index-categories', () =>
    apiFetch<Category[]>('/categories').catch(() => [] as Category[])
  ),
  useAsyncData('index-featured', () =>
    apiFetch<{ list: Product[]; total: number; page: number; per_page: number }>(
      '/products', { params: { featured: true, per_page: 8, sort: 'best_seller' } }
    ).catch(() => ({ list: [], total: 0, page: 1, per_page: 8 }))
  ),
  useAsyncData('index-latest', () =>
    apiFetch<{ list: Product[]; total: number; page: number; per_page: number }>(
      '/products', { params: { per_page: 8, sort: 'latest' } }
    ).catch(() => ({ list: [], total: 0, page: 1, per_page: 8 }))
  ),
])

const categories = computed(() => (categoriesData.value ?? []).filter(c => !c.parent_id))
const featuredProducts = computed(() => featuredData.value?.list ?? [])
const latestProducts = computed(() => latestData.value?.list ?? [])
</script>

<template>
  <div>
    <!-- ======== Hero Banner ======== -->
    <section class="relative bg-gradient-to-br from-primary via-primary-light to-primary-dark text-white overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-96 h-96 bg-accent rounded-full -translate-y-1/2 translate-x-1/2"/>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-white rounded-full translate-y-1/2 -translate-x-1/2"/>
      </div>
      <div class="container mx-auto px-4 py-20 md:py-32 relative z-10 text-center">
        <p class="text-accent font-semibold text-sm tracking-widest uppercase mb-3">🏆 World-Class Jerseys</p>
        <h1 class="text-4xl md:text-6xl font-extrabold mb-6 leading-tight">
          The Authentic<br/>
          <span class="text-accent">Sports Jersey</span> Store
        </h1>
        <p class="text-lg md:text-xl text-white/80 mb-10 max-w-2xl mx-auto">
          Thousands of officially-inspired sports jerseys. Worldwide shipping. Premium quality fabric.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <NuxtLink
            :to="localePath('/products')"
            class="bg-accent hover:bg-accent-light text-white font-bold px-8 py-4 rounded-full transition text-lg shadow-lg"
          >
            {{ t('common.view_all') }} →
          </NuxtLink>
          <a
            href="#categories"
            class="border-2 border-white/40 hover:border-white text-white font-semibold px-8 py-4 rounded-full transition text-lg"
          >
            Browse Categories
          </a>
        </div>
      </div>
    </section>

    <!-- ======== 信任标识 ======== -->
    <section class="bg-gray-50 border-b border-gray-100">
      <div class="container mx-auto px-4 py-4">
        <div class="flex flex-wrap justify-center gap-6 md:gap-12 text-sm text-gray-600">
          <div class="flex items-center gap-2"><span>🚚</span> Free Worldwide Shipping</div>
          <div class="flex items-center gap-2"><span>✅</span> 100% Authentic Quality</div>
          <div class="flex items-center gap-2"><span>🔒</span> Secure Payments</div>
          <div class="flex items-center gap-2"><span>↩️</span> 30-Day Returns</div>
        </div>
      </div>
    </section>

    <!-- ======== 分类导航区 ======== -->
    <section id="categories" class="py-14">
      <div class="container mx-auto px-4">
        <div class="text-center mb-10">
          <h2 class="text-2xl md:text-3xl font-bold text-gray-900">Shop by Category</h2>
          <p class="text-gray-500 mt-2">Find jerseys from your favourite leagues and sports</p>
        </div>

        <div v-if="categories.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
          <NuxtLink
            v-for="cat in categories.slice(0, 12)"
            :key="cat.id"
            :to="localePath(`/categories/${cat.id}`)"
            class="group flex flex-col items-center gap-3 p-4 bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 hover:border-accent transition text-center"
          >
            <div class="w-14 h-14 rounded-full bg-primary/10 flex items-center justify-center group-hover:bg-accent/10 transition overflow-hidden">
              <img v-if="cat.image" :src="cat.image" :alt="cat.name" class="w-full h-full object-cover rounded-full"/>
              <span v-else class="text-2xl">⚽</span>
            </div>
            <span class="text-sm font-medium text-gray-700 group-hover:text-accent transition">{{ cat.name }}</span>
          </NuxtLink>
        </div>

        <!-- 加载占位 -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
          <div v-for="i in 6" :key="i" class="h-28 bg-gray-100 rounded-xl animate-pulse"/>
        </div>

        <div class="text-center mt-8">
          <NuxtLink :to="localePath('/products')" class="text-accent hover:underline font-medium text-sm">
            {{ t('common.view_all') }} categories →
          </NuxtLink>
        </div>
      </div>
    </section>

    <!-- ======== 热门商品推荐 ======== -->
    <section class="py-14 bg-gray-50">
      <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">🔥 Best Sellers</h2>
            <p class="text-gray-500 mt-1 text-sm">Our most popular jerseys right now</p>
          </div>
          <NuxtLink :to="localePath('/products')" class="text-accent hover:underline text-sm font-medium hidden sm:block">
            {{ t('common.view_all') }} →
          </NuxtLink>
        </div>

        <div v-if="featuredProducts.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 md:gap-6">
          <ProductCard v-for="product in featuredProducts" :key="product.id" :product="product" />
        </div>

        <!-- 加载占位 -->
        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="i in 8" :key="i" class="rounded-xl overflow-hidden">
            <div class="aspect-square bg-gray-200 animate-pulse"/>
            <div class="p-3 space-y-2">
              <div class="h-4 bg-gray-200 rounded animate-pulse"/>
              <div class="h-4 bg-gray-200 rounded w-2/3 animate-pulse"/>
            </div>
          </div>
        </div>

        <div class="text-center mt-6 sm:hidden">
          <NuxtLink :to="localePath('/products')" class="text-accent font-medium text-sm">{{ t('common.view_all') }} →</NuxtLink>
        </div>
      </div>
    </section>

    <!-- ======== 新品上架区 ======== -->
    <section class="py-14">
      <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
          <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900">✨ New Arrivals</h2>
            <p class="text-gray-500 mt-1 text-sm">Fresh from the latest seasons</p>
          </div>
          <NuxtLink
            :to="localePath({ path: '/products', query: { sort: 'latest' } })"
            class="text-accent hover:underline text-sm font-medium hidden sm:block"
          >
            {{ t('common.view_all') }} →
          </NuxtLink>
        </div>

        <div v-if="latestProducts.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 md:gap-6">
          <ProductCard v-for="product in latestProducts" :key="product.id" :product="product" />
        </div>

        <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div v-for="i in 8" :key="i" class="rounded-xl overflow-hidden">
            <div class="aspect-square bg-gray-200 animate-pulse"/>
            <div class="p-3 space-y-2">
              <div class="h-4 bg-gray-200 rounded animate-pulse"/>
              <div class="h-4 bg-gray-200 rounded w-2/3 animate-pulse"/>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ======== 品牌宣传 Banner ======== -->
    <section class="py-16 bg-primary text-white">
      <div class="container mx-auto px-4 text-center">
        <h2 class="text-2xl md:text-3xl font-bold mb-4">Ready to Find Your Jersey?</h2>
        <p class="text-white/70 mb-8 max-w-xl mx-auto">Join thousands of fans worldwide who trust JerseyHolic for premium sports apparel.</p>
        <NuxtLink
          :to="localePath('/products')"
          class="bg-accent hover:bg-accent-light text-white font-bold px-10 py-4 rounded-full transition text-lg shadow-lg inline-block"
        >
          Shop Now
        </NuxtLink>
      </div>
    </section>
  </div>
</template>
