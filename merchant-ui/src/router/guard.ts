import type { Router } from 'vue-router'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import { getToken } from '@/utils/auth'

NProgress.configure({ showSpinner: false })

/** 白名单路由（无需登录） */
const whiteList = ['/login', '/404']

export function setupRouterGuard(router: Router): void {
  router.beforeEach(async (to, _from, next) => {
    NProgress.start()

    const token = getToken()
    const toPath = to.path

    if (token) {
      // 已登录访问登录页 → 重定向到仪表盘
      if (toPath === '/login') {
        next({ path: '/dashboard' })
        return
      }

      next()
    } else {
      // 未登录
      if (whiteList.includes(toPath)) {
        next()
      } else {
        // 重定向到登录页，携带原始路径
        next({ path: '/login', query: { redirect: toPath } })
      }
    }
  })

  router.afterEach(() => {
    NProgress.done()
  })
}
