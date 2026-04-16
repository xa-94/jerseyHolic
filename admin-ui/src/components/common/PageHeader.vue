<script setup lang="ts">
interface Action {
  label: string
  type?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'default'
  icon?: string
  onClick: () => void
  permission?: string
}

interface Props {
  title: string
  subtitle?: string
  actions?: Action[]
}

defineProps<Props>()
</script>

<template>
  <div class="page-header">
    <div class="page-header__left">
      <h2 class="page-header__title">{{ title }}</h2>
      <p v-if="subtitle" class="page-header__subtitle">{{ subtitle }}</p>
    </div>
    <div v-if="actions?.length" class="page-header__actions">
      <el-button
        v-for="(action, i) in actions"
        :key="i"
        :type="action.type || 'default'"
        @click="action.onClick"
      >
        <el-icon v-if="action.icon"><component :is="action.icon" /></el-icon>
        {{ action.label }}
      </el-button>
    </div>
  </div>
</template>

<style scoped lang="scss">
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;

  &__left {
    flex: 1;
  }

  &__title {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0;
    line-height: 1.4;
  }

  &__subtitle {
    font-size: 13px;
    color: #909399;
    margin: 4px 0 0;
  }

  &__actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
  }
}
</style>
