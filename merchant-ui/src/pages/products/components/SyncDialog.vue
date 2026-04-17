<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Close, Loading } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { syncBatchProducts, type MasterProduct, type SyncField, type PricingStrategy } from '@/api/product'

// ─── Props / Emits ────────────────────────────────────────────────────────────

interface Props {
  visible: boolean
  products: MasterProduct[]
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'success'): void
}>()

// ─── 内部状态 ─────────────────────────────────────────────────────────────────

const userStore = useUserStore()

/** 已选商品（可在弹窗内移除） */
const pendingProducts = ref<MasterProduct[]>([])

/** 已选目标站点 ID 列表 */
const selectedStoreIds = ref<number[]>([])

/** 同步字段选择 */
const syncFields = ref<SyncField[]>(['name', 'description', 'price', 'images', 'variants'])

/** 价格策略 */
const pricingStrategy = ref<PricingStrategy>('original')

/** 价格倍率 / 固定价 */
const pricingValue = ref<number>(1.0)

/** 是否正在同步 */
const syncing = ref(false)

// ─── 监听商品列表变化 ─────────────────────────────────────────────────────────

watch(
  () => props.products,
  (val) => {
    pendingProducts.value = [...val]
    selectedStoreIds.value = []
    syncFields.value = ['name', 'description', 'price', 'images', 'variants']
    pricingStrategy.value = 'original'
    pricingValue.value = 1.0
  },
  { immediate: true },
)

// ─── 站点列表 ─────────────────────────────────────────────────────────────────

const storeList = computed(() => userStore.stores)

// ─── 操作 ─────────────────────────────────────────────────────────────────────

function removeProduct(index: number) {
  pendingProducts.value.splice(index, 1)
}

function toggleAllStores(checked: boolean) {
  if (checked) {
    selectedStoreIds.value = storeList.value.map(s => s.id)
  } else {
    selectedStoreIds.value = []
  }
}

const allStoresSelected = computed(() =>
  storeList.value.length > 0 && selectedStoreIds.value.length === storeList.value.length,
)

const indeterminate = computed(() =>
  selectedStoreIds.value.length > 0 && selectedStoreIds.value.length < storeList.value.length,
)

// ─── 确认同步 ─────────────────────────────────────────────────────────────────

async function handleConfirm() {
  if (pendingProducts.value.length === 0) {
    ElMessage.warning('没有需要同步的商品')
    return
  }
  if (selectedStoreIds.value.length === 0) {
    ElMessage.warning('请选择至少一个目标站点')
    return
  }
  if (syncFields.value.length === 0) {
    ElMessage.warning('请选择至少一个同步字段')
    return
  }

  syncing.value = true
  try {
    await syncBatchProducts({
      master_product_ids: pendingProducts.value.map(p => p.id),
      store_ids: selectedStoreIds.value,
      options: {
        sync_fields: syncFields.value,
        pricing_strategy: pricingStrategy.value,
        pricing_value: pricingStrategy.value !== 'original' ? pricingValue.value : undefined,
      },
    })
    emit('success')
    emit('update:visible', false)
  } finally {
    syncing.value = false
  }
}

function handleClose() {
  if (!syncing.value) {
    emit('update:visible', false)
  }
}

// ─── 字段选项 ─────────────────────────────────────────────────────────────────

const syncFieldOptions: Array<{ label: string; value: SyncField }> = [
  { label: '商品名称', value: 'name' },
  { label: '描述内容', value: 'description' },
  { label: '价格', value: 'price' },
  { label: '图片', value: 'images' },
  { label: '变体/SKU', value: 'variants' },
]
</script>

<template>
  <el-dialog
    :model-value="visible"
    title="同步商品到站点"
    width="760px"
    :close-on-click-modal="false"
    :close-on-press-escape="!syncing"
    @close="handleClose"
    @update:model-value="handleClose"
  >
    <div class="sync-dialog-body">
      <!-- 左侧：待同步商品 -->
      <div class="sync-panel sync-products-panel">
        <div class="panel-header">
          <span class="panel-title">待同步商品</span>
          <span class="panel-count">{{ pendingProducts.length }} 个</span>
        </div>
        <div class="panel-content">
          <el-empty v-if="pendingProducts.length === 0" description="暂无商品" :image-size="60" />
          <div v-else class="product-list">
            <div
              v-for="(product, index) in pendingProducts"
              :key="product.id"
              class="product-item"
            >
              <div class="product-info">
                <span class="product-name" :title="product.name">{{ product.name }}</span>
                <span class="product-sku">{{ product.sku }}</span>
              </div>
              <el-button
                link
                type="danger"
                :icon="Close"
                size="small"
                @click="removeProduct(index)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧：目标站点 -->
      <div class="sync-panel sync-stores-panel">
        <div class="panel-header">
          <span class="panel-title">目标站点</span>
          <el-checkbox
            :model-value="allStoresSelected"
            :indeterminate="indeterminate"
            @change="(val) => toggleAllStores(val as boolean)"
          >
            全选
          </el-checkbox>
        </div>
        <div class="panel-content">
          <el-empty v-if="storeList.length === 0" description="暂无可用站点" :image-size="60" />
          <el-checkbox-group v-else v-model="selectedStoreIds" class="store-list">
            <el-checkbox
              v-for="store in storeList"
              :key="store.id"
              :label="store.id"
              class="store-item"
            >
              <div class="store-item-content">
                <span class="store-name">{{ store.name }}</span>
                <span class="store-domain">{{ store.domain }}</span>
              </div>
              <el-tag
                :type="store.status === 'active' ? 'success' : 'info'"
                size="small"
              >
                {{ store.status === 'active' ? '启用' : '停用' }}
              </el-tag>
            </el-checkbox>
          </el-checkbox-group>
        </div>
      </div>
    </div>

    <!-- 底部选项 -->
    <div class="sync-options">
      <!-- 同步字段 -->
      <div class="option-section">
        <div class="option-label">同步字段：</div>
        <el-checkbox-group v-model="syncFields" class="fields-group">
          <el-checkbox
            v-for="opt in syncFieldOptions"
            :key="opt.value"
            :label="opt.value"
          >
            {{ opt.label }}
          </el-checkbox>
        </el-checkbox-group>
      </div>

      <!-- 价格策略 -->
      <div class="option-section">
        <div class="option-label">价格策略：</div>
        <div class="pricing-row">
          <el-radio-group v-model="pricingStrategy">
            <el-radio label="original">使用原价</el-radio>
            <el-radio label="multiplier">按倍率</el-radio>
            <el-radio label="fixed">固定价格</el-radio>
          </el-radio-group>
          <el-input-number
            v-if="pricingStrategy === 'multiplier'"
            v-model="pricingValue"
            :min="0.1"
            :max="10"
            :step="0.1"
            :precision="2"
            size="small"
            style="width: 120px; margin-left: 12px;"
          >
            <template #suffix>× 倍</template>
          </el-input-number>
          <el-input-number
            v-if="pricingStrategy === 'fixed'"
            v-model="pricingValue"
            :min="0"
            :step="1"
            :precision="2"
            size="small"
            style="width: 140px; margin-left: 12px;"
          >
            <template #prefix">¥</template>
          </el-input-number>
        </div>
      </div>
    </div>

    <!-- 弹窗底部按钮 -->
    <template #footer>
      <div class="dialog-footer">
        <el-button :disabled="syncing" @click="handleClose">取消</el-button>
        <el-button
          type="primary"
          :loading="syncing"
          :disabled="pendingProducts.length === 0 || selectedStoreIds.length === 0"
          @click="handleConfirm"
        >
          <el-icon v-if="syncing"><Loading /></el-icon>
          {{ syncing ? '同步中...' : `确认同步（${pendingProducts.length} 个商品 → ${selectedStoreIds.length} 个站点）` }}
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<style scoped lang="scss">
.sync-dialog-body {
  display: flex;
  gap: 16px;
  min-height: 300px;
}

.sync-panel {
  flex: 1;
  border: 1px solid #dcdfe6;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;

  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 14px;
    background: #f5f7fa;
    border-bottom: 1px solid #dcdfe6;

    .panel-title {
      font-size: 13px;
      font-weight: 600;
      color: #303133;
    }

    .panel-count {
      font-size: 12px;
      color: #909399;
    }
  }

  .panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
    max-height: 280px;
  }
}

.product-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.product-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 10px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background: #fff;

  &:hover {
    background: #f5f7fa;
  }

  .product-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .product-name {
    font-size: 13px;
    color: #303133;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .product-sku {
    font-size: 11px;
    color: #909399;
  }
}

.store-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.store-item {
  display: flex !important;
  align-items: center;
  padding: 8px 10px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  margin-right: 0 !important;
  width: 100%;

  &:hover {
    background: #f5f7fa;
  }

  :deep(.el-checkbox__label) {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .store-item-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .store-name {
    font-size: 13px;
    color: #303133;
  }

  .store-domain {
    font-size: 11px;
    color: #909399;
  }
}

.sync-options {
  margin-top: 16px;
  padding: 14px 16px;
  background: #f8f9fb;
  border-radius: 6px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.option-section {
  display: flex;
  align-items: flex-start;
  gap: 8px;

  .option-label {
    font-size: 13px;
    color: #606266;
    white-space: nowrap;
    line-height: 32px;
    min-width: 72px;
  }
}

.fields-group {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.pricing-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
