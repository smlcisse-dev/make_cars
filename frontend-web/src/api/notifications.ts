import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import type { PushNotification } from '@/types/notification'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchNotifications(space: ProfessionalSpace, page = 1): Promise<PaginatedResponse<PushNotification>> {
  const response = await http.get<PaginatedResponse<PushNotification>>(`/${space}/notifications`, { params: { page } })
  return response.data
}

export async function markNotificationRead(space: ProfessionalSpace, id: number): Promise<PushNotification> {
  const response = await http.post<ApiEnvelope<PushNotification>>(`/${space}/notifications/${id}/read`)
  return response.data.data
}
