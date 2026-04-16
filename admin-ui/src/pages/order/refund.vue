<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import { getOrderDetail, refundOrder, type Order } from '@/api/order'

const route = useRoute()
const router = useRouter()
const orderId = computed(() => Number(route.params.id))

// ==================== 数据状态 ====================
const pageLoading = ref(false)
const submitting = ref(false)
const order = ref<Order | null>(null)
const formRef = ref<FormInstance>()

// ==================== 退款表单 ====================
const refundForm = ref({
  type: 'full' as 'full' | 'partial',
  amount: 0,
  reason: '',
})

// ==================== 表单校验规则 ====================
const formRules: FormRules = {
  type: [{ required: true, message: '请选择退款类型', trigger: 'change' }],
  amount: [
    {
      validator: (_rule: unknown, value: number, callback: (err?: Error) => void) => {
        if (refundForm.value.type !== 'partial') {
          callback()
          return
        }
        if (!value || value <= 0) {
          callback(new Error('请输入退款金额'))
        } else if (order.value && value > order.value.total) {
          callback(new Error(`退款金额不能超过订单总金额 ${order.value.total.toFixed(2)}`))
        } else {
          callback()
        }
      },
      trigger: 'blur',
    },
  ],
  reason: [
    { required: true, message: '请输入退款原因', trigger: 'blur' },
    { min: 5, message: '退款原因至少 5 个字符', trigger: 'blur' },
  ],
}

// ==================== 加载订单数据 ====================
async function loadOrder() {
  pageLoading.value = true
  try {
    const res = await getOrderDetail(orderId.value)
    order.value = res.data
    // 默认部分退款金额 = 订单总额
    refundForm.value.amount = order.value?.total ?? 0
  } catch {
    order.value = null
  } finally {
    pageLoading.value = false
  }
}

// ==================== 计算金额显示 ====================
const isPartial = computed(() => refundForm.value.type === 'partial')

const refundAmountLabel = computed(() => {
  if (!order.value) return ''
  if (refundForm.value.type === 'full') {
    return `全额退款：${order.value.currency} ${order.value.total.toFixed(2)}`
  }
  return '请输入退款金额'
})

function formatAmount(amount: number, currency = 'USD') {
  return `${currency} ${(amount ?? 0).toFixed(2)}`
}

// ==================== 提交退款 ====================
async function handleSubmit() {
  if (!formRef.value) return

  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  const amount =
    refundForm.value.type === 'full'
      ? order.value?.total ?? 0
      : refundForm.value.amount

  try {
    await ElMessageBox.confirm(
      `确认${refundForm.value.type === 'full' ? '全额' : '部分'}退款 ${formatAmount(amount, order.value?.currency)}？此操作不可撤销。`,
      '退款确认',
      {
        type: 'warning',
        confirmButtonText: '确认退款',
        cancelButtonText: '取消',
        confirmButtonClass: 'el-button--danger',
      },
    )
  } catch {
    return // 用户取消
  }

  submitting.value = true
  try {
    await refundOrder(orderId.value, {
      type: refundForm.value.type,
      amount: isPartial.value ? refundForm.value.amount : undefined,
      reason: refundForm.value.reason,
    })
    ElMessage.success('退款申请提交成功')
    router.push(`/order/detail/${orderId.value}`)
  } catch {
    // 错误已由拦截器处理
  } finally {
    submitting.value = false
  }
}

// ==================== 初始化 ====================
onMounted(() => {
  loadOrder()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      title="申请退款"
      :actions="[
        { label: '返回详情', type: 'default', icon: 'ArrowLeft', onClick: () => router.push(`/order/detail/${orderId}`) },
      ]"
    />

    <div v-loading="pageLoading">
      <!-- 订单信息卡片（只读） -->
      <el-card v-if="order" shadow="never" class="refund-card">
        <template #header>
          <span class="card-title">订单信息</span>
        </template>
        <el-descriptions :column="3" border size="small">
          <el-descriptions-item label="订单号">
            <span class="order-no">{{ order.order_no }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="下单时间">{{ order.created_at }}</el-descriptions-item>
          <el-descriptions-item label="来源域名">{{ order.domain || '-' }}</el-descriptions-item>
          <el-descriptions-item label="客户姓名">{{ order.customer_name }}</el-descriptions-item>
          <el-descriptions-item label="客户邮箱">{{ order.customer_email }}</el-descriptions-item>
          <el-descriptions-item label="支付方式">{{ order.pay_type_label || order.pay_type || '-' }}</el-descriptions-item>
          <el-descriptions-item label="商品小计">{{ formatAmount(order.price, order.currency) }}</el-descriptions-item>
          <el-descriptions-item label="运费">{{ formatAmount(order.shipping_fee, order.currency) }}</el-descriptions-item>
          <el-descriptions-item label="订单总额">
            <span class="total-amount">{{ formatAmount(order.total, order.currency) }}</span>
          </el-descriptions-item>
        </el-descriptions>
      </el-card>

      <!-- 退款表单 -->
      <el-card shadow="never" class="refund-card">
        <template #header>
          <span class="card-title">退款信息</span>
        </template>

        <el-form
          ref="formRef"
          :model="refundForm"
          :rules="formRules"
          label-width="110px"
          class="refund-form"
        >
          <!-- 退款类型 -->
          <el-form-item label="退款类型" prop="type">
            <el-radio-group v-model="refundForm.type">
              <el-radio-button value="full">
                <el-icon><Check /></el-icon>
                全额退款
              </el-radio-button>
              <el-radio-button value="partial">
                <el-icon><Minus /></el-icon>
                部分退款
              </el-radio-button>
            </el-radio-group>
          </el-form-item>

          <!-- 全额退款提示 -->
          <el-form-item v-if="!isPartial" label="退款金额">
            <el-alert
              :title="refundAmountLabel"
              type="info"
              :closable="false"
              show-icon
            />
          </el-form-item>

          <!-- 部分退款金额 -->
          <el-form-item v-else label="退款金额" prop="amount">
            <el-input-number
              v-model="refundForm.amount"
              :min="0.01"
              :max="order?.total ?? 99999"
              :precision="2"
              :step="1"
              style="width: 200px"
            />
            <span class="amount-hint">
              最大可退：{{ formatAmount(order?.total ?? 0, order?.currency) }}
            </span>
          </el-form-item>

          <!-- 退款原因 -->
          <el-form-item label="退款原因" prop="reason">
            <el-input
              v-model="refundForm.reason"
              type="textarea"
              :rows="4"
              placeholder="请详细描述退款原因（至少5个字符）"
              maxlength="500"
              show-word-limit
              style="width: 500px"
            />
          </el-form-item>

          <!-- 操作按钮 -->
          <el-form-item>
            <el-button
              type="danger"
              :loading="submitting"
              @click="handleSubmit"
            >
              <el-icon><RefreshLeft /></el-icon>
              确认提交退款
            </el-button>
            <el-button @click="router.push(`/order/detail/${orderId}`)">
              取消
            </el-button>
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 订单不存在 -->
      <el-card v-if="!order && !pageLoading" shadow="never">
        <el-empty description="订单不存在或无法退款">
          <el-button @click="router.back()">返回</el-button>
        </el-empty>
      </el-card>
    </div>
  </div>
</template>

<style scoped lang="scss">
.refund-card {
  margin-bottom: 16px;

  .card-title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }
}

.order-no {
  font-weight: 600;
  color: #409eff;
}

.total-amount {
  font-size: 15px;
  font-weight: 700;
  color: #f56c6c;
}

.refund-form {
  max-width: 700px;
  padding-top: 8px;
}

.amount-hint {
  margin-left: 12px;
  font-size: 12px;
  color: #909399;
}
</style>
