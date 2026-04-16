<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import {
  getOrderDetail,
  updatePayStatus,
  updateShipStatus,
  addOrderHistory,
  type Order,
} from '@/api/order'

const route = useRoute()
const router = useRouter()
const orderId = computed(() => Number(route.params.id))

// ==================== 状态配置 ====================
const PAY_STATUS_MAP: Record<number, { label: string; type: string }> = {
  1: { label: '待支付', type: 'warning' },
  2: { label: '支付失败', type: 'danger' },
  3: { label: '已支付', type: 'success' },
  4: { label: '已取消', type: 'info' },
  5: { label: '部分退款', type: 'warning' },
  6: { label: '已退款', type: 'info' },
  7: { label: '交易中', type: 'primary' },
  8: { label: '部分退款中', type: 'warning' },
  9: { label: '退款中', type: 'warning' },
}

const SHIP_STATUS_MAP: Record<number, { label: string; type: string }> = {
  0: { label: '未处理', type: 'info' },
  1: { label: '待配货', type: 'warning' },
  3: { label: '配货中', type: 'primary' },
  8: { label: '配货完成', type: 'success' },
  9: { label: '物流已揽收', type: 'success' },
}

const PAY_STATUS_OPTIONS = [
  { label: '待支付', value: 1 },
  { label: '支付失败', value: 2 },
  { label: '已支付', value: 3 },
  { label: '已取消', value: 4 },
  { label: '部分退款', value: 5 },
  { label: '已退款', value: 6 },
  { label: '交易中', value: 7 },
  { label: '部分退款中', value: 8 },
  { label: '退款中', value: 9 },
]

const SHIP_STATUS_OPTIONS = [
  { label: '未处理', value: 0 },
  { label: '待配货', value: 1 },
  { label: '配货中', value: 3 },
  { label: '配货完成', value: 8 },
  { label: '物流已揽收', value: 9 },
]

// ==================== 数据状态 ====================
const loading = ref(false)
const order = ref<Order | null>(null)

// ==================== 加载数据 ====================
async function loadOrder() {
  loading.value = true
  try {
    const res = await getOrderDetail(orderId.value)
    order.value = res.data
  } catch {
    order.value = null
  } finally {
    loading.value = false
  }
}

// ==================== 更新支付状态弹窗 ====================
const payStatusDialog = ref(false)
const payStatusForm = ref({ status: 0, remark: '' })
const payStatusUpdating = ref(false)

function openPayStatusDialog() {
  payStatusForm.value = { status: order.value?.pay_status ?? 0, remark: '' }
  payStatusDialog.value = true
}

async function submitPayStatus() {
  payStatusUpdating.value = true
  try {
    await updatePayStatus(orderId.value, {
      status: payStatusForm.value.status,
      remark: payStatusForm.value.remark,
    })
    ElMessage.success('支付状态更新成功')
    payStatusDialog.value = false
    await loadOrder()
  } catch {
    // 错误已由拦截器处理
  } finally {
    payStatusUpdating.value = false
  }
}

// ==================== 更新发货状态弹窗 ====================
const shipStatusDialog = ref(false)
const shipStatusForm = ref({ status: 0, remark: '' })
const shipStatusUpdating = ref(false)

function openShipStatusDialog() {
  shipStatusForm.value = { status: order.value?.shipment_status ?? 0, remark: '' }
  shipStatusDialog.value = true
}

async function submitShipStatus() {
  shipStatusUpdating.value = true
  try {
    await updateShipStatus(orderId.value, {
      status: shipStatusForm.value.status,
      remark: shipStatusForm.value.remark,
    })
    ElMessage.success('发货状态更新成功')
    shipStatusDialog.value = false
    await loadOrder()
  } catch {
    // 错误已由拦截器处理
  } finally {
    shipStatusUpdating.value = false
  }
}

// ==================== 添加备注 ====================
const remarkInput = ref('')
const remarkLoading = ref(false)
const notifyCustomer = ref(false)

async function submitRemark() {
  if (!remarkInput.value.trim()) {
    ElMessage.warning('请输入备注内容')
    return
  }
  remarkLoading.value = true
  try {
    await addOrderHistory(orderId.value, {
      comment: remarkInput.value.trim(),
      notify_customer: notifyCustomer.value,
    })
    ElMessage.success('备注添加成功')
    remarkInput.value = ''
    notifyCustomer.value = false
    await loadOrder()
  } catch {
    // 错误已由拦截器处理
  } finally {
    remarkLoading.value = false
  }
}

// ==================== 跳转退款 ====================
function goRefund() {
  router.push(`/order/refund/${orderId.value}`)
}

// ==================== 辅助函数 ====================
function getPayStatusConfig(status: number) {
  return PAY_STATUS_MAP[status] ?? { label: `状态${status}`, type: 'info' }
}

function getShipStatusConfig(status: number) {
  return SHIP_STATUS_MAP[status] ?? { label: `状态${status}`, type: 'info' }
}

function formatAmount(amount: number, currency = 'USD') {
  return `${currency} ${(amount ?? 0).toFixed(2)}`
}

function formatAddress(addr: Order['shipping_address']) {
  if (!addr) return '-'
  const parts = [
    addr.address1,
    addr.address2,
    addr.city,
    addr.state,
    addr.postcode,
    addr.country,
  ].filter(Boolean)
  return parts.join(', ')
}

// ==================== 初始化 ====================
onMounted(() => {
  loadOrder()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      :title="order ? `订单详情 #${order.order_no}` : '订单详情'"
      :actions="[{ label: '返回列表', type: 'default', icon: 'ArrowLeft', onClick: () => router.back() }]"
    />

    <div v-loading="loading" class="order-detail">
      <template v-if="order">
        <!-- 状态操作区 -->
        <el-card shadow="never" class="detail-card">
          <template #header>
            <span class="card-title">订单状态</span>
          </template>
          <div class="status-bar">
            <div class="status-bar__item">
              <span class="status-bar__label">支付状态：</span>
              <el-tag :type="getPayStatusConfig(order.pay_status).type as any">
                {{ order.pay_status_label || getPayStatusConfig(order.pay_status).label }}
              </el-tag>
            </div>
            <div class="status-bar__item">
              <span class="status-bar__label">发货状态：</span>
              <el-tag :type="getShipStatusConfig(order.shipment_status).type as any">
                {{ order.shipment_status_label || getShipStatusConfig(order.shipment_status).label }}
              </el-tag>
            </div>
            <div class="status-bar__actions">
              <el-button type="primary" size="small" @click="openPayStatusDialog">
                更新支付状态
              </el-button>
              <el-button type="success" size="small" @click="openShipStatusDialog">
                更新发货状态
              </el-button>
              <el-button
                type="warning"
                size="small"
                :disabled="![3, 5, 7].includes(order.pay_status)"
                @click="goRefund"
              >
                <el-icon><RefreshLeft /></el-icon> 申请退款
              </el-button>
            </div>
          </div>
        </el-card>

        <!-- 基本信息 -->
        <el-card shadow="never" class="detail-card">
          <template #header>
            <span class="card-title">基本信息</span>
          </template>
          <el-descriptions :column="3" border>
            <el-descriptions-item label="订单号">{{ order.order_no }}</el-descriptions-item>
            <el-descriptions-item label="下单时间">{{ order.created_at }}</el-descriptions-item>
            <el-descriptions-item label="来源域名">{{ order.domain || '-' }}</el-descriptions-item>
            <el-descriptions-item label="客户姓名">{{ order.customer_name }}</el-descriptions-item>
            <el-descriptions-item label="客户邮箱">{{ order.customer_email }}</el-descriptions-item>
            <el-descriptions-item label="客户电话">{{ order.customer_phone || '-' }}</el-descriptions-item>
            <el-descriptions-item label="SKU类型">
              <el-tag v-if="order.is_diy" size="small" type="success" style="margin-right: 4px">DIY</el-tag>
              <el-tag v-if="order.is_wpz" size="small" type="primary" style="margin-right: 4px">WPZ</el-tag>
              <el-tag v-if="order.is_zw" size="small" type="danger">正品</el-tag>
              <span v-if="!order.is_diy && !order.is_wpz && !order.is_zw">普通</span>
            </el-descriptions-item>
            <el-descriptions-item label="支付方式">{{ order.pay_type_label || order.pay_type || '-' }}</el-descriptions-item>
            <el-descriptions-item label="备注">{{ order.remark || '-' }}</el-descriptions-item>
          </el-descriptions>
        </el-card>

        <!-- 商品明细 -->
        <el-card shadow="never" class="detail-card">
          <template #header>
            <span class="card-title">商品明细</span>
          </template>
          <el-table :data="order.items || []" border stripe style="width: 100%">
            <el-table-column label="图片" width="80" align="center">
              <template #default="{ row }">
                <el-image
                  v-if="row.image"
                  :src="row.image"
                  :preview-src-list="[row.image]"
                  fit="cover"
                  style="width: 52px; height: 52px; border-radius: 4px"
                />
                <div v-else class="img-placeholder">
                  <el-icon><Picture /></el-icon>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="商品名称" min-width="180">
              <template #default="{ row }">
                <div>{{ row.product_name }}</div>
                <div v-if="row.safe_name" class="safe-name">{{ row.safe_name }}</div>
                <div v-if="row.options" class="item-options">{{ row.options }}</div>
              </template>
            </el-table-column>
            <el-table-column label="SKU" prop="sku" width="140" show-overflow-tooltip />
            <el-table-column label="单价" width="110" align="right">
              <template #default="{ row }">
                {{ formatAmount(row.price, order!.currency) }}
              </template>
            </el-table-column>
            <el-table-column label="数量" prop="quantity" width="80" align="center" />
            <el-table-column label="小计" width="120" align="right">
              <template #default="{ row }">
                <span class="subtotal">{{ formatAmount(row.subtotal ?? row.price * row.quantity, order!.currency) }}</span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>

        <!-- 两列布局：收货地址 + 支付信息 -->
        <el-row :gutter="16">
          <!-- 收货地址 -->
          <el-col :span="12">
            <el-card shadow="never" class="detail-card">
              <template #header>
                <span class="card-title">收货地址</span>
              </template>
              <template v-if="order.shipping_address">
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item label="姓名">
                    {{ order.shipping_address.firstname }} {{ order.shipping_address.lastname }}
                  </el-descriptions-item>
                  <el-descriptions-item label="电话">{{ order.shipping_address.phone || '-' }}</el-descriptions-item>
                  <el-descriptions-item label="完整地址">{{ formatAddress(order.shipping_address) }}</el-descriptions-item>
                  <el-descriptions-item label="国家">{{ order.shipping_address.country }}</el-descriptions-item>
                  <el-descriptions-item label="邮编">{{ order.shipping_address.postcode }}</el-descriptions-item>
                </el-descriptions>
              </template>
              <el-empty v-else description="无收货地址信息" :image-size="60" />
            </el-card>
          </el-col>

          <!-- 支付信息 -->
          <el-col :span="12">
            <el-card shadow="never" class="detail-card">
              <template #header>
                <span class="card-title">支付信息</span>
              </template>
              <el-descriptions :column="1" border size="small">
                <el-descriptions-item label="支付方式">{{ order.pay_type_label || order.pay_type || '-' }}</el-descriptions-item>
                <el-descriptions-item label="支付状态">
                  <el-tag :type="getPayStatusConfig(order.pay_status).type as any" size="small">
                    {{ order.pay_status_label || getPayStatusConfig(order.pay_status).label }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="商品小计">{{ formatAmount(order.price, order.currency) }}</el-descriptions-item>
                <el-descriptions-item label="运费">{{ formatAmount(order.shipping_fee, order.currency) }}</el-descriptions-item>
                <el-descriptions-item label="税费">{{ formatAmount(order.tax_amount, order.currency) }}</el-descriptions-item>
                <el-descriptions-item v-if="order.discount_amount" label="折扣">
                  -{{ formatAmount(order.discount_amount, order.currency) }}
                </el-descriptions-item>
                <el-descriptions-item label="订单总计">
                  <span class="total-amount">{{ formatAmount(order.total, order.currency) }}</span>
                </el-descriptions-item>
              </el-descriptions>
            </el-card>
          </el-col>
        </el-row>

        <!-- 操作历史时间线 -->
        <el-card shadow="never" class="detail-card">
          <template #header>
            <span class="card-title">操作历史</span>
          </template>
          <el-timeline v-if="order.histories && order.histories.length > 0">
            <el-timeline-item
              v-for="item in order.histories"
              :key="item.id"
              :timestamp="item.created_at"
              placement="top"
            >
              <div class="history-item">
                <span class="history-item__comment">{{ item.comment }}</span>
                <span v-if="item.operator" class="history-item__operator">— {{ item.operator }}</span>
              </div>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无操作记录" :image-size="60" />
        </el-card>

        <!-- 添加备注 -->
        <el-card shadow="never" class="detail-card">
          <template #header>
            <span class="card-title">添加备注</span>
          </template>
          <div class="remark-form">
            <el-input
              v-model="remarkInput"
              type="textarea"
              :rows="3"
              placeholder="输入备注内容..."
              maxlength="500"
              show-word-limit
            />
            <div class="remark-form__footer">
              <el-checkbox v-model="notifyCustomer">通知客户</el-checkbox>
              <el-button
                type="primary"
                :loading="remarkLoading"
                :disabled="!remarkInput.trim()"
                @click="submitRemark"
              >
                提交备注
              </el-button>
            </div>
          </div>
        </el-card>
      </template>

      <!-- 加载失败 -->
      <el-card v-else-if="!loading" shadow="never">
        <el-empty description="订单不存在或加载失败">
          <el-button @click="router.back()">返回</el-button>
        </el-empty>
      </el-card>
    </div>

    <!-- 更新支付状态弹窗 -->
    <el-dialog v-model="payStatusDialog" title="更新支付状态" width="440px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="新支付状态" required>
          <el-select v-model="payStatusForm.status" style="width: 100%">
            <el-option
              v-for="opt in PAY_STATUS_OPTIONS"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="payStatusForm.remark"
            type="textarea"
            :rows="3"
            placeholder="可选，记录状态变更原因"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="payStatusDialog = false">取消</el-button>
        <el-button
          type="primary"
          :loading="payStatusUpdating"
          @click="submitPayStatus"
        >
          确认更新
        </el-button>
      </template>
    </el-dialog>

    <!-- 更新发货状态弹窗 -->
    <el-dialog v-model="shipStatusDialog" title="更新发货状态" width="440px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="新发货状态" required>
          <el-select v-model="shipStatusForm.status" style="width: 100%">
            <el-option
              v-for="opt in SHIP_STATUS_OPTIONS"
              :key="opt.value"
              :label="opt.label"
              :value="opt.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input
            v-model="shipStatusForm.remark"
            type="textarea"
            :rows="3"
            placeholder="可选，记录状态变更原因"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shipStatusDialog = false">取消</el-button>
        <el-button
          type="primary"
          :loading="shipStatusUpdating"
          @click="submitShipStatus"
        >
          确认更新
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.order-detail {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-card {
  .card-title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }
}

.status-bar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;

  &__item {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  &__label {
    font-size: 13px;
    color: #606266;
    white-space: nowrap;
  }

  &__actions {
    margin-left: auto;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
}

.safe-name {
  font-size: 11px;
  color: #909399;
  margin-top: 2px;
}

.item-options {
  font-size: 11px;
  color: #909399;
  margin-top: 2px;
}

.subtotal {
  font-weight: 500;
  color: #e6a23c;
}

.total-amount {
  font-size: 16px;
  font-weight: 700;
  color: #f56c6c;
}

.img-placeholder {
  width: 52px;
  height: 52px;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  font-size: 18px;
}

.history-item {
  font-size: 13px;
  color: #606266;

  &__comment {
    color: #303133;
  }

  &__operator {
    color: #909399;
    font-size: 12px;
    margin-left: 8px;
  }
}

.remark-form {
  &__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
  }
}
</style>
