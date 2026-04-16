<script setup lang="ts">
import type { Product } from '~/types/product'

const props = defineProps<{
  product: Product
}>()

const { t } = useI18n()
const localePath = useLocalePath()
const { addToCart } = useCart()

const displayPrice = computed(() =>
  props.product.skus?.[0]?.price ?? props.product.price
)

const displayOriginalPrice = computed(() =>
  props.product.skus?.[0]?.original_price ?? props.product.original_price
)

const hasDiscount = computed(() =>
  displayOriginalPrice.value && displayOriginalPrice.value > displayPrice.value
)

const discountPercent = computed(() => {
  if (!hasDiscount.value || !displayOriginalPrice.value) return 0
  return Math.round((1 - displayPrice.value / displayOriginalPrice.value) * 100)
})

const thumbnail = computed(() =>
  props.product.images?.[0]?.url ?? props.product.thumbnail ?? '/placeholder.jpg'
)

const isAdding = ref(false)

async function handleAddToCart() {
  isAdding.value = true
  addToCart(props.product, 1)
  setTimeout(() => { isAdding.value = false }, 800)
}
</script>

<template>
  <div class="group bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow overflow-hidden border border-gray-100">
    <!-- 图片区 -->
    <NuxtLink :to="localePath(`/products/${product.id}`)">
      <div class="relative overflow-hidden aspect-square bg-gray-100">
        <img
          :src="thumbnail"
          :alt="product.name"
          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          loading="lazy"
        />
        <!-- 折扣 badge -->
        <span
          v-if="hasDiscount"
          class="absolute top-2 start-2 bg-accent text-white text-xs font-bold px-2 py-1 rounded-full"
        >
          -{{ discountPercent }}%
        </span>
        <!-- 缺货遮罩 -->
        <div
          v-if="product.stock === 0"
          class="absolute inset-0 bg-black/40 flex items-center justify-center"
        >
          <span class="text-white font-semibold text-sm bg-black/60 px-3 py-1 rounded-full">
            {{ t('common.out_of_stock') }}
          </span>
        </div>
      </div>
    </NuxtLink>

    <!-- 信息区 -->
    <div class="p-3">
      <NuxtLink :to="localePath(`/products/${product.id}`)">
        <h3 class="text-sm font-medium text-gray-800 line-clamp-2 hover:text-accent transition leading-snug min-h-[2.5rem]">
          {{ product.name }}
        </h3>
      </NuxtLink>

      <!-- 价格 -->
      <div class="flex items-baseline gap-2 mt-2">
        <span class="text-base font-bold text-accent">${{ displayPrice.toFixed(2) }}</span>
        <span v-if="hasDiscount" class="text-xs text-gray-400 line-through">
          ${{ displayOriginalPrice!.toFixed(2) }}
        </span>
      </div>

      <!-- 加购按钮 -->
      <button
        @click="handleAddToCart"
        :disabled="product.stock === 0 || isAdding"
        class="mt-3 w-full py-2 rounded-lg text-sm font-medium transition
          bg-primary text-white hover:bg-primary-dark
          disabled:opacity-50 disabled:cursor-not-allowed
          active:scale-95"
        :class="{ 'bg-green-600 hover:bg-green-700': isAdding }"
      >
        <span v-if="isAdding">✓ Added!</span>
        <span v-else-if="product.stock === 0">{{ t('common.out_of_stock') }}</span>
        <span v-else>{{ t('common.add_to_cart') }}</span>
      </button>
    </div>
  </div>
</template>
