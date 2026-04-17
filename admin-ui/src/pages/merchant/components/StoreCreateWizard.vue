<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { createStore, type CreateStorePayload } from '@/api/merchant'

// ==================== Props & Emits ====================
const props = defineProps<{
  merchantId: number
}>()

const emit = defineEmits<{
  (e: 'success'): void
  (e: 'close'): void
}>()

// ==================== 弹窗状态 ====================
const visible = ref(false)
const currentStep = ref(0)
const submitting = ref(false)

// ==================== 表单数据 ====================
const form = reactive<CreateStorePayload>({
  name: '',
  domain: '',
  market: '',
  language: '',
  currency: '',
  category: '',
})

// ==================== 步骤配置 ====================
const steps = [
  { title: '基本信息', description: '站点名称与域名' },
  { title: '市场配置', description: '目标市场与语言货币' },
  { title: '品类选择', description: '选择商品品类' },
  { title: '确认创建', description: '核对并提交' },
]

// ==================== 选项数据 ====================
const marketOptions = [
  { label: '中国大陆', value: 'CN' },
  { label: '美国', value: 'US' },
  { label: '英国', value: 'GB' },
  { label: '德国', value: 'DE' },
  { label: '法国', value: 'FR' },
  { label: '日本', value: 'JP' },
  { label: '韩国', value: 'KR' },
  { label: '澳大利亚', value: 'AU' },
  { label: '加拿大', value: 'CA' },
]

const languageOptions = [
  { label: '简体中文', value: 'zh-CN' },
  { label: 'English', value: 'en' },
  { label: 'Deutsch', value: 'de' },
  { label: 'Français', value: 'fr' },
  { label: 'Español', value: 'es' },
  { label: '日本語', value: 'ja' },
  { label: '한국어', value: 'ko' },
  { label: 'Português (BR)', value: 'pt-BR' },
  { label: 'Português (PT)', value: 'pt-PT' },
  { label: 'Nederlands', value: 'nl' },
  { label: 'Polski', value: 'pl' },
  { label: 'Svenska', value: 'sv' },
  { label: 'Dansk', value: 'da' },
  { label: 'العربية', value: 'ar' },
  { label: 'Türkçe', value: 'tr' },
  { label: 'Ελληνικά', value: 'el' },
]

const currencyOptions = [
  { label: 'CNY — 人民币', value: 'CNY' },
  { label: 'USD — 美元', value: 'USD' },
  { label: 'EUR — 欧元', value: 'EUR' },
  { label: 'GBP — 英镑', value: 'GBP' },
  { label: 'JPY — 日元', value: 'JPY' },
  { label: 'KRW — 韩元', value: 'KRW' },
  { label: 'AUD — 澳元', value: 'AUD' },
  { label: 'CAD — 加元', value: 'CAD' },
]

const categoryOptions = [
  { label: '运动服装', value: 'sports_apparel' },
  { label: '足球装备', value: 'football_equipment' },
  { label: '篮球装备', value: 'basketball_equipment' },
  { label: '跑步装备', value: 'running_equipment' },
  { label: '户外运动', value: 'outdoor_sports' },
  { label: '球类运动', value: 'ball_sports' },
  { label: '健身器材', value: 'fitness_equipment' },
  { label: '运动配件', value: 'sports_accessories' },
]

// ==================== 步骤校验 ====================
const step1Valid = computed(() => form.name.trim() !== '' && form.domain.trim() !== '')
const step2Valid = computed(() => form.market !== '' && form.language !== '' && form.currency !== '')
const step3Valid = computed(() => form.category !== '')

function canProceed(): boolean {
  if (currentStep.value === 0) return step1Valid.value
  if (currentStep.value === 1) return step2Valid.value
  if (currentStep.value === 2) return step3Valid.value
  return true
}

// ==================== 步骤导航 ====================
function handleNext() {
  if (!canProceed()) {
    ElMessage.warning('请填写完整当前步骤的必填项')
    return
  }
  if (currentStep.value < steps.length - 1) {
    currentStep.value++
  }
}

function handlePrev() {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

// ==================== 提交 ====================
async function handleSubmit() {
  submitting.value = true
  try {
    await createStore(props.merchantId, { ...form })
    ElMessage.success('站点创建成功')
    emit('success')
    handleClose()
  } catch {
    // 由拦截器处理
  } finally {
    submitting.value = false
  }
}

// ==================== 打开/关闭 ====================
function open() {
  currentStep.value = 0
  form.name = ''
  form.domain = ''
  form.market = ''
  form.language = ''
  form.currency = ''
  form.category = ''
  visible.value = true
}

function handleClose() {
  visible.value = false
  emit('close')
}

// 暴露 open 方法给父组件调用
defineExpose({ open })

// ==================== 工具函数 ====================
function getLabelByValue(options: { label: string; value: string }[], value: string): string {
  return options.find((o) => o.value === value)?.label ?? value
}
</script>

<template>
  <el-dialog
    v-model="visible"
    title="创建站点"
    width="620px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <!-- 步骤条 -->
    <el-steps :active="currentStep" finish-status="success" align-center style="margin-bottom: 32px">
      <el-step
        v-for="step in steps"
        :key="step.title"
        :title="step.title"
        :description="step.description"
      />
    </el-steps>

    <!-- Step 1：基本信息 -->
    <div v-show="currentStep === 0" class="step-content">
      <el-form label-width="100px" label-position="right">
        <el-form-item label="站点名称" required>
          <el-input
            v-model="form.name"
            placeholder="请输入站点名称"
            maxlength="60"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="域名" required>
          <el-input
            v-model="form.domain"
            placeholder="例：shop.example.com"
          >
            <template #prepend>https://</template>
          </el-input>
          <div class="form-tip">请填写不带协议的域名，系统将自动使用 HTTPS</div>
        </el-form-item>
      </el-form>
    </div>

    <!-- Step 2：市场配置 -->
    <div v-show="currentStep === 1" class="step-content">
      <el-form label-width="100px" label-position="right">
        <el-form-item label="目标市场" required>
          <el-select v-model="form.market" placeholder="请选择目标市场" style="width: 100%">
            <el-option
              v-for="m in marketOptions"
              :key="m.value"
              :label="m.label"
              :value="m.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="站点语言" required>
          <el-select v-model="form.language" placeholder="请选择语言" style="width: 100%">
            <el-option
              v-for="l in languageOptions"
              :key="l.value"
              :label="l.label"
              :value="l.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="结算货币" required>
          <el-select v-model="form.currency" placeholder="请选择货币" style="width: 100%">
            <el-option
              v-for="c in currencyOptions"
              :key="c.value"
              :label="c.label"
              :value="c.value"
            />
          </el-select>
        </el-form-item>
      </el-form>
    </div>

    <!-- Step 3：品类选择 -->
    <div v-show="currentStep === 2" class="step-content">
      <el-form label-width="100px" label-position="right">
        <el-form-item label="主营品类" required>
          <el-radio-group v-model="form.category" class="category-group">
            <el-radio-button
              v-for="cat in categoryOptions"
              :key="cat.value"
              :value="cat.value"
            >
              {{ cat.label }}
            </el-radio-button>
          </el-radio-group>
        </el-form-item>
      </el-form>
    </div>

    <!-- Step 4：确认信息 -->
    <div v-show="currentStep === 3" class="step-content">
      <el-descriptions :column="1" border>
        <el-descriptions-item label="站点名称">{{ form.name }}</el-descriptions-item>
        <el-descriptions-item label="域名">https://{{ form.domain }}</el-descriptions-item>
        <el-descriptions-item label="目标市场">
          {{ getLabelByValue(marketOptions, form.market) }}
        </el-descriptions-item>
        <el-descriptions-item label="站点语言">
          {{ getLabelByValue(languageOptions, form.language) }}
        </el-descriptions-item>
        <el-descriptions-item label="结算货币">
          {{ getLabelByValue(currencyOptions, form.currency) }}
        </el-descriptions-item>
        <el-descriptions-item label="主营品类">
          {{ getLabelByValue(categoryOptions, form.category) }}
        </el-descriptions-item>
      </el-descriptions>
      <el-alert
        type="info"
        show-icon
        :closable="false"
        title="请确认以上信息无误，站点创建后域名不可更改"
        style="margin-top: 16px"
      />
    </div>

    <!-- 底部按钮 -->
    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button v-if="currentStep > 0" @click="handlePrev">上一步</el-button>
      <el-button
        v-if="currentStep < steps.length - 1"
        type="primary"
        @click="handleNext"
      >
        下一步
      </el-button>
      <el-button
        v-if="currentStep === steps.length - 1"
        type="primary"
        :loading="submitting"
        @click="handleSubmit"
      >
        确认创建
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped lang="scss">
.step-content {
  min-height: 200px;
  padding: 0 16px;
}

.form-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.4;
}

.category-group {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;

  :deep(.el-radio-button__inner) {
    border-radius: 4px !important;
  }
}
</style>
