<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { createUser, updateUser, getRoleList } from '@/api/user'
import { getStoreList } from '@/api/store'
import type { MerchantUser, UserFormData, Role } from '@/api/user'
import type { StoreDetail } from '@/api/store'

// ─── Props & Emits ──────────────────────────────────────────────────────────

const props = defineProps<{
  visible: boolean
  editData?: MerchantUser | null
}>()

const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'success'): void
}>()

// ─── State ──────────────────────────────────────────────────────────────────

const formRef = ref<FormInstance>()
const submitting = ref(false)
const roleList = ref<Role[]>([])
const storeList = ref<StoreDetail[]>([])

/** 是否是编辑模式 */
const isEdit = computed(() => !!props.editData)

const dialogTitle = computed(() => isEdit.value ? '编辑用户' : '新增用户')

const defaultForm = (): UserFormData => ({
  name: '',
  email: '',
  password: '',
  role_id: 0,
  store_ids: [],
  status: 'active',
})

const form = reactive<UserFormData>(defaultForm())

const rules: FormRules = {
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' },
    { min: 2, max: 50, message: '长度在 2-50 个字符', trigger: 'blur' },
  ],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  password: [
    {
      required: !isEdit.value,
      validator: (_rule, value, callback) => {
        if (!isEdit.value && !value) {
          callback(new Error('创建用户时密码必填'))
        } else if (value && value.length < 8) {
          callback(new Error('密码至少 8 位'))
        } else {
          callback()
        }
      },
      trigger: 'blur',
    },
  ],
  role_id: [
    { required: true, message: '请选择角色', trigger: 'change' },
    { type: 'number', min: 1, message: '请选择角色', trigger: 'change' },
  ],
}

// ─── Watch ──────────────────────────────────────────────────────────────────

watch(
  () => props.visible,
  async (val) => {
    if (val) {
      // 加载角色和站点列表
      await Promise.all([loadRoles(), loadStores()])

      // 填充表单
      if (props.editData) {
        Object.assign(form, {
          name: props.editData.name,
          email: props.editData.email,
          password: '',
          role_id: props.editData.role_id ?? 0,
          store_ids: [...(props.editData.store_ids ?? [])],
          status: props.editData.status,
        })
      } else {
        Object.assign(form, defaultForm())
      }

      // 重置校验状态
      formRef.value?.clearValidate()
    }
  },
)

// ─── Methods ────────────────────────────────────────────────────────────────

async function loadRoles() {
  try {
    const res = await getRoleList()
    roleList.value = res.data?.data ?? []
  } catch {
    // 静默失败
  }
}

async function loadStores() {
  try {
    const res = await getStoreList()
    storeList.value = res.data?.data ?? []
  } catch {
    // 静默失败
  }
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  submitting.value = true
  try {
    const payload: UserFormData = {
      name: form.name,
      email: form.email,
      role_id: form.role_id,
      store_ids: form.store_ids,
      status: form.status,
    }
    // 密码：创建必填，编辑时若为空则不传
    if (form.password) {
      payload.password = form.password
    }

    if (isEdit.value && props.editData) {
      await updateUser(props.editData.id, payload)
      ElMessage.success('用户更新成功')
    } else {
      await createUser(payload)
      ElMessage.success('用户创建成功')
    }

    emit('success')
    handleClose()
  } finally {
    submitting.value = false
  }
}

function handleClose() {
  emit('update:visible', false)
  formRef.value?.resetFields()
}
</script>

<template>
  <el-dialog
    :model-value="props.visible"
    :title="dialogTitle"
    width="560px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="90px"
      label-position="right"
    >
      <!-- 姓名 -->
      <el-form-item label="姓名" prop="name">
        <el-input v-model="form.name" placeholder="请输入姓名" maxlength="50" show-word-limit />
      </el-form-item>

      <!-- 邮箱 -->
      <el-form-item label="邮箱" prop="email">
        <el-input
          v-model="form.email"
          placeholder="请输入邮箱"
          :disabled="isEdit"
          autocomplete="new-email"
        />
      </el-form-item>

      <!-- 密码 -->
      <el-form-item label="密码" prop="password">
        <el-input
          v-model="form.password"
          type="password"
          show-password
          :placeholder="isEdit ? '不填则不修改密码' : '请输入密码（至少8位）'"
          autocomplete="new-password"
        />
      </el-form-item>

      <!-- 角色 -->
      <el-form-item label="角色" prop="role_id">
        <el-select v-model="form.role_id" placeholder="请选择角色" style="width: 100%;">
          <el-option
            v-for="role in roleList"
            :key="role.id"
            :label="role.display_name || role.name"
            :value="role.id"
          />
        </el-select>
      </el-form-item>

      <!-- 站点权限 -->
      <el-form-item label="站点权限">
        <div class="store-permissions">
          <el-checkbox-group v-model="form.store_ids">
            <el-checkbox
              v-for="store in storeList"
              :key="store.id"
              :label="store.id"
              :value="store.id"
              style="margin-bottom: 6px; display: block;"
            >
              <span>{{ store.name }}</span>
              <el-tag
                :type="store.status === 'active' ? 'success' : 'info'"
                size="small"
                style="margin-left: 6px;"
              >
                {{ store.status === 'active' ? '启用' : '停用' }}
              </el-tag>
            </el-checkbox>
          </el-checkbox-group>
          <div v-if="storeList.length === 0" class="no-store">暂无可分配的站点</div>
        </div>
      </el-form-item>

      <!-- 状态（编辑时才显示） -->
      <el-form-item v-if="isEdit" label="状态" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio value="active">启用</el-radio>
          <el-radio value="inactive">停用</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="handleClose">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="handleSubmit">
        {{ isEdit ? '保存修改' : '确认创建' }}
      </el-button>
    </template>
  </el-dialog>
</template>

<style scoped lang="scss">
.store-permissions {
  width: 100%;
  max-height: 200px;
  overflow-y: auto;
  padding: 8px;
  border: 1px solid #e4e7ed;
  border-radius: 6px;
  background: #fafafa;
}

.no-store {
  color: #909399;
  font-size: 13px;
  text-align: center;
  padding: 12px 0;
}
</style>
