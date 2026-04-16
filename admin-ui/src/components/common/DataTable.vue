<script setup lang="ts" generic="T extends Record<string, unknown>">
import { ref, computed } from 'vue'

interface Column {
  prop?: string
  label: string
  width?: number | string
  minWidth?: number | string
  fixed?: 'left' | 'right' | boolean
  align?: 'left' | 'center' | 'right'
  sortable?: boolean | 'custom'
  slot?: string
  showOverflowTooltip?: boolean
  formatter?: (row: T, column: Column, value: unknown) => string
}

interface Props {
  data: T[]
  columns: Column[]
  total?: number
  page?: number
  pageSize?: number
  pageSizes?: number[]
  loading?: boolean
  selection?: boolean
  rowKey?: string
  stripe?: boolean
  border?: boolean
  emptyText?: string
  height?: string | number
}

const props = withDefaults(defineProps<Props>(), {
  total: 0,
  page: 1,
  pageSize: 20,
  pageSizes: () => [10, 20, 50, 100],
  loading: false,
  selection: false,
  rowKey: 'id',
  stripe: true,
  border: false,
  emptyText: '暂无数据',
})

const emit = defineEmits<{
  'update:page': [page: number]
  'update:pageSize': [size: number]
  'selection-change': [rows: T[]]
  'sort-change': [sort: { prop: string; order: string | null }]
  'row-click': [row: T]
}>()

const selectedRows = ref<T[]>([])

const pageProxy = computed({
  get: () => props.page ?? 1,
  set: (val: number) => emit('update:page', val),
})

const pageSizeProxy = computed({
  get: () => props.pageSize ?? 20,
  set: (val: number) => emit('update:pageSize', val),
})

function handleSelectionChange(rows: T[]) {
  selectedRows.value = rows
  emit('selection-change', rows)
}

function handleSortChange(sort: { prop: string; order: string | null }) {
  emit('sort-change', sort)
}

// 暴露方法给父组件
defineExpose({
  clearSelection: () => {
    selectedRows.value = []
  },
  getSelectedRows: () => selectedRows.value,
})
</script>

<template>
  <div class="data-table">
    <el-table
      v-loading="loading"
      :data="data"
      :row-key="rowKey"
      :stripe="stripe"
      :border="border"
      :height="height"
      :empty-text="emptyText"
      @selection-change="handleSelectionChange"
      @sort-change="handleSortChange"
      @row-click="(row: T) => emit('row-click', row)"
      style="width: 100%"
    >
      <!-- 多选列 -->
      <el-table-column
        v-if="selection"
        type="selection"
        width="50"
        align="center"
        fixed="left"
      />

      <!-- 数据列 -->
      <template v-for="col in columns" :key="col.prop || col.label">
        <!-- 自定义 slot 列 -->
        <el-table-column
          v-if="col.slot"
          :prop="col.prop"
          :label="col.label"
          :width="col.width"
          :min-width="col.minWidth"
          :fixed="col.fixed"
          :align="col.align || 'left'"
          :sortable="col.sortable"
          :show-overflow-tooltip="col.showOverflowTooltip"
        >
          <template #default="scope">
            <slot :name="col.slot" v-bind="scope" />
          </template>
        </el-table-column>

        <!-- 普通列 -->
        <el-table-column
          v-else
          :prop="col.prop"
          :label="col.label"
          :width="col.width"
          :min-width="col.minWidth"
          :fixed="col.fixed"
          :align="col.align || 'left'"
          :sortable="col.sortable"
          :show-overflow-tooltip="col.showOverflowTooltip !== false"
          :formatter="col.formatter as any"
        />
      </template>

      <!-- 操作列 slot -->
      <slot name="action-column" />
    </el-table>

    <!-- 分页 -->
    <div v-if="total > 0" class="data-table__pagination">
      <el-pagination
        v-model:current-page="pageProxy"
        v-model:page-size="pageSizeProxy"
        :page-sizes="pageSizes"
        :total="total"
        layout="total, sizes, prev, pager, next, jumper"
        background
      />
    </div>
  </div>
</template>

<style scoped lang="scss">
.data-table {
  &__pagination {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
    padding: 4px 0;
  }
}
</style>
