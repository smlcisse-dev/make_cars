import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { ProfessionalRegistration, RegistrationStatus } from '@/types/registration'

// Comme pour /auth/*, les réponses ponctuelles (une seule ressource, un
// message) sont enveloppées dans { data, message } — voir
// app/Http/Controllers/Api/Controller.php::success() côté backend.
interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface RegistrationListParams {
  status?: RegistrationStatus
  // 1 = seulement les dossiers ayant une demande de réactivation en attente.
  reactivation_requested?: 1
  page?: number
}

export async function fetchRegistrations(
  params: RegistrationListParams,
): Promise<PaginatedResponse<ProfessionalRegistration>> {
  const response = await http.get<PaginatedResponse<ProfessionalRegistration>>('/admin/registrations', {
    params,
  })
  return response.data
}

export async function fetchRegistration(id: number): Promise<ProfessionalRegistration> {
  const response = await http.get<ApiEnvelope<ProfessionalRegistration>>(`/admin/registrations/${id}`)
  return response.data.data
}

export async function approveRegistration(id: number): Promise<ProfessionalRegistration> {
  const response = await http.post<ApiEnvelope<ProfessionalRegistration>>(`/admin/registrations/${id}/approve`)
  return response.data.data
}

export async function rejectRegistration(id: number, reason: string): Promise<ProfessionalRegistration> {
  const response = await http.post<ApiEnvelope<ProfessionalRegistration>>(`/admin/registrations/${id}/reject`, {
    reason,
  })
  return response.data.data
}

/**
 * Suspend un compte déjà approuvé (motif obligatoire, CLAUDE.md §5, ajout
 * v0.6). Le statut d'inscription reste "approved" côté backend : seul
 * `is_suspended` change, d'où l'absence de nouveau statut ici.
 */
export async function suspendRegistration(id: number, reason: string): Promise<ProfessionalRegistration> {
  const response = await http.post<ApiEnvelope<ProfessionalRegistration>>(`/admin/registrations/${id}/suspend`, {
    reason,
  })
  return response.data.data
}

// Réactivation : effet immédiat, aucun motif requis (CLAUDE.md §5, ajout v0.6).
export async function reactivateRegistration(id: number): Promise<ProfessionalRegistration> {
  const response = await http.post<ApiEnvelope<ProfessionalRegistration>>(`/admin/registrations/${id}/reactivate`)
  return response.data.data
}

/**
 * Refuse la demande de réactivation en attente (motif obligatoire, CLAUDE.md
 * §5, ajout v0.28) : le compte reste suspendu. Renvoie la fiche complète.
 */
export async function refuseReactivationRequest(
  id: number,
  reason: string,
): Promise<ProfessionalRegistration> {
  const response = await http.post<ApiEnvelope<ProfessionalRegistration>>(
    `/admin/registrations/${id}/reactivation-request/refuse`,
    { reason },
  )
  return response.data.data
}

/**
 * Le fichier est privé (disque non public côté backend) : `download_url`
 * exige le token Sanctum, donc une simple balise <a href> échouerait (pas
 * d'en-tête Authorization sur une navigation classique). On le récupère en
 * "blob" via notre client Axios (qui, lui, ajoute le token via son
 * intercepteur — voir src/api/http.ts), puis on ouvre ce blob dans un nouvel
 * onglet : le navigateur l'affiche ou le télécharge selon son type MIME.
 */
export async function fetchRegistrationDocumentBlob(downloadUrl: string): Promise<Blob> {
  const response = await http.get<Blob>(downloadUrl, { responseType: 'blob' })
  return response.data
}
