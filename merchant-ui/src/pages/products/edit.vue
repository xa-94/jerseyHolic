<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Delete, ArrowLeft, UploadFilled } from '@element-plus/icons-vue'
import type { FormInstance, FormRules, UploadFile, UploadProps } from 'element-plus'
import {
  getProductDetail,
  createProduct,
  updateProduct,
  getCategoryL1List,
  getCategoryL2List,
  type ProductFormData,
  type ProductTranslation,
  type ProductStatus,
  type CategoryL1,
  type CategoryL2,
} from '@/api/product'

// ─── 路由 ─────────────────────────────────────────────────────────────────────

const route = useRoute()
const router = useRouter()

const productId = computed(() => {
  const id = route.params.id
  return id ? Number(id) : null
})

const isEdit = computed(() => productId.value !== null)
const pageTitle = computed(() => (isEdit.value ? '编辑商品' : '新增商品'))

// ─── 表单数据 ─────────────────────────────────────────────────────────────────

const formRef = ref<FormInstance>()

const form = reactive<ProductFormData>({
  sku: '',
  name: '',
  category_l1_id: 0,
  category_l2_id: 0,
  price: 0,
  cost_price: undefined,
  status: 'draft',
  variants: [],
  translations: [],
  images: [],
})

const formRules: FormRules = {
  name: [{ required: true, message: '请输入商品名称', trigger: 'blur' }],
  sku: [{ required: true, message: '请输入 SKU', trigger: 'blur' }],
  category_l1_id: [{ required: true, message: '请选择一级品类', trigger: 'change' }],
  price: [{ required: true, message: '请输入价格', trigger: 'blur' }],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }],
}

// ─── Tab ─────────────────────────────────────────────────────────────────────

const activeTab = ref('basic')

// ─── 品类联动 ─────────────────────────────────────────────────────────────────

const categoryL1List = ref<CategoryL1[]>([])
const categoryL2List = ref<CategoryL2[]>([])

async function loadCategoryL1() {
  const res = await getCategoryL1List()
  categoryL1List.value = res.data
}

async function loadCategoryL2(l1Id: number) {
  if (!l1Id) {
    categoryL2List.value = []
    form.category_l2_id = 0
    return
  }
  const res = await getCategoryL2List({ l1_id: l1Id })
  categoryL2List.value = res.data
  if (!categoryL2List.value.find(c => c.id === form.category_l2_id)) {
    form.category_l2_id = 0
  }
}

watch(() => form.category_l1_id, (val) => {
  loadCategoryL2(val)
})

// ─── SKU 变体 ─────────────────────────────────────────────────────────────────

function addVariant() {
  form.variants.push({
    sku: '',
    size: '',
    color: '',
    stock: 0,
    price: form.price || 0,
    cost_price: form.cost_price,
  })
}

function removeVariant(index: number) {
  form.variants.splice(index, 1)
}

// ─── 多语言翻译 ───────────────────────────────────────────────────────────────

const locales = [
  { code: 'en', label: 'English' },
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

function initTranslations() {
  const existing = form.translations
  locales.forEach(locale => {
    if (!existing.find(t => t.locale === locale.code)) {
      existing.push({ locale: locale.code, name: '', description: '' })
    }
  })
}

function getTranslation(locale: string): ProductTranslation {
  const t = form.translations.find(t => t.locale === locale)
  if (t) return t
  const newT: ProductTranslation = { locale, name: '', description: '' }
  form.translations.push(newT)
  return newT
}

// ─── 图片上传 ─────────────────────────────────────────────────────────────────

const uploadAction = computed(() => `${import.meta.env.VITE_API_BASE_URL || ''}/api/v1/merchant/upload/image`)

function onUploadSuccess(response: { data: { url: string } }, _file: UploadFile) {
  if (response?.data?.url) {
    const isFirst = form.images.length === 0
    form.images.push({
      url: response.data.url,
      sort: form.images.length,
      is_main: isFirst,
    })
  }
}

function onUploadError(_err: Error) {
  ElMessage.error('图片上传失败')
}

const onBeforeUpload: UploadProps['beforeUpload'] = (file) => {
  const isImage = file.type.startsWith('image/')
  const isLt5M = file.size / 1024 / 1024 < 5
  if (!isImage) {
    ElMessage.error('只能上传图片文件')
    return false
  }
  if (!isLt5M) {
    ElMessage.error('图片大小不能超过 5MB')
    return false
  }
  return true
}

function removeImage(index: number) {
  form.images.splice(index, 1)
  // 若删除了主图，设置第一张为主图
  if (form.images.length > 0 && !form.images.find(i => i.is_main)) {
    form.images[0].is_main = true
  }
  // 重新排序
  form.images.forEach((img, i) => {
    img.sort = i
  })
}

function setMainImage(index: number) {
  form.images.forEach((img, i) => {
    img.is_main = i === index
  })
}

// ─── 加载编辑数据 ─────────────────────────────────────────────────────────────

const pageLoading = ref(false)

async function loadProductData() {
  if (!productId.value) return
  pageLoading.value = true
  try {
    const res = await getProductDetail(productId.value)
    const p = res.data
    form.sku = p.sku
    form.name = p.name
    form.category_l1_id = p.category_l1_id
    form.category_l2_id = p.category_l2_id
    form.price = p.price
    form.cost_price = p.cost_price
    form.status = p.status
    form.variants = p.variants.map(v => ({ ...v }))
    form.translations = p.translations.map(t => ({ ...t }))
    form.images = p.images.map(img => ({ ...img }))
    // 确保所有语言都有翻译
    initTranslations()
    // 加载 L2 品类
    if (p.category_l1_id) {
      await loadCategoryL2(p.category_l1_id)
    }
  } finally {
    pageLoading.value = false
  }
}

// ─── 提交 ─────────────────────────────────────────────────────────────────────

const saving = ref(false)

async function handleSave(targetStatus?: ProductStatus) {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) {
    activeTab.value = 'basic'
    ElMessage.warning('请完善基本信息')
    return
  }

  if (targetStatus) {
    form.status = targetStatus
  }

  saving.value = true
  try {
    const payload = { ...form }
    if (isEdit.value && productId.value) {
      await updateProduct(productId.value, payload)
      ElMessage.success('保存成功')
    } else {
      await createProduct(payload)
      ElMessage.success('商品创建成功')
      router.push('/products')
    }
  } finally {
    saving.value = false
  }
}

async function handleSaveDraft() {
  await handleSave('draft')
}

async function handlePublish() {
  await handleSave('active')
}

// ─── 取消 ─────────────────────────────────────────────────────────────────────

async function handleCancel() {
  await ElMessageBox.confirm('确定要放弃当前修改并离开吗？', '提示', {
    type: 'warning',
    confirmButtonText: '确定离开',
    cancelButtonText: '继续编辑',
  })
  router.push('/products')
}

// ─── 初始化 ───────────────────────────────────────────────────────────────────

onMounted(async () => {
  await loadCategoryL1()
  if (isEdit.value) {
    await loadProductData()
  } else {
    initTranslations()
  }
})
</script>

<template>
  <div class="page-container" v-loading="pageLoading">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="page-header-left">
        <el-button link :icon="ArrowLeft" @click="router.push('/products')">返回商品列表</el-button>
        <h2 class="page-title">{{ pageTitle }}</h2>
      </div>
    </div>

    <el-form
      ref="formRef"
      :model="form"
      :rules="formRules"
      label-position="top"
      class="product-form"
    >
      <el-tabs v-model="activeTab" class="product-tabs">
        <!-- ─── Tab 1 基本信息 ─────────────────────────────────────────────── -->
        <el-tab-pane label="基本信息" name="basic">
          <div class="tab-content">
            <el-row :gutter="24">
              <el-col :span="12">
                <el-form-item label="商品名称" prop="name">
                  <el-input v-model="form.name" placeholder="请输入商品名称" clearable />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="SKU" prop="sku">
                  <el-input v-model="form.sku" placeholder="请输入 SKU" clearable />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="一级品类" prop="category_l1_id">
                  <el-select
                    v-model="form.category_l1_id"
                    placeholder="请选择一级品类"
                    clearable
                    class="full-width"
                  >
                    <el-option
                      v-for="cat in categoryL1List"
                      :key="cat.id"
                      :label="cat.name"
                      :value="cat.id"
                    />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="二级品类" prop="category_l2_id">
                  <el-select
                    v-model="form.category_l2_id"
                    placeholder="请先选择一级品类"
                    clearable
                    :disabled="!form.category_l1_id"
                    class="full-width"
                  >
                    <el-option
                      v-for="cat in categoryL2List"
                      :key="cat.id"
                      :label="cat.name"
                      :value="cat.id"
                    />
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="销售价格（¥）" prop="price">
                  <el-input-number
                    v-model="form.price"
                    :min="0"
                    :precision="2"
                    :step="1"
                    class="full-width"
                    placeholder="0.00"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="成本价（¥）">
                  <el-input-number
                    v-model="form.cost_price"
                    :min="0"
                    :precision="2"
                    :step="1"
                    class="full-width"
                    placeholder="0.00"
                  />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="商品状态" prop="status">
                  <el-select v-model="form.status" class="full-width">
                    <el-option label="草稿" value="draft" />
                    <el-option label="已上架" value="active" />
                    <el-option label="已下架" value="inactive" />
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>
          </div>
        </el-tab-pane>

        <!-- ─── Tab 2 SKU/变体 ─────────────────────────────────────────────── -->
        <el-tab-pane label="SKU/变体" name="variants">
          <div class="tab-content">
            <div class="tab-section-header">
              <span class="section-title">变体列表</span>
              <el-button type="primary" size="small" :icon="Plus" @click="addVariant">
                添加变体
              </el-button>
            </div>

            <el-table :data="form.variants" border empty-text="暂无变体，点击「添加变体」">
              <el-table-column label="SKU" min-width="140">
                <template #default="{ row }">
                  <el-input v-model="row.sku" placeholder="变体 SKU" size="small" />
                </template>
              </el-table-column>
              <el-table-column label="尺码" width="100">
                <template #default="{ row }">
                  <el-input v-model="row.size" placeholder="如 XL" size="small" />
                </template>
              </el-table-column>
              <el-table-column label="颜色" width="100">
                <template #default="{ row }">
                  <el-input v-model="row.color" placeholder="如 Red" size="small" />
                </template>
              </el-table-column>
              <el-table-column label="库存" width="110">
                <template #default="{ row }">
                  <el-input-number v-model="row.stock" :min="0" size="small" class="full-width" />
                </template>
              </el-table-column>
              <el-table-column label="价格（¥）" width="130">
                <template #default="{ row }">
                  <el-input-number v-model="row.price" :min="0" :precision="2" size="small" class="full-width" />
                </template>
              </el-table-column>
              <el-table-column label="成本价（¥）" width="130">
                <template #default="{ row }">
                  <el-input-number v-model="row.cost_price" :min="0" :precision="2" size="small" class="full-width" />
                </template>
              </el-table-column>
              <el-table-column label="操作" width="70" align="center">
                <template #default="{ $index }">
                  <el-button link type="danger" :icon="Delete" @click="removeVariant($index)" />
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-tab-pane>

        <!-- ─── Tab 3 多语言翻译 ───────────────────────────────────────────── -->
        <el-tab-pane label="多语言翻译" name="translations">
          <div class="tab-content">
            <el-tabs type="card" class="locale-tabs">
              <el-tab-pane
                v-for="locale in locales"
                :key="locale.code"
                :label="locale.label"
                :name="locale.code"
              >
                <div class="locale-form">
                  <el-form-item :label="`${locale.label} 商品名称`">
                    <el-input
                      v-model="getTranslation(locale.code).name"
                      :placeholder="`请输入 ${locale.label} 商品名称`"
                    />
                  </el-form-item>
                  <el-form-item :label="`${locale.label} 商品描述`">
                    <el-input
                      v-model="getTranslation(locale.code).description"
                      type="textarea"
                      :rows="6"
                      :placeholder="`请输入 ${locale.label} 商品描述`"
                    />
                  </el-form-item>
                </div>
              </el-tab-pane>
            </el-tabs>
          </div>
        </el-tab-pane>

        <!-- ─── Tab 4 图片管理 ────────────────────────────────────────────── -->
        <el-tab-pane label="图片管理" name="images">
          <div class="tab-content">
            <div class="tab-section-header">
              <span class="section-title">商品图片（最多 10 张）</span>
              <span class="section-hint">点击图片可设为主图；拖拽可调整排序</span>
            </div>

            <!-- 上传区域 -->
            <el-upload
              v-if="form.images.length < 10"
              :action="uploadAction"
              :show-file-list="false"
              :before-upload="onBeforeUpload"
              :on-success="onUploadSuccess"
              :on-error="onUploadError"
              multiple
              accept="image/*"
              class="image-uploader"
            >
              <div class="upload-area">
                <el-icon class="upload-icon"><UploadFilled /></el-icon>
                <div class="upload-text">拖拽或点击上传图片</div>
                <div class="upload-hint">支持 JPG、PNG、WebP，单张不超过 5MB</div>
              </div>
            </el-upload>

            <!-- 已上传图片列表 -->
            <div v-if="form.images.length > 0" class="image-grid">
              <div
                v-for="(img, index) in form.images"
                :key="index"
                class="image-item"
                :class="{ 'is-main': img.is_main }"
              >
                <el-image :src="img.url" fit="cover" class="image-preview" />
                <div class="image-overlay">
                  <span v-if="img.is_main" class="main-badge">主图</span>
                  <div class="image-actions">
                    <el-button
                      v-if="!img.is_main"
                      link
                      size="small"
                      style="color: #fff;"
                      @click="setMainImage(index)"
                    >
                      设为主图
                    </el-button>
                    <el-button link size="small" type="danger" :icon="Delete" @click="removeImage(index)" />
                  </div>
                </div>
              </div>
            </div>

            <el-empty v-else description="暂无图片，请上传" :image-size="80" style="margin-top: 20px;" />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-form>

    <!-- 底部固定操作栏 -->
    <div class="page-footer">
      <el-button @click="handleCancel">取消</el-button>
      <el-button :loading="saving" @click="handleSaveDraft">保存草稿</el-button>
      <el-button type="primary" :loading="saving" @click="handlePublish">发布上架</el-button>
    </div>
  </div>
</template>

<style scoped lang="scss">
.page-container {
  padding: 20px;
  padding-bottom: 80px; // 为底部 footer 留空间
}

.page-header {
  margin-bottom: 16px;

  .page-header-left {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .page-title {
    font-size: 20px;
    font-weight: 600;
    color: #303133;
    margin: 0;
  }
}

.product-form {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}

.product-tabs {
  :deep(.el-tabs__header) {
    padding: 0 16px;
    background: #fafafa;
    border-radius: 8px 8px 0 0;
  }
}

.tab-content {
  padding: 20px 24px;
}

.full-width {
  width: 100%;
}

.tab-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;

  .section-title {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
  }

  .section-hint {
    font-size: 12px;
    color: #909399;
  }
}

.locale-tabs {
  :deep(.el-tabs__header) {
    margin-bottom: 16px;
  }

  :deep(.el-tabs__item) {
    font-size: 12px;
  }
}

.locale-form {
  max-width: 720px;
}

.image-uploader {
  margin-bottom: 16px;
}

.upload-area {
  border: 2px dashed #dcdfe6;
  border-radius: 8px;
  padding: 40px 20px;
  text-align: center;
  cursor: pointer;
  transition: border-color 0.2s;

  &:hover {
    border-color: #409eff;
  }

  .upload-icon {
    font-size: 40px;
    color: #c0c4cc;
    margin-bottom: 12px;
  }

  .upload-text {
    font-size: 14px;
    color: #606266;
    margin-bottom: 6px;
  }

  .upload-hint {
    font-size: 12px;
    color: #909399;
  }
}

.image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, 120px);
  gap: 12px;
}

.image-item {
  position: relative;
  width: 120px;
  height: 120px;
  border-radius: 6px;
  overflow: hidden;
  border: 2px solid transparent;
  cursor: pointer;

  &.is-main {
    border-color: #409eff;
  }

  .image-preview {
    width: 100%;
    height: 100%;
  }

  .image-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.2s;
  }

  &:hover .image-overlay,
  &.is-main .image-overlay {
    opacity: 1;
  }

  .main-badge {
    background: #409eff;
    color: #fff;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
  }

  .image-actions {
    display: flex;
    gap: 4px;
  }
}

.page-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 60px;
  background: #fff;
  border-top: 1px solid #ebeef5;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0 40px;
  gap: 12px;
  z-index: 100;
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.06);
}
</style>
