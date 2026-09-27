import { api } from './client'

export type Role = 'owner' | 'admin' | 'user'

export interface CurrentUser {
  id: number
  display_name: string
  email: string
  role: Role
}

interface UserResponse {
  user: CurrentUser | null
}

// Visiteur non connecté : null (le back répond { user: null }, pas une erreur)
export const fetchCurrentUser = () => api.get<UserResponse>('/auth/me').then((r) => r.user)

export const login = (email: string, password: string) =>
  api.post<UserResponse>('/auth/login', { email, password })

export const logout = () => api.post<unknown>('/auth/logout')

export const requestReset = (email: string) => api.post<unknown>('/auth/forgot', { email })

export const resetPassword = (token: string, password: string) =>
  api.post<unknown>('/auth/reset', { token, password })
