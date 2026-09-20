import http from '@/api/http'
import type { OpeningHour, ProfessionalProfile, ProfessionalSpace } from '@/types/profile'
import type { ProfileStatus } from '@/types/user'

// Le backend renvoie la complétude du profil dans `meta.profile_status` (voir
// ProfileController) : chaque appel qui modifie le profil est suivi d'un
// rechargement pour récupérer profil + statut à jour.
interface ProfileResponse {
  data: ProfessionalProfile
  meta: { profile_status: ProfileStatus }
}

export interface LoadedProfile {
  profile: ProfessionalProfile
  status: ProfileStatus
}

export interface ProfileInfoPayload {
  name: string
  description: string | null
  address: string
  phone: string
  latitude: number
  longitude: number
  department_id: number
  commune_id: number
  arrondissement_id: number
  neighborhood: string
}

export interface OpeningHourPayload {
  day_of_week: number
  is_closed: boolean
  opens_at: string | null
  closes_at: string | null
}

function unwrap(response: { data: ProfileResponse }): LoadedProfile {
  return { profile: response.data.data, status: response.data.meta.profile_status }
}

export async function fetchProfile(space: ProfessionalSpace): Promise<LoadedProfile> {
  return unwrap(await http.get<ProfileResponse>(`/${space}/profile`))
}

export async function updateProfile(
  space: ProfessionalSpace,
  payload: ProfileInfoPayload,
): Promise<LoadedProfile> {
  return unwrap(await http.put<ProfileResponse>(`/${space}/profile`, payload))
}

// Remplace les 7 jours d'un coup (le backend exige la semaine complète).
export async function updateOpeningHours(
  space: ProfessionalSpace,
  hours: OpeningHourPayload[],
): Promise<void> {
  await http.put(`/${space}/profile/opening-hours`, { hours })
}

export async function uploadImages(space: ProfessionalSpace, files: File[]): Promise<void> {
  // Les fichiers partent en multipart/form-data : Axios fixe l'en-tête (avec
  // la « boundary ») tout seul quand on lui donne un FormData.
  const formData = new FormData()
  files.forEach((file) => formData.append('images[]', file))
  await http.post(`/${space}/profile/images`, formData)
}

export async function deleteImage(space: ProfessionalSpace, imageId: number): Promise<void> {
  await http.delete(`/${space}/profile/images/${imageId}`)
}

export type { OpeningHour }
