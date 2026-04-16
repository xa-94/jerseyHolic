<script setup lang="ts">
import type { ProductImage } from '~/types/product'

const props = defineProps<{
  images: ProductImage[]
  productName?: string
}>()

const activeIndex = ref(0)

const activeImage = computed(() => props.images[activeIndex.value] ?? props.images[0])

function prev() {
  activeIndex.value = activeIndex.value === 0
    ? props.images.length - 1
    : activeIndex.value - 1
}

function next() {
  activeIndex.value = activeIndex.value === props.images.length - 1
    ? 0
    : activeIndex.value + 1
}

// 键盘支持
function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowLeft') prev()
  if (e.key === 'ArrowRight') next()
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <!-- 主图展示区 -->
    <div class="relative bg-gray-100 rounded-xl overflow-hidden aspect-square">
      <Transition name="fade" mode="out-in">
        <img
          :key="activeImage?.url"
          :src="activeImage?.url ?? '/placeholder.jpg'"
          :alt="activeImage?.alt ?? productName"
          class="w-full h-full object-cover"
        />
      </Transition>

      <!-- 左右切换箭头（多图时显示） -->
      <template v-if="images.length > 1">
        <button
          @click="prev"
          class="absolute start-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full p-2 transition"
          aria-label="Previous image"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <button
          @click="next"
          class="absolute end-2 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full p-2 transition"
          aria-label="Next image"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <!-- 小圆点指示器（移动端） -->
        <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5">
          <button
            v-for="(_, i) in images"
            :key="i"
            @click="activeIndex = i"
            class="w-2 h-2 rounded-full transition"
            :class="i === activeIndex ? 'bg-white' : 'bg-white/40'"
          />
        </div>
      </template>
    </div>

    <!-- 缩略图列表 -->
    <div v-if="images.length > 1" class="flex gap-2 overflow-x-auto pb-1">
      <button
        v-for="(img, i) in images"
        :key="img.id ?? i"
        @click="activeIndex = i"
        class="shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition"
        :class="i === activeIndex
          ? 'border-accent'
          : 'border-transparent hover:border-gray-300'"
      >
        <img
          :src="img.url"
          :alt="img.alt ?? productName"
          class="w-full h-full object-cover"
          loading="lazy"
        />
      </button>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
