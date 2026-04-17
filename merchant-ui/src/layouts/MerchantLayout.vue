<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { useAppStore } from '@/stores/app'
import StoreSwitcher from '@/components/StoreSwitcher.vue'

const COLLAPSE_KEY = 'jh_merchant_sidebar_collapse'

const isCollapse = ref(localStorage.getItem(COLLAPSE_KEY) === 'true')
const router = useRouter()
const route = useRoute()
const userStore = useUserStore()
const appStore = useAppStore()

/** 菜单配置 */
const menuConfig = [
  {
    index: '/dashboard',
    title: '仪表盘',
    icon: 'Odometer',
  },
  {
    index: '/stores',
    title: '站点管理',
    icon: 'Shop',
  },
  {
    index: '/products',
    title: '商品管理',
    icon: 'Goods',
    children: [
      { index: '/products', title: '商品列表' },
      { index: '/products/sync', title: '商品同步' },
    ],
  },
  {
    index: '/orders',
    title: '订单管理',
    icon: 'Document',
  },
  {
    index: '/settlement',
    title: '结算中心',
    icon: 'Money',
  },
  {
    index: '/users',
    title: '用户管理',
    icon: 'User',
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
  appStore.setSidebarCollapse(isCollapse.value)
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
        <span class="logo-full" v-show="!isCollapse">Merchant</span>
        <span class="logo-mini" v-show="isCollapse">M</span>
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
          <!-- 站点切换 -->
          <StoreSwitcher />

          <!-- 用户下拉菜单 -->
          <el-dropdown trigger="click">
            <span class="user-info">
              <el-avatar :size="32" style="background:#409eff; margin-right:8px">
                {{ (userStore.userInfo?.name ?? 'M')[0].toUpperCase() }}
              </el-avatar>
              <span>{{ userStore.userInfo?.name ?? 'Merchant' }}</span>
              <el-icon class="ml-4"><ArrowDown /></el-icon>
            </span>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item disabled>
                  <el-icon><UserFilled /></el-icon>
                  {{ userStore.userInfo?.email ?? '' }}
                </el-dropdown-item>
                <el-dropdown-item divided @click="handleLogout">
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

.store-tag {
  cursor: pointer;
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
