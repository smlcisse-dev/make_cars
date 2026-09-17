import http from '@/api/http'
import type { User } from '@/types/user'

export interface LoginCredentials {
  email: string
  password: string
}

interface LoginResponseData {
  user: User
  token: string
}

// Toutes les réponses de l'API sont enveloppées dans { data, message } côté
// backend (app/Http/Controllers/Api/Controller.php::success()).
interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function login(credentials: LoginCredentials): Promise<LoginResponseData> {
  const response = await http.post<ApiEnvelope<LoginResponseData>>('/auth/login', credentials)
  return response.data.data
}

export async function fetchCurrentUser(): Promise<User> {
  const response = await http.get<ApiEnvelope<User>>('/auth/me')
  return response.data.data
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout')
}
