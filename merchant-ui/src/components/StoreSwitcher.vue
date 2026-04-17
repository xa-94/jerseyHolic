<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Shop, ArrowDown, CircleCheck } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'
import { getStoreList } from '@/api/store'
import type { StoreDetail } from '@/api/store'

const userStore = useUserStore()

/** 从 API 获取的完整站点列表（含 category/market 等） */
const storeList = ref<StoreDetail[]>([])

/** 全部站点选项的虚拟 ID */
const ALL_STORES_ID = -1

/** 当前选中的站点 ID（-1 = 全部站点） */
const selectedId = computed({
  get: () => userStore.currentStoreId ?? ALL_STORES_ID,
  set: (val: number) => {
    if (val === ALL_STORES_ID) {
      // 全部站点：将 currentStoreId 设为 null（Pinia setup store 直接赋值 ref 是合法的）
      userStore.currentStoreId = null
    } else {
      userStore.setCurrentStore(val)
    }
  },
})

/** 当前站点信息（用于触发器显示） */
const currentStore = computed(() => {
  if (selectedId.value === ALL_STORES_ID) return null
  return storeList.value.find(s => s.id === selectedId.value) ?? null
})

/** 触发器显示文字 */
const triggerLabel = computed(() => {
  if (!currentStore.value) return '全部站点'
  return currentStore.value.name
})

/** 触发器显示域名 */
const triggerDomain = computed(() => {
  if (!currentStore.value) return ''
  return currentStore.value.domain
})

/** 加载站点列表 */
async function loadStores() {
  try {
    const res = await getStoreList()
    storeList.value = res.data?.data ?? []
  } catch {
    // 降级：使用 userStore.stores 中的基础信息
    storeList.value = userStore.stores as StoreDetail[]
  }
}

onMounted(() => {
  loadStores()
})
</script>

<template>
  <el-dropdown trigger="click" class="store-switcher">
    <div class="store-trigger">
      <el-icon class="store-icon"><Shop /></el-icon>
      <div class="store-info">
        <span class="store-name">{{ triggerLabel }}</span>
        <span v-if="triggerDomain" class="store-domain">{{ triggerDomain }}</span>
      </div>
      <el-icon class="arrow-icon"><ArrowDown /></el-icon>
    </div>

    <template #dropdown>
      <el-dropdown-menu class="store-dropdown-menu">
        <!-- 全部站点 -->
        <el-dropdown-item
          :class="{ 'is-selected': selectedId === ALL_STORES_ID }"
          @click="selectedId = ALL_STORES_ID"
        >
          <div class="store-option">
            <div class="store-option-main">
              <span class="option-name">全部站点</span>
              <span class="option-desc">聚合视图</span>
            </div>
            <el-icon v-if="selectedId === ALL_STORES_ID" class="check-icon">
              <CircleCheck />
            </el-icon>
          </div>
        </el-dropdown-item>

        <el-dropdown-item divided disabled style="padding: 4px 12px; font-size: 12px; color: #909399;">
          切换站点
        </el-dropdown-item>

        <!-- 站点列表 -->
        <el-dropdown-item
          v-for="store in storeList"
          :key="store.id"
          :class="{ 'is-selected': selectedId === store.id }"
          @click="selectedId = store.id"
        >
          <div class="store-option">
            <div class="store-option-main">
              <div class="option-header">
                <span class="option-name">{{ store.name }}</span>
                <el-tag
                  :type="store.status === 'active' ? 'success' : 'info'"
                  size="small"
                  style="margin-left: 6px;"
                >
                  {{ store.status === 'active' ? '启用' : '停用' }}
                </el-tag>
              </div>
              <span class="option-domain">{{ store.domain }}</span>
              <span v-if="store.market" class="option-market">{{ store.market }}</span>
            </div>
            <el-icon v-if="selectedId === store.id" class="check-icon">
              <CircleCheck />
            </el-icon>
          </div>
        </el-dropdown-item>

        <!-- 空状态 -->
        <el-dropdown-item v-if="storeList.length === 0" disabled>
          <span style="color: #909399; font-size: 13px;">暂无站点</span>
        </el-dropdown-item>
      </el-dropdown-menu>
    </template>
  </el-dropdown>
</template>

<style scoped lang="scss">
.store-switcher {
  margin-right: 16px;
}

.store-trigger {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 6px;
  border: 1px solid #dcdfe6;
  background: #f5f7fa;
  cursor: pointer;
  transition: all 0.2s;
  min-width: 140px;
  max-width: 220px;

  &:hover {
    border-color: #409eff;
    background: #ecf5ff;
  }
}

.store-icon {
  color: #409eff;
  font-size: 14px;
  flex-shrink: 0;
}

.store-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.store-name {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.store-domain {
  font-size: 11px;
  color: #909399;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.arrow-icon {
  font-size: 12px;
  color: #909399;
  flex-shrink: 0;
}

.store-dropdown-menu {
  min-width: 260px;
}

.store-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 8px;
}

.store-option-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.option-header {
  display: flex;
  align-items: center;
}

.option-name {
  font-size: 13px;
  font-weight: 500;
  color: #303133;
}

.option-desc {
  font-size: 12px;
  color: #909399;
}

.option-domain {
  font-size: 12px;
  color: #606266;
}

.option-market {
  font-size: 11px;
  color: #909399;
}

.check-icon {
  color: #409eff;
  font-size: 15px;
  flex-shrink: 0;
}

:deep(.is-selected) {
  background: #ecf5ff;

  .option-name {
    color: #409eff;
  }
}
</style>
