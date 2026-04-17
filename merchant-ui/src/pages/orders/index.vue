<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Search, Download, View } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { getOrderList, exportOrders } from '@/api/order'
import type { Order, OrderStatus, OrderListParams } from '@/api/order'
import OrderDetail from './components/OrderDetail.vue'

const userStore = useUserStore()

// ─── 筛选表单 ─────────────────────────────────────────────────────────────
const filterForm = reactive<OrderListParams>({
  page: 1,
  per_page: 20,
  store_id: '',
  status: '',
  date_from: '',
  date_to: '',
  search: '',
})

/** el-date-picker 双向绑定用的日期范围数组 */
const dateRange = ref<[string, string] | null>(null)

/** 同步日期范围到 filterForm */
function onDateRangeChange(val: [string, string] | null) {
  if (val) {
    filterForm.date_from = val[0]
    filterForm.date_to = val[1]
  } else {
    filterForm.date_from = ''
    filterForm.date_to = ''
  }
}

// ─── 表格数据 ─────────────────────────────────────────────────────────────
const loading = ref(false)
const orderList = ref<Order[]>([])
const total = ref(0)

/** 构造请求参数（过滤空值） */
function buildParams(): OrderListParams {
  const p: OrderListParams = { page: filterForm.page, per_page: filterForm.per_page }
  if (filterForm.store_id) p.store_id = filterForm.store_id
  if (filterForm.status) p.status = filterForm.status
  if (filterForm.date_from) p.date_from = filterForm.date_from
  if (filterForm.date_to) p.date_to = filterForm.date_to
  if (filterForm.search) p.search = filterForm.search
  return p
}

async function loadOrders() {
  loading.value = true
  try {
    const res = await getOrderList(buildParams())
    orderList.value = res.data.data
    total.value = res.data.meta.total
  } catch {
    // 错误已由请求拦截器处理
  } finally {
    loading.value = false
  }
}

/** 搜索 — 重置到第 1 页 */
function handleSearch() {
  filterForm.page = 1
  loadOrders()
}

/** 重置筛选 */
function handleReset() {
  filterForm.store_id = ''
  filterForm.status = ''
  filterForm.date_from = ''
  filterForm.date_to = ''
  filterForm.search = ''
  dateRange.value = null
  filterForm.page = 1
  loadOrders()
}

/** 分页变化 */
function handlePageChange(page: number) {
  filterForm.page = page
  loadOrders()
}

function handleSizeChange(size: number) {
  filterForm.per_page = size
  filterForm.page = 1
  loadOrders()
}

// ─── 导出 CSV ─────────────────────────────────────────────────────────────
const exporting = ref(false)

async function handleExport() {
  exporting.value = true
  try {
    const blob = await exportOrders({
      store_id: filterForm.store_id || undefined,
      status: (filterForm.status as OrderStatus) || undefined,
      date_from: filterForm.date_from || undefined,
      date_to: filterForm.date_to || undefined,
      search: filterForm.search || undefined,
    })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `orders_${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
    ElMessage.success('导出成功')
  } catch {
    // 错误已由请求拦截器处理
  } finally {
    exporting.value = false
  }
}

// ─── 订单详情 Drawer ──────────────────────────────────────────────────────
const orderDetailRef = ref<InstanceType<typeof OrderDetail> | null>(null)

function openDetail(row: Order) {
  orderDetailRef.value?.open(row.id)
}

// ─── 状态 Tag 配置 ────────────────────────────────────────────────────────
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

/** 格式化金额（千分位 + 2 位小数） */
function formatCurrency(amount: number, currency: string = 'USD'): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount)
}

/** 格式化时间 */
function formatDate(dateStr: string): string {
  return dateStr.replace('T', ' ').slice(0, 19)
}

// 站点列表（来自用户 store）
const storeOptions = computed(() => userStore.stores)

onMounted(() => {
  loadOrders()
})
</script>

<template>
  <div class="orders-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">订单列表</h2>
    </div>

    <!-- ── 筛选栏 ──────────────────────────────────────────────────────── -->
    <el-card shadow="never" class="filter-card">
      <el-form :model="filterForm" inline class="filter-form">
        <!-- 站点筛选 -->
        <el-form-item label="站点">
          <el-select
            v-model="filterForm.store_id"
            placeholder="全部站点"
            clearable
            style="width: 160px"
          >
            <el-option
              v-for="store in storeOptions"
              :key="store.id"
              :label="store.name"
              :value="store.id"
            />
          </el-select>
        </el-form-item>

        <!-- 订单状态 -->
        <el-form-item label="状态">
          <el-select
            v-model="filterForm.status"
            placeholder="全部状态"
            clearable
            style="width: 130px"
          >
            <el-option label="待处理" value="pending" />
            <el-option label="处理中" value="processing" />
            <el-option label="已发货" value="shipped" />
            <el-option label="已送达" value="delivered" />
            <el-option label="已取消" value="cancelled" />
            <el-option label="已退款" value="refunded" />
          </el-select>
        </el-form-item>

        <!-- 日期范围 -->
        <el-form-item label="下单时间">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="~"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 240px"
            @change="onDateRangeChange"
          />
        </el-form-item>

        <!-- 搜索框 -->
        <el-form-item label="搜索">
          <el-input
            v-model="filterForm.search"
            placeholder="订单号 / 客户名"
            clearable
            :prefix-icon="Search"
            style="width: 200px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>

        <!-- 操作按钮 -->
        <el-form-item>
          <el-button type="primary" @click="handleSearch" :loading="loading">查询</el-button>
          <el-button @click="handleReset">重置</el-button>
          <el-button
            type="success"
            :icon="Download"
            :loading="exporting"
            @click="handleExport"
          >导出 CSV</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- ── 订单表格 ────────────────────────────────────────────────────── -->
    <el-card shadow="never" class="table-card">
      <el-table
        v-loading="loading"
        :data="orderList"
        stripe
        style="width: 100%"
      >
        <!-- 订单号 -->
        <el-table-column label="订单号" min-width="160" fixed="left">
          <template #default="{ row }">
            <el-button type="primary" link size="small" @click="openDetail(row)">
              {{ row.order_no }}
            </el-button>
          </template>
        </el-table-column>

        <!-- 站点名称 -->
        <el-table-column prop="store_name" label="站点" min-width="110" show-overflow-tooltip />

        <!-- 客户名 -->
        <el-table-column prop="customer_name" label="客户" min-width="110" show-overflow-tooltip />

        <!-- 商品数量 -->
        <el-table-column label="商品" width="72" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="info">{{ row.items?.length ?? 0 }} 件</el-tag>
          </template>
        </el-table-column>

        <!-- 订单金额 -->
        <el-table-column label="金额" min-width="120" align="right">
          <template #default="{ row }">
            <span class="amount">{{ formatCurrency(row.total_amount, row.currency) }}</span>
          </template>
        </el-table-column>

        <!-- 订单状态 -->
        <el-table-column label="订单状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag
              :type="ORDER_STATUS_MAP[row.status as OrderStatus]?.type"
              size="small"
            >
              {{ ORDER_STATUS_MAP[row.status as OrderStatus]?.label ?? row.status }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 支付状态 -->
        <el-table-column label="支付状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag
              :type="PAYMENT_STATUS_MAP[row.payment_status]?.type"
              size="small"
            >
              {{ PAYMENT_STATUS_MAP[row.payment_status]?.label ?? row.payment_status }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 下单时间 -->
        <el-table-column label="下单时间" min-width="160">
          <template #default="{ row }">
            <span class="time-text">{{ formatDate(row.created_at) }}</span>
          </template>
        </el-table-column>

        <!-- 操作 -->
        <el-table-column label="操作" width="88" fixed="right" align="center">
          <template #default="{ row }">
            <el-button type="primary" link :icon="View" size="small" @click="openDetail(row)">
              详情
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="filterForm.page"
          v-model:page-size="filterForm.per_page"
          :total="total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <!-- ── 订单详情 Drawer ────────────────────────────────────────────── -->
    <OrderDetail ref="orderDetailRef" />
  </div>
</template>

<style scoped lang="scss">
.orders-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.filter-card {
  :deep(.el-card__body) {
    padding: 16px 16px 0;
  }
}

.filter-form {
  flex-wrap: wrap;
}

.table-card {
  :deep(.el-card__body) {
    padding: 0;
  }

  :deep(.el-table) {
    border-radius: 0;
  }
}

.amount {
  font-weight: 600;
  color: #303133;
}

.time-text {
  font-size: 13px;
  color: #606266;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding: 16px;
  border-top: 1px solid #f0f2f5;
}
</style>
