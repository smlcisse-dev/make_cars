import http from '@/api/http'
import type { Dispute, DisputeMessage, DisputeStatus } from '@/types/dispute'
import type { PaginatedResponse } from '@/types/pagination'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchDisputes(params: { status?: DisputeStatus; page?: number } = {}): Promise<PaginatedResponse<Dispute>> {
  const response = await http.get<PaginatedResponse<Dispute>>('/market-space/disputes', { params })
  return response.data
}

export async function fetchDispute(id: number): Promise<Dispute> {
  const response = await http.get<ApiEnvelope<Dispute>>(`/market-space/disputes/${id}`)
  return response.data.data
}

export async function respondToDispute(id: number, body: string): Promise<DisputeMessage> {
  const response = await http.post<ApiEnvelope<DisputeMessage>>(`/market-space/disputes/${id}/respond`, { body })
  return response.data.data
}

// Fichier privé : même mécanisme blob authentifié que les autres pièces jointes.
export async function fetchDisputeAttachmentBlob(disputeId: number, attachmentId: number): Promise<Blob> {
  const response = await http.get<Blob>(`/market-space/disputes/${disputeId}/attachments/${attachmentId}/download`, {
    responseType: 'blob',
  })
  return response.data
}
