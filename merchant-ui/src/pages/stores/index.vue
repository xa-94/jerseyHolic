<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Shop, Link, Goods, Document, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getStoreList } from '@/api/store'
import type { StoreDetail } from '@/api/store'

const router = useRouter()
const loading = ref(false)
const storeList = ref<StoreDetail[]>([])

/** 语言标签映射 */
const languageMap: Record<string, string> = {
  en: 'English',
  de: 'Deutsch',
  fr: 'Français',
  es: 'Español',
  it: 'Italiano',
  ja: '日本語',
  ko: '한국어',
  'pt-BR': 'Português (BR)',
  'pt-PT': 'Português (PT)',
  nl: 'Nederlands',
  pl: 'Polski',
  sv: 'Svenska',
  da: 'Dansk',
  ar: 'العربية',
  tr: 'Türkçe',
  el: 'Ελληνικά',
}

function getLanguageLabel(code: string): string {
  return languageMap[code] ?? code
}

async function loadStores() {
  loading.value = true
  try {
    const res = await getStoreList()
    storeList.value = res.data?.data ?? []
  } finally {
    loading.value = false
  }
}

function openDomain(domain: string) {
  if (!domain) return
  const url = domain.startsWith('http') ? domain : `https://${domain}`
  window.open(url, '_blank')
}

function goProducts(storeId: number) {
  router.push({ path: '/products', query: { store_id: String(storeId) } })
}

function goOrders(storeId: number) {
  router.push({ path: '/orders', query: { store_id: String(storeId) } })
}

onMounted(() => {
  loadStores()
})
</script>

<template>
  <div class="stores-page">
    <!-- 页面标题栏 -->
    <div class="page-header">
      <div class="page-header-left">
        <h2 class="page-title">站点管理</h2>
        <el-tag type="info" size="small" style="margin-left: 8px;">共 {{ storeList.length }} 个站点</el-tag>
      </div>
      <el-button :icon="Refresh" @click="loadStores" :loading="loading" size="small">刷新</el-button>
    </div>

    <!-- 加载状态 -->
    <div v-loading="loading" style="min-height: 200px;">
      <!-- 站点卡片网格 -->
      <el-row :gutter="16" v-if="storeList.length > 0">
        <el-col
          v-for="store in storeList"
          :key="store.id"
          :xs="24"
          :sm="12"
          :lg="8"
          :xl="6"
          style="margin-bottom: 16px;"
        >
          <el-card class="store-card" shadow="hover">
            <!-- 卡片头：站点名 + 状态 -->
            <div class="store-header">
              <div class="store-icon-wrap">
                <el-icon :size="20" color="#409eff"><Shop /></el-icon>
              </div>
              <div class="store-name-wrap">
                <span class="store-name">{{ store.name }}</span>
                <el-tag
                  :type="store.status === 'active' ? 'success' : 'info'"
                  size="small"
                  style="margin-left: 6px;"
                >
                  {{ store.status === 'active' ? '启用' : '停用' }}
                </el-tag>
              </div>
            </div>

            <!-- 站点信息列表 -->
            <div class="store-meta">
              <!-- 域名 -->
              <div class="meta-item">
                <span class="meta-label">域名</span>
                <span class="meta-value domain-link" @click="openDomain(store.domain)">
                  {{ store.domain }}
                  <el-icon class="link-icon" :size="12"><Link /></el-icon>
                </span>
              </div>

              <!-- 品类 -->
              <div class="meta-item" v-if="store.category">
                <span class="meta-label">品类</span>
                <span class="meta-value">{{ store.category }}</span>
              </div>

              <!-- 目标市场 -->
              <div class="meta-item" v-if="store.market">
                <span class="meta-label">市场</span>
                <span class="meta-value">{{ store.market }}</span>
              </div>

              <!-- 语言 -->
              <div class="meta-item">
                <span class="meta-label">语言</span>
                <span class="meta-value">{{ getLanguageLabel(store.language) }}</span>
              </div>

              <!-- 货币 -->
              <div class="meta-item">
                <span class="meta-label">货币</span>
                <span class="meta-value">{{ store.currency }}</span>
              </div>
            </div>

            <!-- 卡片底部：操作 -->
            <div class="store-actions">
              <el-button
                size="small"
                :icon="Goods"
                @click="goProducts(store.id)"
              >
                查看商品
              </el-button>
              <el-button
                size="small"
                :icon="Document"
                @click="goOrders(store.id)"
              >
                查看订单
              </el-button>
            </div>
          </el-card>
        </el-col>
      </el-row>

      <!-- 空状态 -->
      <el-empty
        v-else-if="!loading"
        description="暂无站点数据"
        style="padding: 60px 0;"
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.stores-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-header-left {
  display: flex;
  align-items: center;
}

.page-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.store-card {
  height: 100%;
  border-radius: 10px;
  transition: transform 0.2s;

  &:hover {
    transform: translateY(-2px);
  }

  :deep(.el-card__body) {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
}

.store-header {
  display: flex;
  align-items: center;
  gap: 10px;
}

.store-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #ecf5ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.store-name-wrap {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
  flex-wrap: wrap;
  gap: 4px;
}

.store-name {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.store-meta {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 10px 0;
  border-top: 1px solid #f0f2f5;
  border-bottom: 1px solid #f0f2f5;
}

.meta-item {
  display: flex;
  align-items: center;
  gap: 8px;
}

.meta-label {
  font-size: 12px;
  color: #909399;
  width: 36px;
  flex-shrink: 0;
}

.meta-value {
  font-size: 13px;
  color: #606266;

  &.domain-link {
    color: #409eff;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 3px;

    &:hover {
      text-decoration: underline;
    }
  }
}

.link-icon {
  flex-shrink: 0;
}

.store-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
