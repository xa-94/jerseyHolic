<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, Delete, Edit, Sort, Search } from '@element-plus/icons-vue'
import {
  getProductList,
  deleteProduct,
  batchUpdateStatus,
  getCategoryL1List,
  type MasterProduct,
  type ProductStatus,
  type CategoryL1,
} from '@/api/product'
import SyncDialog from './components/SyncDialog.vue'

const router = useRouter()

// ─── 筛选条件 ────────────────────────────────────────────────────────────────

const filters = reactive({
  search: '',
  category_l1: '' as number | '',
  status: '' as ProductStatus | '',
})

// ─── 分页 ────────────────────────────────────────────────────────────────────

const pagination = reactive({
  page: 1,
  per_page: 20,
  total: 0,
})

// ─── 表格数据 ─────────────────────────────────────────────────────────────────

const loading = ref(false)
const productList = ref<MasterProduct[]>([])
const selectedRows = ref<MasterProduct[]>([])

// ─── 品类数据 ─────────────────────────────────────────────────────────────────

const categoryL1List = ref<CategoryL1[]>([])

// ─── 同步弹窗 ─────────────────────────────────────────────────────────────────

const syncDialogVisible = ref(false)
const syncProducts = ref<MasterProduct[]>([])

// ─── 加载数据 ─────────────────────────────────────────────────────────────────

async function loadProducts() {
  loading.value = true
  try {
    const res = await getProductList({
      page: pagination.page,
      per_page: pagination.per_page,
      search: filters.search || undefined,
      category_l1: filters.category_l1 || undefined,
      status: filters.status || undefined,
    })
    productList.value = res.data.list
    pagination.total = res.data.total
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  const res = await getCategoryL1List()
  categoryL1List.value = res.data
}

onMounted(() => {
  loadProducts()
  loadCategories()
})

// ─── 筛选 & 分页 ──────────────────────────────────────────────────────────────

function handleSearch() {
  pagination.page = 1
  loadProducts()
}

function handlePageChange(page: number) {
  pagination.page = page
  loadProducts()
}

function handleSizeChange(size: number) {
  pagination.per_page = size
  pagination.page = 1
  loadProducts()
}

function resetFilters() {
  filters.search = ''
  filters.category_l1 = ''
  filters.status = ''
  pagination.page = 1
  loadProducts()
}

// ─── 选中行 ───────────────────────────────────────────────────────────────────

function handleSelectionChange(rows: MasterProduct[]) {
  selectedRows.value = rows
}

// ─── 编辑 / 新增 ──────────────────────────────────────────────────────────────

function goEdit(id?: number) {
  if (id) {
    router.push(`/products/edit/${id}`)
  } else {
    router.push('/products/edit')
  }
}

// ─── 删除 ─────────────────────────────────────────────────────────────────────

async function handleDelete(row: MasterProduct) {
  await ElMessageBox.confirm(`确定要删除商品「${row.name}」吗？`, '删除确认', {
    type: 'warning',
    confirmButtonText: '确定删除',
    cancelButtonText: '取消',
  })
  await deleteProduct(row.id)
  ElMessage.success('删除成功')
  loadProducts()
}

// ─── 批量操作 ─────────────────────────────────────────────────────────────────

async function handleBatchStatus(status: ProductStatus) {
  if (selectedRows.value.length === 0) {
    ElMessage.warning('请先选择商品')
    return
  }
  const label = status === 'active' ? '上架' : '下架'
  await ElMessageBox.confirm(`确定要批量${label} ${selectedRows.value.length} 个商品吗？`, '批量操作', {
    type: 'warning',
  })
  await batchUpdateStatus({
    ids: selectedRows.value.map(r => r.id),
    status,
  })
  ElMessage.success(`批量${label}成功`)
  loadProducts()
}

function handleBatchSync() {
  if (selectedRows.value.length === 0) {
    ElMessage.warning('请先选择商品')
    return
  }
  syncProducts.value = [...selectedRows.value]
  syncDialogVisible.value = true
}

function handleSingleSync(row: MasterProduct) {
  syncProducts.value = [row]
  syncDialogVisible.value = true
}

function onSyncSuccess() {
  syncDialogVisible.value = false
  ElMessage.success('同步任务已提交')
  loadProducts()
}

// ─── 工具函数 ─────────────────────────────────────────────────────────────────

const statusMap: Record<ProductStatus, { label: string; type: 'success' | 'info' | 'warning' }> = {
  active: { label: '已上架', type: 'success' },
  inactive: { label: '已下架', type: 'info' },
  draft: { label: '草稿', type: 'warning' },
}

function formatPrice(price: number) {
  return `¥ ${price.toFixed(2)}`
}

function getImageUrls(product: MasterProduct): string[] {
  return product.images?.map(i => i.url) ?? []
}

const hasSelection = computed(() => selectedRows.value.length > 0)
</script>

<template>
  <div class="page-container">
    <!-- 页面头部 -->
    <div class="page-header">
      <h2 class="page-title">商品管理</h2>
      <div class="page-header-actions">
        <el-button type="primary" :icon="Plus" @click="goEdit()">新增商品</el-button>
        <el-button :icon="Refresh" @click="loadProducts">刷新</el-button>
      </div>
    </div>

    <el-card shadow="never">
      <!-- 筛选栏 -->
      <div class="filter-bar">
        <el-input
          v-model="filters.search"
          placeholder="搜索商品名称 / SKU"
          :prefix-icon="Search"
          clearable
          class="filter-search"
          @keyup.enter="handleSearch"
          @clear="handleSearch"
        />
        <el-select
          v-model="filters.category_l1"
          placeholder="全部品类"
          clearable
          class="filter-select"
          @change="handleSearch"
        >
          <el-option
            v-for="cat in categoryL1List"
            :key="cat.id"
            :label="cat.name"
            :value="cat.id"
          />
        </el-select>
        <el-select
          v-model="filters.status"
          placeholder="全部状态"
          clearable
          class="filter-select"
          @change="handleSearch"
        >
          <el-option label="已上架" value="active" />
          <el-option label="已下架" value="inactive" />
          <el-option label="草稿" value="draft" />
        </el-select>
        <el-button @click="resetFilters">重置</el-button>
      </div>

      <!-- 批量操作栏 -->
      <div v-if="hasSelection" class="batch-bar">
        <span class="batch-info">已选 {{ selectedRows.length }} 项</span>
        <el-button size="small" type="success" @click="handleBatchStatus('active')">批量上架</el-button>
        <el-button size="small" type="info" @click="handleBatchStatus('inactive')">批量下架</el-button>
        <el-button size="small" type="primary" :icon="Sort" @click="handleBatchSync">同步到站点</el-button>
      </div>

      <!-- 商品表格 -->
      <el-table
        v-loading="loading"
        :data="productList"
        row-key="id"
        border
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="50" align="center" />

        <!-- 图片 -->
        <el-table-column label="图片" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.images?.[0]?.url"
              :src="row.images[0].url"
              :preview-src-list="getImageUrls(row)"
              style="width: 60px; height: 60px; border-radius: 4px;"
              fit="cover"
              lazy
            />
            <div v-else class="img-placeholder">
              <el-icon><Goods /></el-icon>
            </div>
          </template>
        </el-table-column>

        <!-- 商品名称 -->
        <el-table-column label="商品名称" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">
            <el-link type="primary" @click="goEdit(row.id)">{{ row.name }}</el-link>
          </template>
        </el-table-column>

        <!-- SKU -->
        <el-table-column prop="sku" label="SKU" width="140" show-overflow-tooltip />

        <!-- 品类 -->
        <el-table-column label="品类" width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.category_l1_name">
              {{ row.category_l1_name }}
              <span v-if="row.category_l2_name" class="category-sep"> / {{ row.category_l2_name }}</span>
            </span>
            <span v-else class="text-muted">—</span>
          </template>
        </el-table-column>

        <!-- 价格 -->
        <el-table-column label="价格" width="110" align="right">
          <template #default="{ row }">
            <span class="price-text">{{ formatPrice(row.price) }}</span>
          </template>
        </el-table-column>

        <!-- 状态 -->
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusMap[row.status as ProductStatus]?.type">
              {{ statusMap[row.status as ProductStatus]?.label }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 已同步站点 -->
        <el-table-column label="已同步站点" width="110" align="center">
          <template #default="{ row }">
            <el-badge
              v-if="row.synced_stores_count > 0"
              :value="row.synced_stores_count"
              type="primary"
            />
            <span v-else class="text-muted">0</span>
          </template>
        </el-table-column>

        <!-- 操作列 -->
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" :icon="Edit" @click="goEdit(row.id)">编辑</el-button>
            <el-button link type="success" :icon="Sort" @click="handleSingleSync(row)">同步</el-button>
            <el-button link type="danger" :icon="Delete" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[10, 20, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <!-- 同步弹窗 -->
    <SyncDialog
      v-model:visible="syncDialogVisible"
      :products="syncProducts"
      @success="onSyncSuccess"
    />
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

  .page-header-actions {
    display: flex;
    gap: 8px;
  }
}

.filter-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;

  .filter-search {
    width: 260px;
  }

  .filter-select {
    width: 160px;
  }
}

.batch-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #ecf5ff;
  border-radius: 6px;
  margin-bottom: 12px;

  .batch-info {
    font-size: 13px;
    color: #409eff;
    margin-right: 4px;
  }
}

.img-placeholder {
  width: 60px;
  height: 60px;
  background: #f5f7fa;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  font-size: 20px;
}

.price-text {
  font-weight: 500;
  color: #e6502f;
}

.category-sep {
  color: #909399;
}

.text-muted {
  color: #c0c4cc;
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
