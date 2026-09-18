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
