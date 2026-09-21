import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { PushNotification } from '@/types/notification'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchNotifications(page = 1): Promise<PaginatedResponse<PushNotification>> {
  const response = await http.get<PaginatedResponse<PushNotification>>('/market-space/notifications', { params: { page } })
  return response.data
}

export async function markNotificationRead(id: number): Promise<PushNotification> {
  const response = await http.post<ApiEnvelope<PushNotification>>(`/market-space/notifications/${id}/read`)
  return response.data.data
}
