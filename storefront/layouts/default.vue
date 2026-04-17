<script setup lang="ts">
const { locale, locales } = useI18n()
const { isRtl, storeName, getLogoUrl, theme } = useStore()

// 语言方向：优先使用站点配置的 isRtl，其次根据 locale 语言包属性判断
const currentDir = computed(() => {
  if (isRtl.value) return 'rtl'
  const current = (locales.value as any[]).find((l) => l.code === locale.value)
  return current?.dir || 'ltr'
})

// 应用主题色 CSS 变量
const primaryColor = computed(() => theme.value.primary_color || '#1a73e8')

// 内联样式：将 CSS 变量设置到 :root
watchEffect(() => {
  if (import.meta.client) {
    document.documentElement.style.setProperty('--primary-color', primaryColor.value)
  }
})

// Logo URL
const logoUrl = computed(() => getLogoUrl())

// 动态 head （站点名称为页面标题前缀）
useHead({
  htmlAttrs: {
    dir: currentDir,
    lang: locale,
  },
})
</script>

<template>
  <div
    :dir="currentDir"
    :lang="locale"
    class="min-h-screen flex flex-col"
    :class="{ 'rtl': isRtl }"
  >
    <!-- 将 logoUrl 和 storeName 通过 provide 共享给子组件 -->
    <LayoutHeader :logo-url="logoUrl" :store-name="storeName" />
    <main class="flex-grow">
      <slot />
    </main>
    <LayoutFooter />
  </div>
</template>
