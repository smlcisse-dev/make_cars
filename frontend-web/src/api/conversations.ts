import http from '@/api/http'
import type { ChatMessage, Conversation } from '@/types/conversation'
import type { PaginatedResponse } from '@/types/pagination'

// Le chat existe dans les deux espaces professionnels (mêmes endpoints, préfixe
// de route différent) : `space` choisit le préfixe, Garagiste par défaut.
export type ChatSpace = 'garage' | 'market-space'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchConversations(page = 1, space: ChatSpace = 'garage'): Promise<PaginatedResponse<Conversation>> {
  const response = await http.get<PaginatedResponse<Conversation>>(`/${space}/conversations`, { params: { page } })
  return response.data
}

export async function fetchMessages(
  conversationId: number,
  page = 1,
  space: ChatSpace = 'garage',
): Promise<PaginatedResponse<ChatMessage>> {
  const response = await http.get<PaginatedResponse<ChatMessage>>(
    `/${space}/conversations/${conversationId}/messages`,
    { params: { page } },
  )
  return response.data
}

export async function sendMessage(
  conversationId: number,
  body: string | null,
  image: File | null,
  space: ChatSpace = 'garage',
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

export async function fetchMessageImageBlob(conversationId: number, messageId: number, space: ChatSpace = 'garage'): Promise<Blob> {
  const response = await http.get<Blob>(
    `/${space}/conversations/${conversationId}/messages/${messageId}/image`,
    { responseType: 'blob' },
  )
  return response.data
}
