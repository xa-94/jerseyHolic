<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import SearchForm from '@/components/common/SearchForm.vue'
import DataTable from '@/components/common/DataTable.vue'
import {
  getProductList,
  deleteProduct,
  toggleProductStatus,
  batchDeleteProducts,
  batchUpdateProductStatus,
  type Product,
} from '@/api/product'
import { getCategoryTree, type Category } from '@/api/category'

const router = useRouter()

// ==================== 搜索参数 ====================
const searchForm = reactive({
  keyword: '',
  category_id: '' as number | '',
  status: '',
  min_price: '' as number | '',
  max_price: '' as number | '',
})

// ==================== 表格数据 ====================
const loading = ref(false)
const tableData = ref<Product[]>([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)
const selectedRows = ref<Product[]>([])
const tableRef = ref()

// ==================== 分类树 ====================
const categoryTree = ref<Category[]>([])

async function loadCategoryTree() {
  try {
    const res = await getCategoryTree()
    categoryTree.value = res.data || []
  } catch {
    // 静默处理
  }
}

// ==================== 加载数据 ====================
async function loadData() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: pageSize.value,
    }
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.category_id !== '') params.category_id = searchForm.category_id
    if (searchForm.status) params.status = searchForm.status
    if (searchForm.min_price !== '') params.min_price = searchForm.min_price
    if (searchForm.max_price !== '') params.max_price = searchForm.max_price

    const res = await getProductList(params as any)
    const d = res.data
    // 后端分页字段统一为 list
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
  searchForm.category_id = ''
  searchForm.status = ''
  searchForm.min_price = ''
  searchForm.max_price = ''
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

// ==================== 选择 ====================
function handleSelectionChange(rows: Product[]) {
  selectedRows.value = rows
}

const selectedIds = computed(() => selectedRows.value.map((r) => r.id))

// ==================== 操作 ====================
function handleCreate() {
  router.push('/product/create')
}

function handleEdit(row: Product) {
  router.push(`/product/edit/${row.id}`)
}

async function handleDelete(row: Product) {
  try {
    await ElMessageBox.confirm(`确认删除商品「${row.name}」？此操作不可撤销。`, '删除确认', {
      type: 'warning',
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
    })
    await deleteProduct(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch {
    // 用户取消或请求失败
  }
}

async function handleToggleStatus(row: Product) {
  try {
    await toggleProductStatus(row.id)
    ElMessage.success(`已${row.status === 'active' ? '禁用' : '启用'}`)
    loadData()
  } catch {
    // 恢复原值
    row.status = row.status === 'active' ? 'inactive' : 'active'
  }
}

async function handleBatchDelete() {
  if (selectedIds.value.length === 0) {
    ElMessage.warning('请先选择商品')
    return
  }
  try {
    await ElMessageBox.confirm(`确认批量删除 ${selectedIds.value.length} 个商品？`, '批量删除', {
      type: 'warning',
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
    })
    await batchDeleteProducts(selectedIds.value)
    ElMessage.success('批量删除成功')
    loadData()
  } catch {
    // 取消
  }
}

async function handleBatchStatus(status: string) {
  if (selectedIds.value.length === 0) {
    ElMessage.warning('请先选择商品')
    return
  }
  try {
    await batchUpdateProductStatus(selectedIds.value, status)
    ElMessage.success(`批量${status === 'active' ? '启用' : '禁用'}成功`)
    loadData()
  } catch {
    // 失败
  }
}

// ==================== SKU 标签颜色 ====================
function getSkuTagType(prefix: string | undefined): string {
  if (!prefix) return 'info'
  const map: Record<string, string> = {
    hic: 'danger',
    WPZ: 'primary',
    DIY: 'success',
    NBL: 'info',
  }
  return map[prefix] ?? 'info'
}

// ==================== 表格列定义 ====================
const columns = [
  { label: '图片', slot: 'image', width: 80, align: 'center' as const },
  { label: '商品名称', slot: 'name', minWidth: 200 },
  { label: 'SKU', slot: 'sku', width: 150 },
  { label: '价格', slot: 'price', width: 130, align: 'right' as const },
  { label: '库存', slot: 'stock', width: 90, align: 'center' as const },
  { label: '状态', slot: 'status', width: 90, align: 'center' as const },
  { label: '操作', slot: 'action', width: 140, fixed: 'right' as const, align: 'center' as const },
]

// ==================== 初始化 ====================
onMounted(() => {
  loadCategoryTree()
  loadData()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      title="商品列表"
      :actions="[
        { label: '新增商品', type: 'primary', icon: 'Plus', onClick: handleCreate },
      ]"
    />

    <!-- 搜索区域 -->
    <SearchForm :loading="loading" @search="handleSearch" @reset="handleReset">
      <el-form-item label="关键词">
        <el-input
          v-model="searchForm.keyword"
          placeholder="商品名/SKU"
          clearable
          style="width: 180px"
          @keyup.enter="handleSearch"
        />
      </el-form-item>
      <el-form-item label="分类">
        <el-tree-select
          v-model="searchForm.category_id"
          :data="categoryTree"
          :props="{ label: 'name', children: 'children' }"
          placeholder="全部分类"
          clearable
          filterable
          style="width: 180px"
          check-strictly
        />
      </el-form-item>
      <el-form-item label="状态">
        <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 110px">
          <el-option label="启用" value="active" />
          <el-option label="禁用" value="inactive" />
          <el-option label="草稿" value="draft" />
        </el-select>
      </el-form-item>
      <el-form-item label="价格区间">
        <el-input
          v-model="searchForm.min_price"
          placeholder="最低价"
          type="number"
          style="width: 100px"
        />
        <span style="margin: 0 6px; color: #909399">-</span>
        <el-input
          v-model="searchForm.max_price"
          placeholder="最高价"
          type="number"
          style="width: 100px"
        />
      </el-form-item>
    </SearchForm>

    <!-- 批量操作栏 -->
    <div v-if="selectedRows.length > 0" class="batch-bar">
      <span class="batch-bar__tip">已选 <strong>{{ selectedRows.length }}</strong> 个商品</span>
      <el-button size="small" type="success" @click="handleBatchStatus('active')">批量启用</el-button>
      <el-button size="small" type="warning" @click="handleBatchStatus('inactive')">批量禁用</el-button>
      <el-button size="small" type="danger" @click="handleBatchDelete">批量删除</el-button>
    </div>

    <!-- 表格 -->
    <el-card shadow="never" style="margin-top: 8px">
      <DataTable
        ref="tableRef"
        :data="tableData"
        :columns="columns"
        :total="total"
        :page="currentPage"
        :page-size="pageSize"
        :loading="loading"
        selection
        row-key="id"
        @update:page="handlePageChange"
        @update:page-size="handlePageSizeChange"
        @selection-change="handleSelectionChange"
      >
        <!-- 图片列 -->
        <template #image="{ row }">
          <el-image
            v-if="row.image || (row.images && row.images[0])"
            :src="row.image || row.images[0]"
            :preview-src-list="[row.image || row.images[0]]"
            fit="cover"
            style="width: 60px; height: 60px; border-radius: 4px"
          />
          <div v-else class="img-placeholder">
            <el-icon><Picture /></el-icon>
          </div>
        </template>

        <!-- 商品名称列 -->
        <template #name="{ row }">
          <div class="product-name">
            <div class="product-name__real">{{ row.name }}</div>
            <div v-if="row.safe_name" class="product-name__safe">{{ row.safe_name }}</div>
          </div>
        </template>

        <!-- SKU 列 -->
        <template #sku="{ row }">
          <el-tag :type="getSkuTagType(row.sku_prefix) as any" size="small">
            {{ row.sku }}
          </el-tag>
        </template>

        <!-- 价格列 -->
        <template #price="{ row }">
          <div class="price-cell">
            <span class="price-cell__main">
              ¥{{ (row.effective_price ?? row.price).toFixed(2) }}
            </span>
            <span v-if="row.special_price && row.special_price < row.price" class="price-cell__original">
              ¥{{ row.price.toFixed(2) }}
            </span>
          </div>
        </template>

        <!-- 库存列 -->
        <template #stock="{ row }">
          <span :class="{ 'low-stock': (row.quantity ?? row.stock) < 10 }">
            {{ row.quantity ?? row.stock }}
          </span>
        </template>

        <!-- 状态列 -->
        <template #status="{ row }">
          <el-switch
            :model-value="row.status === 'active'"
            @change="handleToggleStatus(row)"
            :disabled="row.status === 'draft'"
          />
        </template>

        <!-- 操作列 -->
        <template #action="{ row }">
          <el-button type="primary" size="small" link @click="handleEdit(row)">
            <el-icon><Edit /></el-icon> 编辑
          </el-button>
          <el-button type="danger" size="small" link @click="handleDelete(row)">
            <el-icon><Delete /></el-icon> 删除
          </el-button>
        </template>
      </DataTable>
    </el-card>
  </div>
</template>

<style scoped lang="scss">
.batch-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: #ecf5ff;
  border: 1px solid #b3d8ff;
  border-radius: 4px;
  margin-bottom: 8px;

  &__tip {
    font-size: 13px;
    color: #606266;
    margin-right: 8px;
  }
}

.product-name {
  &__real {
    font-size: 13px;
    color: #303133;
    line-height: 1.4;
  }

  &__safe {
    font-size: 11px;
    color: #909399;
    margin-top: 2px;
  }
}

.price-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-end;

  &__main {
    font-weight: 500;
    color: #e6a23c;
  }

  &__original {
    font-size: 11px;
    color: #c0c4cc;
    text-decoration: line-through;
  }
}

.low-stock {
  color: #f56c6c;
  font-weight: 600;
}

.img-placeholder {
  width: 60px;
  height: 60px;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  font-size: 20px;
}
</style>
