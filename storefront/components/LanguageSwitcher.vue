<script setup lang="ts">
/**
 * LanguageSwitcher — 语言切换器组件
 *
 * - 下拉显示当前语言原生名称（如 "Deutsch"、"العربية"）
 * - 选项列表来自 useLocale().availableLocales（站点启用的语言）
 * - 切换时调用 switchLocale（@nuxtjs/i18n setLocale + 路由重定向）
 * - TailwindCSS 样式，支持 RTL
 */

const { locale, availableLocales, currentLocaleName, switchLocale, isRtl } = useLocale()

// 是否正在切换（防止重复点击）
const isSwitching = ref(false)

// 下拉开关
const isOpen = ref(false)

// 当站点只有一种语言时不显示切换器
const hasMultipleLocales = computed(() => availableLocales.value.length > 1)

async function handleSelect(code: string) {
  if (isSwitching.value || code === locale.value) {
    isOpen.value = false
    return
  }
  isSwitching.value = true
  isOpen.value = false
  try {
    await switchLocale(code)
  }
  finally {
    isSwitching.value = false
  }
}

// 获取 locale 对象的 name 属性
function getLocaleName(l: unknown): string {
  if (typeof l === 'string') return l
  const obj = l as { code: string; name?: string }
  return obj.name || obj.code
}
function getLocaleCode(l: unknown): string {
  if (typeof l === 'string') return l
  return (l as { code: string }).code
}

// 点击外部关闭下拉
const dropdownRef = ref<HTMLElement | null>(null)
onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
})
onUnmounted(() => {
  document.removeEventListener('click', handleOutsideClick)
})
function handleOutsideClick(e: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}
</script>

<template>
  <div
    v-if="hasMultipleLocales"
    ref="dropdownRef"
    class="relative"
  >
    <!-- 触发按钮 -->
    <button
      type="button"
      class="flex items-center gap-1 px-2 py-1 rounded text-sm text-white/80 hover:text-white hover:bg-white/10 transition-colors focus:outline-none focus:ring-1 focus:ring-white/30"
      :aria-label="`Current language: ${currentLocaleName}`"
      :aria-expanded="isOpen"
      :disabled="isSwitching"
      @click.stop="isOpen = !isOpen"
    >
      <!-- 地球仪图标 -->
      <svg
        class="w-4 h-4 shrink-0"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
        />
      </svg>
      <span class="font-medium hidden sm:inline max-w-[6rem] truncate">{{ currentLocaleName }}</span>
      <!-- 加载旋转 / chevron -->
      <svg
        v-if="isSwitching"
        class="w-3 h-3 animate-spin"
        fill="none"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
      <svg
        v-else
        class="w-3 h-3 transition-transform duration-200"
        :class="{ 'rotate-180': isOpen }"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
      </svg>
    </button>

    <!-- 下拉面板 -->
    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-1"
    >
      <ul
        v-if="isOpen"
        role="listbox"
        aria-label="Select language"
        class="dropdown-menu absolute top-full mt-1 z-50 min-w-[10rem] max-h-72 overflow-y-auto rounded-md shadow-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 py-1 text-sm"
      >
        <li
          v-for="loc in availableLocales"
          :key="getLocaleCode(loc)"
          role="option"
          :aria-selected="getLocaleCode(loc) === locale"
          class="cursor-pointer px-3 py-2 flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          :class="{
            'text-primary font-semibold bg-gray-50 dark:bg-gray-700': getLocaleCode(loc) === locale,
            'text-gray-700 dark:text-gray-300': getLocaleCode(loc) !== locale
          }"
          @click="handleSelect(getLocaleCode(loc))"
        >
          <!-- 当前选中勾选 -->
          <svg
            v-if="getLocaleCode(loc) === locale"
            class="w-3.5 h-3.5 shrink-0 text-primary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          <span v-else class="w-3.5 shrink-0" aria-hidden="true" />
          <!-- 原生语言名称 -->
          <span
            :lang="getLocaleCode(loc)"
            :dir="(loc as any).dir || 'ltr'"
          >
            {{ getLocaleName(loc) }}
          </span>
          <!-- 小语言代码标识 -->
          <span class="ms-auto text-xs text-gray-400 font-mono uppercase">
            {{ getLocaleCode(loc) }}
          </span>
        </li>
      </ul>
    </Transition>
  </div>
</template>
