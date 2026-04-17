import type { RouteRecordRaw } from 'vue-router'

/** 公开路由（无需登录） */
export const publicRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/pages/login/index.vue'),
    meta: { title: '登录', hidden: true },
  },
  {
    path: '/404',
    name: 'NotFound',
    component: () => import('@/pages/error/404.vue'),
    meta: { title: '404', hidden: true },
  },
]

/** 受保护路由（需要登录） */
export const protectedRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    component: () => import('@/layouts/MerchantLayout.vue'),
    redirect: '/dashboard',
    children: [
      // 仪表盘
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/pages/dashboard/index.vue'),
        meta: { title: '仪表盘', icon: 'Odometer', requiresAuth: true },
      },

      // 站点管理
      {
        path: 'stores',
        name: 'Stores',
        component: () => import('@/pages/stores/index.vue'),
        meta: { title: '站点管理', icon: 'Shop', requiresAuth: true },
      },

      // 商品管理
      {
        path: 'products',
        name: 'Products',
        component: () => import('@/pages/products/index.vue'),
        meta: { title: '商品列表', icon: 'Goods', requiresAuth: true },
      },
      {
        path: 'products/edit/:id?',
        name: 'ProductEdit',
        component: () => import('@/pages/products/edit.vue'),
        meta: { title: '商品编辑', requiresAuth: true, hidden: true },
      },
      {
        path: 'products/sync',
        name: 'ProductsSync',
        component: () => import('@/pages/products/sync.vue'),
        meta: { title: '商品同步', requiresAuth: true },
      },

      // 订单管理
      {
        path: 'orders',
        name: 'Orders',
        component: () => import('@/pages/orders/index.vue'),
        meta: { title: '订单列表', icon: 'Document', requiresAuth: true },
      },

      // 结算中心
      {
        path: 'settlement',
        name: 'Settlement',
        component: () => import('@/pages/settlement/index.vue'),
        meta: { title: '结算中心', icon: 'Money', requiresAuth: true },
      },

      // 用户管理
      {
        path: 'users',
        name: 'Users',
        component: () => import('@/pages/users/index.vue'),
        meta: { title: '用户管理', icon: 'User', requiresAuth: true },
      },
    ],
  },
]

/** Catch-all 路由 */
export const catchAllRoute: RouteRecordRaw = {
  path: '/:pathMatch(.*)*',
  redirect: '/404',
}
