<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { TrendCharts, ShoppingCart, Document, Money, Goods, View, Refresh } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { getDashboardStats } from '@/api/dashboard'
import type { DashboardStats } from '@/api/dashboard'

const router = useRouter()
const userStore = useUserStore()

const loading = ref(false)
const stats = ref<DashboardStats | null>(null)

/** 格式化金额 */
function formatAmount(val: number): string {
  if (val >= 10000) {
    return `¥${(val / 10000).toFixed(2)}万`
  }
  return `¥${val.toFixed(2)}`
}

/** 获取仪表盘统计数据 */
async function loadStats() {
  loading.value = true
  try {
    const res = await getDashboardStats(
      userStore.currentStoreId ? { store_id: userStore.currentStoreId } : undefined,
    )
    stats.value = res.data
  } catch {
    // 错误已由请求拦截器处理
  } finally {
    loading.value = false
  }
}

/** 趋势图 SVG — 简易折线图 */
const trendSvgLines = computed(() => {
  const data = stats.value?.trends ?? []
  if (data.length < 2) return { salesPath: '', ordersPath: '', maxSales: 0, maxOrders: 0, labels: [] }

  const W = 640
  const H = 200
  const PAD_X = 40
  const PAD_Y = 20
  const innerW = W - PAD_X * 2
  const innerH = H - PAD_Y * 2

  const maxSales = Math.max(...data.map(d => d.sales), 1)
  const maxOrders = Math.max(...data.map(d => d.orders), 1)

  const toX = (i: number) => PAD_X + (i / (data.length - 1)) * innerW
  const toSalesY = (v: number) => PAD_Y + innerH - (v / maxSales) * innerH
  const toOrdersY = (v: number) => PAD_Y + innerH - (v / maxOrders) * innerH

  const salesPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'}${toX(i).toFixed(1)},${toSalesY(d.sales).toFixed(1)}`).join(' ')
  const ordersPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'}${toX(i).toFixed(1)},${toOrdersY(d.orders).toFixed(1)}`).join(' ')

  // 标签取每5个
  const labels = data
    .map((d, i) => ({ i, date: d.date.slice(5) }))
    .filter((_, i) => i % Math.ceil(data.length / 6) === 0 || i === data.length - 1)
    .map(({ i, date }) => ({ x: toX(i), date }))

  return { salesPath, ordersPath, maxSales, maxOrders, labels, W, H, PAD_X, PAD_Y, innerH, innerW }
})

onMounted(() => {
  loadStats()
})
</script>

<template>
  <div class="dashboard-page" v-loading="loading">
    <!-- 页面标题 + 刷新 -->
    <div class="page-header">
      <h2 class="page-title">仪表盘</h2>
      <el-button :icon="Refresh" @click="loadStats" :loading="loading" size="small">刷新</el-button>
    </div>

    <!-- ── 4 张统计卡片 ───────────────────────────────── -->
    <el-row :gutter="16" class="stat-row">
      <!-- 今日销售额 -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="stat-card stat-green" shadow="hover">
          <div class="stat-content">
            <div class="stat-body">
              <div class="stat-label">今日销售额</div>
              <div class="stat-value">
                {{ stats ? formatAmount(stats.today_sales) : '—' }}
              </div>
            </div>
            <div class="stat-icon-wrap green">
              <el-icon><Money /></el-icon>
            </div>
          </div>
          <div class="stat-footer">
            <span class="stat-hint">当前站点销售数据</span>
          </div>
        </el-card>
      </el-col>

      <!-- 今日订单数 -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="stat-card stat-blue" shadow="hover">
          <div class="stat-content">
            <div class="stat-body">
              <div class="stat-label">今日订单数</div>
              <div class="stat-value">
                {{ stats ? stats.today_orders : '—' }}
              </div>
            </div>
            <div class="stat-icon-wrap blue">
              <el-icon><Document /></el-icon>
            </div>
          </div>
          <div class="stat-footer">
            <span class="stat-hint">今日新增订单</span>
          </div>
        </el-card>
      </el-col>

      <!-- 待处理订单 -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="stat-card stat-orange" shadow="hover">
          <div class="stat-content">
            <div class="stat-body">
              <div class="stat-label">待处理订单</div>
              <div class="stat-value warning">
                {{ stats ? stats.pending_orders : '—' }}
              </div>
            </div>
            <div class="stat-icon-wrap orange">
              <el-icon><ShoppingCart /></el-icon>
            </div>
          </div>
          <div class="stat-footer">
            <el-tag v-if="stats && stats.pending_orders > 0" type="warning" size="small">需及时处理</el-tag>
            <span v-else class="stat-hint">暂无待处理</span>
          </div>
        </el-card>
      </el-col>

      <!-- 可结算余额 -->
      <el-col :xs="24" :sm="12" :lg="6">
        <el-card class="stat-card stat-purple" shadow="hover">
          <div class="stat-content">
            <div class="stat-body">
              <div class="stat-label">可结算余额</div>
              <div class="stat-value">
                {{ stats ? formatAmount(stats.available_balance) : '—' }}
              </div>
            </div>
            <div class="stat-icon-wrap purple">
              <el-icon><TrendCharts /></el-icon>
            </div>
          </div>
          <div class="stat-footer">
            <el-button type="primary" link size="small" @click="router.push('/settlement')">
              前往结算
            </el-button>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- ── 趋势图 + 站点占比 ─────────────────────────── -->
    <el-row :gutter="16" class="chart-row">
      <!-- 近 30 天趋势 -->
      <el-col :xs="24" :lg="16">
        <el-card shadow="never">
          <template #header>
            <div class="card-header">
              <span>近 30 天趋势</span>
              <el-tag type="info" size="small">销售额 + 订单数</el-tag>
            </div>
          </template>

          <!-- 简易 SVG 折线图 -->
          <div v-if="stats && stats.trends.length >= 2" class="trend-chart">
            <svg
              :viewBox="`0 0 ${trendSvgLines.W} ${trendSvgLines.H}`"
              preserveAspectRatio="none"
              class="trend-svg"
            >
              <!-- 网格线 -->
              <line
                v-for="n in 4"
                :key="n"
                :x1="trendSvgLines.PAD_X"
                :x2="(trendSvgLines.W ?? 640) - (trendSvgLines.PAD_X ?? 40)"
                :y1="(trendSvgLines.PAD_Y ?? 20) + ((trendSvgLines.innerH ?? 160) / 4) * n"
                :y2="(trendSvgLines.PAD_Y ?? 20) + ((trendSvgLines.innerH ?? 160) / 4) * n"
                stroke="#f0f0f0"
                stroke-width="1"
              />

              <!-- 销售额折线（绿色） -->
              <path
                :d="trendSvgLines.salesPath"
                fill="none"
                stroke="#67c23a"
                stroke-width="2"
                stroke-linejoin="round"
              />

              <!-- 订单数折线（蓝色） -->
              <path
                :d="trendSvgLines.ordersPath"
                fill="none"
                stroke="#409eff"
                stroke-width="2"
                stroke-linejoin="round"
                stroke-dasharray="4 2"
              />

              <!-- X 轴标签 -->
              <text
                v-for="label in trendSvgLines.labels"
                :key="label.date"
                :x="label.x"
                :y="(trendSvgLines.H ?? 200) - 4"
                text-anchor="middle"
                font-size="10"
                fill="#909399"
              >{{ label.date }}</text>
            </svg>

            <!-- 图例 -->
            <div class="trend-legend">
              <span class="legend-item green"><span class="legend-dot"></span>销售额</span>
              <span class="legend-item blue"><span class="legend-dot dashed"></span>订单数</span>
            </div>
          </div>

          <!-- 趋势表格（兜底 / 数据不足时） -->
          <template v-else>
            <el-table
              :data="stats?.trends ?? []"
              size="small"
              max-height="280"
              stripe
            >
              <el-table-column prop="date" label="日期" width="120" />
              <el-table-column label="销售额">
                <template #default="{ row }">{{ formatAmount(row.sales) }}</template>
              </el-table-column>
              <el-table-column prop="orders" label="订单数" />
            </el-table>
          </template>

          <el-empty v-if="!stats || stats.trends.length === 0" description="暂无趋势数据" />
        </el-card>
      </el-col>

      <!-- 站点销售占比 -->
      <el-col :xs="24" :lg="8">
        <el-card shadow="never" class="distribution-card">
          <template #header>
            <span>站点销售占比</span>
          </template>

          <div v-if="stats && stats.store_distribution.length > 0" class="distribution-list">
            <div
              v-for="(item, index) in stats.store_distribution"
              :key="item.store_name"
              class="distribution-item"
            >
              <div class="dist-header">
                <span class="dist-name">{{ item.store_name }}</span>
                <span class="dist-amount">{{ formatAmount(item.sales) }}</span>
              </div>
              <el-progress
                :percentage="item.percentage"
                :color="['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399'][index % 5]"
                :show-text="false"
                style="margin: 4px 0;"
              />
              <div class="dist-percent">{{ item.percentage.toFixed(1) }}%</div>
            </div>
          </div>

          <el-empty v-else description="暂无站点数据" />
        </el-card>
      </el-col>
    </el-row>

    <!-- ── 快捷入口 ──────────────────────────────────── -->
    <el-card shadow="never" class="quick-entry-card">
      <template #header>
        <span>快捷入口</span>
      </template>

      <div class="quick-entries">
        <div class="entry-item" @click="router.push('/products')">
          <div class="entry-icon green">
            <el-icon :size="24"><Goods /></el-icon>
          </div>
          <span class="entry-label">新增商品</span>
        </div>

        <div class="entry-item" @click="router.push('/orders')">
          <div class="entry-icon blue">
            <el-icon :size="24"><Document /></el-icon>
          </div>
          <span class="entry-label">查看订单</span>
        </div>

        <div class="entry-item" @click="router.push('/products/sync')">
          <div class="entry-icon orange">
            <el-icon :size="24"><Refresh /></el-icon>
          </div>
          <span class="entry-label">同步管理</span>
        </div>

        <div class="entry-item" @click="router.push('/settlement')">
          <div class="entry-icon purple">
            <el-icon :size="24"><Money /></el-icon>
          </div>
          <span class="entry-label">结算中心</span>
        </div>
      </div>
    </el-card>
  </div>
</template>

<style scoped lang="scss">
.dashboard-page {
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

// ── 统计卡片 ─────────────────────────────────────────
.stat-row {
  margin-bottom: 0 !important;
}

.stat-card {
  border-radius: 8px;
  transition: transform 0.2s;

  &:hover {
    transform: translateY(-2px);
  }

  :deep(.el-card__body) {
    padding: 16px;
  }
}

.stat-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.stat-body {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: #303133;
  line-height: 1;

  &.warning {
    color: #e6a23c;
  }
}

.stat-icon-wrap {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;

  .el-icon {
    font-size: 22px;
    color: #fff;
  }

  &.green { background: linear-gradient(135deg, #67c23a, #4caf50); }
  &.blue  { background: linear-gradient(135deg, #409eff, #1976d2); }
  &.orange { background: linear-gradient(135deg, #e6a23c, #fb8c00); }
  &.purple { background: linear-gradient(135deg, #9c27b0, #7b1fa2); }
}

.stat-footer {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid #f0f0f0;
  font-size: 12px;
}

.stat-hint {
  color: #c0c4cc;
}

// ── 趋势图 ───────────────────────────────────────────
.chart-row {
  margin-bottom: 0 !important;
}

.card-header {
  display: flex;
  align-items: center;
  gap: 8px;
}

.trend-chart {
  width: 100%;
}

.trend-svg {
  width: 100%;
  height: 220px;
  display: block;
}

.trend-legend {
  display: flex;
  gap: 16px;
  justify-content: center;
  margin-top: 8px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #606266;

  &.green .legend-dot { background: #67c23a; }
  &.blue .legend-dot { background: #409eff; }
}

.legend-dot {
  width: 24px;
  height: 2px;
  border-radius: 1px;

  &.dashed {
    background: repeating-linear-gradient(
      to right,
      #409eff 0,
      #409eff 4px,
      transparent 4px,
      transparent 6px
    );
    height: 2px;
  }
}

// ── 站点占比 ─────────────────────────────────────────
.distribution-card {
  :deep(.el-card__body) {
    padding: 16px;
  }
}

.distribution-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.distribution-item {
  padding: 4px 0;
}

.dist-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}

.dist-name {
  font-size: 13px;
  color: #303133;
  font-weight: 500;
}

.dist-amount {
  font-size: 13px;
  color: #606266;
}

.dist-percent {
  font-size: 11px;
  color: #909399;
  text-align: right;
}

// ── 快捷入口 ─────────────────────────────────────────
.quick-entry-card {
  :deep(.el-card__body) {
    padding: 16px;
  }
}

.quick-entries {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.entry-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 20px;
  border-radius: 10px;
  border: 1px solid #e4e7ed;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 100px;

  &:hover {
    border-color: #409eff;
    background: #ecf5ff;
    transform: translateY(-2px);

    .entry-label {
      color: #409eff;
    }
  }
}

.entry-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;

  &.green { background: #f0f9eb; color: #67c23a; }
  &.blue  { background: #ecf5ff; color: #409eff; }
  &.orange { background: #fdf6ec; color: #e6a23c; }
  &.purple { background: #f3e5f5; color: #9c27b0; }
}

.entry-label {
  font-size: 13px;
  color: #303133;
  font-weight: 500;
}
</style>
