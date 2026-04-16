import axios, { type AxiosInstance, type AxiosRequestConfig, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'
import { getToken, removeToken } from '@/utils/auth'
import router from '@/router'
import type { ApiResponse } from '@/types/api'

/** 创建 Axios 实例 */
const service: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_PREFIX || '/api/v1/admin',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
  },
})

/** 请求拦截器 — 自动注入 Token */
service.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = getToken()
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  },
)

/** 响应拦截器 — 统一处理 {code, message, data} */
service.interceptors.response.use(
  (response: AxiosResponse<ApiResponse>) => {
    const res = response.data

    // code !== 0 视为业务错误
    if (res.code !== 0) {
      ElMessage.error(res.message || '请求失败')

      // 401 未认证 → 跳转登录
      if (res.code === 401) {
        removeToken()
        router.push('/login')
      }

      // 403 无权限
      if (res.code === 403) {
        ElMessage.error('无权限访问该资源')
      }

      return Promise.reject(new Error(res.message || '请求失败'))
    }

    return res as unknown as AxiosResponse
  },
  (error) => {
    const status = error.response?.status

    switch (status) {
      case 401:
        removeToken()
        router.push('/login')
        ElMessage.error('登录已过期，请重新登录')
        break
      case 403:
        ElMessage.error('无权限访问该资源')
        break
      case 404:
        ElMessage.error('请求的资源不存在')
        break
      case 500:
        ElMessage.error('服务器内部错误')
        break
      default:
        ElMessage.error(error.message || '网络错误')
    }

    return Promise.reject(error)
  },
)

/** 封装 GET 请求 */
export function get<T = unknown>(url: string, params?: Record<string, unknown>, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  return service.get(url, { params, ...config }) as Promise<ApiResponse<T>>
}

/** 封装 POST 请求 */
export function post<T = unknown>(url: string, data?: Record<string, unknown>, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  return service.post(url, data, config) as Promise<ApiResponse<T>>
}

/** 封装 PUT 请求 */
export function put<T = unknown>(url: string, data?: Record<string, unknown>, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  return service.put(url, data, config) as Promise<ApiResponse<T>>
}

/** 封装 DELETE 请求 */
export function del<T = unknown>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> {
  return service.delete(url, config) as Promise<ApiResponse<T>>
}

export default service
