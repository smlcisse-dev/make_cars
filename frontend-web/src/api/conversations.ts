import http from '@/api/http'
import type { ChatMessage, Conversation } from '@/types/conversation'
import type { PaginatedResponse } from '@/types/pagination'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchConversations(page = 1): Promise<PaginatedResponse<Conversation>> {
  const response = await http.get<PaginatedResponse<Conversation>>('/garage/conversations', { params: { page } })
  return response.data
}

export async function fetchMessages(
  conversationId: number,
  page = 1,
): Promise<PaginatedResponse<ChatMessage>> {
  const response = await http.get<PaginatedResponse<ChatMessage>>(
    `/garage/conversations/${conversationId}/messages`,
    { params: { page } },
  )
  return response.data
}

export async function sendMessage(
  conversationId: number,
  body: string | null,
  image: File | null,
): Promise<ChatMessage> {
  const formData = new FormData()
  if (body) formData.append('body', body)
  if (image) formData.append('image', image)

  const response = await http.post<ApiEnvelope<ChatMessage>>(
    `/garage/conversations/${conversationId}/messages`,
    formData,
  )
  return response.data.data
}

export async function fetchMessageImageBlob(conversationId: number, messageId: number): Promise<Blob> {
  const response = await http.get<Blob>(
    `/garage/conversations/${conversationId}/messages/${messageId}/image`,
    { responseType: 'blob' },
  )
  return response.data
}
