import http from '@/api/http'
import type { Order } from '@/types/order'
import type { PaginatedResponse } from '@/types/pagination'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchMarketSpaceOrders(page = 1): Promise<PaginatedResponse<Order>> {
  const response = await http.get<PaginatedResponse<Order>>('/market-space/orders', { params: { page } })
  return response.data
}

export async function fetchMarketSpaceOrder(id: number): Promise<Order> {
  const response = await http.get<ApiEnvelope<Order>>(`/market-space/orders/${id}`)
  return response.data.data
}

export async function markMarketSpaceOrderPaid(id: number): Promise<Order> {
  const response = await http.post<ApiEnvelope<Order>>(`/market-space/orders/${id}/mark-paid`)
  return response.data.data
}

// Fichier privé : même mécanisme blob authentifié que les PDF de devis.
export async function fetchMarketSpaceOrderPdfBlob(id: number): Promise<Blob> {
  const response = await http.get<Blob>(`/market-space/orders/${id}/pdf`, { responseType: 'blob' })
  return response.data
}
