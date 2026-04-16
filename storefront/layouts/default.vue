<script setup lang="ts">
const { locale, locales } = useI18n()

const currentDir = computed(() => {
  const current = (locales.value as any[]).find((l) => l.code === locale.value)
  return current?.dir || 'ltr'
})
</script>

<template>
  <div :dir="currentDir" :lang="locale" class="min-h-screen flex flex-col">
    <LayoutHeader />
    <main class="flex-grow">
      <slot />
    </main>
    <LayoutFooter />
  </div>
</template>
