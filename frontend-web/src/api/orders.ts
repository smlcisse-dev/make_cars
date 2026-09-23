import http from '@/api/http'
import type { Order } from '@/types/order'
import type { PaginatedResponse } from '@/types/pagination'
import type { ProfessionalSpace } from '@/types/professionalSpace'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchOrders(space: ProfessionalSpace, page = 1): Promise<PaginatedResponse<Order>> {
  const response = await http.get<PaginatedResponse<Order>>(`/${space}/orders`, { params: { page } })
  return response.data
}

export async function fetchOrder(space: ProfessionalSpace, id: number): Promise<Order> {
  const response = await http.get<ApiEnvelope<Order>>(`/${space}/orders/${id}`)
  return response.data.data
}

export async function markOrderPaid(space: ProfessionalSpace, id: number): Promise<Order> {
  const response = await http.post<ApiEnvelope<Order>>(`/${space}/orders/${id}/mark-paid`)
  return response.data.data
}

// Fichier privé : même mécanisme blob authentifié que les PDF de devis.
export async function fetchOrderPdfBlob(space: ProfessionalSpace, id: number): Promise<Blob> {
  const response = await http.get<Blob>(`/${space}/orders/${id}/pdf`, { responseType: 'blob' })
  return response.data
}
