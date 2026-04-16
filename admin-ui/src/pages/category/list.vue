<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import {
  getCategoryTree,
  createCategory,
  updateCategory,
  deleteCategory,
  reorderCategories,
  type Category,
} from '@/api/category'

// ==================== 多语言 ====================
const LANGUAGES = [
  { code: 'en', label: 'English', required: true },
  { code: 'de', label: 'Deutsch' },
  { code: 'fr', label: 'Français' },
  { code: 'es', label: 'Español' },
  { code: 'it', label: 'Italiano' },
  { code: 'ja', label: '日本語' },
  { code: 'ko', label: '한국어' },
  { code: 'pt-BR', label: 'Português (BR)' },
  { code: 'pt-PT', label: 'Português (PT)' },
  { code: 'nl', label: 'Nederlands' },
  { code: 'pl', label: 'Polski' },
  { code: 'sv', label: 'Svenska' },
  { code: 'da', label: 'Dansk' },
  { code: 'ar', label: 'العربية' },
  { code: 'tr', label: 'Türkçe' },
  { code: 'el', label: 'Ελληνικά' },
]

interface DescItem {
  locale: string
  name: string
}

// ==================== 状态 ====================
const loading = ref(false)
const tableData = ref<Category[]>([])

// ==================== 加载数据 ====================
async function loadData() {
  loading.value = true
  try {
    const res = await getCategoryTree()
    tableData.value = res.data || []
  } catch {
    tableData.value = []
  } finally {
    loading.value = false
  }
}

// ==================== 弹窗表单 ====================
const dialogVisible = ref(false)
const dialogTitle = ref('新增分类')
const editingId = ref<number | null>(null)
const formRef = ref<FormInstance>()
const formLoading = ref(false)
const langTab = ref('en')

const formData = reactive({
  parent_id: null as number | null,
  status: 'active' as 'active' | 'inactive',
  sort_order: 0,
  image: '',
  descriptions: LANGUAGES.map((lang) => ({
    locale: lang.code,
    name: '',
  })) as DescItem[],
})


// ==================== 操作 ====================
function handleCreate(parentRow?: Category) {
  editingId.value = null
  dialogTitle.value = '新增分类'
  langTab.value = 'en'

  // 重置
  formData.parent_id = parentRow?.id ?? null
  formData.status = 'active'
  formData.sort_order = 0
  formData.image = ''
  formData.descriptions = LANGUAGES.map((lang) => ({ locale: lang.code, name: '' }))

  dialogVisible.value = true
}

function handleEdit(row: Category) {
  editingId.value = row.id
  dialogTitle.value = '编辑分类'
  langTab.value = 'en'

  formData.parent_id = row.parent_id ?? null
  formData.status = row.status
  formData.sort_order = row.sort_order
  formData.image = row.image ?? ''

  // 填充多语言描述
  formData.descriptions = LANGUAGES.map((lang) => {
    const existing = (row as any).descriptions?.find((d: any) => d.locale === lang.code)
    return {
      locale: lang.code,
      name: existing?.name ?? (lang.code === 'en' ? row.name : ''),
    }
  })

  dialogVisible.value = true
}

async function handleDelete(row: Category) {
  const hasChildren = (row.children?.length ?? 0) > 0
  const hasProducts = (row as any).products_count > 0

  if (hasChildren) {
    ElMessage.warning('该分类下有子分类，请先删除子分类')
    return
  }
  if (hasProducts) {
    ElMessage.warning(`该分类下有 ${(row as any).products_count} 个商品，请先移除商品`)
    return
  }

  try {
    await ElMessageBox.confirm(`确认删除分类「${row.name}」？`, '删除确认', {
      type: 'warning',
      confirmButtonText: '确认删除',
      cancelButtonText: '取消',
    })
    await deleteCategory(row.id)
    ElMessage.success('删除成功')
    loadData()
  } catch {
    // 取消
  }
}

async function handleSortChange(row: Category, val: number) {
  try {
    await reorderCategories([{ id: row.id, sort_order: val, parent_id: row.parent_id }])
    ElMessage.success('排序已更新')
    loadData()
  } catch {
    //
  }
}

// ==================== 提交表单 ====================
async function handleSubmit() {
  // 验证 EN 名称
  const enDesc = formData.descriptions.find((d) => d.locale === 'en')
  if (!enDesc?.name.trim()) {
    langTab.value = 'en'
    ElMessage.warning('English 分类名称为必填')
    return
  }

  formLoading.value = true
  try {
    const payload: Record<string, unknown> = {
      parent_id: formData.parent_id,
      status: formData.status,
      sort_order: formData.sort_order,
      image: formData.image || null,
      name: enDesc.name.trim(),
      descriptions: formData.descriptions
        .filter((d) => d.name.trim())
        .map((d) => ({ locale: d.locale, name: d.name.trim() })),
    }

    if (editingId.value) {
      await updateCategory(editingId.value, payload as any)
      ElMessage.success('保存成功')
    } else {
      await createCategory(payload as any)
      ElMessage.success('创建成功')
    }
    dialogVisible.value = false
    loadData()
  } catch {
    //
  } finally {
    formLoading.value = false
  }
}

function handleDialogClose() {
  dialogVisible.value = false
}

// ==================== 树形表格父级选项 ====================
// ==================== 初始化 ====================
onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="page-container">
    <PageHeader
      title="分类管理"
      :actions="[
        { label: '新增分类', type: 'primary', icon: 'Plus', onClick: () => handleCreate() },
      ]"
    />

    <el-card shadow="never">
      <el-table
        v-loading="loading"
        :data="tableData"
        row-key="id"
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
        default-expand-all
        border
        style="width: 100%"
      >
        <!-- 分类名称 -->
        <el-table-column label="分类名称" prop="name" min-width="220" />

        <!-- 图片 -->
        <el-table-column label="图片" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.image"
              :src="row.image"
              fit="cover"
              style="width: 40px; height: 40px; border-radius: 4px"
            />
            <span v-else class="text-muted">—</span>
          </template>
        </el-table-column>

        <!-- 商品数 -->
        <el-table-column label="商品数" prop="products_count" width="90" align="center">
          <template #default="{ row }">
            <el-tag size="small" type="info">{{ row.products_count ?? 0 }}</el-tag>
          </template>
        </el-table-column>

        <!-- 状态 -->
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
              {{ row.status === 'active' ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 排序 -->
        <el-table-column label="排序" width="110" align="center">
          <template #default="{ row }">
            <el-input-number
              :model-value="row.sort_order"
              size="small"
              :min="0"
              :max="9999"
              controls-position="right"
              style="width: 90px"
              @change="(val: number | undefined) => val !== undefined && handleSortChange(row, val)"
            />
          </template>
        </el-table-column>

        <!-- 操作 -->
        <el-table-column label="操作" width="230" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" link @click="handleCreate(row)">
              <el-icon><Plus /></el-icon> 添加子分类
            </el-button>
            <el-button type="warning" size="small" link @click="handleEdit(row)">
              <el-icon><Edit /></el-icon> 编辑
            </el-button>
            <el-button type="danger" size="small" link @click="handleDelete(row)">
              <el-icon><Delete /></el-icon> 删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="580px"
      :close-on-click-modal="false"
      @close="handleDialogClose"
    >
      <el-form
        ref="formRef"
        :model="formData"
        label-width="100px"
        style="padding: 0 10px"
      >
        <!-- 父分类 -->
        <el-form-item label="父分类">
          <el-tree-select
            v-model="formData.parent_id"
            :data="tableData"
            :props="{ label: 'name', children: 'children' }"
            placeholder="无（顶级分类）"
            clearable
            filterable
            check-strictly
            style="width: 100%"
          />
        </el-form-item>

        <!-- 状态 -->
        <el-form-item label="状态">
          <el-radio-group v-model="formData.status">
            <el-radio-button value="active">启用</el-radio-button>
            <el-radio-button value="inactive">禁用</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <!-- 排序 -->
        <el-form-item label="排序">
          <el-input-number v-model="formData.sort_order" :min="0" :max="9999" />
        </el-form-item>

        <!-- 图片 URL -->
        <el-form-item label="图片 URL">
          <el-input v-model="formData.image" placeholder="图片 URL（可选）" style="width: 100%" />
        </el-form-item>

        <!-- 多语言名称 -->
        <el-form-item label="多语言名称" style="margin-bottom: 0">
          <el-tabs v-model="langTab" type="card" style="width: 100%">
            <el-tab-pane
              v-for="lang in LANGUAGES"
              :key="lang.code"
              :label="lang.required ? `${lang.label} *` : lang.label"
              :name="lang.code"
            >
              <el-input
                v-model="formData.descriptions.find(d => d.locale === lang.code)!.name"
                :placeholder="`${lang.label} 分类名称${lang.required ? '（必填）' : ''}`"
                :required="lang.required"
                style="width: 100%; margin-top: 8px"
              />
            </el-tab-pane>
          </el-tabs>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="formLoading" @click="handleSubmit">
          {{ editingId ? '保存修改' : '创建分类' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.text-muted {
  color: #c0c4cc;
}
</style>
