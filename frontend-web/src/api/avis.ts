import http from '@/api/http'
import type { Avis, AvisStatus, AvisTargetType } from '@/types/avis'
import type { PaginatedResponse } from '@/types/pagination'

// Réponses ponctuelles (une seule ressource, un message) enveloppées dans
// { data, message } — voir app/Http/Controllers/Api/Controller.php::success()
// côté backend, même schéma que src/api/adminReview.ts.
interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface AvisListParams {
  status?: AvisStatus
  reviewable_type?: AvisTargetType
  page?: number
}

export async function fetchAvisList(params: AvisListParams): Promise<PaginatedResponse<Avis>> {
  const response = await http.get<PaginatedResponse<Avis>>('/admin/reviews', { params })
  return response.data
}

export async function fetchAvis(id: number): Promise<Avis> {
  const response = await http.get<ApiEnvelope<Avis>>(`/admin/reviews/${id}`)
  return response.data.data
}

// Masquage logique tracé, motif obligatoire — jamais une suppression
// (CLAUDE.md §5, ajout v0.10).
export async function moderateAvis(id: number, reason: string): Promise<Avis> {
  const response = await http.post<ApiEnvelope<Avis>>(`/admin/reviews/${id}/moderate`, { reason })
  return response.data.data
}
