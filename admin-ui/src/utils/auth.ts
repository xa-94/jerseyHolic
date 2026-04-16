const TOKEN_KEY = 'jh_admin_token'

/** 获取 Token */
export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}

/** 设置 Token */
export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token)
}

/** 移除 Token */
export function removeToken(): void {
  localStorage.removeItem(TOKEN_KEY)
}

/** 是否已登录 */
export function isAuthenticated(): boolean {
  return !!getToken()
}
