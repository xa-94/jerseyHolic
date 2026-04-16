<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ShoppingCart, Money, User, Goods } from '@element-plus/icons-vue'

interface StatCard {
  title: string
  value: string | number
  icon: any
  color: string
}

const stats = ref<StatCard[]>([
  { title: '今日订单', value: '-', icon: ShoppingCart, color: '#409eff' },
  { title: '今日收入', value: '-', icon: Money, color: '#67c23a' },
  { title: '新增用户', value: '-', icon: User, color: '#e6a23c' },
  { title: '商品总数', value: '-', icon: Goods, color: '#f56c6c' },
])

onMounted(() => {
  // TODO: 从 API 加载统计数据
  stats.value = [
    { title: '今日订单', value: 128, icon: ShoppingCart, color: '#409eff' },
    { title: '今日收入', value: '$12,680', icon: Money, color: '#67c23a' },
    { title: '新增用户', value: 36, icon: User, color: '#e6a23c' },
    { title: '商品总数', value: 1024, icon: Goods, color: '#f56c6c' },
  ]
})
</script>

<template>
  <div class="dashboard-page">
    <h2 class="page-title">欢迎回来，管理员</h2>
    <p class="page-subtitle">以下是今日的运营概况</p>

    <el-row :gutter="20" class="mt-20">
      <el-col v-for="(item, index) in stats" :key="index" :xs="24" :sm="12" :lg="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-info">
              <div class="stat-title">{{ item.title }}</div>
              <div class="stat-value">{{ item.value }}</div>
            </div>
            <div class="stat-icon" :style="{ backgroundColor: item.color }">
              <el-icon :size="28" color="#fff"><component :is="item.icon" /></el-icon>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<style scoped lang="scss">
.dashboard-page {
  .page-title {
    font-size: 22px;
    font-weight: 600;
    color: #303133;
    margin: 0;
  }
  .page-subtitle {
    font-size: 14px;
    color: #909399;
    margin: 8px 0 0;
  }
}

.stat-card {
  margin-bottom: 20px;

  .stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .stat-info {
    .stat-title {
      font-size: 14px;
      color: #909399;
      margin-bottom: 8px;
    }
    .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: #303133;
    }
  }

  .stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}
</style>
