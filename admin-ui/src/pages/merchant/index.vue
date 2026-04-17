<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import SearchForm from '@/components/common/SearchForm.vue'
import DataTable from '@/components/common/DataTable.vue'
import {
  getMerchants,
  reviewMerchant,
  type Merchant,
} from '@/api/merchant'
import type { MerchantStatus, MerchantTier } from '@/types/merchant'

const router = useRouter()

// ==================== 搜索参数 ====================
const searchForm = reactive({
  search: '',
  status: '' as MerchantStatus | '',
  tier: '' as MerchantTier | '',
})

// ==================== 表格数据 ====================
const loading = ref(false)
const tableData = ref<Merchant[]>([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)

// ==================== 加载数据 ====================
async function loadData() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: pageSize.value,
    }
    if (searchForm.search) params.search = searchForm.search
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.tier) params.tier = searchForm.tier

    const res = await getMerchants(params as any)
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
  searchForm.search = ''
  searchForm.status = ''
  searchForm.tier = ''
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

// ==================== 操作 ====================
function handleViewDetail(row: Merchant) {
  router.push(`/merchant/${row.id}`)
}

// 快速审核弹窗
const reviewDialogVisible = ref(false)
const reviewingMerchant = ref<Merchant | null>(null)
const reviewForm = reactive({
  action: '' as 'approve' | 'reject',
  reason: '',
})
const reviewLoading = ref(false)

function handleReview(row: Merchant) {
  reviewingMerchant.value = row
  reviewForm.action = '' as any
  reviewForm.reason = ''
  reviewDialogVisible.value = true
}

async function submitReview() {
  if (!reviewForm.action) {
    ElMessage.warning('请选择审核结果')
    return
  }
  if (reviewForm.action === 'reject' && !reviewForm.reason.trim()) {
    ElMessage.warning('拒绝时请填写原因')
    return
  }
  reviewLoading.value = true
  try {
    await reviewMerchant(reviewingMerchant.value!.id, {
      action: reviewForm.action,
      reason: reviewForm.reason || undefined,
    })
    ElMessage.success(reviewForm.action === 'approve' ? '审核通过' : '已拒绝')
    reviewDialogVisible.value = false
    loadData()
  } catch {
    // 请求失败由拦截器处理
  } finally {
    reviewLoading.value = false
  }
}

// ==================== 状态/等级标签 ====================
function getStatusType(status: MerchantStatus): string {
  const map: Record<MerchantStatus, string> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    frozen: 'info',
  }
  return map[status] ?? 'info'
}

function getStatusLabel(status: MerchantStatus): string {
  const map: Record<MerchantStatus, string> = {
    pending: '待审核',
    approved: '已通过',
    rejected: '已拒绝',
    frozen: '已冻结',
  }
  return map[status] ?? status
}

function getTierType(tier: MerchantTier): string {
  const map: Record<MerchantTier, string> = {
    standard: '',
    silver: 'info',
    gold: 'warning',
    diamond: 'primary',
  }
  return map[tier] ?? ''
}

function getTierLabel(tier: MerchantTier): string {
  const map: Record<MerchantTier, string> = {
    standard: '标准',
    silver: '银牌',
    gold: '金牌',
    diamond: '钻石',
  }
  return map[tier] ?? tier
}

// ==================== 表格列定义 ====================
const columns = [
  { label: '公司名称', slot: 'company', minWidth: 180 },
  { label: '联系人', prop: 'contact_name', width: 110 },
  { label: '邮箱', prop: 'email', minWidth: 160 },
  { label: '状态', slot: 'status', width: 100, align: 'center' as const },
  { label: '等级', slot: 'tier', width: 90, align: 'center' as const },
  { label: '站点数', prop: 'store_count', width: 80, align: 'center' as const },
  { label: '注册时间', prop: 'created_at', width: 160 },
  { label: '操作', slot: 'action', width: 150, fixed: 'right' as const, align: 'center' as const },
]

// ==================== 初始化 ====================
onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="page-container">
    <PageHeader title="商户管理" />

    <!-- 搜索区域 -->
    <SearchForm :loading="loading" @search="handleSearch" @reset="handleReset">
      <el-form-item label="关键词">
        <el-input
          v-model="searchForm.search"
          placeholder="公司名/联系人/邮箱"
          clearable
          style="width: 200px"
          @keyup.enter="handleSearch"
        />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px">
          <el-option label="待审核" value="pending" />
          <el-option label="已通过" value="approved" />
          <el-option label="已拒绝" value="rejected" />
          <el-option label="已冻结" value="frozen" />
        </el-select>
      </el-form-item>
      <el-form-item label="等级">
        <el-select v-model="searchForm.tier" placeholder="全部" clearable style="width: 110px">
          <el-option label="标准" value="standard" />
          <el-option label="银牌" value="silver" />
          <el-option label="金牌" value="gold" />
          <el-option label="钻石" value="diamond" />
        </el-select>
      </el-form-item>
    </SearchForm>

    <!-- 表格 -->
    <el-card shadow="never" style="margin-top: 8px">
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
      >
        <!-- 公司名称列 -->
        <template #company="{ row }">
          <div class="company-cell">
            <span class="company-cell__name">{{ row.company_name }}</span>
            <span v-if="row.country" class="company-cell__country">{{ row.country }}</span>
          </div>
        </template>

        <!-- 状态列 -->
        <template #status="{ row }">
          <el-tag :type="getStatusType(row.status) as any" size="small">
            {{ getStatusLabel(row.status) }}
          </el-tag>
        </template>

        <!-- 等级列 -->
        <template #tier="{ row }">
          <el-tag :type="getTierType(row.tier) as any" size="small" effect="plain">
            {{ getTierLabel(row.tier) }}
          </el-tag>
        </template>

        <!-- 操作列 -->
        <template #action="{ row }">
          <el-button type="primary" size="small" link @click="handleViewDetail(row)">
            <el-icon><View /></el-icon> 详情
          </el-button>
          <el-button
            v-if="row.status === 'pending'"
            type="warning"
            size="small"
            link
            @click="handleReview(row)"
          >
            <el-icon><Check /></el-icon> 审核
          </el-button>
        </template>
      </DataTable>
    </el-card>

    <!-- 审核弹窗 -->
    <el-dialog
      v-model="reviewDialogVisible"
      title="商户审核"
      width="480px"
      :close-on-click-modal="false"
    >
      <div v-if="reviewingMerchant" class="review-info">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="公司名称">{{ reviewingMerchant.company_name }}</el-descriptions-item>
          <el-descriptions-item label="联系人">{{ reviewingMerchant.contact_name }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ reviewingMerchant.email }}</el-descriptions-item>
        </el-descriptions>
      </div>

      <el-form style="margin-top: 20px" label-width="80px">
        <el-form-item label="审核结果" required>
          <el-radio-group v-model="reviewForm.action">
            <el-radio value="approve">
              <el-tag type="success" size="small">通过</el-tag>
            </el-radio>
            <el-radio value="reject">
              <el-tag type="danger" size="small">拒绝</el-tag>
            </el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="审核备注">
          <el-input
            v-model="reviewForm.reason"
            type="textarea"
            :rows="3"
            placeholder="拒绝时必填，通过时选填"
          />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="reviewDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="reviewLoading" @click="submitReview">
          确认提交
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.company-cell {
  display: flex;
  flex-direction: column;

  &__name {
    font-size: 13px;
    color: #303133;
    font-weight: 500;
  }

  &__country {
    font-size: 11px;
    color: #909399;
    margin-top: 2px;
  }
}

.review-info {
  margin-bottom: 4px;
}
</style>
