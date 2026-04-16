<script setup lang="ts">
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="not-found">
    <h1 class="error-code">404</h1>
    <p class="error-text">抱歉，您访问的页面不存在</p>
    <el-button type="primary" @click="router.push('/dashboard')">返回首页</el-button>
  </div>
</template>

<style scoped lang="scss">
.not-found {
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f0f2f5;
}
.error-code {
  font-size: 120px;
  font-weight: 700;
  color: #dcdfe6;
  margin: 0;
  line-height: 1;
}
.error-text {
  font-size: 18px;
  color: #909399;
  margin: 16px 0 32px;
}
</style>
