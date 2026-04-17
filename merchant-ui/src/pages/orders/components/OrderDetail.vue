<script setup lang="ts">
import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import { CopyDocument, Picture } from '@element-plus/icons-vue'
import { getOrderDetail } from '@/api/order'
import type { Order, OrderStatus } from '@/api/order'

// ─── 状态 ─────────────────────────────────────────────────────────────────
const visible = ref(false)
const loading = ref(false)
const order = ref<Order | null>(null)

// ─── 订单状态配置 ─────────────────────────────────────────────────────────
const ORDER_STATUS_MAP: Record<OrderStatus, { label: string; type: 'warning' | 'primary' | '' | 'success' | 'info' | 'danger' }> = {
  pending:    { label: '待处理', type: 'warning' },
  processing: { label: '处理中', type: 'primary' },
  shipped:    { label: '已发货', type: '' },
  delivered:  { label: '已送达', type: 'success' },
  cancelled:  { label: '已取消', type: 'info' },
  refunded:   { label: '已退款', type: 'danger' },
}

const PAYMENT_STATUS_MAP: Record<string, { label: string; type: 'warning' | '' | 'success' | 'info' | 'danger' }> = {
  unpaid:   { label: '未支付', type: 'warning' },
  paid:     { label: '已支付', type: 'success' },
  refunded: { label: '已退款', type: 'danger' },
}

// ─── 工具函数 ─────────────────────────────────────────────────────────────

function formatCurrency(amount: number, currency: string = 'USD'): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount)
}

function formatDate(dateStr: string): string {
  return dateStr.replace('T', ' ').slice(0, 19)
}

/** 复制运单号到剪贴板 */
async function copyTracking(trackingNo: string) {
  try {
    await navigator.clipboard.writeText(trackingNo)
    ElMessage.success('运单号已复制')
  } catch {
    ElMessage.error('复制失败，请手动复制')
  }
}

// ─── 暴露 open 方法 ───────────────────────────────────────────────────────

async function open(orderId: number) {
  visible.value = true
  order.value = null
  loading.value = true
  try {
    const res = await getOrderDetail(orderId)
    order.value = res.data
  } catch {
    // 错误由拦截器处理
    visible.value = false
  } finally {
    loading.value = false
  }
}

defineExpose({ open })
</script>

<template>
  <el-drawer
    v-model="visible"
    title="订单详情"
    direction="rtl"
    size="600px"
    :destroy-on-close="true"
  >
    <div v-loading="loading" class="order-detail">
      <template v-if="order">
        <!-- ── 订单信息头部 ──────────────────────────────────────────── -->
        <div class="detail-section header-section">
          <div class="order-no-row">
            <span class="order-no">{{ order.order_no }}</span>
            <el-tag
              :type="ORDER_STATUS_MAP[order.status]?.type"
              size="default"
            >
              {{ ORDER_STATUS_MAP[order.status]?.label ?? order.status }}
            </el-tag>
          </div>
          <div class="order-meta">
            <span class="meta-item">
              <span class="meta-label">站点：</span>{{ order.store_name }}
            </span>
            <span class="meta-item">
              <span class="meta-label">下单时间：</span>{{ formatDate(order.created_at) }}
            </span>
          </div>
        </div>

        <!-- ── 商品明细 ────────────────────────────────────────────── -->
        <div class="detail-section">
          <div class="section-title">商品明细</div>
          <div class="items-list">
            <div
              v-for="(item, index) in order.items"
              :key="index"
              class="order-item-row"
            >
              <div class="item-image">
                <el-image
                  v-if="item.image_url"
                  :src="item.image_url"
                  fit="cover"
                  class="product-thumb"
                />
                <div v-else class="product-thumb placeholder">
                  <el-icon color="#c0c4cc"><Picture /></el-icon>
                </div>
              </div>
              <div class="item-info">
                <div class="item-name">{{ item.product_name }}</div>
                <div class="item-sku">SKU: {{ item.sku }}</div>
              </div>
              <div class="item-price-qty">
                <span class="item-unit-price">{{ formatCurrency(item.price, order.currency) }}</span>
                <span class="item-qty"> × {{ item.quantity }}</span>
              </div>
              <div class="item-subtotal">
                {{ formatCurrency(item.price * item.quantity, order.currency) }}
              </div>
            </div>
          </div>

          <!-- 金额汇总 -->
          <div class="amount-summary">
            <div class="summary-row">
              <span class="summary-label">商品小计</span>
              <span class="summary-value">{{ formatCurrency(order.subtotal, order.currency) }}</span>
            </div>
            <div class="summary-row">
              <span class="summary-label">运费</span>
              <span class="summary-value">{{ formatCurrency(order.shipping_fee, order.currency) }}</span>
            </div>
            <div class="summary-row total-row">
              <span class="summary-label">合计</span>
              <span class="summary-value total-value">
                {{ formatCurrency(order.total_amount, order.currency) }}
              </span>
            </div>
          </div>
        </div>

        <!-- ── 收货地址 ────────────────────────────────────────────── -->
        <div class="detail-section">
          <div class="section-title">收货地址</div>
          <div class="address-info">
            <div class="address-row">
              <span class="addr-label">收件人：</span>
              <span>{{ order.shipping_address.name }}</span>
            </div>
            <div class="address-row">
              <span class="addr-label">电话：</span>
              <span>{{ order.shipping_address.phone }}</span>
            </div>
            <div class="address-row">
              <span class="addr-label">地址：</span>
              <span>
                {{ order.shipping_address.address_line1 }},
                {{ order.shipping_address.city }},
                {{ order.shipping_address.state }}
                {{ order.shipping_address.postal_code }},
                {{ order.shipping_address.country }}
              </span>
            </div>
          </div>
        </div>

        <!-- ── 支付信息 ────────────────────────────────────────────── -->
        <div class="detail-section">
          <div class="section-title">支付信息</div>
          <div class="info-grid">
            <div class="info-item">
              <span class="info-label">支付方式</span>
              <span class="info-value">{{ order.payment_method || '—' }}</span>
            </div>
            <div class="info-item">
              <span class="info-label">支付状态</span>
              <el-tag
                :type="PAYMENT_STATUS_MAP[order.payment_status]?.type"
                size="small"
              >
                {{ PAYMENT_STATUS_MAP[order.payment_status]?.label ?? order.payment_status }}
              </el-tag>
            </div>
          </div>
        </div>

        <!-- ── 物流信息 ────────────────────────────────────────────── -->
        <div class="detail-section" v-if="order.tracking_number || order.carrier">
          <div class="section-title">物流信息</div>
          <div class="info-grid">
            <div class="info-item" v-if="order.carrier">
              <span class="info-label">承运商</span>
              <span class="info-value">{{ order.carrier }}</span>
            </div>
            <div class="info-item" v-if="order.tracking_number">
              <span class="info-label">运单号</span>
              <div class="tracking-wrap">
                <span class="info-value tracking-no">{{ order.tracking_number }}</span>
                <el-button
                  type="primary"
                  link
                  :icon="CopyDocument"
                  size="small"
                  @click="copyTracking(order!.tracking_number!)"
                >复制</el-button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- 加载占位 -->
      <el-empty v-else-if="!loading" description="暂无订单数据" />
    </div>
  </el-drawer>
</template>

<style scoped lang="scss">
.order-detail {
  padding: 0 4px;
  min-height: 200px;
}

.detail-section {
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f0f2f5;

  &:last-child {
    border-bottom: none;
    margin-bottom: 0;
  }
}

// ── 头部 ──────────────────────────────────────────────────
.header-section {
  padding-bottom: 16px;
}

.order-no-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.order-no {
  font-size: 16px;
  font-weight: 700;
  color: #303133;
}

.order-meta {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
}

.meta-item {
  font-size: 13px;
  color: #606266;
}

.meta-label {
  color: #909399;
}

// ── Section 标题 ───────────────────────────────────────────
.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
  padding-left: 8px;
  border-left: 3px solid #409eff;
}

// ── 商品明细 ───────────────────────────────────────────────
.items-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 12px;
}

.order-item-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px;
  border-radius: 6px;
  background: #fafafa;
}

.item-image {
  flex-shrink: 0;
}

.product-thumb {
  width: 48px;
  height: 48px;
  border-radius: 4px;
  object-fit: cover;
  border: 1px solid #e4e7ed;

  &.placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f7fa;
  }
}

.item-info {
  flex: 1;
  min-width: 0;
}

.item-name {
  font-size: 13px;
  color: #303133;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.item-sku {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}

.item-price-qty {
  font-size: 13px;
  color: #606266;
  flex-shrink: 0;
  white-space: nowrap;
}

.item-unit-price {
  color: #303133;
}

.item-qty {
  color: #909399;
}

.item-subtotal {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  flex-shrink: 0;
  min-width: 80px;
  text-align: right;
}

// ── 金额汇总 ───────────────────────────────────────────────
.amount-summary {
  background: #f8f9fa;
  border-radius: 6px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #606266;

  &.total-row {
    margin-top: 6px;
    padding-top: 8px;
    border-top: 1px dashed #d9d9d9;
  }
}

.total-value {
  font-size: 16px;
  font-weight: 700;
  color: #e6a23c;
}

// ── 收货地址 ───────────────────────────────────────────────
.address-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.address-row {
  display: flex;
  font-size: 13px;
  color: #303133;
  line-height: 1.5;
}

.addr-label {
  color: #909399;
  flex-shrink: 0;
  width: 52px;
}

// ── 信息网格 ───────────────────────────────────────────────
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.info-label {
  font-size: 12px;
  color: #909399;
}

.info-value {
  font-size: 13px;
  color: #303133;
}

// ── 物流 ───────────────────────────────────────────────────
.tracking-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
}

.tracking-no {
  font-family: monospace;
  font-size: 13px;
}
</style>
