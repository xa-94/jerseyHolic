<script setup lang="ts">
/**
 * CurrencySwitcher — 货币切换器组件
 *
 * - 显示当前货币符号 + 代码（如 "$ USD"）
 * - 下拉选项来自 tenantStore.currencies
 * - 切换后通过 useCurrency().setCurrency 更新 cookie + ref
 * - TailwindCSS 样式，支持 RTL
 */

const { currentCurrency, availableCurrencies, getCurrencyLabel, setCurrency } = useCurrency()

// 下拉开关
const isOpen = ref(false)

// 当站点只有一种货币时，不显示切换器
const hasMultipleCurrencies = computed(() => availableCurrencies.value.length > 1)

function handleSelect(code: string) {
  setCurrency(code)
  isOpen.value = false
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
  <!-- 只有多货币时才渲染 -->
  <div
    v-if="hasMultipleCurrencies"
    ref="dropdownRef"
    class="relative"
  >
    <!-- 触发按钮 -->
    <button
      type="button"
      class="flex items-center gap-1 px-2 py-1 rounded text-sm text-white/80 hover:text-white hover:bg-white/10 transition-colors focus:outline-none focus:ring-1 focus:ring-white/30"
      :aria-label="`Current currency: ${currentCurrency}`"
      :aria-expanded="isOpen"
      @click.stop="isOpen = !isOpen"
    >
      <span class="font-medium">{{ getCurrencyLabel() }}</span>
      <!-- Chevron down -->
      <svg
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
        :aria-label="`Select currency`"
        class="dropdown-menu absolute top-full mt-1 z-50 min-w-[8rem] rounded-md shadow-lg bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 py-1 text-sm"
        :class="$attrs.class"
      >
        <li
          v-for="code in availableCurrencies"
          :key="code"
          role="option"
          :aria-selected="code === currentCurrency"
          class="cursor-pointer px-3 py-2 flex items-center gap-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
          :class="{
            'text-primary font-semibold bg-gray-50 dark:bg-gray-700': code === currentCurrency,
            'text-gray-700 dark:text-gray-300': code !== currentCurrency
          }"
          @click="handleSelect(code)"
        >
          <!-- 当前选中勾选图标 -->
          <svg
            v-if="code === currentCurrency"
            class="w-3.5 h-3.5 shrink-0 text-primary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          <span v-else class="w-3.5 shrink-0" aria-hidden="true" />
          <span>{{ getCurrencyLabel(code) }}</span>
        </li>
      </ul>
    </Transition>
  </div>

  <!-- 单货币：仅展示，不可点击 -->
  <span
    v-else-if="availableCurrencies.length === 1"
    class="text-sm text-white/70 px-1"
  >
    {{ getCurrencyLabel() }}
  </span>
</template>
