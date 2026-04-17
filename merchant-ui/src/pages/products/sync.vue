<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Delete, Edit } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { useUserStore } from '@/stores/user'
import {
  getSyncLogs,
  getSyncRules,
  createSyncRule,
  updateSyncRule,
  deleteSyncRule,
  type SyncLog,
  type SyncRule,
  type SyncRuleFormData,
  type SyncStatus,
  type SyncField,
} from '@/api/product'

const userStore = useUserStore()

// ─── 顶部 Tab ─────────────────────────────────────────────────────────────────

const activeTab = ref<'logs' | 'rules'>('logs')

// ═══════════════════════════════════════════════════════════════════════════════
//  同步日志 Tab
// ═══════════════════════════════════════════════════════════════════════════════

// ─── 筛选 ─────────────────────────────────────────────────────────────────────

const logFilters = reactive({
  store_id: '' as number | '',
  status: '' as SyncStatus | '',
  date_range: [] as string[],
})

// ─── 分页 ─────────────────────────────────────────────────────────────────────

const logPagination = reactive({
  page: 1,
  per_page: 20,
  total: 0,
})

// ─── 数据 ─────────────────────────────────────────────────────────────────────

const logLoading = ref(false)
const logList = ref<SyncLog[]>([])
const expandedRows = ref<number[]>([])

// ─── 加载日志 ─────────────────────────────────────────────────────────────────

async function loadLogs() {
  logLoading.value = true
  try {
    const res = await getSyncLogs({
      page: logPagination.page,
      per_page: logPagination.per_page,
      status: logFilters.status || undefined,
      store_id: logFilters.store_id || undefined,
      date_start: logFilters.date_range?.[0] || undefined,
      date_end: logFilters.date_range?.[1] || undefined,
    })
    logList.value = res.data.list
    logPagination.total = res.data.total
  } finally {
    logLoading.value = false
  }
}

function handleLogSearch() {
  logPagination.page = 1
  loadLogs()
}

function resetLogFilters() {
  logFilters.store_id = ''
  logFilters.status = ''
  logFilters.date_range = []
  logPagination.page = 1
  loadLogs()
}

function handleLogPageChange(page: number) {
  logPagination.page = page
  loadLogs()
}

// ─── 工具 ─────────────────────────────────────────────────────────────────────

const statusMap: Record<SyncStatus, { label: string; type: 'success' | 'danger' | 'warning' }> = {
  success: { label: '成功', type: 'success' },
  failed: { label: '失败', type: 'danger' },
  pending: { label: '处理中', type: 'warning' },
}

function formatDuration(ms?: number): string {
  if (!ms) return '—'
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

// ═══════════════════════════════════════════════════════════════════════════════
//  同步规则 Tab
// ═══════════════════════════════════════════════════════════════════════════════

// ─── 数据 ─────────────────────────────────────────────────────────────────────

const ruleLoading = ref(false)
const ruleList = ref<SyncRule[]>([])

async function loadRules() {
  ruleLoading.value = true
  try {
    const res = await getSyncRules()
    ruleList.value = res.data
  } finally {
    ruleLoading.value = false
  }
}

// ─── 规则弹窗 ─────────────────────────────────────────────────────────────────

const ruleDialogVisible = ref(false)
const ruleDialogTitle = ref('新增同步规则')
const editingRuleId = ref<number | null>(null)
const ruleFormRef = ref<FormInstance>()
const ruleSaving = ref(false)

const ruleForm = reactive<SyncRuleFormData>({
  name: '',
  target_store_ids: [],
  exclude_store_ids: [],
  sync_fields: ['name', 'description', 'price', 'images', 'variants'],
  pricing_strategy: 'original',
  pricing_value: undefined,
  status: 'active',
})

const ruleFormRules: FormRules = {
  name: [{ required: true, message: '请输入规则名称', trigger: 'blur' }],
  target_store_ids: [{ required: true, type: 'array', min: 1, message: '请选择至少一个目标站点', trigger: 'change' }],
  sync_fields: [{ required: true, type: 'array', min: 1, message: '请选择至少一个同步字段', trigger: 'change' }],
}

const syncFieldOptions: Array<{ label: string; value: SyncField }> = [
  { label: '商品名称', value: 'name' },
  { label: '描述内容', value: 'description' },
  { label: '价格', value: 'price' },
  { label: '图片', value: 'images' },
  { label: '变体/SKU', value: 'variants' },
]

function openAddRule() {
  editingRuleId.value = null
  ruleDialogTitle.value = '新增同步规则'
  resetRuleForm()
  ruleDialogVisible.value = true
}

function openEditRule(rule: SyncRule) {
  editingRuleId.value = rule.id
  ruleDialogTitle.value = '编辑同步规则'
  ruleForm.name = rule.name
  ruleForm.target_store_ids = [...rule.target_store_ids]
  ruleForm.exclude_store_ids = [...rule.exclude_store_ids]
  ruleForm.sync_fields = [...rule.sync_fields]
  ruleForm.pricing_strategy = rule.pricing_strategy
  ruleForm.pricing_value = rule.pricing_value
  ruleForm.status = rule.status
  ruleDialogVisible.value = true
}

function resetRuleForm() {
  ruleForm.name = ''
  ruleForm.target_store_ids = []
  ruleForm.exclude_store_ids = []
  ruleForm.sync_fields = ['name', 'description', 'price', 'images', 'variants']
  ruleForm.pricing_strategy = 'original'
  ruleForm.pricing_value = undefined
  ruleForm.status = 'active'
}

async function handleRuleSave() {
  const valid = await ruleFormRef.value?.validate().catch(() => false)
  if (!valid) return

  ruleSaving.value = true
  try {
    const payload = { ...ruleForm }
    if (editingRuleId.value) {
      await updateSyncRule(editingRuleId.value, payload)
      ElMessage.success('规则更新成功')
    } else {
      await createSyncRule(payload)
      ElMessage.success('规则创建成功')
    }
    ruleDialogVisible.value = false
    loadRules()
  } finally {
    ruleSaving.value = false
  }
}

async function handleDeleteRule(rule: SyncRule) {
  await ElMessageBox.confirm(`确定要删除规则「${rule.name}」吗？`, '删除确认', {
    type: 'warning',
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
  })
  await deleteSyncRule(rule.id)
  ElMessage.success('删除成功')
  loadRules()
}

// ─── 显示站点名称 ──────────────────────────────────────────────────────────────

function getStoreNames(ids: number[]): string {
  if (!ids || ids.length === 0) return '—'
  return ids
    .map(id => userStore.stores.find(s => s.id === id)?.name || `站点#${id}`)
    .join(', ')
}

// ─── 初始化 ───────────────────────────────────────────────────────────────────

onMounted(() => {
  loadLogs()
  loadRules()
})
</script>

<template>
  <div class="page-container">
    <!-- 页面头部 -->
    <div class="page-header">
      <h2 class="page-title">同步管理</h2>
    </div>

    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- ══════════════════════════════════════════════════════════════════
          同步日志 Tab
        ═════════════════════════════════════════════════════════════════════ -->
        <el-tab-pane label="同步日志" name="logs">
          <!-- 筛选栏 -->
          <div class="filter-bar">
            <el-select
              v-model="logFilters.store_id"
              placeholder="全部站点"
              clearable
              class="filter-select"
              @change="handleLogSearch"
            >
              <el-option
                v-for="store in userStore.stores"
                :key="store.id"
                :label="store.name"
                :value="store.id"
              />
            </el-select>
            <el-select
              v-model="logFilters.status"
              placeholder="全部状态"
              clearable
              class="filter-select"
              @change="handleLogSearch"
            >
              <el-option label="成功" value="success" />
              <el-option label="失败" value="failed" />
              <el-option label="处理中" value="pending" />
            </el-select>
            <el-date-picker
              v-model="logFilters.date_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="YYYY-MM-DD"
              class="filter-date"
              @change="handleLogSearch"
            />
            <el-button :icon="Refresh" @click="resetLogFilters">重置</el-button>
          </div>

          <!-- 日志表格 -->
          <el-table
            v-loading="logLoading"
            :data="logList"
            row-key="id"
            border
            :expand-row-keys="expandedRows.map(String)"
          >
            <el-table-column type="expand">
              <template #default="{ row }">
                <div class="expand-error" v-if="row.error_message">
                  <strong>错误信息：</strong>
                  <span class="error-text">{{ row.error_message }}</span>
                </div>
                <div v-else class="expand-error text-muted">无错误信息</div>
              </template>
            </el-table-column>

            <el-table-column label="商品名称" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ row.product_name }}</template>
            </el-table-column>

            <el-table-column label="目标站点" width="140" show-overflow-tooltip>
              <template #default="{ row }">{{ row.store_name }}</template>
            </el-table-column>

            <el-table-column label="同步状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="statusMap[row.status as SyncStatus]?.type">
                  {{ statusMap[row.status as SyncStatus]?.label }}
                </el-tag>
              </template>
            </el-table-column>

            <el-table-column label="同步时间" width="170">
              <template #default="{ row }">{{ row.synced_at || row.created_at }}</template>
            </el-table-column>

            <el-table-column label="耗时" width="90" align="right">
              <template #default="{ row }">
                <span :class="{ 'text-muted': !row.duration_ms }">
                  {{ formatDuration(row.duration_ms) }}
                </span>
              </template>
            </el-table-column>

            <el-table-column label="错误摘要" min-width="200" show-overflow-tooltip>
              <template #default="{ row }">
                <span v-if="row.error_message" class="error-brief">{{ row.error_message }}</span>
                <span v-else class="text-muted">—</span>
              </template>
            </el-table-column>
          </el-table>

          <!-- 分页 -->
          <div class="pagination-wrap">
            <el-pagination
              v-model:current-page="logPagination.page"
              v-model:page-size="logPagination.per_page"
              :total="logPagination.total"
              :page-sizes="[20, 50, 100]"
              layout="total, sizes, prev, pager, next, jumper"
              background
              @current-change="handleLogPageChange"
            />
          </div>
        </el-tab-pane>

        <!-- ══════════════════════════════════════════════════════════════════
          同步规则 Tab
        ═════════════════════════════════════════════════════════════════════ -->
        <el-tab-pane label="同步规则" name="rules">
          <div class="rules-header">
            <el-button type="primary" :icon="Plus" @click="openAddRule">新增规则</el-button>
            <el-button :icon="Refresh" @click="loadRules">刷新</el-button>
          </div>

          <el-table v-loading="ruleLoading" :data="ruleList" border>
            <el-table-column label="规则名称" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">{{ row.name }}</template>
            </el-table-column>

            <el-table-column label="目标站点" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ getStoreNames(row.target_store_ids) }}</template>
            </el-table-column>

            <el-table-column label="排除站点" min-width="140" show-overflow-tooltip>
              <template #default="{ row }">
                <span v-if="row.exclude_store_ids?.length">{{ getStoreNames(row.exclude_store_ids) }}</span>
                <span v-else class="text-muted">—</span>
              </template>
            </el-table-column>

            <el-table-column label="同步字段" min-width="160" show-overflow-tooltip>
              <template #default="{ row }">
                <el-tag
                  v-for="field in row.sync_fields"
                  :key="field"
                  size="small"
                  style="margin-right: 4px;"
                >
                  {{ syncFieldOptions.find(o => o.value === field)?.label || field }}
                </el-tag>
              </template>
            </el-table-column>

            <el-table-column label="价格策略" width="120">
              <template #default="{ row }">
                <span v-if="row.pricing_strategy === 'original'">原价</span>
                <span v-else-if="row.pricing_strategy === 'multiplier'">{{ row.pricing_value }}× 倍率</span>
                <span v-else>固定 ¥{{ row.pricing_value }}</span>
              </template>
            </el-table-column>

            <el-table-column label="状态" width="90" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : 'info'">
                  {{ row.status === 'active' ? '启用' : '停用' }}
                </el-tag>
              </template>
            </el-table-column>

            <el-table-column label="操作" width="120" align="center" fixed="right">
              <template #default="{ row }">
                <el-button link type="primary" :icon="Edit" @click="openEditRule(row)">编辑</el-button>
                <el-button link type="danger" :icon="Delete" @click="handleDeleteRule(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- ═══ 规则编辑弹窗 ════════════════════════════════════════════════════ -->
    <el-dialog
      v-model="ruleDialogVisible"
      :title="ruleDialogTitle"
      width="600px"
      :close-on-click-modal="false"
    >
      <el-form
        ref="ruleFormRef"
        :model="ruleForm"
        :rules="ruleFormRules"
        label-position="top"
      >
        <el-form-item label="规则名称" prop="name">
          <el-input v-model="ruleForm.name" placeholder="请输入规则名称" />
        </el-form-item>

        <el-form-item label="目标站点" prop="target_store_ids">
          <el-select
            v-model="ruleForm.target_store_ids"
            multiple
            placeholder="请选择目标站点"
            class="full-width"
          >
            <el-option
              v-for="store in userStore.stores"
              :key="store.id"
              :label="`${store.name}（${store.domain}）`"
              :value="store.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="排除站点">
          <el-select
            v-model="ruleForm.exclude_store_ids"
            multiple
            placeholder="不排除任何站点（可选）"
            class="full-width"
          >
            <el-option
              v-for="store in userStore.stores"
              :key="store.id"
              :label="`${store.name}（${store.domain}）`"
              :value="store.id"
            />
          </el-select>
        </el-form-item>

        <el-form-item label="同步字段" prop="sync_fields">
          <el-checkbox-group v-model="ruleForm.sync_fields">
            <el-checkbox
              v-for="opt in syncFieldOptions"
              :key="opt.value"
              :label="opt.value"
            >
              {{ opt.label }}
            </el-checkbox>
          </el-checkbox-group>
        </el-form-item>

        <el-form-item label="价格策略">
          <el-radio-group v-model="ruleForm.pricing_strategy">
            <el-radio label="original">使用原价</el-radio>
            <el-radio label="multiplier">按倍率</el-radio>
            <el-radio label="fixed">固定价格</el-radio>
          </el-radio-group>
          <div v-if="ruleForm.pricing_strategy !== 'original'" style="margin-top: 8px;">
            <el-input-number
              v-model="ruleForm.pricing_value"
              :min="0"
              :precision="2"
              :step="0.1"
              style="width: 160px;"
            />
            <span class="pricing-suffix">
              {{ ruleForm.pricing_strategy === 'multiplier' ? '× 倍' : '元（固定）' }}
            </span>
          </div>
        </el-form-item>

        <el-form-item label="规则状态">
          <el-radio-group v-model="ruleForm.status">
            <el-radio label="active">启用</el-radio>
            <el-radio label="inactive">停用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="ruleDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="ruleSaving" @click="handleRuleSave">
          {{ editingRuleId ? '保存修改' : '创建规则' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.page-container {
  padding: 20px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;

  .page-title {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0;
  }
}

.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;

  .filter-select {
    width: 150px;
  }

  .filter-date {
    width: 260px;
  }
}

.rules-header {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}

.expand-error {
  padding: 12px 20px;
  font-size: 13px;
  color: #606266;

  .error-text {
    color: #f56c6c;
    margin-left: 8px;
  }
}

.error-brief {
  color: #f56c6c;
  font-size: 12px;
}

.text-muted {
  color: #c0c4cc;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}

.full-width {
  width: 100%;
}

.pricing-suffix {
  margin-left: 8px;
  font-size: 13px;
  color: #606266;
}
</style>
