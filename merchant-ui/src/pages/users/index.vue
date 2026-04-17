<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { Plus, Search, Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { getUserList, deleteUser } from '@/api/user'
import type { MerchantUser, UserListParams } from '@/api/user'
import UserDialog from './components/UserDialog.vue'

// ─── 状态 ────────────────────────────────────────────────────────────────────

const loading = ref(false)
const userList = ref<MerchantUser[]>([])
const total = ref(0)

/** 搜索参数 */
const searchForm = reactive<UserListParams>({
  keyword: '',
  status: '',
  page: 1,
  per_page: 20,
})

/** 弹窗状态 */
const dialogVisible = ref(false)
const editUser = ref<MerchantUser | null>(null)

// ─── 方法 ────────────────────────────────────────────────────────────────────

async function loadUsers() {
  loading.value = true
  try {
    const params: UserListParams = {}
    if (searchForm.keyword) params.keyword = searchForm.keyword
    if (searchForm.status) params.status = searchForm.status
    params.page = searchForm.page
    params.per_page = searchForm.per_page

    const res = await getUserList(params)
    userList.value = res.data?.list ?? []
    total.value = res.data?.total ?? 0
  } finally {
    loading.value = false
  }
}

function handleSearch() {
  searchForm.page = 1
  loadUsers()
}

function handleReset() {
  searchForm.keyword = ''
  searchForm.status = ''
  searchForm.page = 1
  loadUsers()
}

function handleCreate() {
  editUser.value = null
  dialogVisible.value = true
}

function handleEdit(row: MerchantUser) {
  editUser.value = row
  dialogVisible.value = true
}

async function handleDelete(row: MerchantUser) {
  try {
    await deleteUser(row.id)
    ElMessage.success('用户已删除')
    loadUsers()
  } catch {
    // 错误由拦截器处理
  }
}

function handleDialogSuccess() {
  loadUsers()
}

function handlePageChange(page: number) {
  searchForm.page = page
  loadUsers()
}

function handleSizeChange(size: number) {
  searchForm.per_page = size
  searchForm.page = 1
  loadUsers()
}

onMounted(() => {
  loadUsers()
})
</script>

<template>
  <div class="users-page">
    <!-- 页面标题 -->
    <div class="page-header">
      <h2 class="page-title">用户管理</h2>
      <el-button type="primary" :icon="Plus" @click="handleCreate">新增用户</el-button>
    </div>

    <!-- 搜索栏 -->
    <el-card shadow="never" class="search-card">
      <el-form :model="searchForm" inline>
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="姓名/邮箱"
            clearable
            style="width: 200px;"
            @keyup.enter="handleSearch"
          />
        </el-form-item>

        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px;">
            <el-option label="启用" value="active" />
            <el-option label="停用" value="inactive" />
          </el-select>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" :icon="Search" @click="handleSearch">搜索</el-button>
          <el-button :icon="Refresh" @click="handleReset">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 用户表格 -->
    <el-card shadow="never">
      <el-table
        v-loading="loading"
        :data="userList"
        border
        stripe
        style="width: 100%;"
        row-key="id"
      >
        <!-- 姓名 -->
        <el-table-column prop="name" label="姓名" min-width="120" show-overflow-tooltip />

        <!-- 邮箱 -->
        <el-table-column prop="email" label="邮箱" min-width="180" show-overflow-tooltip />

        <!-- 角色 -->
        <el-table-column label="角色" width="120" align="center">
          <template #default="{ row }">
            <el-tag type="primary" size="small">{{ row.role || '—' }}</el-tag>
          </template>
        </el-table-column>

        <!-- 状态 -->
        <el-table-column label="状态" width="90" align="center">
          <template #default="{ row }">
            <el-tag
              :type="row.status === 'active' ? 'success' : 'info'"
              size="small"
            >
              {{ row.status === 'active' ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>

        <!-- 创建时间 -->
        <el-table-column prop="created_at" label="创建时间" width="160" align="center">
          <template #default="{ row }">
            {{ row.created_at ? row.created_at.replace('T', ' ').slice(0, 16) : '—' }}
          </template>
        </el-table-column>

        <!-- 操作 -->
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button
              type="primary"
              link
              size="small"
              @click="handleEdit(row)"
            >编辑</el-button>

            <el-popconfirm
              title="确认删除该用户吗？"
              confirm-button-text="确认删除"
              cancel-button-text="取消"
              confirm-button-type="danger"
              @confirm="handleDelete(row)"
            >
              <template #reference>
                <el-button type="danger" link size="small">删除</el-button>
              </template>
            </el-popconfirm>
          </template>
        </el-table-column>

        <!-- 空状态 -->
        <template #empty>
          <el-empty description="暂无用户数据" />
        </template>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="searchForm.page"
          v-model:page-size="searchForm.per_page"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @current-change="handlePageChange"
          @size-change="handleSizeChange"
        />
      </div>
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <UserDialog
      v-model:visible="dialogVisible"
      :edit-data="editUser"
      @success="handleDialogSuccess"
    />
  </div>
</template>

<style scoped lang="scss">
.users-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
  color: #303133;
}

.search-card {
  :deep(.el-card__body) {
    padding: 16px;
  }

  :deep(.el-form-item) {
    margin-bottom: 0;
  }
}

.pagination-wrap {
  display: flex;
  justify-content: flex-end;
  margin-top: 16px;
}
</style>
