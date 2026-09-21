import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { GarageService, ServiceFormPayload } from '@/types/garageService'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export async function fetchGarageServices(page = 1): Promise<PaginatedResponse<GarageService>> {
  const response = await http.get<PaginatedResponse<GarageService>>('/garage/services', {
    params: { page },
  })
  return response.data
}

function toFormData(payload: ServiceFormPayload, image: File | null): FormData {
  const formData = new FormData()
  formData.append('name', payload.name)
  formData.append('description', payload.description)
  formData.append('category', payload.category)
  formData.append('price', String(payload.price))
  formData.append('duration_minutes', String(payload.duration_minutes))
  if (image) {
    formData.append('image', image)
  }
  return formData
}

// Le message de confirmation vient du backend (déjà correct : « en attente de
// validation admin ») — on le renvoie avec le service pour l'afficher tel quel.
export interface ServiceMutationResult {
  service: GarageService
  message: string
}

export async function createGarageService(
  payload: ServiceFormPayload,
  image: File | null,
): Promise<ServiceMutationResult> {
  const response = await http.post<ApiEnvelope<GarageService>>('/garage/services', toFormData(payload, image))
  return { service: response.data.data, message: response.data.message ?? 'Service ajouté.' }
}

// PUT + fichier : PHP ne parse pas fiablement un PUT multipart natif, on passe
// donc par un POST avec `_method=PUT` (spoofing de méthode Laravel).
export async function updateGarageService(
  id: number,
  payload: ServiceFormPayload,
  image: File | null,
): Promise<ServiceMutationResult> {
  const formData = toFormData(payload, image)
  formData.append('_method', 'PUT')
  const response = await http.post<ApiEnvelope<GarageService>>(`/garage/services/${id}`, formData)
  return { service: response.data.data, message: response.data.message ?? 'Service mis à jour.' }
}

export async function updateGarageServiceAvailability(id: number, isActive: boolean): Promise<GarageService> {
  const response = await http.put<ApiEnvelope<GarageService>>(`/garage/services/${id}/availability`, {
    is_active: isActive,
  })
  return response.data.data
}

export async function deleteGarageService(id: number): Promise<void> {
  await http.delete(`/garage/services/${id}`)
}

// Catalogue complet (sans pagination visible) pour le sélecteur de lignes de
// devis — le backend plafonne `per_page` à 100. Le filtrage par statut
// (`approved`, actif) se fait côté appelant : le backend renvoie tout ce qui
// appartient au garage.
export async function fetchAllGarageServices(): Promise<GarageService[]> {
  const response = await http.get<PaginatedResponse<GarageService>>('/garage/services', {
    params: { per_page: 100 },
  })
  return response.data.data
}
