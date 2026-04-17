<script setup lang="ts">
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import { auditSettlement, type Settlement } from '@/api/merchant'

// ==================== Props & Emits ====================
defineProps<{
  // 当前弹窗绑定的结算单（由父组件传入，通过 open() 设置）
}>()

const emit = defineEmits<{
  (e: 'success'): void
  (e: 'close'): void
}>()

// ==================== 弹窗状态 ====================
const visible = ref(false)
const submitting = ref(false)
const currentSettlement = ref<Settlement | null>(null)

// ==================== 审核表单 ====================
const auditForm = reactive({
  action: '' as 'approve' | 'reject',
  remark: '',
})

// ==================== 打开/关闭 ====================
function open(settlement: Settlement) {
  currentSettlement.value = settlement
  auditForm.action = '' as any
  auditForm.remark = ''
  visible.value = true
}

function handleClose() {
  visible.value = false
  emit('close')
}

defineExpose({ open })

// ==================== 提交审核 ====================
async function handleSubmit() {
  if (!auditForm.action) {
    ElMessage.warning('请选择审核结果')
    return
  }
  if (auditForm.action === 'reject' && !auditForm.remark.trim()) {
    ElMessage.warning('拒绝时请填写备注原因')
    return
  }
  submitting.value = true
  try {
    await auditSettlement(currentSettlement.value!.id, {
      action: auditForm.action,
      remark: auditForm.remark || undefined,
    })
    ElMessage.success(auditForm.action === 'approve' ? '结算审核通过' : '已拒绝该结算单')
    emit('success')
    handleClose()
  } catch {
    // 由拦截器处理
  } finally {
    submitting.value = false
  }
}

// ==================== 工具函数 ====================
function formatAmount(amount: number): string {
  return amount.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<template>
  <el-dialog
    v-model="visible"
    title="结算审核"
    width="520px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <template v-if="currentSettlement">
      <!-- 结算单详情 -->
      <el-descriptions :column="2" border size="small">
        <el-descriptions-item label="结算周期" :span="2">
          {{ currentSettlement.period_start }} ~ {{ currentSettlement.period_end }}
        </el-descriptions-item>
        <el-descriptions-item label="应结总额">
          <span class="amount-text">¥{{ formatAmount(currentSettlement.total_amount) }}</span>
        </el-descriptions-item>
        <el-descriptions-item label="平台佣金">
          <span class="commission-text">
            ¥{{ formatAmount(currentSettlement.commission) }}
            <span class="rate-text">（{{ currentSettlement.commission_rate }}%）</span>
          </span>
        </el-descriptions-item>
        <el-descriptions-item label="实际净额" :span="2">
          <span class="net-amount-text">¥{{ formatAmount(currentSettlement.net_amount) }}</span>
        </el-descriptions-item>
      </el-descriptions>

      <!-- 审核操作 -->
      <el-form style="margin-top: 20px" label-width="80px">
        <el-form-item label="审核结果" required>
          <el-radio-group v-model="auditForm.action">
            <el-radio value="approve">
              <el-tag type="success" size="small" effect="plain">通过</el-tag>
            </el-radio>
            <el-radio value="reject" style="margin-left: 16px">
              <el-tag type="danger" size="small" effect="plain">拒绝</el-tag>
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="审核备注">
          <el-input
            v-model="auditForm.remark"
            type="textarea"
            :rows="3"
            placeholder="拒绝时必填，通过时选填"
          />
        </el-form-item>
      </el-form>
    </template>

    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">
        提交审核
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped lang="scss">
.amount-text {
  font-weight: 500;
  color: #303133;
}

.commission-text {
  color: #e6a23c;
  font-weight: 500;
}

.rate-text {
  font-size: 12px;
  font-weight: normal;
  color: #909399;
}

.net-amount-text {
  font-size: 15px;
  font-weight: 600;
  color: #67c23a;
}
</style>
