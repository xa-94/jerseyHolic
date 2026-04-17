<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '@/components/common/PageHeader.vue'
import DataTable from '@/components/common/DataTable.vue'
import StoreCreateWizard from './components/StoreCreateWizard.vue'
import SettlementAudit from './components/SettlementAudit.vue'
import {
  getMerchantDetail,
  getMerchantStores,
  getMerchantSettlements,
  getMerchantRiskProfile,
  getMerchantLogs,
  type Merchant,
  type Store,
  type Settlement,
  type RiskProfile,
  type OperationLog,
} from '@/api/merchant'
import type { MerchantStatus, MerchantTier, StoreStatus, SettlementStatus, RiskLevel } from '@/types/merchant'

const route = useRoute()
const router = useRouter()

const merchantId = computed(() => Number(route.params.id))
const activeTab = ref('basic')

// ==================== 商户详情 ====================
const merchant = ref<Merchant | null>(null)
const detailLoading = ref(false)

async function loadDetail() {
  detailLoading.value = true
  try {
    const res = await getMerchantDetail(merchantId.value)
    merchant.value = res.data
  } catch {
    merchant.value = null
  } finally {
    detailLoading.value = false
  }
}

// ==================== Tab 2: 站点列表 ====================
const stores = ref<Store[]>([])
const storesLoading = ref(false)
const wizardRef = ref<InstanceType<typeof StoreCreateWizard> | null>(null)

const storeColumns = [
  { label: '域名', prop: 'domain', minWidth: 180 },
  { label: '站点名称', prop: 'name', minWidth: 140 },
  { label: '状态', slot: 'storeStatus', width: 100, align: 'center' as const },
  { label: '主营品类', prop: 'category', width: 120 },
  { label: '目标市场', prop: 'market', width: 100 },
  { label: '语言', prop: 'language', width: 100 },
  { label: '货币', prop: 'currency', width: 80 },
  { label: '创建时间', prop: 'created_at', width: 160 },
]

async function loadStores() {
  storesLoading.value = true
  try {
    const res = await getMerchantStores(merchantId.value)
    stores.value = res.data ?? []
  } catch {
    stores.value = []
  } finally {
    storesLoading.value = false
  }
}

function handleCreateStore() {
  wizardRef.value?.open()
}

function handleStoreCreated() {
  loadStores()
}

// ==================== Tab 3: 结算记录 ====================
const settlements = ref<Settlement[]>([])
const settlementsLoading = ref(false)
const settlementTotal = ref(0)
const settlementPage = ref(1)
const settlementPageSize = ref(10)
const auditRef = ref<InstanceType<typeof SettlementAudit> | null>(null)

const settlementColumns = [
  { label: '结算周期', slot: 'period', minWidth: 180 },
  { label: '应结总额', slot: 'totalAmount', width: 130, align: 'right' as const },
  { label: '平台佣金', slot: 'commission', width: 130, align: 'right' as const },
  { label: '实际净额', slot: 'netAmount', width: 130, align: 'right' as const },
  { label: '状态', slot: 'settlementStatus', width: 100, align: 'center' as const },
  { label: '操作', slot: 'settlementAction', width: 90, align: 'center' as const },
]

async function loadSettlements() {
  settlementsLoading.value = true
  try {
    const res = await getMerchantSettlements(merchantId.value, {
      page: settlementPage.value,
      per_page: settlementPageSize.value,
    })
    const d = res.data
    settlements.value = (d as any).list ?? []
    settlementTotal.value = d.total ?? 0
  } catch {
    settlements.value = []
    settlementTotal.value = 0
  } finally {
    settlementsLoading.value = false
  }
}

function handleSettlementPageChange(page: number) {
  settlementPage.value = page
  loadSettlements()
}

function handleAuditSettlement(row: Settlement) {
  auditRef.value?.open(row)
}

function handleAuditSuccess() {
  loadSettlements()
}

// ==================== Tab 4: 风控数据 ====================
const riskProfile = ref<RiskProfile | null>(null)
const riskLoading = ref(false)

async function loadRiskProfile() {
  riskLoading.value = true
  try {
    const res = await getMerchantRiskProfile(merchantId.value)
    riskProfile.value = res.data
  } catch {
    riskProfile.value = null
  } finally {
    riskLoading.value = false
  }
}

// ==================== Tab 5: 操作日志 ====================
const logs = ref<OperationLog[]>([])
const logsLoading = ref(false)
const logsTotal = ref(0)
const logsPage = ref(1)
const logsPageSize = ref(20)

async function loadLogs() {
  logsLoading.value = true
  try {
    const res = await getMerchantLogs(merchantId.value, {
      page: logsPage.value,
      per_page: logsPageSize.value,
    })
    const d = res.data
    logs.value = (d as any).list ?? []
    logsTotal.value = d.total ?? 0
  } catch {
    logs.value = []
    logsTotal.value = 0
  } finally {
    logsLoading.value = false
  }
}

function handleLogsPageChange(page: number) {
  logsPage.value = page
  loadLogs()
}

// ==================== Tab 切换懒加载 ====================
const loadedTabs = ref<Set<string>>(new Set(['basic']))

function handleTabChange(tab: string) {
  if (loadedTabs.value.has(tab)) return
  loadedTabs.value.add(tab)
  if (tab === 'stores') loadStores()
  if (tab === 'settlements') loadSettlements()
  if (tab === 'risk') loadRiskProfile()
  if (tab === 'logs') loadLogs()
}

// ==================== 标签工具函数 ====================
function getStatusType(status: MerchantStatus): string {
  const map: Record<MerchantStatus, string> = {
    pending: 'warning', approved: 'success', rejected: 'danger', frozen: 'info',
  }
  return map[status] ?? 'info'
}
function getStatusLabel(status: MerchantStatus): string {
  const map: Record<MerchantStatus, string> = {
    pending: '待审核', approved: '已通过', rejected: '已拒绝', frozen: '已冻结',
  }
  return map[status] ?? status
}
function getTierLabel(tier: MerchantTier): string {
  const map: Record<MerchantTier, string> = {
    standard: '标准', silver: '银牌', gold: '金牌', diamond: '钻石',
  }
  return map[tier] ?? tier
}
function getTierType(tier: MerchantTier): string {
  const map: Record<MerchantTier, string> = {
    standard: '', silver: 'info', gold: 'warning', diamond: 'primary',
  }
  return map[tier] ?? ''
}
function getStoreStatusType(status: StoreStatus): string {
  const map: Record<StoreStatus, string> = { active: 'success', inactive: 'info', suspended: 'danger' }
  return map[status] ?? 'info'
}
function getStoreStatusLabel(status: StoreStatus): string {
  const map: Record<StoreStatus, string> = { active: '运营中', inactive: '已停用', suspended: '已暂停' }
  return map[status] ?? status
}
function getSettlementStatusType(status: SettlementStatus): string {
  const map: Record<SettlementStatus, string> = {
    pending: 'warning', approved: 'success', rejected: 'danger', paid: 'primary',
  }
  return map[status] ?? 'info'
}
function getSettlementStatusLabel(status: SettlementStatus): string {
  const map: Record<SettlementStatus, string> = {
    pending: '待审核', approved: '已通过', rejected: '已拒绝', paid: '已打款',
  }
  return map[status] ?? status
}
function getRiskLevelType(level: RiskLevel): string {
  const map: Record<RiskLevel, string> = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' }
  return map[level] ?? 'info'
}
function getRiskLevelLabel(level: RiskLevel): string {
  const map: Record<RiskLevel, string> = { low: '低风险', medium: '中风险', high: '高风险', critical: '极高风险' }
  return map[level] ?? level
}
function formatAmount(amount: number): string {
  return amount.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function usagePercent(used: number, limit: number): number {
  if (!limit) return 0
  return Math.min(Math.round((used / limit) * 100), 100)
}
function usageStatus(percent: number): '' | 'exception' | 'warning' | 'success' {
  if (percent >= 90) return 'exception'
  if (percent >= 70) return 'warning'
  return 'success'
}

// ==================== 初始化 ====================
onMounted(() => {
  loadDetail()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      title="商户详情"
      :actions="[{ label: '返回列表', icon: 'ArrowLeft', onClick: () => router.push('/merchant') }]"
    />

    <el-skeleton :loading="detailLoading" animated :rows="4">
      <template #default>
        <!-- 顶部概览卡片 -->
        <el-card shadow="never" class="overview-card" v-if="merchant">
          <div class="overview-header">
            <div class="overview-info">
              <h2 class="overview-info__name">{{ merchant.company_name }}</h2>
              <div class="overview-info__meta">
                <el-tag :type="getStatusType(merchant.status) as any" size="small">
                  {{ getStatusLabel(merchant.status) }}
                </el-tag>
                <el-tag :type="getTierType(merchant.tier) as any" size="small" effect="plain" style="margin-left: 8px">
                  {{ getTierLabel(merchant.tier) }}
                </el-tag>
                <span class="overview-info__id">ID: {{ merchant.id }}</span>
              </div>
            </div>
            <div class="overview-stats">
              <div class="stat-item">
                <div class="stat-item__value">{{ merchant.store_count }}</div>
                <div class="stat-item__label">站点数</div>
              </div>
            </div>
          </div>
        </el-card>

        <!-- Tab 内容区 -->
        <el-card shadow="never" style="margin-top: 8px" v-if="merchant">
          <el-tabs v-model="activeTab" @tab-click="(pane: any) => handleTabChange(pane.paneName)">

            <!-- ===== Tab 1: 基本信息 ===== -->
            <el-tab-pane label="基本信息" name="basic">
              <el-descriptions :column="2" border label-width="100px" class="info-desc">
                <el-descriptions-item label="公司名称">{{ merchant.company_name }}</el-descriptions-item>
                <el-descriptions-item label="联系人">{{ merchant.contact_name }}</el-descriptions-item>
                <el-descriptions-item label="邮箱">{{ merchant.email }}</el-descriptions-item>
                <el-descriptions-item label="手机号">{{ merchant.phone }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                  <el-tag :type="getStatusType(merchant.status) as any" size="small">
                    {{ getStatusLabel(merchant.status) }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="等级">
                  <el-tag :type="getTierType(merchant.tier) as any" size="small" effect="plain">
                    {{ getTierLabel(merchant.tier) }}
                  </el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="地址" :span="2">
                  {{ merchant.address ?? '—' }}
                </el-descriptions-item>
                <el-descriptions-item label="国家/地区">{{ merchant.country ?? '—' }}</el-descriptions-item>
                <el-descriptions-item label="注册时间">{{ merchant.created_at }}</el-descriptions-item>
                <el-descriptions-item v-if="merchant.reviewer_name" label="审核人">
                  {{ merchant.reviewer_name }}
                </el-descriptions-item>
                <el-descriptions-item v-if="merchant.reviewed_at" label="审核时间">
                  {{ merchant.reviewed_at }}
                </el-descriptions-item>
                <el-descriptions-item
                  v-if="merchant.review_reason"
                  label="审核备注"
                  :span="2"
                >
                  {{ merchant.review_reason }}
                </el-descriptions-item>
              </el-descriptions>
            </el-tab-pane>

            <!-- ===== Tab 2: 站点列表 ===== -->
            <el-tab-pane label="站点列表" name="stores">
              <div class="tab-toolbar">
                <span class="tab-toolbar__title">站点列表（共 {{ stores.length }} 个）</span>
                <el-button type="primary" size="small" @click="handleCreateStore">
                  <el-icon><Plus /></el-icon> 创建站点
                </el-button>
              </div>
              <DataTable
                :data="stores"
                :columns="storeColumns"
                :total="0"
                :loading="storesLoading"
                row-key="id"
              >
                <template #storeStatus="{ row }">
                  <el-tag :type="getStoreStatusType(row.status) as any" size="small">
                    {{ getStoreStatusLabel(row.status) }}
                  </el-tag>
                </template>
              </DataTable>
            </el-tab-pane>

            <!-- ===== Tab 3: 结算记录 ===== -->
            <el-tab-pane label="结算记录" name="settlements">
              <DataTable
                :data="settlements"
                :columns="settlementColumns"
                :total="settlementTotal"
                :page="settlementPage"
                :page-size="settlementPageSize"
                :loading="settlementsLoading"
                row-key="id"
                @update:page="handleSettlementPageChange"
              >
                <template #period="{ row }">
                  {{ row.period_start }} ~ {{ row.period_end }}
                </template>
                <template #totalAmount="{ row }">
                  ¥{{ formatAmount(row.total_amount) }}
                </template>
                <template #commission="{ row }">
                  <span class="commission-text">
                    ¥{{ formatAmount(row.commission) }}
                    <span style="font-size: 11px; color: #909399">（{{ row.commission_rate }}%）</span>
                  </span>
                </template>
                <template #netAmount="{ row }">
                  <strong class="net-amount-text">¥{{ formatAmount(row.net_amount) }}</strong>
                </template>
                <template #settlementStatus="{ row }">
                  <el-tag :type="getSettlementStatusType(row.status) as any" size="small">
                    {{ getSettlementStatusLabel(row.status) }}
                  </el-tag>
                </template>
                <template #settlementAction="{ row }">
                  <el-button
                    v-if="row.status === 'pending'"
                    type="warning"
                    size="small"
                    link
                    @click="handleAuditSettlement(row)"
                  >
                    审核
                  </el-button>
                  <span v-else class="no-action">—</span>
                </template>
              </DataTable>
            </el-tab-pane>

            <!-- ===== Tab 4: 风控数据 ===== -->
            <el-tab-pane label="风控数据" name="risk">
              <div v-if="riskLoading" style="padding: 40px; text-align: center">
                <el-icon class="is-loading" :size="24"><Loading /></el-icon>
              </div>
              <template v-else-if="riskProfile">
                <!-- 风险评分 -->
                <div class="risk-score-section">
                  <el-card shadow="never" class="risk-score-card">
                    <div class="risk-score-content">
                      <div class="risk-score-content__score">{{ riskProfile.risk_score }}</div>
                      <div class="risk-score-content__label">风险评分</div>
                      <el-tag
                        :type="getRiskLevelType(riskProfile.risk_level) as any"
                        size="large"
                        style="margin-top: 8px"
                      >
                        {{ getRiskLevelLabel(riskProfile.risk_level) }}
                      </el-tag>
                    </div>
                    <el-progress
                      type="dashboard"
                      :percentage="riskProfile.risk_score"
                      :status="riskProfile.risk_score >= 70 ? 'exception' : ''"
                      :width="120"
                      style="margin-left: 40px"
                    />
                  </el-card>
                </div>

                <!-- 额度使用情况 -->
                <el-row :gutter="16" style="margin-top: 16px">
                  <el-col :span="12">
                    <el-card shadow="never">
                      <template #header>
                        <span>日交易限额</span>
                      </template>
                      <div class="limit-info">
                        <div class="limit-info__amounts">
                          <span>已用：¥{{ formatAmount(riskProfile.daily_used) }}</span>
                          <span>限额：¥{{ formatAmount(riskProfile.daily_limit) }}</span>
                        </div>
                        <el-progress
                          :percentage="usagePercent(riskProfile.daily_used, riskProfile.daily_limit)"
                          :status="usageStatus(usagePercent(riskProfile.daily_used, riskProfile.daily_limit))"
                          style="margin-top: 8px"
                        />
                      </div>
                    </el-card>
                  </el-col>
                  <el-col :span="12">
                    <el-card shadow="never">
                      <template #header>
                        <span>月交易限额</span>
                      </template>
                      <div class="limit-info">
                        <div class="limit-info__amounts">
                          <span>已用：¥{{ formatAmount(riskProfile.monthly_used) }}</span>
                          <span>限额：¥{{ formatAmount(riskProfile.monthly_limit) }}</span>
                        </div>
                        <el-progress
                          :percentage="usagePercent(riskProfile.monthly_used, riskProfile.monthly_limit)"
                          :status="usageStatus(usagePercent(riskProfile.monthly_used, riskProfile.monthly_limit))"
                          style="margin-top: 8px"
                        />
                      </div>
                    </el-card>
                  </el-col>
                </el-row>

                <!-- 风险标记 -->
                <el-card shadow="never" style="margin-top: 16px" v-if="riskProfile.flags?.length">
                  <template #header><span>风险标记</span></template>
                  <div class="flags-list">
                    <el-tag
                      v-for="flag in riskProfile.flags"
                      :key="flag"
                      type="danger"
                      size="small"
                      style="margin: 4px"
                    >
                      {{ flag }}
                    </el-tag>
                  </div>
                </el-card>

                <div class="risk-update-time">
                  最后检测时间：{{ riskProfile.last_checked_at }}
                </div>
              </template>
              <el-empty v-else description="暂无风控数据" />
            </el-tab-pane>

            <!-- ===== Tab 5: 操作日志 ===== -->
            <el-tab-pane label="操作日志" name="logs">
              <div v-if="logsLoading" style="padding: 40px; text-align: center">
                <el-icon class="is-loading" :size="24"><Loading /></el-icon>
              </div>
              <template v-else-if="logs.length > 0">
                <el-timeline style="padding: 16px 0">
                  <el-timeline-item
                    v-for="log in logs"
                    :key="log.id"
                    :timestamp="log.created_at"
                    placement="top"
                  >
                    <el-card shadow="never" class="log-card">
                      <div class="log-card__header">
                        <strong class="log-card__action">{{ log.action }}</strong>
                        <span class="log-card__operator">操作人：{{ log.operator }}</span>
                        <span v-if="log.ip" class="log-card__ip">IP：{{ log.ip }}</span>
                      </div>
                      <div class="log-card__desc">{{ log.description }}</div>
                    </el-card>
                  </el-timeline-item>
                </el-timeline>

                <!-- 日志分页 -->
                <div class="logs-pagination">
                  <el-pagination
                    v-model:current-page="logsPage"
                    :page-size="logsPageSize"
                    :total="logsTotal"
                    layout="prev, pager, next, total"
                    background
                    @current-change="handleLogsPageChange"
                  />
                </div>
              </template>
              <el-empty v-else description="暂无操作日志" />
            </el-tab-pane>

          </el-tabs>
        </el-card>
      </template>
    </el-skeleton>

    <!-- 站点创建向导 -->
    <StoreCreateWizard
      ref="wizardRef"
      :merchant-id="merchantId"
      @success="handleStoreCreated"
    />

    <!-- 结算审核弹窗 -->
    <SettlementAudit
      ref="auditRef"
      @success="handleAuditSuccess"
    />
  </div>
</template>

<style scoped lang="scss">
.overview-card {
  margin-bottom: 0;
}

.overview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.overview-info {
  &__name {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0 0 8px 0;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  &__id {
    font-size: 12px;
    color: #909399;
  }
}

.overview-stats {
  display: flex;
  gap: 32px;
}

.stat-item {
  text-align: center;

  &__value {
    font-size: 24px;
    font-weight: 600;
    color: #409eff;
  }

  &__label {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
  }
}

.info-desc {
  margin-top: 4px;
}

.tab-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;

  &__title {
    font-size: 14px;
    color: #303133;
    font-weight: 500;
  }
}

.commission-text {
  color: #e6a23c;
}

.net-amount-text {
  color: #67c23a;
}

.no-action {
  color: #c0c4cc;
  font-size: 13px;
}

.risk-score-section {
  .risk-score-card {
    :deep(.el-card__body) {
      display: flex;
      align-items: center;
    }
  }
}

.risk-score-content {
  text-align: center;
  min-width: 120px;

  &__score {
    font-size: 40px;
    font-weight: 700;
    color: #303133;
    line-height: 1;
  }

  &__label {
    font-size: 13px;
    color: #909399;
    margin-top: 4px;
  }
}

.limit-info {
  &__amounts {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    color: #606266;
  }
}

.flags-list {
  display: flex;
  flex-wrap: wrap;
}

.risk-update-time {
  margin-top: 12px;
  font-size: 12px;
  color: #909399;
  text-align: right;
}

.log-card {
  :deep(.el-card__body) {
    padding: 10px 14px;
  }

  &__header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 4px;
  }

  &__action {
    font-size: 13px;
    color: #303133;
  }

  &__operator,
  &__ip {
    font-size: 12px;
    color: #909399;
  }

  &__desc {
    font-size: 13px;
    color: #606266;
    line-height: 1.5;
  }
}

.logs-pagination {
  display: flex;
  justify-content: flex-end;
  padding: 8px 0 4px;
}
</style>
