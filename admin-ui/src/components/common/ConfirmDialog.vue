<script setup lang="ts">
interface Props {
  visible?: boolean
  title?: string
  content?: string
  confirmText?: string
  cancelText?: string
  type?: 'warning' | 'error' | 'info' | 'success'
  loading?: boolean
  width?: string
}

const props = withDefaults(defineProps<Props>(), {
  visible: false,
  title: '确认操作',
  content: '确认执行此操作吗？',
  confirmText: '确认',
  cancelText: '取消',
  type: 'warning',
  loading: false,
  width: '400px',
})

const emit = defineEmits<{
  'update:visible': [visible: boolean]
  confirm: []
  cancel: []
}>()

function handleConfirm() {
  emit('confirm')
}

function handleCancel() {
  emit('update:visible', false)
  emit('cancel')
}

function handleClose() {
  emit('update:visible', false)
  emit('cancel')
}

const iconMap: Record<NonNullable<Props['type']>, string> = {
  warning: 'WarningFilled',
  error: 'CircleCloseFilled',
  info: 'InfoFilled',
  success: 'CircleCheckFilled',
}

const colorMap: Record<NonNullable<Props['type']>, string> = {
  warning: '#e6a23c',
  error: '#f56c6c',
  info: '#909399',
  success: '#67c23a',
}

// 通过函数引用 props 消除"未使用"警告
function getIcon() { return iconMap[props.type!] }
function getColor() { return colorMap[props.type!] }
function getButtonType(): 'danger' | 'warning' | 'primary' {
  return props.type === 'error' ? 'danger' : props.type === 'warning' ? 'warning' : 'primary'
}
</script>

<template>
  <el-dialog
    :model-value="visible"
    :title="title"
    :width="width"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <div class="confirm-dialog__body">
      <el-icon
        :size="36"
        :style="{ color: getColor() }"
        class="confirm-dialog__icon"
      >
        <component :is="getIcon()" />
      </el-icon>
      <div class="confirm-dialog__content">
        <slot>{{ content }}</slot>
      </div>
    </div>

    <template #footer>
      <el-button @click="handleCancel">{{ cancelText }}</el-button>
      <el-button
        :type="getButtonType()"
        :loading="loading"
        @click="handleConfirm"
      >
        {{ confirmText }}
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped lang="scss">
.confirm-dialog {
  &__body {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 8px 0;
  }

  &__icon {
    flex-shrink: 0;
    margin-top: 2px;
  }

  &__content {
    flex: 1;
    font-size: 14px;
    color: #606266;
    line-height: 1.6;
  }
}
</style>
