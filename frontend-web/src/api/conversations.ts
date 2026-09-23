import http from '@/api/http'
import type { ChatMessage, Conversation } from '@/types/conversation'
import type { PaginatedResponse } from '@/types/pagination'
import type { ProfessionalSpace } from '@/types/professionalSpace'

// Le chat existe dans les deux espaces professionnels (mêmes endpoints, préfixe
// de route différent) : `space` choisit le préfixe.

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchConversations(space: ProfessionalSpace, page = 1): Promise<PaginatedResponse<Conversation>> {
  const response = await http.get<PaginatedResponse<Conversation>>(`/${space}/conversations`, { params: { page } })
  return response.data
}

export async function fetchMessages(
  space: ProfessionalSpace,
  conversationId: number,
  page = 1,
): Promise<PaginatedResponse<ChatMessage>> {
  const response = await http.get<PaginatedResponse<ChatMessage>>(
    `/${space}/conversations/${conversationId}/messages`,
    { params: { page } },
  )
  return response.data
}

export async function sendMessage(
  space: ProfessionalSpace,
  conversationId: number,
  body: string | null,
  image: File | null,
): Promise<ChatMessage> {
  const formData = new FormData()
  if (body) formData.append('body', body)
  if (image) formData.append('image', image)

  const response = await http.post<ApiEnvelope<ChatMessage>>(
    `/${space}/conversations/${conversationId}/messages`,
    formData,
  )
  return response.data.data
}

export async function fetchMessageImageBlob(
  space: ProfessionalSpace,
  conversationId: number,
  messageId: number,
): Promise<Blob> {
  const response = await http.get<Blob>(
    `/${space}/conversations/${conversationId}/messages/${messageId}/image`,
    { responseType: 'blob' },
  )
  return response.data
}
