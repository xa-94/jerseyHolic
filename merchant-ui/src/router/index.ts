import { createRouter, createWebHistory } from 'vue-router'
import { publicRoutes, protectedRoutes, catchAllRoute } from './routes'
import { setupRouterGuard } from './guard'

const router = createRouter({
  history: createWebHistory(),
  routes: [...publicRoutes, ...protectedRoutes, catchAllRoute],
  scrollBehavior: () => ({ top: 0 }),
})

setupRouterGuard(router)

export default router
