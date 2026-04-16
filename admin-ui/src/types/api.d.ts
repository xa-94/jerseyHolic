/** 统一 API 响应格式 */
export interface ApiResponse<T = unknown> {
  code: number
  message: string
  data: T
}

/** 分页请求参数 */
export interface PaginationParams {
  page: number
  per_page: number
}

/** 分页响应数据 */
export interface PaginatedData<T> {
  list: T[]        // 与后端 ApiResponse::paginate() 保持一致
  total: number
  page: number
  per_page: number
}

/** 登录请求参数 */
export interface LoginParams {
  username: string
  password: string
}

/** 登录响应数据 */
export interface LoginResult {
  token: string
  user: UserInfo
}

/** 用户信息 */
export interface UserInfo {
  id: number
  name: string
  email: string
  avatar?: string
  roles: string[]
  permissions: string[]
}

/** 路由元信息 */
export interface RouteMeta {
  title: string
  icon?: string
  hidden?: boolean
  permission?: string
  keepAlive?: boolean
  breadcrumb?: boolean
}
