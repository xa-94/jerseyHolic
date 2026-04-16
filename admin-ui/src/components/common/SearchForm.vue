<script setup lang="ts">
interface Props {
  /** 是否显示折叠按钮 */
  collapsible?: boolean
  /** 初始折叠状态 */
  collapsed?: boolean
  /** 提交按钮文本 */
  submitText?: string
  /** 重置按钮文本 */
  resetText?: string
  /** 是否显示操作按钮 */
  showActions?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  collapsible: false,
  collapsed: false,
  submitText: '搜索',
  resetText: '重置',
  showActions: true,
  loading: false,
})

const emit = defineEmits<{
  search: []
  reset: []
}>()

import { ref } from 'vue'

const isCollapsed = ref(props.collapsed)

function handleSearch() {
  emit('search')
}

function handleReset() {
  emit('reset')
}

function toggleCollapse() {
  isCollapsed.value = !isCollapsed.value
}
</script>

<template>
  <el-card class="search-form" shadow="never">
    <el-form
      inline
      :class="['search-form__inner', { 'is-collapsed': isCollapsed }]"
      @submit.prevent="handleSearch"
    >
      <!-- 自定义搜索字段 slot -->
      <slot />

      <!-- 操作按钮 -->
      <el-form-item v-if="showActions" class="search-form__actions">
        <el-button type="primary" :loading="loading" @click="handleSearch">
          <el-icon><Search /></el-icon>
          {{ submitText }}
        </el-button>
        <el-button @click="handleReset">
          <el-icon><Refresh /></el-icon>
          {{ resetText }}
        </el-button>
        <el-button
          v-if="collapsible"
          type="text"
          @click="toggleCollapse"
        >
          {{ isCollapsed ? '展开' : '收起' }}
          <el-icon>
            <ArrowDown v-if="isCollapsed" />
            <ArrowUp v-else />
          </el-icon>
        </el-button>
      </el-form-item>
    </el-form>
  </el-card>
</template>

<style scoped lang="scss">
.search-form {
  margin-bottom: 16px;

  :deep(.el-card__body) {
    padding: 16px 20px 0;
  }

  &__inner {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
  }

  &__actions {
    margin-left: auto;
  }
}
</style>
