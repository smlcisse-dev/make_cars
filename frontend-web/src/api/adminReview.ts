import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { ReviewStatus } from '@/types/review'

// Comme pour /admin/registrations, les réponses ponctuelles (une seule
// ressource, un message) sont enveloppées dans { data, message } — voir
// app/Http/Controllers/Api/Controller.php::success() côté backend.
interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface AdminReviewListParams {
  status?: ReviewStatus
  page?: number
}

// Usine générique pour les écrans de validation admin : liste filtrée par
// statut + pagination, fiche détail, approbation, rejet avec motif
// obligatoire — même schéma d'endpoints `/admin/<resource>` pour les
// services et les produits (CLAUDE.md §5, règle 5). Les inscriptions
// (src/api/registrations.ts) suivent le même schéma mais gardent leur propre
// module, à cause de leur endpoint supplémentaire de téléchargement de
// justificatif — factoriser les deux aurait forcé cette usine à porter un
// cas qui ne concerne qu'un seul appelant.
export function createAdminReviewApi<T>(resourcePath: string) {
  const base = `/admin/${resourcePath}`

  return {
    async fetchList(params: AdminReviewListParams): Promise<PaginatedResponse<T>> {
      const response = await http.get<PaginatedResponse<T>>(base, { params })
      return response.data
    },

    async fetchOne(id: number): Promise<T> {
      const response = await http.get<ApiEnvelope<T>>(`${base}/${id}`)
      return response.data.data
    },

    async approve(id: number): Promise<T> {
      const response = await http.post<ApiEnvelope<T>>(`${base}/${id}/approve`)
      return response.data.data
    },

    async reject(id: number, reason: string): Promise<T> {
      const response = await http.post<ApiEnvelope<T>>(`${base}/${id}/reject`, { reason })
      return response.data.data
    },
  }
}
