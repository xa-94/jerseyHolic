<script setup lang="ts">
import type { Product, ProductSku } from '~/types/product'

const { t } = useI18n()
const localePath = useLocalePath()
const { locale, locales } = useI18n()
const route = useRoute()
const { addToCart } = useCart()
const { apiFetch } = useApi()

const productId = computed(() => route.params.id as string)

// SSR 获取商品详情
const { data: product, error } = await useAsyncData(
  `product-detail-${productId.value}`,
  () => apiFetch<Product>(`/products/${productId.value}`)
)

// 404 处理
if (error.value || !product.value) {
  throw createError({ statusCode: 404, message: 'Product not found' })
}

// 多语言描述
const localizedDescription = computed(() => {
  if (!product.value?.descriptions) return product.value?.description ?? ''
  const desc = product.value.descriptions.find(d => d.locale === locale.value)
  return desc?.description ?? product.value.description ?? ''
})

const localizedName = computed(() => {
  if (!product.value?.descriptions) return product.value?.name ?? ''
  const desc = product.value.descriptions.find(d => d.locale === locale.value)
  return desc?.name ?? product.value.name ?? ''
})

// 图片列表
const images = computed(() => product.value?.images ?? [])

// SKU 选择
const selectedAttributes = ref<Record<string, string>>({})
const quantity = ref(1)

// 获取所有可用属性维度
const attributeKeys = computed(() => {
  const keys = new Set<string>()
  product.value?.skus?.forEach(sku => {
    Object.keys(sku.attributes).forEach(k => keys.add(k))
  })
  return Array.from(keys)
})

// 每个属性的可选值
function getAttributeValues(key: string): string[] {
  const values = new Set<string>()
  product.value?.skus?.forEach(sku => {
    if (sku.attributes[key]) values.add(sku.attributes[key])
  })
  return Array.from(values)
}

// 根据选中属性匹配 SKU
const matchedSku = computed((): ProductSku | undefined => {
  if (!product.value?.skus?.length) return undefined
  return product.value.skus.find(sku =>
    Object.entries(selectedAttributes.value).every(
      ([k, v]) => sku.attributes[k] === v
    )
  )
})

const displayPrice = computed(() =>
  matchedSku.value?.price ?? product.value?.skus?.[0]?.price ?? product.value?.price ?? 0
)
const displayOriginalPrice = computed(() =>
  matchedSku.value?.original_price ?? product.value?.skus?.[0]?.original_price ?? product.value?.original_price
)
const currentStock = computed(() =>
  matchedSku.value?.stock ?? product.value?.stock ?? 0
)
const hasDiscount = computed(() =>
  displayOriginalPrice.value && displayOriginalPrice.value > displayPrice.value
)
const discountPercent = computed(() => {
  if (!hasDiscount.value || !displayOriginalPrice.value) return 0
  return Math.round((1 - displayPrice.value / displayOriginalPrice.value) * 100)
})

// 加购
const isAdding = ref(false)
function handleAddToCart() {
  if (!product.value) return
  isAdding.value = true
  addToCart(product.value, quantity.value, matchedSku.value)
  setTimeout(() => { isAdding.value = false }, 1000)
}

// SEO
const thumbnail = computed(() =>
  product.value?.images?.[0]?.url ?? product.value?.thumbnail ?? ''
)

useHead({
  title: `${localizedName.value} - JerseyHolic`,
  meta: [
    { name: 'description', content: product.value?.short_description ?? localizedDescription.value.slice(0, 160) },
    { property: 'og:title', content: localizedName.value },
    { property: 'og:description', content: product.value?.short_description ?? '' },
    { property: 'og:image', content: thumbnail.value },
    { property: 'og:type', content: 'product' },
  ],
})

// hreflang 标签（每种语言的 alternate link）
const config = useRuntimeConfig()
const baseUrl = config.public.siteUrl ?? 'https://jerseyholic.com'

useSeoMeta({
  ogUrl: `${baseUrl}/products/${productId.value}`,
})

useHead({
  link: (locales.value as any[]).map((loc) => ({
    rel: 'alternate',
    hreflang: loc.code,
    href: loc.code === 'en'
      ? `${baseUrl}/products/${productId.value}`
      : `${baseUrl}/${loc.code}/products/${productId.value}`,
  })),
})

// 相关商品
const relatedProducts = computed(() => product.value?.related_products?.slice(0, 6) ?? [])
</script>

<template>
  <div class="container mx-auto px-4 py-8" v-if="product">
    <!-- 面包屑 -->
    <nav class="text-sm text-gray-500 mb-6">
      <NuxtLink :to="localePath('/')" class="hover:text-accent">Home</NuxtLink>
      <span class="mx-2">/</span>
      <NuxtLink :to="localePath('/products')" class="hover:text-accent">Products</NuxtLink>
      <span v-if="product.category" class="mx-2">/</span>
      <NuxtLink
        v-if="product.category"
        :to="localePath(`/categories/${product.category.id}`)"
        class="hover:text-accent"
      >{{ product.category.name }}</NuxtLink>
      <span class="mx-2">/</span>
      <span class="text-gray-800 line-clamp-1">{{ localizedName }}</span>
    </nav>

    <!-- 商品主内容 -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 xl:gap-14">
      <!-- 左：图片画廊 -->
      <div>
        <ProductGallery
          :images="images"
          :product-name="localizedName"
        />
      </div>

      <!-- 右：商品信息 -->
      <div class="flex flex-col gap-5">
        <!-- 商品名 -->
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-gray-900 leading-tight">{{ localizedName }}</h1>
          <p v-if="product.short_description" class="text-gray-500 mt-2 text-sm">{{ product.short_description }}</p>
        </div>

        <!-- 价格 -->
        <div class="flex items-baseline gap-3">
          <span class="text-3xl font-extrabold text-accent">${{ displayPrice.toFixed(2) }}</span>
          <span v-if="hasDiscount" class="text-lg text-gray-400 line-through">${{ displayOriginalPrice!.toFixed(2) }}</span>
          <span v-if="hasDiscount" class="bg-accent text-white text-sm font-bold px-2 py-0.5 rounded-full">
            -{{ discountPercent }}%
          </span>
        </div>

        <!-- 库存状态 -->
        <div>
          <span v-if="currentStock > 0" class="inline-flex items-center gap-1 text-green-600 text-sm font-medium">
            <span class="w-2 h-2 rounded-full bg-green-500 inline-block"/>
            {{ t('common.in_stock') }} ({{ currentStock }} left)
          </span>
          <span v-else class="inline-flex items-center gap-1 text-red-500 text-sm font-medium">
            <span class="w-2 h-2 rounded-full bg-red-500 inline-block"/>
            {{ t('common.out_of_stock') }}
          </span>
        </div>

        <!-- SKU 属性选择 -->
        <div v-if="attributeKeys.length > 0" class="space-y-4">
          <div v-for="key in attributeKeys" :key="key">
            <p class="text-sm font-semibold text-gray-700 mb-2 capitalize">
              {{ t(`common.${key}`) || key }}:
              <span class="font-normal text-gray-500">{{ selectedAttributes[key] || 'Select' }}</span>
            </p>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="val in getAttributeValues(key)"
                :key="val"
                @click="selectedAttributes[key] = val"
                class="px-3 py-1.5 rounded-lg border text-sm font-medium transition"
                :class="selectedAttributes[key] === val
                  ? 'border-accent bg-accent/10 text-accent'
                  : 'border-gray-200 text-gray-700 hover:border-gray-400'"
              >
                {{ val }}
              </button>
            </div>
          </div>
        </div>

        <!-- 数量选择 -->
        <div>
          <p class="text-sm font-semibold text-gray-700 mb-2">{{ t('common.quantity') }}</p>
          <div class="flex items-center gap-3">
            <button
              @click="quantity = Math.max(1, quantity - 1)"
              class="w-9 h-9 rounded-lg border border-gray-200 flex items-center justify-center hover:border-accent transition text-lg font-bold"
            >−</button>
            <span class="w-10 text-center font-semibold text-lg">{{ quantity }}</span>
            <button
              @click="quantity = Math.min(currentStock, quantity + 1)"
              :disabled="quantity >= currentStock"
              class="w-9 h-9 rounded-lg border border-gray-200 flex items-center justify-center hover:border-accent transition text-lg font-bold disabled:opacity-40"
            >+</button>
          </div>
        </div>

        <!-- 加购按钮 -->
        <div class="flex gap-3">
          <button
            @click="handleAddToCart"
            :disabled="currentStock === 0 || isAdding"
            class="flex-1 py-4 rounded-xl font-bold text-white transition text-base
              bg-primary hover:bg-primary-dark
              disabled:opacity-50 disabled:cursor-not-allowed"
            :class="{ 'bg-green-600 hover:bg-green-700': isAdding }"
          >
            <span v-if="isAdding">✓ Added to Cart!</span>
            <span v-else-if="currentStock === 0">{{ t('common.out_of_stock') }}</span>
            <span v-else>{{ t('common.add_to_cart') }}</span>
          </button>
          <NuxtLink
            :to="localePath('/cart')"
            class="border-2 border-primary text-primary hover:bg-primary hover:text-white px-5 rounded-xl font-semibold transition text-sm flex items-center"
          >
            {{ t('common.buy_now') }}
          </NuxtLink>
        </div>

        <!-- 商品属性信息 -->
        <div v-if="product.attributes && Object.keys(product.attributes).length > 0"
          class="border border-gray-100 rounded-xl p-4 space-y-2">
          <h3 class="font-semibold text-gray-700 text-sm mb-3">Specifications</h3>
          <div
            v-for="(vals, key) in product.attributes"
            :key="key"
            class="flex text-sm"
          >
            <span class="text-gray-500 w-28 shrink-0 capitalize">{{ key }}</span>
            <span class="text-gray-800">{{ Array.isArray(vals) ? vals.join(', ') : vals }}</span>
          </div>
        </div>

        <!-- 免费配送提示 -->
        <div class="flex items-center gap-3 p-3 bg-green-50 rounded-xl text-sm text-green-700">
          <span>🚚</span>
          <span>Free worldwide shipping on all orders</span>
        </div>
      </div>
    </div>

    <!-- 商品描述 -->
    <div class="mt-12 border-t border-gray-100 pt-8">
      <h2 class="text-xl font-bold text-gray-900 mb-5">{{ t('product.description') }}</h2>
      <div class="prose prose-gray max-w-none text-gray-700 leading-relaxed" v-html="localizedDescription"/>
    </div>

    <!-- 相关商品 -->
    <div v-if="relatedProducts.length > 0" class="mt-12 border-t border-gray-100 pt-8">
      <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('product.related') }}</h2>
      <div class="overflow-x-auto pb-4">
        <div class="flex gap-4" style="min-width: max-content">
          <div v-for="p in relatedProducts" :key="p.id" class="w-44 shrink-0">
            <ProductCard :product="p" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
