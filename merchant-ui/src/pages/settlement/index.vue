<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { View, Money, TrendCharts, Finished, Clock } from '@element-plus/icons-vue'
import { getSettlementList, getSettlementSummary, getSettlementDetail } from '@/api/settlement'
import type { Settlement, SettlementStatus, SettlementListParams, SettlementSummary } from '@/api/settlement'

// ─── 摘要卡片 ─────────────────────────────────────────────────────────────
const summaryLoading = ref(false)
const summary = ref<SettlementSummary | null>(null)

async function loadSummary() {
  summaryLoading.value = true
  try {
    const res = await getSettlementSummary()
    summary.value = res.data
  } catch {
    // 错误由拦截器处理
  } finally {
    summaryLoading.value = false
  }
}

// ─── 结算列表 ─────────────────────────────────────────────────────────────
const filterForm = reactive<SettlementListParams>({
  page: 1,
  per_page: 15,
  status: '',
  date_from: '',
  date_to: '',
})

const dateRange = ref<[string, string] | null>(null)

function onDateRangeChange(val: [string, string] | null) {
  if (val) {
    filterForm.date_from = val[0]
    filterForm.date_to = val[1]
  } else {
    filterForm.date_from = ''
    filterForm.date_to = ''
  }
}

const loading = ref(false)
const settlementList = ref<Settlement[]>([])
const total = ref(0)

function buildParams(): SettlementListParams {
  const p: SettlementListParams = { page: filterForm.page, per_page: filterForm.per_page }
  if (filterForm.status) p.status = filterForm.status
  if (filterForm.date_from) p.date_from = filterForm.date_from
  if (filterForm.date_to) p.date_to = filterForm.date_to
  return p
}

async function loadSettlements() {
  loading.value = true
  try {
    const res = await getSettlementList(buildParams())
    settlementList.value = res.data.data
    total.value = res.data.meta.total
  } catch {
    // 错误由拦截器处理
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  filterForm.page = 1
  loadSettlements()
}

function handleReset() {
  filterForm.status = ''
  filterForm.date_from = ''
  filterForm.date_to = ''
  dateRange.value = null
  filterForm.page = 1
  loadSettlements()
}

function handlePageChange(page: number) {
  filterForm.page = page
  loadSettlements()
}

function handleSizeChange(size: number) {
  filterForm.per_page = size
  filterForm.page = 1
  loadSettlements()
}

// ─── 各站点明细弹窗 ───────────────────────────────────────────────────────
const detailDrawerVisible = ref(false)
const detailLoading = ref(false)
const currentSettlement = ref<Settlement | null>(null)

async function openDetail(row: Settlement) {
  detailDrawerVisible.value = true
  currentSettlement.value = null
  detailLoading.value = true
  try {
    const res = await getSettlementDetail(row.id)
    currentSettlement.value = res.data
  } catch {
    detailDrawerVisible.value = false
  } finally {
    detailLoading.value = false
  }
}

// ─── 状态配置 ─────────────────────────────────────────────────────────────
const SETTLEMENT_STATUS_MAP: Record<SettlementStatus, { label: string; type: 'warning' | 'primary' | 'success' | 'danger' }> = {
  pending:    { label: '待处理', type: 'warning' },
  processing: { label: '处理中', type: 'primary' },
  completed:  { label: '已完成', type: 'success' },
  rejected:   { label: '已拒绝', type: 'danger' },
}

// ─── 工具函数 ─────────────────────────────────────────────────────────────
function formatCurrency(amount: number, currency: string = 'USD'): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount)
}

/** 金额千分位（通用，不带货币符号）*/
function formatAmount(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}

function formatDate(dateStr: string): string {
  return dateStr.slice(0, 10)
}

// ─── 初始化 ───────────────────────────────────────────────────────────────
onMounted(() => {
  loadSummary()
  loadSettlements()
})
</script>

<template>
  <div class="settlement-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">结算中心</h2>
    </div>

    <!-- ── 摘要卡片（4 个） ────────────────────────────────────────────── -->
    <el-row :gutter="16" v-loading="summaryLoading" class="summary-row">
      <!-- 可结算余额（绿色） -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="summary-card" shadow="hover">
          <div class="card-content green">
            <div class="card-body">
              <div class="card-label">可结算余额</div>
              <div class="card-value green-text">
                {{ summary ? formatAmount(summary.available_balance) : '—' }}
              </div>
            </div>
            <div class="card-icon green-bg">
              <el-icon :size="22"><Money /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 本月预估佣金（蓝色） -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="summary-card" shadow="hover">
          <div class="card-content blue">
            <div class="card-body">
              <div class="card-label">本月预估佣金</div>
              <div class="card-value blue-text">
                {{ summary ? formatAmount(summary.this_month_estimated_commission) : '—' }}
              </div>
            </div>
            <div class="card-icon blue-bg">
              <el-icon :size="22"><TrendCharts /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 累计已结算（紫色） -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="summary-card" shadow="hover">
          <div class="card-content purple">
            <div class="card-body">
              <div class="card-label">累计已结算</div>
              <div class="card-value purple-text">
                {{ summary ? formatAmount(summary.total_settled) : '—' }}
              </div>
            </div>
            <div class="card-icon purple-bg">
              <el-icon :size="22"><Finished /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- 待处理金额（橙色） -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="summary-card" shadow="hover">
          <div class="card-content orange">
            <div class="card-body">
              <div class="card-label">待处理金额</div>
              <div class="card-value orange-text">
                {{ summary ? formatAmount(summary.pending_amount) : '—' }}
              </div>
            </div>
            <div class="card-icon orange-bg">
              <el-icon :size="22"><Clock /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ── 结算记录 ────────────────────────────────────────────────────── -->
    <el-card shadow="never" class="list-card">
      <template #header>
        <div class="list-card-header">
          <span class="list-title">结算记录</span>
          <!-- 筛选表单 -->
          <el-form :model="filterForm" inline class="filter-form">
            <el-form-item label="状态">
              <el-select
                v-model="filterForm.status"
                placeholder="全部状态"
                clearable
                style="width: 120px"
              >
                <el-option label="待处理" value="pending" />
                <el-option label="处理中" value="processing" />
                <el-option label="已完成" value="completed" />
                <el-option label="已拒绝" value="rejected" />
              </el-select>
            </el-form-item>
            <el-form-item label="周期">
              <el-date-picker
                v-model="dateRange"
                type="daterange"
                range-separator="~"
                start-placeholder="开始"
                end-placeholder="结束"
                value-format="YYYY-MM-DD"
                style="width: 220px"
                @change="onDateRangeChange"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" size="small" @click="handleSearch" :loading="loading">查询</el-button>
              <el-button size="small" @click="handleReset">重置</el-button>
            </el-form-item>
          </el-form>
        </div>
      </template>

      <el-table
        v-loading="loading"
        :data="settlementList"
        stripe
        style="width: 100%"
      >
        <!-- 结算周期 -->
        <el-table-column label="结算周期" min-width="180">
          <template #default="{ row }">
            <span class="period-text">{{ formatDate(row.period_start) }} ~ {{ formatDate(row.period_end) }}</span>
          </template>
        </el-table-column>

        <!-- 总销售额 -->
        <el-table-column label="总销售额" min-width="120" align="right">
          <template #default="{ row }">
            {{ formatAmount(row.total_sales) }}
          </template>
        </el-table-column>

        <!-- 佣金率 -->
        <el-table-column label="佣金率" width="80" align="center">
          <template #default="{ row }">
            <span class="rate-text">{{ (row.commission_rate * 100).toFixed(1) }}%</span>
          </template>
        </el-table-column>

        <!-- 佣金金额 -->
        <el-table-column label="佣金" min-width="110" align="right">
          <template #default="{ row }">
            <span class="commission-text">{{ formatAmount(row.total_commission) }}</span>
          </template>
        </el-table-column>

        <!-- 退款扣除 -->
        <el-table-column label="退款扣除" min-width="110" align="right">
          <template #default="{ row }">
            <span class="refund-text" v-if="row.total_refunds > 0">
              -{{ formatAmount(row.total_refunds) }}
            </span>
            <span v-else class="zero-text">—</span>
          </template>
        </el-table-column>

        <!-- 调整金额 -->
        <el-table-column label="调整" min-width="100" align="right">
          <template #default="{ row }">
            <span
              :class="row.adjustment >= 0 ? 'adjust-pos' : 'adjust-neg'"
              v-if="row.adjustment !== 0"
            >
              {{ row.adjustment >= 0 ? '+' : '' }}{{ formatAmount(row.adjustment) }}
            </span>
            <span v-else class="zero-text">—</span>
          </template>
        </el-table-column>

        <!-- 净结算额 -->
        <el-table-column label="净结算额" min-width="120" align="right">
          <template #default="{ row }">
            <span class="net-amount">{{ formatAmount(row.net_amount) }}</span>
          </template>
        </el-table-column>

        <!-- 状态 -->
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag
              :type="SETTLEMENT_STATUS_MAP[row.status as SettlementStatus]?.type"
              size="small"
            >
              {{ SETTLEMENT_STATUS_MAP[row.status as SettlementStatus]?.label ?? row.status }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 结算时间 -->
        <el-table-column label="结算时间" min-width="110" align="center">
          <template #default="{ row }">
            <span class="time-text">{{ row.paid_at ? formatDate(row.paid_at) : '—' }}</span>
          </template>
        </el-table-column>

        <!-- 操作 -->
        <el-table-column label="操作" width="88" fixed="right" align="center">
          <template #default="{ row }">
            <el-button
              type="primary"
              link
              :icon="View"
              size="small"
              @click="openDetail(row)"
            >明细</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="filterForm.page"
          v-model:page-size="filterForm.per_page"
          :total="total"
          :page-sizes="[10, 15, 30, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <!-- ── 各站点明细 Drawer ─────────────────────────────────────────── -->
    <el-drawer
      v-model="detailDrawerVisible"
      title="结算明细"
      direction="rtl"
      size="560px"
      :destroy-on-close="true"
    >
      <div v-loading="detailLoading" class="detail-drawer">
        <template v-if="currentSettlement">
          <!-- 基础信息 -->
          <div class="detail-section">
            <div class="section-title">结算周期</div>
            <div class="period-info">
              <el-descriptions :column="2" size="small" border>
                <el-descriptions-item label="周期">
                  {{ formatDate(currentSettlement.period_start) }} ~ {{ formatDate(currentSettlement.period_end) }}
                </el-descriptions-item>
                <el-descriptions-item label="状态">
                  <el-tag
                    :type="SETTLEMENT_STATUS_MAP[currentSettlement.status]?.type"
                    size="small"
                  >
                    {{ SETTLEMENT_STATUS_MAP[currentSettlement.status]?.label }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="总销售额">
                  {{ formatAmount(currentSettlement.total_sales) }}
                </el-descriptions-item>
                <el-descriptions-item label="佣金率">
                  {{ (currentSettlement.commission_rate * 100).toFixed(1) }}%
                </el-descriptions-item>
                <el-descriptions-item label="佣金金额">
                  {{ formatAmount(currentSettlement.total_commission) }}
                </el-descriptions-item>
                <el-descriptions-item label="退款扣除">
                  {{ currentSettlement.total_refunds > 0 ? `-${formatAmount(currentSettlement.total_refunds)}` : '—' }}
                </el-descriptions-item>
                <el-descriptions-item label="调整金额">
                  {{ currentSettlement.adjustment !== 0
                    ? `${currentSettlement.adjustment >= 0 ? '+' : ''}${formatAmount(currentSettlement.adjustment)}`
                    : '—' }}
                </el-descriptions-item>
                <el-descriptions-item label="净结算额">
                  <span class="net-amount">{{ formatAmount(currentSettlement.net_amount) }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="结算时间" :span="2">
                  {{ currentSettlement.paid_at ? currentSettlement.paid_at.slice(0, 19).replace('T', ' ') : '—' }}
                </el-descriptions-item>
              </el-descriptions>
            </div>
          </div>

          <!-- 各站点明细 -->
          <div class="detail-section" v-if="currentSettlement.store_details?.length">
            <div class="section-title">各站点明细</div>
            <el-table
              :data="currentSettlement.store_details"
              size="small"
              border
              stripe
            >
              <el-table-column prop="store_name" label="站点名称" min-width="120" show-overflow-tooltip />
              <el-table-column label="站点销售额" min-width="110" align="right">
                <template #default="{ row }">{{ formatAmount(row.sales) }}</template>
              </el-table-column>
              <el-table-column label="站点佣金" min-width="100" align="right">
                <template #default="{ row }">
                  <span class="commission-text">{{ formatAmount(row.commission) }}</span>
                </template>
              </el-table-column>
              <el-table-column label="站点净额" min-width="100" align="right">
                <template #default="{ row }">
                  <span class="net-amount">{{ formatAmount(row.net) }}</span>
                </template>
              </el-table-column>
            </el-table>
          </div>

          <!-- 收款信息 -->
          <div class="detail-section" v-if="currentSettlement.bank_info">
            <div class="section-title">收款账户</div>
            <div class="bank-info">{{ currentSettlement.bank_info }}</div>
          </div>
        </template>

        <el-empty v-else-if="!detailLoading" description="暂无明细数据" />
      </div>
    </el-drawer>
  </div>
</template>

<style scoped lang="scss">
.settlement-page {
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

// ── 摘要卡片 ──────────────────────────────────────────────
.summary-row {
  margin-bottom: 0 !important;
}

.summary-card {
  border-radius: 8px;
  transition: transform 0.2s;

  &:hover {
    transform: translateY(-2px);
  }

  :deep(.el-card__body) {
    padding: 16px;
  }
}

.card-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-body {
  flex: 1;
}

.card-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.card-value {
  font-size: 24px;
  font-weight: 700;
  line-height: 1;

  &.green-text  { color: #67c23a; }
  &.blue-text   { color: #409eff; }
  &.purple-text { color: #9c27b0; }
  &.orange-text { color: #e6a23c; }
}

.card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;

  &.green-bg  { background: linear-gradient(135deg, #67c23a, #4caf50); }
  &.blue-bg   { background: linear-gradient(135deg, #409eff, #1976d2); }
  &.purple-bg { background: linear-gradient(135deg, #9c27b0, #7b1fa2); }
  &.orange-bg { background: linear-gradient(135deg, #e6a23c, #fb8c00); }
}

// ── 结算记录卡片 ───────────────────────────────────────────
.list-card {
  :deep(.el-card__body) {
    padding: 0;
  }

  :deep(.el-card__header) {
    padding: 12px 16px;
  }
}

.list-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
}

.list-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}

.filter-form {
  margin: 0;

  :deep(.el-form-item) {
    margin-bottom: 0;
  }
}

// ── 表格内容 ───────────────────────────────────────────────
.period-text {
  font-size: 13px;
  color: #303133;
}

.rate-text {
  font-size: 13px;
  color: #606266;
}

.commission-text {
  color: #e6a23c;
  font-weight: 500;
}

.refund-text {
  color: #f56c6c;
}

.zero-text {
  color: #c0c4cc;
}

.adjust-pos {
  color: #67c23a;
}

.adjust-neg {
  color: #f56c6c;
}

.net-amount {
  font-weight: 700;
  color: #303133;
  font-size: 14px;
}

.time-text {
  font-size: 13px;
  color: #909399;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  padding: 16px;
  border-top: 1px solid #f0f2f5;
}

// ── 明细 Drawer ────────────────────────────────────────────
.detail-drawer {
  padding: 0 4px;
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

.section-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 12px;
  padding-left: 8px;
  border-left: 3px solid #409eff;
}

.period-info {
  :deep(.el-descriptions__label) {
    width: 90px;
    color: #909399;
  }
}

.bank-info {
  font-size: 13px;
  color: #303133;
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
  line-height: 1.6;
}
</style>
