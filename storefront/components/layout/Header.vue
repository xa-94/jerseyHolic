<script setup lang="ts">
import type { Category } from '~/types/product'

const { t } = useI18n()
const localePath = useLocalePath()
const { cartCount } = useCart()

// 从 API 获取分类
const { data: categories } = await useAsyncData('header-categories', async () => {
  try {
    const { apiFetch } = useApi()
    return await apiFetch<Category[]>('/categories')
  } catch {
    return [] as Category[]
  }
})

const topCategories = computed(() =>
  (categories.value ?? []).filter(c => !c.parent_id).slice(0, 6)
)

// 搜索
const searchQuery = ref('')
const router = useRouter()

function handleSearch() {
  if (searchQuery.value.trim()) {
    router.push(localePath({ path: '/products', query: { keyword: searchQuery.value.trim() } }))
    searchQuery.value = ''
    isMobileOpen.value = false
  }
}

// 移动端菜单
const isMobileOpen = ref(false)
</script>

<template>
  <header class="bg-primary text-white sticky top-0 z-40 shadow-lg">
    <div class="container mx-auto px-4">
      <!-- 主导航栏 -->
      <nav class="flex items-center justify-between h-16">
        <!-- Logo -->
        <NuxtLink :to="localePath('/')" class="text-xl font-bold tracking-wide shrink-0">
          Jersey<span class="text-accent">Holic</span>
        </NuxtLink>

        <!-- 桌面端：分类导航 -->
        <div class="hidden lg:flex items-center gap-1 mx-4 flex-1 justify-center">
          <NuxtLink
            :to="localePath('/products')"
            class="px-3 py-2 text-sm rounded hover:bg-white/10 transition whitespace-nowrap"
          >
            {{ t('nav.products') }}
          </NuxtLink>
          <NuxtLink
            v-for="cat in topCategories"
            :key="cat.id"
            :to="localePath(`/categories/${cat.id}`)"
            class="px-3 py-2 text-sm rounded hover:bg-white/10 transition whitespace-nowrap"
          >
            {{ cat.name }}
          </NuxtLink>
        </div>

        <!-- 右侧工具栏 -->
        <div class="flex items-center gap-3 shrink-0">
          <!-- 搜索框（桌面端） -->
          <form @submit.prevent="handleSearch" class="hidden md:flex items-center bg-white/10 rounded-full px-3 py-1">
            <input
              v-model="searchQuery"
              type="text"
              :placeholder="t('common.search_placeholder')"
              class="bg-transparent text-sm outline-none placeholder-white/60 w-36 lg:w-48"
            />
            <button type="submit" class="text-white/70 hover:text-white ml-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
              </svg>
            </button>
          </form>

          <!-- 货币切换 -->
          <CurrencySwitcher />

          <!-- 语言切换 -->
          <LanguageSwitcher />

          <!-- 购物车 -->
          <NuxtLink :to="localePath('/cart')" class="relative hover:text-accent transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <span
              v-if="cartCount > 0"
              class="absolute -top-2 -end-2 bg-accent text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"
            >
              {{ cartCount > 99 ? '99+' : cartCount }}
            </span>
          </NuxtLink>

          <!-- 账户 -->
          <NuxtLink :to="localePath('/account')" class="hidden md:block hover:text-accent transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </NuxtLink>

          <!-- 汉堡菜单按钮（移动端） -->
          <button
            class="lg:hidden p-1 hover:text-accent transition"
            @click="isMobileOpen = !isMobileOpen"
            :aria-label="isMobileOpen ? t('common.close') : 'Menu'"
          >
            <svg v-if="!isMobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      </nav>

      <!-- 移动端搜索框 -->
      <div class="lg:hidden pb-3">
        <form @submit.prevent="handleSearch" class="flex items-center bg-white/10 rounded-full px-4 py-2">
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('common.search_placeholder')"
            class="bg-transparent text-sm outline-none placeholder-white/60 flex-1"
          />
          <button type="submit" class="text-white/70 hover:text-white">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
          </button>
        </form>
      </div>
    </div>

    <!-- 移动端侧边抽屉导航 -->
    <Transition name="slide">
      <div v-if="isMobileOpen" class="lg:hidden bg-primary-dark border-t border-white/10">
        <nav class="container mx-auto px-4 py-3 flex flex-col gap-1">
          <NuxtLink
            :to="localePath('/')"
            class="block px-3 py-2 rounded hover:bg-white/10 transition text-sm"
            @click="isMobileOpen = false"
          >
            {{ t('nav.home') }}
          </NuxtLink>
          <NuxtLink
            :to="localePath('/products')"
            class="block px-3 py-2 rounded hover:bg-white/10 transition text-sm"
            @click="isMobileOpen = false"
          >
            {{ t('nav.products') }}
          </NuxtLink>
          <NuxtLink
            v-for="cat in topCategories"
            :key="cat.id"
            :to="localePath(`/categories/${cat.id}`)"
            class="block px-3 py-2 rounded hover:bg-white/10 transition text-sm"
            @click="isMobileOpen = false"
          >
            {{ cat.name }}
          </NuxtLink>
          <hr class="border-white/10 my-2"/>
          <NuxtLink
            :to="localePath('/account')"
            class="block px-3 py-2 rounded hover:bg-white/10 transition text-sm"
            @click="isMobileOpen = false"
          >
            {{ t('nav.account') }}
          </NuxtLink>
        </nav>
      </div>
    </Transition>
  </header>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
  transition: all 0.2s ease;
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
