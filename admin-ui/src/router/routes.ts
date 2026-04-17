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
    component: () => import('@/layouts/DefaultLayout.vue'),
    redirect: '/dashboard',
    children: [
      // 仪表盘
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/pages/dashboard/index.vue'),
        meta: { title: '仪表盘', icon: 'Odometer', requiresAuth: true },
      },

      // 商品管理
      {
        path: 'product/list',
        name: 'ProductList',
        component: () => import('@/pages/product/list.vue'),
        meta: { title: '商品列表', icon: 'Goods', requiresAuth: true },
      },
      {
        path: 'product/create',
        name: 'ProductCreate',
        component: () => import('@/pages/product/edit.vue'),
        meta: { title: '新增商品', hidden: true, requiresAuth: true },
      },
      {
        path: 'product/edit/:id',
        name: 'ProductEdit',
        component: () => import('@/pages/product/edit.vue'),
        meta: { title: '编辑商品', hidden: true, requiresAuth: true },
      },
      {
        path: 'product/mapping',
        name: 'ProductMapping',
        component: () => import('@/pages/product/mapping.vue'),
        meta: { title: '映射管理', requiresAuth: true },
      },

      // 分类管理
      {
        path: 'category/list',
        name: 'CategoryList',
        component: () => import('@/pages/category/list.vue'),
        meta: { title: '分类管理', requiresAuth: true },
      },

      // 订单管理
      {
        path: 'order/list',
        name: 'OrderList',
        component: () => import('@/pages/order/list.vue'),
        meta: { title: '订单列表', icon: 'Document', requiresAuth: true },
      },
      {
        path: 'order/detail/:id',
        name: 'OrderDetail',
        component: () => import('@/pages/order/detail.vue'),
        meta: { title: '订单详情', hidden: true, requiresAuth: true },
      },
      {
        path: 'order/refund',
        name: 'OrderRefund',
        component: () => import('@/pages/order/refund.vue'),
        meta: { title: '退款管理', requiresAuth: true },
      },

      // 支付管理
      {
        path: 'payment/accounts',
        name: 'PaymentAccounts',
        component: () => import('@/pages/payment/accounts.vue'),
        meta: { title: '账号管理', icon: 'CreditCard', requiresAuth: true },
      },
      {
        path: 'payment/transactions',
        name: 'PaymentTransactions',
        component: () => import('@/pages/payment/transactions.vue'),
        meta: { title: '交易记录', requiresAuth: true },
      },

      // 物流管理
      {
        path: 'shipping/list',
        name: 'ShippingList',
        component: () => import('@/pages/shipping/list.vue'),
        meta: { title: '发货管理', icon: 'Van', requiresAuth: true },
      },
      {
        path: 'shipping/providers',
        name: 'ShippingProviders',
        component: () => import('@/pages/shipping/providers.vue'),
        meta: { title: '物流商配置', requiresAuth: true },
      },

      // 用户管理
      {
        path: 'user/admins',
        name: 'UserAdmins',
        component: () => import('@/pages/user/admins.vue'),
        meta: { title: '管理员', icon: 'User', requiresAuth: true },
      },
      {
        path: 'user/merchants',
        name: 'UserMerchants',
        component: () => import('@/pages/user/merchants.vue'),
        meta: { title: '商户', requiresAuth: true },
      },
      {
        path: 'user/customers',
        name: 'UserCustomers',
        component: () => import('@/pages/user/customers.vue'),
        meta: { title: '买家', requiresAuth: true },
      },

      // 营销管理
      {
        path: 'marketing/coupons',
        name: 'MarketingCoupons',
        component: () => import('@/pages/marketing/coupons.vue'),
        meta: { title: '优惠券', icon: 'Present', requiresAuth: true },
      },
      {
        path: 'marketing/promotions',
        name: 'MarketingPromotions',
        component: () => import('@/pages/marketing/promotions.vue'),
        meta: { title: '促销活动', requiresAuth: true },
      },

      // 商户管理
      {
        path: 'merchant',
        name: 'MerchantList',
        component: () => import('@/pages/merchant/index.vue'),
        meta: { title: '商户管理', icon: 'OfficeBuilding', requiresAuth: true },
      },
      {
        path: 'merchant/:id',
        name: 'MerchantDetail',
        component: () => import('@/pages/merchant/detail.vue'),
        meta: { title: '商户详情', hidden: true, requiresAuth: true },
      },

      // 系统设置
      {
        path: 'setting/general',
        name: 'SettingGeneral',
        component: () => import('@/pages/setting/general.vue'),
        meta: { title: '基础配置', icon: 'Setting', requiresAuth: true },
      },
      {
        path: 'setting/rbac',
        name: 'SettingRbac',
        component: () => import('@/pages/setting/rbac.vue'),
        meta: { title: '权限管理', requiresAuth: true },
      },
      {
        path: 'setting/logs',
        name: 'SettingLogs',
        component: () => import('@/pages/setting/logs.vue'),
        meta: { title: '操作日志', requiresAuth: true },
      },
    ],
  },
]

/** Catch-all 路由 */
export const catchAllRoute: RouteRecordRaw = {
  path: '/:pathMatch(.*)*',
  redirect: '/404',
}
