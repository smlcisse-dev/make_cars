import http from '@/api/http'
import type { AppointmentStatus } from '@/types/appointment'
import type { OrderStatus } from '@/types/order'
import type { PaginatedResponse } from '@/types/pagination'
import type { QuoteStatus } from '@/types/quote'
import type {
  StructureKind,
  SupervisedAppointment,
  SupervisedConversation,
  SupervisedOrder,
  SupervisedQuote,
  SupervisedStructure,
} from '@/types/supervision'

// Supervision admin en lecture seule (CLAUDE.md §5, règle 8) : uniquement des
// GET. Les fiches renvoient `{ data }` (ressource Laravel), les listes
// l'enveloppe paginée `{ data, meta }`. Seuls les filtres que le backend
// accepte déjà sont proposés : `status` (RDV, devis, commandes) et
// `garage_id` (RDV).
interface ApiEnvelope<T> {
  data: T
}

// Segment d'URL de chaque type de structure côté API.
const STRUCTURE_PATHS: Record<StructureKind, string> = {
  garage: '/admin/garages',
  market_space: '/admin/market-space-accounts',
}

export async function fetchSupervisedStructures(
  kind: StructureKind,
  page: number,
): Promise<PaginatedResponse<SupervisedStructure>> {
  const response = await http.get<PaginatedResponse<SupervisedStructure>>(STRUCTURE_PATHS[kind], { params: { page } })
  return response.data
}

export async function fetchSupervisedStructure(kind: StructureKind, id: number): Promise<SupervisedStructure> {
  const response = await http.get<ApiEnvelope<SupervisedStructure>>(`${STRUCTURE_PATHS[kind]}/${id}`)
  return response.data.data
}

export interface AppointmentListParams {
  page: number
  status?: AppointmentStatus
  garage_id?: number
}

export async function fetchSupervisedAppointments(
  params: AppointmentListParams,
): Promise<PaginatedResponse<SupervisedAppointment>> {
  const response = await http.get<PaginatedResponse<SupervisedAppointment>>('/admin/appointments', { params })
  return response.data
}

export async function fetchSupervisedAppointment(id: number): Promise<SupervisedAppointment> {
  const response = await http.get<ApiEnvelope<SupervisedAppointment>>(`/admin/appointments/${id}`)
  return response.data.data
}

export async function fetchSupervisedQuotes(params: {
  page: number
  status?: QuoteStatus
}): Promise<PaginatedResponse<SupervisedQuote>> {
  const response = await http.get<PaginatedResponse<SupervisedQuote>>('/admin/quotes', { params })
  return response.data
}

export async function fetchSupervisedQuote(id: number): Promise<SupervisedQuote> {
  const response = await http.get<ApiEnvelope<SupervisedQuote>>(`/admin/quotes/${id}`)
  return response.data.data
}

export async function fetchSupervisedOrders(params: {
  page: number
  status?: OrderStatus
}): Promise<PaginatedResponse<SupervisedOrder>> {
  const response = await http.get<PaginatedResponse<SupervisedOrder>>('/admin/orders', { params })
  return response.data
}

export async function fetchSupervisedOrder(id: number): Promise<SupervisedOrder> {
  const response = await http.get<ApiEnvelope<SupervisedOrder>>(`/admin/orders/${id}`)
  return response.data.data
}

export async function fetchSupervisedConversations(page: number): Promise<PaginatedResponse<SupervisedConversation>> {
  const response = await http.get<PaginatedResponse<SupervisedConversation>>('/admin/conversations', {
    params: { page },
  })
  return response.data
}

export async function fetchSupervisedConversation(id: number): Promise<SupervisedConversation> {
  const response = await http.get<ApiEnvelope<SupervisedConversation>>(`/admin/conversations/${id}`)
  return response.data.data
}
