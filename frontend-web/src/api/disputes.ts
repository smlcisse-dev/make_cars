import http from '@/api/http'
import type { Dispute, DisputeMessage, DisputeStatus } from '@/types/dispute'
import type { PaginatedResponse } from '@/types/pagination'
import type { ProfessionalSpace } from '@/types/professionalSpace'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchDisputes(
  space: ProfessionalSpace,
  params: { status?: DisputeStatus; page?: number } = {},
): Promise<PaginatedResponse<Dispute>> {
  const response = await http.get<PaginatedResponse<Dispute>>(`/${space}/disputes`, { params })
  return response.data
}

export async function fetchDispute(space: ProfessionalSpace, id: number): Promise<Dispute> {
  const response = await http.get<ApiEnvelope<Dispute>>(`/${space}/disputes/${id}`)
  return response.data.data
}

export async function respondToDispute(space: ProfessionalSpace, id: number, body: string): Promise<DisputeMessage> {
  const response = await http.post<ApiEnvelope<DisputeMessage>>(`/${space}/disputes/${id}/respond`, { body })
  return response.data.data
}

// Fichier privé : même mécanisme blob authentifié que les autres pièces jointes.
export async function fetchDisputeAttachmentBlob(
  space: ProfessionalSpace,
  disputeId: number,
  attachmentId: number,
): Promise<Blob> {
  const response = await http.get<Blob>(`/${space}/disputes/${disputeId}/attachments/${attachmentId}/download`, {
    responseType: 'blob',
  })
  return response.data
}
