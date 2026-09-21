import http from '@/api/http'
import type { Order } from '@/types/order'
import type { PaginatedResponse } from '@/types/pagination'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchOrders(page = 1): Promise<PaginatedResponse<Order>> {
  const response = await http.get<PaginatedResponse<Order>>('/garage/orders', { params: { page } })
  return response.data
}

export async function fetchOrder(id: number): Promise<Order> {
  const response = await http.get<ApiEnvelope<Order>>(`/garage/orders/${id}`)
  return response.data.data
}

export async function markOrderPaid(id: number): Promise<Order> {
  const response = await http.post<ApiEnvelope<Order>>(`/garage/orders/${id}/mark-paid`)
  return response.data.data
}

// Fichier privé : même mécanisme blob authentifié que les PDF de devis.
export async function fetchOrderPdfBlob(id: number): Promise<Blob> {
  const response = await http.get<Blob>(`/garage/orders/${id}/pdf`, { responseType: 'blob' })
  return response.data
}
