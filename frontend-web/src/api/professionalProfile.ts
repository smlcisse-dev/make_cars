import http from '@/api/http'
import type {
  LegalInfo,
  LegalStatus,
  OpeningHour,
  ProfessionalProfile,
  ProfileRegistrationMeta,
} from '@/types/profile'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import type { ProfileStatus } from '@/types/user'

// Le backend renvoie la complétude du profil dans `meta.profile_status` (voir
// ProfileController) et, depuis v0.26, l'état du dossier d'inscription :
// complétude des informations légales, statut du dossier et informations
// légales elles-mêmes (BuildsProfessionalProfileMeta). Ces trois clés sont
// absentes si le compte n'a pas de dossier (cas anormal), d'où le `?` qui les
// rend facultatives dans le type. Chaque appel qui modifie le profil est
// suivi d'un rechargement pour récupérer profil + statuts à jour.
interface ProfileResponse {
  data: ProfessionalProfile
  meta: {
    profile_status: ProfileStatus
    legal_status?: LegalStatus
    registration?: ProfileRegistrationMeta
    legal?: LegalInfo
  }
}

export interface LoadedProfile {
  profile: ProfessionalProfile
  status: ProfileStatus
  legalStatus: LegalStatus | null
  registration: ProfileRegistrationMeta | null
  legal: LegalInfo | null
}

export interface LegalInfoPayload {
  business_registration_number: string
  ifu: string
  npi: string
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
  const { data, meta } = response.data
  return {
    profile: data,
    status: meta.profile_status,
    legalStatus: meta.legal_status ?? null,
    registration: meta.registration ?? null,
    legal: meta.legal ?? null,
  }
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

// Informations légales privées (RCCM, IFU, NPI) du dossier d'inscription
// (CLAUDE.md §5, ajout v0.26).
export async function updateLegalInfo(
  space: ProfessionalSpace,
  payload: LegalInfoPayload,
): Promise<void> {
  await http.put(`/${space}/profile/legal`, payload)
}

// Un seul document du registre de commerce : le nouveau remplace l'ancien.
export async function uploadBusinessRegistrationDocument(
  space: ProfessionalSpace,
  file: File,
): Promise<void> {
  const formData = new FormData()
  formData.append('document', file)
  await http.post(`/${space}/profile/legal/document`, formData)
}

// Fichier privé : même mécanisme blob authentifié que les PDF de devis
// (fetchQuoteVersionPdfBlob) — une simple balise <a href> n'enverrait pas le
// token Sanctum.
export async function fetchBusinessRegistrationDocumentBlob(
  space: ProfessionalSpace,
): Promise<Blob> {
  const response = await http.get<Blob>(`/${space}/profile/legal/document`, {
    responseType: 'blob',
  })
  return response.data
}

// Passe le dossier en `pending` (422 `registration_incomplete` si le profil ou
// les informations légales sont incomplets).
export async function submitRegistration(space: ProfessionalSpace): Promise<void> {
  await http.post(`/${space}/profile/submit`)
}

export type { OpeningHour }
