<script setup lang="ts">
const { locale, locales } = useI18n()
const switchLocalePath = useSwitchLocalePath()

const isOpen = ref(false)

const currentLocale = computed(() => {
  return locales.value.find((l: any) => l.code === locale.value)
})

function close() {
  isOpen.value = false
}
</script>

<template>
  <div class="relative" v-click-outside="close">
    <button @click="isOpen = !isOpen" class="flex items-center gap-1 text-sm hover:text-accent-light">
       (currentLocale as any)?.name || 'EN' 
      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
    </button>

    <div v-show="isOpen" class="absolute end-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 max-h-64 overflow-y-auto">
      <NuxtLink
        v-for="loc in locales"
        :key="(loc as any).code"
        :to="switchLocalePath((loc as any).code)"
        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
        :class="{ 'font-bold text-primary': (loc as any).code === locale }"
        @click="close"
      >
         (loc as any).name 
      </NuxtLink>
    </div>
  </div>
</template>
