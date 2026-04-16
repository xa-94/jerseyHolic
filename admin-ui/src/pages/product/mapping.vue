<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import { getProductList, updateProductMapping, type Product } from '@/api/product'

// ==================== 类型 ====================
interface MappingRow {
  id: number
  name: string
  sku: string
  sku_prefix: string
  safe_name: string
  mapping_type: 'exact' | 'sku_prefix' | 'default'
  editing: boolean
  editSafeName: string
  editMappingType: 'exact' | 'sku_prefix' | 'default'
}

// ==================== 数据 ====================
const loading = ref(false)
const saving = ref<number | null>(null)
const tableData = ref<MappingRow[]>([])
const total = ref(0)
const currentPage = ref(1)
const pageSize = ref(20)

const searchKeyword = ref('')

const MAPPING_TYPE_LABELS: Record<string, string> = {
  exact: '精确映射',
  sku_prefix: 'SKU前缀通用名',
  default: '兜底默认',
}

const MAPPING_TYPE_TAGS: Record<string, string> = {
  exact: 'success',
  sku_prefix: 'primary',
  default: 'info',
}

// ==================== 加载数据 ====================
async function loadData() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      page: currentPage.value,
      per_page: pageSize.value,
    }
    if (searchKeyword.value) params.keyword = searchKeyword.value

    const res = await getProductList(params as any)
    const d = res.data
    const items: Product[] = (d as any).list ?? []
    total.value = d.total ?? 0

    tableData.value = items.map((p) => ({
      id: p.id,
      name: p.name,
      sku: p.sku,
      sku_prefix: p.sku_prefix ?? '',
      safe_name: p.safe_name ?? '',
      mapping_type: (p.mapping_type as any) ?? 'default',
      editing: false,
      editSafeName: p.safe_name ?? '',
      editMappingType: (p.mapping_type as any) ?? 'default',
    }))
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
  searchKeyword.value = ''
  currentPage.value = 1
  loadData()
}

// ==================== 行内编辑 ====================
function startEdit(row: MappingRow) {
  row.editSafeName = row.safe_name
  row.editMappingType = row.mapping_type
  row.editing = true
}

function cancelEdit(row: MappingRow) {
  row.editing = false
}

async function saveRow(row: MappingRow) {
  if (!row.editSafeName.trim()) {
    ElMessage.warning('安全名称不能为空')
    return
  }
  saving.value = row.id
  try {
    await updateProductMapping(row.id, {
      safe_name: row.editSafeName.trim(),
      mapping_type: row.editMappingType,
    })
    row.safe_name = row.editSafeName.trim()
    row.mapping_type = row.editMappingType
    row.editing = false
    ElMessage.success('映射已保存')
  } catch {
    //
  } finally {
    saving.value = null
  }
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

// ==================== 初始化 ====================
onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      title="商品映射管理"
      subtitle="管理商品真实名称与安全名称的映射关系，用于前台展示脱敏"
    />

    <!-- 说明提示 -->
    <el-alert
      type="info"
      :closable="false"
      style="margin-bottom: 16px"
    >
      <template #title>
        <strong>映射规则说明</strong>
      </template>
      <ul style="margin: 6px 0 0; padding-left: 18px; font-size: 13px; line-height: 1.8">
        <li><strong>精确映射</strong>：该商品使用指定的安全名称，优先级最高</li>
        <li><strong>SKU前缀通用名</strong>：同 SKU 前缀的商品共用一个安全名称</li>
        <li><strong>兜底默认</strong>：未配置精确映射时使用的默认安全名称</li>
      </ul>
    </el-alert>

    <!-- 搜索 -->
    <el-card shadow="never" style="margin-bottom: 16px">
      <el-form inline>
        <el-form-item label="关键词">
          <el-input
            v-model="searchKeyword"
            placeholder="商品名称 / SKU"
            clearable
            style="width: 220px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <el-icon><Search /></el-icon> 搜索
          </el-button>
          <el-button @click="handleReset">
            <el-icon><Refresh /></el-icon> 重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 表格 -->
    <el-card shadow="never">
      <el-table
        v-loading="loading"
        :data="tableData"
        border
        stripe
        style="width: 100%"
      >
        <el-table-column type="index" label="#" width="60" align="center" />
        <el-table-column label="商品ID" prop="id" width="80" align="center" />
        <el-table-column label="商品名称" prop="name" min-width="200" show-overflow-tooltip />
        <el-table-column label="SKU" prop="sku" width="160">
          <template #default="{ row }">
            <el-tag size="small">{{ row.sku }}</el-tag>
          </template>
        </el-table-column>

        <!-- 安全映射名 -->
        <el-table-column label="安全映射名" min-width="200">
          <template #default="{ row }">
            <template v-if="row.editing">
              <el-input
                v-model="row.editSafeName"
                size="small"
                placeholder="请输入安全商品名称"
                style="width: 100%"
              />
            </template>
            <template v-else>
              <span v-if="row.safe_name" class="safe-name">{{ row.safe_name }}</span>
              <span v-else class="text-muted">未配置</span>
            </template>
          </template>
        </el-table-column>

        <!-- 映射类型 -->
        <el-table-column label="映射类型" width="160" align="center">
          <template #default="{ row }">
            <template v-if="row.editing">
              <el-select v-model="row.editMappingType" size="small" style="width: 130px">
                <el-option value="exact" label="精确映射" />
                <el-option value="sku_prefix" label="SKU前缀通用名" />
                <el-option value="default" label="兜底默认" />
              </el-select>
            </template>
            <template v-else>
              <el-tag
                :type="MAPPING_TYPE_TAGS[row.mapping_type] as any"
                size="small"
              >
                {{ MAPPING_TYPE_LABELS[row.mapping_type] }}
              </el-tag>
            </template>
          </template>
        </el-table-column>

        <!-- 操作 -->
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <template v-if="row.editing">
              <el-button
                type="primary"
                size="small"
                :loading="saving === row.id"
                @click="saveRow(row)"
              >
                保存
              </el-button>
              <el-button size="small" @click="cancelEdit(row)">取消</el-button>
            </template>
            <template v-else>
              <el-button type="primary" size="small" link @click="startEdit(row)">
                <el-icon><Edit /></el-icon> 编辑映射
              </el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div v-if="total > 0" style="display: flex; justify-content: flex-end; margin-top: 16px">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :page-sizes="[20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          background
          @current-change="handlePageChange"
          @size-change="handlePageSizeChange"
        />
      </div>
    </el-card>
  </div>
</template>

<style scoped lang="scss">
.safe-name {
  color: #67c23a;
  font-weight: 500;
}

.text-muted {
  color: #c0c4cc;
  font-style: italic;
}
</style>
