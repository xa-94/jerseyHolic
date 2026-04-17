<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { User, Lock } from '@element-plus/icons-vue'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

// ─── 表单 ref ────────────────────────────────────────────────────────────────

const formRef = ref<FormInstance>()

// ─── 表单数据 ─────────────────────────────────────────────────────────────────

const form = reactive({
  email: '',
  password: '',
  remember_me: false,
})

// ─── 状态 ─────────────────────────────────────────────────────────────────────

const loading = ref(false)

// ─── 表单验证规则 ──────────────────────────────────────────────────────────────

const rules: FormRules = {
  email: [
    { required: true, message: '请输入邮箱地址', trigger: 'blur' },
    { type: 'email', message: '请输入有效的邮箱格式', trigger: ['blur', 'change'] },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码长度不能少于 6 位', trigger: 'blur' },
  ],
}

// ─── 登录处理 ─────────────────────────────────────────────────────────────────

async function handleLogin() {
  if (!formRef.value) return

  const valid = await formRef.value.validate().catch(() => false)
  if (!valid) return

  loading.value = true
  try {
    await userStore.login({
      email: form.email,
      password: form.password,
      remember_me: form.remember_me,
    })

    // 跳转到 redirect 指定页面，默认 /dashboard
    const redirect = (route.query.redirect as string) || '/dashboard'
    router.push(redirect)
  } catch {
    // request.ts 中已通过 ElMessage 显示错误，这里额外保底提示
    ElMessage.error('登录失败，请检查邮箱或密码')
  } finally {
    loading.value = false
  }
}

// ─── Enter 键提交 ─────────────────────────────────────────────────────────────

function handleEnterKey(event: KeyboardEvent) {
  if (event.key === 'Enter') {
    handleLogin()
  }
}

// ─── 挂载时聚焦邮箱框 ─────────────────────────────────────────────────────────

onMounted(() => {
  const emailInput = document.querySelector<HTMLInputElement>('.login-email-input input')
  emailInput?.focus()
})
</script>

<template>
  <div class="login-page" @keydown="handleEnterKey">
    <!-- 背景装饰 -->
    <div class="login-bg">
      <div class="bg-circle bg-circle--1" />
      <div class="bg-circle bg-circle--2" />
    </div>

    <!-- 登录卡片 -->
    <div class="login-card">
      <!-- Logo & 标题 -->
      <div class="login-header">
        <div class="login-logo">
          <el-icon size="36" color="#409eff"><Shop /></el-icon>
        </div>
        <h1 class="login-title">JerseyHolic Merchant</h1>
        <p class="login-subtitle">商户管理后台</p>
      </div>

      <!-- 登录表单 -->
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        class="login-form"
        size="large"
        @submit.prevent
      >
        <!-- 邮箱 -->
        <el-form-item prop="email">
          <el-input
            v-model="form.email"
            class="login-email-input"
            placeholder="请输入邮箱地址"
            autocomplete="username"
            :prefix-icon="User"
            clearable
          />
        </el-form-item>

        <!-- 密码 -->
        <el-form-item prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入密码"
            autocomplete="current-password"
            :prefix-icon="Lock"
            show-password
          />
        </el-form-item>

        <!-- 记住我 -->
        <el-form-item class="login-remember">
          <el-checkbox v-model="form.remember_me">记住我</el-checkbox>
        </el-form-item>

        <!-- 登录按钮 -->
        <el-form-item>
          <el-button
            type="primary"
            class="login-btn"
            :loading="loading"
            @click="handleLogin"
          >
            {{ loading ? '登录中...' : '登 录' }}
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 底部版权信息 -->
      <div class="login-footer">
        <span>© 2025 JerseyHolic. All rights reserved.</span>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.login-page {
  position: relative;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #1a237e 0%, #283593 40%, #1565c0 100%);
  overflow: hidden;
}

// 背景装饰圆圈
.login-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.bg-circle {
  position: absolute;
  border-radius: 50%;
  opacity: 0.08;
  background: #fff;

  &--1 {
    width: 500px;
    height: 500px;
    top: -150px;
    right: -100px;
  }

  &--2 {
    width: 350px;
    height: 350px;
    bottom: -100px;
    left: -80px;
  }
}

// 登录卡片
.login-card {
  position: relative;
  z-index: 1;
  width: 420px;
  padding: 48px 40px 36px;
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);

  @media (max-width: 480px) {
    width: 100%;
    min-height: 100vh;
    border-radius: 0;
    padding: 60px 24px 36px;
  }
}

// 头部
.login-header {
  text-align: center;
  margin-bottom: 36px;
}

.login-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: linear-gradient(135deg, #e3f2fd, #bbdefb);
  margin-bottom: 16px;
}

.login-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0 0 6px;
  letter-spacing: 0.5px;
}

.login-subtitle {
  font-size: 13px;
  color: #909399;
  margin: 0;
}

// 表单
.login-form {
  :deep(.el-form-item) {
    margin-bottom: 20px;
  }

  :deep(.el-input__inner) {
    height: 44px;
    font-size: 14px;
  }

  :deep(.el-input__prefix) {
    color: #909399;
  }
}

.login-remember {
  :deep(.el-form-item__content) {
    justify-content: space-between;
  }

  :deep(.el-checkbox__label) {
    font-size: 13px;
    color: #606266;
  }
}

.login-btn {
  width: 100%;
  height: 44px;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 2px;
  border-radius: 8px;
  background: linear-gradient(135deg, #409eff, #2b7de9);
  border: none;
  box-shadow: 0 4px 12px rgba(64, 158, 255, 0.4);
  transition: all 0.2s;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(64, 158, 255, 0.5);
  }

  &:active:not(:disabled) {
    transform: translateY(0);
  }
}

// 底部
.login-footer {
  margin-top: 28px;
  text-align: center;
  font-size: 12px;
  color: #c0c4cc;
}
</style>
