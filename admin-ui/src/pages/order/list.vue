<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import SearchForm from '@/components/common/SearchForm.vue'
import DataTable from '@/components/common/DataTable.vue'
import {
  getOrderList,
  exportOrders,
  type Order,
  type OrderListParams,
} from '@/api/order'

const router = useRouter()

// ==================== 支付状态枚举 ====================
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

// ==================== 发货状态枚举 ====================
const SHIP_STATUS_MAP: Record<number, { label: string; type: string }> = {
  0: { label: '未处理', type: 'info' },
  1: { label: '待配货', type: 'warning' },
  3: { label: '配货中', type: 'primary' },
  8: { label: '配货完成', type: 'success' },
  9: { label: '物流已揽收', type: 'success' },
}

// ==================== 搜索参数 ====================
const searchForm = reactive<OrderListParams>({
  keyword: '',
  pay_status: '',
  shipment_status: '',
  date_from: '',
  date_to: '',
  domain: '',
  sku_type: '',
})
const dateRange = ref<[string, string] | null>(null)

// ==================== 表格数据 ====================
const loading = ref(false)
const tableData = ref<Order[]>([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const selectedRows = ref<Order[]>([])
const exportLoading = ref(false)

// ==================== 加载数据 ====================
async function loadData() {
  loading.value = true
  try {
    const params: OrderListParams = {
      page: currentPage.value,
      per_page: pageSize.value,
    }
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.pay_status !== '') params.pay_status = searchForm.pay_status
    if (searchForm.shipment_status !== '') params.shipment_status = searchForm.shipment_status
    if (searchForm.domain) params.domain = searchForm.domain
    if (searchForm.sku_type) params.sku_type = searchForm.sku_type
    if (dateRange.value) {
      params.date_from = dateRange.value[0]
      params.date_to = dateRange.value[1]
    }

    const res = await getOrderList(params)
    const d = res.data
    tableData.value = (d as any).list ?? []
    total.value = d.total ?? 0
  } catch {
    tableData.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  currentPage.value = 1
  loadData()
}

function handleReset() {
  searchForm.keyword = ''
  searchForm.pay_status = ''
  searchForm.shipment_status = ''
  searchForm.domain = ''
  searchForm.sku_type = ''
  dateRange.value = null
  currentPage.value = 1
  loadData()
}

// ==================== 分页 ====================
function handlePageChange(page: number) {
  currentPage.value = page
  loadData()
}

function handlePageSizeChange(size: number) {
  pageSize.value = size
  currentPage.value = 1
  loadData()
}

// ==================== 导出 ====================
async function handleExport() {
  exportLoading.value = true
  try {
    const params: Omit<OrderListParams, 'page' | 'per_page'> = {}
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.pay_status !== '') params.pay_status = searchForm.pay_status
    if (searchForm.shipment_status !== '') params.shipment_status = searchForm.shipment_status
    if (searchForm.domain) params.domain = searchForm.domain
    if (searchForm.sku_type) params.sku_type = searchForm.sku_type
    if (dateRange.value) {
      params.date_from = dateRange.value[0]
      params.date_to = dateRange.value[1]
    }
    const res = await exportOrders(params)
    if (res.data?.url) {
      window.open(res.data.url, '_blank')
    }
    ElMessage.success('导出成功')
  } catch {
    // 错误已由拦截器处理
  } finally {
    exportLoading.value = false
  }
}

// ==================== 操作 ====================
function handleViewDetail(row: Order) {
  router.push(`/order/detail/${row.id}`)
}

function handleRefund(row: Order) {
  router.push(`/order/refund/${row.id}`)
}

// ==================== 状态辅助函数 ====================
function getPayStatusConfig(status: number) {
  return PAY_STATUS_MAP[status] ?? { label: `状态${status}`, type: 'info' }
}

function getShipStatusConfig(status: number) {
  return SHIP_STATUS_MAP[status] ?? { label: `状态${status}`, type: 'info' }
}

function formatAmount(amount: number, currency = 'USD') {
  return `${currency} ${(amount ?? 0).toFixed(2)}`
}

// ==================== 表格列定义 ====================
const columns = [
  { label: '订单号', slot: 'order_no', minWidth: 160 },
  { label: '客户信息', slot: 'customer', minWidth: 160 },
  { label: '商品数', slot: 'items_count', width: 80, align: 'center' as const },
  { label: '金额', slot: 'amount', width: 130, align: 'right' as const },
  { label: '支付状态', slot: 'pay_status', width: 100, align: 'center' as const },
  { label: '发货状态', slot: 'ship_status', width: 110, align: 'center' as const },
  { label: '来源域名', slot: 'domain', width: 140 },
  { label: '下单时间', prop: 'created_at', width: 160, showOverflowTooltip: true },
  { label: '操作', slot: 'action', width: 130, fixed: 'right' as const, align: 'center' as const },
]

// selectedIds 保留备用（批量操作扩展点）
// const selectedIds = computed(() => selectedRows.value.map((r) => r.id))

// ==================== 初始化 ====================
onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      title="订单列表"
      :actions="[
        { label: '导出订单', type: 'default', icon: 'Download', onClick: handleExport },
      ]"
    />

    <!-- 搜索区域 -->
    <SearchForm :loading="loading" @search="handleSearch" @reset="handleReset">
      <el-form-item label="关键词">
        <el-input
          v-model="searchForm.keyword"
          placeholder="订单号/客户邮箱/客户名"
          clearable
          style="width: 200px"
          @keyup.enter="handleSearch"
        />
      </el-form-item>
      <el-form-item label="支付状态">
        <el-select
          v-model="searchForm.pay_status"
          placeholder="全部"
          clearable
          style="width: 120px"
        >
          <el-option label="待支付" :value="1" />
          <el-option label="支付失败" :value="2" />
          <el-option label="已支付" :value="3" />
          <el-option label="已取消" :value="4" />
          <el-option label="部分退款" :value="5" />
          <el-option label="已退款" :value="6" />
          <el-option label="交易中" :value="7" />
          <el-option label="部分退款中" :value="8" />
          <el-option label="退款中" :value="9" />
        </el-select>
      </el-form-item>
      <el-form-item label="发货状态">
        <el-select
          v-model="searchForm.shipment_status"
          placeholder="全部"
          clearable
          style="width: 120px"
        >
          <el-option label="未处理" :value="0" />
          <el-option label="待配货" :value="1" />
          <el-option label="配货中" :value="3" />
          <el-option label="配货完成" :value="8" />
          <el-option label="物流已揽收" :value="9" />
        </el-select>
      </el-form-item>
      <el-form-item label="下单时间">
        <el-date-picker
          v-model="dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 220px"
        />
      </el-form-item>
      <el-form-item label="来源域名">
        <el-input
          v-model="searchForm.domain"
          placeholder="域名"
          clearable
          style="width: 150px"
        />
      </el-form-item>
      <el-form-item label="SKU类型">
        <el-select
          v-model="searchForm.sku_type"
          placeholder="全部"
          clearable
          style="width: 110px"
        >
          <el-option label="正品" value="zw" />
          <el-option label="DIY" value="diy" />
          <el-option label="WPZ" value="wpz" />
        </el-select>
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never">
      <DataTable
        :data="tableData"
        :columns="columns"
        :total="total"
        :page="currentPage"
        :page-size="pageSize"
        :loading="loading"
        row-key="id"
        @update:page="handlePageChange"
        @update:page-size="handlePageSizeChange"
        @selection-change="(rows: Order[]) => (selectedRows = rows)"
      >
        <!-- 订单号列 -->
        <template #order_no="{ row }">
          <div class="order-no-cell">
            <el-link
              type="primary"
              :underline="false"
              @click="handleViewDetail(row)"
            >
              {{ row.order_no }}
            </el-link>
            <div class="order-no-cell__tags">
              <el-tag v-if="row.is_diy" size="small" type="success">DIY</el-tag>
              <el-tag v-if="row.is_wpz" size="small" type="primary">WPZ</el-tag>
              <el-tag v-if="row.is_zw" size="small" type="danger">正品</el-tag>
            </div>
          </div>
        </template>

        <!-- 客户信息列 -->
        <template #customer="{ row }">
          <div class="customer-cell">
            <div class="customer-cell__name">{{ row.customer_name }}</div>
            <div class="customer-cell__email">{{ row.customer_email }}</div>
          </div>
        </template>

        <!-- 商品数列 -->
        <template #items_count="{ row }">
          <el-tag size="small" type="info">
            {{ row.items_count ?? row.items?.length ?? '-' }} 件
          </el-tag>
        </template>

        <!-- 金额列 -->
        <template #amount="{ row }">
          <span class="amount-cell">{{ formatAmount(row.total, row.currency) }}</span>
        </template>

        <!-- 支付状态列 -->
        <template #pay_status="{ row }">
          <el-tag
            :type="getPayStatusConfig(row.pay_status).type as any"
            size="small"
          >
            {{ row.pay_status_label || getPayStatusConfig(row.pay_status).label }}
          </el-tag>
        </template>

        <!-- 发货状态列 -->
        <template #ship_status="{ row }">
          <el-tag
            :type="getShipStatusConfig(row.shipment_status).type as any"
            size="small"
          >
            {{ row.shipment_status_label || getShipStatusConfig(row.shipment_status).label }}
          </el-tag>
        </template>

        <!-- 来源域名列 -->
        <template #domain="{ row }">
          <span class="domain-cell">{{ row.domain || '-' }}</span>
        </template>

        <!-- 操作列 -->
        <template #action="{ row }">
          <el-button type="primary" size="small" link @click="handleViewDetail(row)">
            <el-icon><View /></el-icon> 详情
          </el-button>
          <el-button
            type="warning"
            size="small"
            link
            :disabled="![3, 5, 7].includes(row.pay_status)"
            @click="handleRefund(row)"
          >
            <el-icon><RefreshLeft /></el-icon> 退款
          </el-button>
        </template>
      </DataTable>
    </el-card>
  </div>
</template>

<style scoped lang="scss">
.order-no-cell {
  &__tags {
    display: flex;
    gap: 4px;
    margin-top: 4px;
    flex-wrap: wrap;
  }
}

.customer-cell {
  &__name {
    font-size: 13px;
    color: #303133;
    line-height: 1.4;
  }

  &__email {
    font-size: 11px;
    color: #909399;
    margin-top: 2px;
  }
}

.amount-cell {
  font-weight: 600;
  color: #e6a23c;
  font-size: 13px;
}

.domain-cell {
  font-size: 12px;
  color: #606266;
  word-break: break-all;
}
</style>
