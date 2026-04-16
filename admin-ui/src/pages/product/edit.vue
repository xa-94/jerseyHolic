<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import PageHeader from '@/components/common/PageHeader.vue'
import {
  getProductById,
  createProduct,
  updateProduct,
} from '@/api/product'
import { getCategoryTree, type Category } from '@/api/category'

// ==================== 16 种语言 ====================
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
  description: string
  seo_title: string
  seo_description: string
  seo_keywords: string
}

interface SkuItem {
  id?: number
  sku_code: string
  price: number
  special_price: number | null
  quantity: number
  options_str: string
}

interface ImageItem {
  id?: number
  url: string
  sort_order: number
  is_primary: boolean
}

// ==================== 路由 ====================
const route = useRoute()
const router = useRouter()
const productId = computed(() => (route.params.id ? Number(route.params.id) : null))
const isEdit = computed(() => !!productId.value)

// ==================== 状态 ====================
const loading = ref(false)
const saving = ref(false)
const activeTab = ref('basic')
const formRef = ref<FormInstance>()
const categoryTree = ref<Category[]>([])

// ==================== 表单数据 ====================
const baseForm = reactive({
  category_id: null as number | null,
  sku: '',
  sku_prefix: '',
  status: 'active' as 'active' | 'inactive' | 'draft',
  sort_order: 0,
  price: 0,
  special_price: null as number | null,
  special_price_start: '',
  special_price_end: '',
})

const descriptions = ref<DescItem[]>(
  LANGUAGES.map((lang) => ({
    locale: lang.code,
    name: '',
    description: '',
    seo_title: '',
    seo_description: '',
    seo_keywords: '',
  }))
)

const images = ref<ImageItem[]>([])
const skus = ref<SkuItem[]>([])

// ==================== 表单验证 ====================
const baseRules = reactive<FormRules>({
  category_id: [{ required: true, message: '请选择分类', trigger: 'change' }],
  price: [{ required: true, message: '请输入价格', trigger: 'blur' }],
})

// ==================== 加载数据 ====================
async function loadCategoryTree() {
  try {
    const res = await getCategoryTree()
    categoryTree.value = res.data || []
  } catch {
    //
  }
}

async function loadProduct() {
  if (!productId.value) return
  loading.value = true
  try {
    const res = await getProductById(productId.value)
    const p = res.data as any

    // 基础信息
    baseForm.category_id = p.category_id ?? null
    baseForm.sku = p.sku ?? ''
    baseForm.sku_prefix = p.sku_prefix ?? extractSkuPrefix(p.sku)
    baseForm.status = p.status ?? 'active'
    baseForm.sort_order = p.sort_order ?? 0
    baseForm.price = p.price ?? 0
    baseForm.special_price = p.special_price ?? null
    baseForm.special_price_start = p.special_price_start ?? ''
    baseForm.special_price_end = p.special_price_end ?? ''

    // 多语言描述
    if (p.descriptions?.length) {
      for (const desc of p.descriptions) {
        const idx = descriptions.value.findIndex((d) => d.locale === desc.locale)
        if (idx >= 0) {
          descriptions.value[idx] = {
            locale: desc.locale,
            name: desc.name ?? '',
            description: desc.description ?? '',
            seo_title: desc.seo_title ?? '',
            seo_description: desc.seo_description ?? '',
            seo_keywords: desc.seo_keywords ?? '',
          }
        }
      }
    }

    // 图片
    if (p.images?.length) {
      images.value = p.images.map((img: any, idx: number) => ({
        id: img.id,
        url: typeof img === 'string' ? img : img.url,
        sort_order: img.sort_order ?? idx,
        is_primary: img.is_primary ?? idx === 0,
      }))
    }

    // SKU
    if (p.skus?.length) {
      skus.value = p.skus.map((s: any) => ({
        id: s.id,
        sku_code: s.sku_code ?? '',
        price: s.price ?? 0,
        special_price: s.special_price ?? null,
        quantity: s.quantity ?? 0,
        options_str: s.options ? JSON.stringify(s.options) : '',
      }))
    }
  } catch {
    ElMessage.error('加载商品失败')
  } finally {
    loading.value = false
  }
}

function extractSkuPrefix(sku: string): string {
  if (!sku) return ''
  const match = sku.match(/^([A-Za-z]+)/i)
  return match ? match[1] : ''
}

// ==================== 保存 ====================
async function handleSave() {
  // 验证基础表单
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) {
    activeTab.value = 'basic'
    ElMessage.warning('请检查基础信息')
    return
  }

  // 验证 EN 名称
  const enDesc = descriptions.value.find((d) => d.locale === 'en')
  if (!enDesc?.name) {
    activeTab.value = 'desc'
    ElMessage.warning('English 商品名称为必填')
    return
  }

  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      category_id: baseForm.category_id,
      status: baseForm.status,
      sort_order: baseForm.sort_order,
      price: baseForm.price,
      special_price: baseForm.special_price || null,
      special_price_start: baseForm.special_price_start || null,
      special_price_end: baseForm.special_price_end || null,
      // 名称取 EN
      name: enDesc.name,
      descriptions: descriptions.value
        .filter((d) => d.name)
        .map((d) => ({
          locale: d.locale,
          name: d.name,
          description: d.description,
          seo_title: d.seo_title,
          seo_description: d.seo_description,
          seo_keywords: d.seo_keywords,
        })),
      images: images.value.map((img) => ({
        url: img.url,
        sort_order: img.sort_order,
        is_primary: img.is_primary,
      })),
      skus: skus.value.map((s) => ({
        sku_code: s.sku_code,
        price: s.price,
        special_price: s.special_price,
        quantity: s.quantity,
        options: s.options_str ? JSON.parse(s.options_str) : {},
      })),
    }

    if (isEdit.value && productId.value) {
      await updateProduct(productId.value, payload as any)
      ElMessage.success('保存成功')
    } else {
      await createProduct({ ...payload, sku: baseForm.sku } as any)
      ElMessage.success('创建成功')
      router.push('/product/list')
    }
  } catch {
    // 请求拦截已提示
  } finally {
    saving.value = false
  }
}

function handleCancel() {
  router.push('/product/list')
}

// ==================== 图片操作 ====================
function handleImageUpload(response: any, _file: any) {
  if (response?.data?.url) {
    images.value.push({
      url: response.data.url,
      sort_order: images.value.length,
      is_primary: images.value.length === 0,
    })
  }
}

function handleSetPrimary(idx: number) {
  images.value.forEach((img, i) => {
    img.is_primary = i === idx
  })
}

function handleRemoveImage(idx: number) {
  images.value.splice(idx, 1)
  if (!images.value.some((img) => img.is_primary) && images.value.length > 0) {
    images.value[0].is_primary = true
  }
}

// ==================== SKU 操作 ====================
const skuDialogVisible = ref(false)
const editingSkuIdx = ref<number | null>(null)
const skuForm = reactive<SkuItem>({
  sku_code: '',
  price: 0,
  special_price: null,
  quantity: 0,
  options_str: '',
})

function handleAddSku() {
  Object.assign(skuForm, { sku_code: '', price: 0, special_price: null, quantity: 0, options_str: '' })
  editingSkuIdx.value = null
  skuDialogVisible.value = true
}

function handleEditSku(idx: number) {
  const s = skus.value[idx]
  Object.assign(skuForm, { ...s })
  editingSkuIdx.value = idx
  skuDialogVisible.value = true
}

function handleSaveSku() {
  if (!skuForm.sku_code) {
    ElMessage.warning('请输入 SKU 编码')
    return
  }
  if (editingSkuIdx.value !== null) {
    skus.value[editingSkuIdx.value] = { ...skuForm }
  } else {
    skus.value.push({ ...skuForm })
  }
  skuDialogVisible.value = false
}

function handleDeleteSku(idx: number) {
  skus.value.splice(idx, 1)
}

// ==================== 初始化 ====================
onMounted(() => {
  loadCategoryTree()
  loadProduct()
})

// API baseURL for upload
const uploadUrl = `${import.meta.env.VITE_API_PREFIX || '/api/v1/admin'}/products/upload-image`
</script>

<template>
  <div class="page-container" v-loading="loading">
    <PageHeader
      :title="isEdit ? '编辑商品' : '新增商品'"
      :actions="[
        { label: '保存', type: 'primary', onClick: handleSave },
        { label: '取消', onClick: handleCancel },
      ]"
    />

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ===== 基础信息 Tab ===== -->
      <el-tab-pane label="基础信息" name="basic">
        <el-form
          ref="formRef"
          :model="baseForm"
          :rules="baseRules"
          label-width="110px"
          style="max-width: 700px; padding: 20px 0"
        >
          <el-form-item label="所属分类" prop="category_id">
            <el-tree-select
              v-model="baseForm.category_id"
              :data="categoryTree"
              :props="{ label: 'name', children: 'children' }"
              placeholder="请选择分类"
              clearable
              filterable
              check-strictly
              style="width: 100%"
            />
          </el-form-item>

          <el-form-item label="SKU">
            <el-input
              v-model="baseForm.sku"
              :readonly="isEdit"
              placeholder="商品 SKU"
              style="width: 100%"
            />
          </el-form-item>

          <el-form-item label="SKU 前缀" v-if="baseForm.sku_prefix">
            <el-tag :type="{ hic: 'danger', WPZ: 'primary', DIY: 'success', NBL: 'info' }[baseForm.sku_prefix] as any || 'info'">
              {{ baseForm.sku_prefix }}
            </el-tag>
          </el-form-item>

          <el-form-item label="状态">
            <el-radio-group v-model="baseForm.status">
              <el-radio-button value="active">启用</el-radio-button>
              <el-radio-button value="inactive">禁用</el-radio-button>
              <el-radio-button value="draft">草稿</el-radio-button>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="排序">
            <el-input-number v-model="baseForm.sort_order" :min="0" :max="9999" />
          </el-form-item>
        </el-form>
      </el-tab-pane>

      <!-- ===== 多语言描述 Tab ===== -->
      <el-tab-pane label="多语言描述" name="desc">
        <el-tabs type="card" style="padding: 16px 0">
          <el-tab-pane
            v-for="lang in LANGUAGES"
            :key="lang.code"
            :label="lang.required ? `${lang.label} *` : lang.label"
            :name="lang.code"
          >
            <el-form label-width="110px" style="max-width: 700px; padding: 16px 0">
              <el-form-item :label="'商品名称'" :required="lang.required">
                <el-input
                  v-model="descriptions.find(d => d.locale === lang.code)!.name"
                  placeholder="请输入商品名称"
                  style="width: 100%"
                />
              </el-form-item>
              <el-form-item label="商品描述">
                <el-input
                  v-model="descriptions.find(d => d.locale === lang.code)!.description"
                  type="textarea"
                  :rows="5"
                  placeholder="商品描述"
                  style="width: 100%"
                />
              </el-form-item>
              <el-form-item label="SEO 标题">
                <el-input
                  v-model="descriptions.find(d => d.locale === lang.code)!.seo_title"
                  placeholder="SEO 标题"
                  style="width: 100%"
                />
              </el-form-item>
              <el-form-item label="SEO 描述">
                <el-input
                  v-model="descriptions.find(d => d.locale === lang.code)!.seo_description"
                  type="textarea"
                  :rows="3"
                  placeholder="SEO 描述"
                  style="width: 100%"
                />
              </el-form-item>
              <el-form-item label="SEO 关键词">
                <el-input
                  v-model="descriptions.find(d => d.locale === lang.code)!.seo_keywords"
                  placeholder="用逗号分隔关键词"
                  style="width: 100%"
                />
              </el-form-item>
            </el-form>
          </el-tab-pane>
        </el-tabs>
      </el-tab-pane>

      <!-- ===== 图片管理 Tab ===== -->
      <el-tab-pane label="图片管理" name="images">
        <div style="padding: 20px 0">
          <!-- 上传区 -->
          <el-upload
            :action="uploadUrl"
            :show-file-list="false"
            :on-success="handleImageUpload"
            accept="image/*"
            multiple
          >
            <el-button type="primary" plain>
              <el-icon><Upload /></el-icon> 上传图片
            </el-button>
            <template #tip>
              <div class="el-upload__tip">支持 JPG/PNG/WEBP，单张不超过 5MB</div>
            </template>
          </el-upload>

          <!-- 图片列表 -->
          <div class="image-list" style="margin-top: 16px">
            <div
              v-for="(img, idx) in images"
              :key="idx"
              class="image-item"
              :class="{ 'is-primary': img.is_primary }"
            >
              <el-image
                :src="img.url"
                fit="cover"
                style="width: 100px; height: 100px; border-radius: 4px"
              />
              <div class="image-item__actions">
                <el-tooltip content="设为主图">
                  <el-button
                    :type="img.is_primary ? 'warning' : 'default'"
                    size="small"
                    :icon="img.is_primary ? 'StarFilled' : 'Star'"
                    circle
                    @click="handleSetPrimary(idx)"
                  />
                </el-tooltip>
                <el-tooltip content="删除">
                  <el-button
                    type="danger"
                    size="small"
                    icon="Delete"
                    circle
                    @click="handleRemoveImage(idx)"
                  />
                </el-tooltip>
              </div>
              <el-tag v-if="img.is_primary" type="warning" size="small" class="image-item__primary-tag">主图</el-tag>
            </div>

            <el-empty v-if="images.length === 0" description="暂无图片" :image-size="80" />
          </div>
        </div>
      </el-tab-pane>

      <!-- ===== SKU/变体 Tab ===== -->
      <el-tab-pane label="SKU/变体" name="skus">
        <div style="padding: 20px 0">
          <div style="margin-bottom: 16px">
            <el-button type="primary" @click="handleAddSku">
              <el-icon><Plus /></el-icon> 新增 SKU
            </el-button>
          </div>
          <el-table :data="skus" border stripe>
            <el-table-column label="SKU 编码" prop="sku_code" min-width="150" />
            <el-table-column label="价格" prop="price" width="110">
              <template #default="{ row }">¥{{ row.price }}</template>
            </el-table-column>
            <el-table-column label="特殊价格" prop="special_price" width="110">
              <template #default="{ row }">
                <span v-if="row.special_price">¥{{ row.special_price }}</span>
                <span v-else class="text-muted">—</span>
              </template>
            </el-table-column>
            <el-table-column label="库存" prop="quantity" width="90" align="center" />
            <el-table-column label="选项" min-width="150">
              <template #default="{ row }">
                <el-text v-if="row.options_str" size="small" type="info">
                  {{ row.options_str }}
                </el-text>
                <span v-else class="text-muted">—</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="130" align="center">
              <template #default="{ $index }">
                <el-button type="primary" size="small" link @click="handleEditSku($index)">编辑</el-button>
                <el-button type="danger" size="small" link @click="handleDeleteSku($index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-tab-pane>

      <!-- ===== 价格 Tab ===== -->
      <el-tab-pane label="价格设置" name="price">
        <el-form label-width="130px" style="max-width: 600px; padding: 20px 0">
          <el-form-item label="基础价格">
            <el-input-number
              v-model="baseForm.price"
              :min="0"
              :precision="2"
              style="width: 180px"
            />
            <span style="margin-left: 8px; color: #909399">元</span>
          </el-form-item>
          <el-form-item label="特殊价格">
            <el-input-number
              v-model="baseForm.special_price"
              :min="0"
              :precision="2"
              style="width: 180px"
            />
            <span style="margin-left: 8px; color: #909399">元（留空表示无特殊价格）</span>
          </el-form-item>
          <el-form-item label="促销开始时间">
            <el-date-picker
              v-model="baseForm.special_price_start"
              type="datetime"
              placeholder="选择开始时间"
              value-format="YYYY-MM-DD HH:mm:ss"
              style="width: 220px"
            />
          </el-form-item>
          <el-form-item label="促销结束时间">
            <el-date-picker
              v-model="baseForm.special_price_end"
              type="datetime"
              placeholder="选择结束时间"
              value-format="YYYY-MM-DD HH:mm:ss"
              style="width: 220px"
            />
          </el-form-item>
        </el-form>
      </el-tab-pane>
    </el-tabs>

    <!-- 底部操作 -->
    <div class="edit-footer">
      <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      <el-button @click="handleCancel">取消返回</el-button>
    </div>

    <!-- SKU 编辑弹窗 -->
    <el-dialog v-model="skuDialogVisible" :title="editingSkuIdx !== null ? '编辑 SKU' : '新增 SKU'" width="480px">
      <el-form :model="skuForm" label-width="100px">
        <el-form-item label="SKU 编码" required>
          <el-input v-model="skuForm.sku_code" placeholder="如：hic001-S-Red" />
        </el-form-item>
        <el-form-item label="价格">
          <el-input-number v-model="skuForm.price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="特殊价格">
          <el-input-number v-model="skuForm.special_price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="库存">
          <el-input-number v-model="skuForm.quantity" :min="0" />
        </el-form-item>
        <el-form-item label="选项 (JSON)">
          <el-input
            v-model="skuForm.options_str"
            type="textarea"
            :rows="3"
            placeholder='{"size":"M","color":"Red"}'
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="skuDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSaveSku">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped lang="scss">
.edit-footer {
  display: flex;
  gap: 12px;
  padding: 20px 0;
  border-top: 1px solid #ebeef5;
  margin-top: 16px;
}

.image-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.image-item {
  position: relative;
  border: 2px solid #dcdfe6;
  border-radius: 6px;
  overflow: hidden;

  &.is-primary {
    border-color: #e6a23c;
  }

  &__actions {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 4px;
    padding: 4px;
    background: rgba(0, 0, 0, 0.5);
    opacity: 0;
    transition: opacity 0.2s;
  }

  &:hover &__actions {
    opacity: 1;
  }

  &__primary-tag {
    position: absolute;
    top: 4px;
    left: 4px;
  }
}

.text-muted {
  color: #c0c4cc;
}
</style>
