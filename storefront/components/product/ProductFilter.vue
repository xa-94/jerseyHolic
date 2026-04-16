<script setup lang="ts">
import type { Category } from '~/types/product'

const props = defineProps<{
  categories: Category[]
  modelValue: {
    categoryId?: number
    priceMin?: number
    priceMax?: number
    sort?: string
  }
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: typeof props.modelValue): void
}>()

const { t } = useI18n()

const local = reactive({ ...props.modelValue })

watch(() => props.modelValue, (v) => Object.assign(local, v))

function apply() {
  emit('update:modelValue', { ...local })
}

function resetFilters() {
  local.categoryId = undefined
  local.priceMin = undefined
  local.priceMax = undefined
  emit('update:modelValue', {})
}

const sortOptions = [
  { value: 'latest', label: t('sort.latest') },
  { value: 'price_asc', label: t('sort.price_asc') },
  { value: 'price_desc', label: t('sort.price_desc') },
  { value: 'best_seller', label: t('sort.best_seller') },
]
</script>

<template>
  <aside class="space-y-6">
    <!-- 排序（移动端也显示） -->
    <div>
      <h3 class="font-semibold text-gray-800 mb-3">{{ t('sort.title') }}</h3>
      <div class="space-y-1">
        <label
          v-for="opt in sortOptions"
          :key="opt.value"
          class="flex items-center gap-2 cursor-pointer"
        >
          <input
            type="radio"
            :value="opt.value"
            v-model="local.sort"
            @change="apply"
            class="accent-accent"
          />
          <span class="text-sm text-gray-700">{{ opt.label }}</span>
        </label>
      </div>
    </div>

    <!-- 分类筛选 -->
    <div v-if="categories.length > 0">
      <h3 class="font-semibold text-gray-800 mb-3">{{ t('nav.categories') ?? 'Categories' }}</h3>
      <ul class="space-y-1">
        <li>
          <button
            @click="local.categoryId = undefined; apply()"
            class="text-sm w-full text-start px-2 py-1 rounded transition"
            :class="!local.categoryId ? 'bg-primary/10 text-primary font-medium' : 'text-gray-700 hover:bg-gray-100'"
          >
            All
          </button>
        </li>
        <li v-for="cat in categories" :key="cat.id">
          <button
            @click="local.categoryId = cat.id; apply()"
            class="text-sm w-full text-start px-2 py-1 rounded transition"
            :class="local.categoryId === cat.id ? 'bg-primary/10 text-primary font-medium' : 'text-gray-700 hover:bg-gray-100'"
          >
            {{ cat.name }}
            <span v-if="cat.product_count" class="text-gray-400 text-xs ms-1">({{ cat.product_count }})</span>
          </button>
        </li>
      </ul>
    </div>

    <!-- 价格区间 -->
    <div>
      <h3 class="font-semibold text-gray-800 mb-3">{{ t('filter.price_range') ?? 'Price Range' }}</h3>
      <div class="flex gap-2 items-center">
        <input
          type="number"
          v-model.number="local.priceMin"
          :placeholder="t('filter.min') ?? 'Min'"
          min="0"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-accent"
          @change="apply"
        />
        <span class="text-gray-400">—</span>
        <input
          type="number"
          v-model.number="local.priceMax"
          :placeholder="t('filter.max') ?? 'Max'"
          min="0"
          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-accent"
          @change="apply"
        />
      </div>
    </div>

    <!-- 重置按钮 -->
    <button
      @click="resetFilters"
      class="w-full text-sm text-gray-500 border border-gray-200 rounded-lg py-2 hover:border-accent hover:text-accent transition"
    >
      {{ t('filter.reset') ?? 'Reset Filters' }}
    </button>
  </aside>
</template>
