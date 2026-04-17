import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAppStore = defineStore('app', () => {
  /** 侧边栏是否折叠 */
  const sidebarCollapse = ref(false)

  /** 设置侧边栏折叠状态 */
  function setSidebarCollapse(collapsed: boolean): void {
    sidebarCollapse.value = collapsed
  }

  /** 切换侧边栏 */
  function toggleSidebar(): void {
    sidebarCollapse.value = !sidebarCollapse.value
  }

  return {
    sidebarCollapse,
    setSidebarCollapse,
    toggleSidebar,
  }
})
