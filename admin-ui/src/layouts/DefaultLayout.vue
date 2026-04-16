<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'

const COLLAPSE_KEY = 'jh_sidebar_collapse'

const isCollapse = ref(localStorage.getItem(COLLAPSE_KEY) === 'true')
const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

/** 菜单配置 */
const menuConfig = [
  {
    index: '/dashboard',
    title: '仪表盘',
    icon: 'Odometer',
  },
  {
    index: '/product',
    title: '商品管理',
    icon: 'Goods',
    children: [
      { index: '/product/list', title: '商品列表' },
      { index: '/category/list', title: '分类管理' },
      { index: '/product/mapping', title: '映射管理' },
    ],
  },
  {
    index: '/order',
    title: '订单管理',
    icon: 'Document',
    children: [
      { index: '/order/list', title: '订单列表' },
      { index: '/order/refund', title: '退款管理' },
    ],
  },
  {
    index: '/payment',
    title: '支付管理',
    icon: 'CreditCard',
    children: [
      { index: '/payment/accounts', title: '账号管理' },
      { index: '/payment/transactions', title: '交易记录' },
    ],
  },
  {
    index: '/shipping',
    title: '物流管理',
    icon: 'Van',
    children: [
      { index: '/shipping/list', title: '发货管理' },
      { index: '/shipping/providers', title: '物流商配置' },
    ],
  },
  {
    index: '/user',
    title: '用户管理',
    icon: 'User',
    children: [
      { index: '/user/admins', title: '管理员' },
      { index: '/user/merchants', title: '商户' },
      { index: '/user/customers', title: '买家' },
    ],
  },
  {
    index: '/marketing',
    title: '营销管理',
    icon: 'Present',
    children: [
      { index: '/marketing/coupons', title: '优惠券' },
      { index: '/marketing/promotions', title: '促销活动' },
    ],
  },
  {
    index: '/setting',
    title: '系统设置',
    icon: 'Setting',
    children: [
      { index: '/setting/general', title: '基础配置' },
      { index: '/setting/rbac', title: '权限管理' },
      { index: '/setting/logs', title: '操作日志' },
    ],
  },
]

/** 面包屑 */
const breadcrumbs = computed(() => {
  const matched = route.matched.filter(r => r.meta?.title)
  return matched.map(r => (r.meta?.title as string) || '')
})

function toggleSidebar() {
  isCollapse.value = !isCollapse.value
  localStorage.setItem(COLLAPSE_KEY, String(isCollapse.value))
}

async function handleLogout() {
  await userStore.logout()
  router.push('/login')
}
</script>

<template>
  <el-container class="layout-container">
    <!-- 侧边栏 -->
    <el-aside :width="isCollapse ? '64px' : '220px'" class="layout-aside">
      <div class="logo-area">
        <span class="logo-full" v-show="!isCollapse">JerseyHolic</span>
        <span class="logo-mini" v-show="isCollapse">JH</span>
      </div>

      <el-scrollbar class="menu-scrollbar">
        <el-menu
          :default-active="route.path"
          :collapse="isCollapse"
          :collapse-transition="false"
          router
          background-color="#304156"
          text-color="#bfcbd9"
          active-text-color="#409eff"
        >
          <template v-for="item in menuConfig" :key="item.index">
            <!-- 有子菜单 -->
            <el-sub-menu v-if="item.children" :index="item.index">
              <template #title>
                <el-icon><component :is="item.icon" /></el-icon>
                <span>{{ item.title }}</span>
              </template>
              <el-menu-item
                v-for="child in item.children"
                :key="child.index"
                :index="child.index"
              >
                {{ child.title }}
              </el-menu-item>
            </el-sub-menu>

            <!-- 无子菜单 -->
            <el-menu-item v-else :index="item.index">
              <el-icon><component :is="item.icon" /></el-icon>
              <template #title>{{ item.title }}</template>
            </el-menu-item>
          </template>
        </el-menu>
      </el-scrollbar>
    </el-aside>

    <el-container style="overflow: hidden;">
      <!-- 顶部栏 -->
      <el-header class="layout-header">
        <div class="header-left">
          <el-icon class="collapse-btn" @click="toggleSidebar">
            <Expand v-if="isCollapse" />
            <Fold v-else />
          </el-icon>
          <!-- 面包屑 -->
          <el-breadcrumb separator="/" class="breadcrumb">
            <el-breadcrumb-item
              v-for="(crumb, i) in breadcrumbs"
              :key="i"
            >{{ crumb }}</el-breadcrumb-item>
          </el-breadcrumb>
        </div>

        <div class="header-right">
          <el-dropdown trigger="click">
            <span class="user-info">
              <el-avatar :size="32" style="background:#409eff; margin-right:8px">
                {{ (userStore.userInfo?.name ?? 'A')[0].toUpperCase() }}
              </el-avatar>
              <span>{{ userStore.userInfo?.name ?? 'Admin' }}</span>
              <el-icon class="ml-4"><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item disabled>
                  <el-icon><UserFilled /></el-icon>
                  {{ userStore.userInfo?.email ?? '' }}
                </el-dropdown-item>
                <el-dropdown-item divided @click="router.push('/setting/general')">
                  <el-icon><Setting /></el-icon>个人设置
                </el-dropdown-item>
                <el-dropdown-item @click="handleLogout">
                  <el-icon><SwitchButton /></el-icon>退出登录
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </el-header>

      <!-- 主内容区 -->
      <el-main class="layout-main">
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped lang="scss">
.layout-container {
  height: 100vh;
  overflow: hidden;
}

.layout-aside {
  background: #304156;
  transition: width 0.28s;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.logo-area {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  flex-shrink: 0;

  .logo-full {
    color: #fff;
    font-size: 18px;
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: 1px;
  }

  .logo-mini {
    color: #409eff;
    font-size: 20px;
    font-weight: 700;
  }
}

.menu-scrollbar {
  flex: 1;
  overflow: hidden;

  :deep(.el-scrollbar__wrap) {
    overflow-x: hidden;
  }

  :deep(.el-menu) {
    border-right: none;
  }

  :deep(.el-sub-menu .el-menu-item) {
    background-color: #263445 !important;

    &:hover, &.is-active {
      background-color: #1f2d3d !important;
    }
  }
}

.layout-header {
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 20px;
  border-bottom: 1px solid #e6e6e6;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.08);
  flex-shrink: 0;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.collapse-btn {
  cursor: pointer;
  font-size: 20px;
  color: #606266;

  &:hover {
    color: #409eff;
  }
}

.breadcrumb {
  :deep(.el-breadcrumb__inner) {
    font-size: 14px;
  }
}

.header-right {
  display: flex;
  align-items: center;
}

.user-info {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 4px;
  transition: background 0.2s;

  &:hover {
    background: #f5f7fa;
  }

  .ml-4 {
    margin-left: 4px;
  }

  span {
    font-size: 14px;
    color: #303133;
  }
}

.layout-main {
  background: #f0f2f5;
  padding: 20px;
  overflow-y: auto;
  height: calc(100vh - 60px);
}
</style>
