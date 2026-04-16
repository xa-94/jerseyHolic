<script setup lang="ts">
const props = defineProps<{
  total: number
  page: number
  perPage: number
}>()

const emit = defineEmits<{
  (e: 'change', page: number): void
}>()

const totalPages = computed(() => Math.ceil(props.total / props.perPage))

const pages = computed(() => {
  const p = props.page
  const t = totalPages.value
  if (t <= 7) return Array.from({ length: t }, (_, i) => i + 1)

  const arr: (number | '...')[] = [1]
  if (p > 3) arr.push('...')

  for (let i = Math.max(2, p - 1); i <= Math.min(t - 1, p + 1); i++) {
    arr.push(i)
  }

  if (p < t - 2) arr.push('...')
  arr.push(t)
  return arr
})

function goTo(p: number) {
  if (p < 1 || p > totalPages.value || p === props.page) return
  emit('change', p)
}
</script>

<template>
  <div v-if="totalPages > 1" class="flex items-center justify-center gap-1 mt-8">
    <!-- 上一页 -->
    <button
      @click="goTo(page - 1)"
      :disabled="page === 1"
      class="w-9 h-9 rounded-lg flex items-center justify-center border border-gray-200 text-gray-600
        hover:border-accent hover:text-accent transition
        disabled:opacity-40 disabled:cursor-not-allowed"
      aria-label="Previous page"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
      </svg>
    </button>

    <!-- 页码 -->
    <template v-for="p in pages" :key="p">
      <span v-if="p === '...'" class="w-9 h-9 flex items-center justify-center text-gray-400 text-sm">
        &hellip;
      </span>
      <button
        v-else
        @click="goTo(p as number)"
        class="w-9 h-9 rounded-lg flex items-center justify-center text-sm border transition"
        :class="p === page
          ? 'bg-primary text-white border-primary font-bold'
          : 'border-gray-200 text-gray-600 hover:border-accent hover:text-accent'"
      >
        {{ p }}
      </button>
    </template>

    <!-- 下一页 -->
    <button
      @click="goTo(page + 1)"
      :disabled="page === totalPages"
      class="w-9 h-9 rounded-lg flex items-center justify-center border border-gray-200 text-gray-600
        hover:border-accent hover:text-accent transition
        disabled:opacity-40 disabled:cursor-not-allowed"
      aria-label="Next page"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
    </button>
  </div>
</template>
