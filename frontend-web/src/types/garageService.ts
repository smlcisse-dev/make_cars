// Reflète ServiceResource côté backend pour l'espace Garagiste : mêmes champs
// que types/service.ts (admin), SAUF `garage`, jamais chargé par
// ServiceController@Garage (index/store/update/updateAvailability) — le
// garagiste consulte son propre catalogue, inutile d'y répéter son propre nom.
import type { ReviewStatus } from '@/types/review'
import type { ServiceCategory } from '@/types/service'

export type { ServiceCategory }

// Libellés français à faire correspondre mot pour mot à
// App\Enums\ServiceCategory::label() côté backend (le backend renvoie
// `category_label` en lecture, mais le <select> du formulaire en a besoin).
export const SERVICE_CATEGORY_OPTIONS: { value: ServiceCategory; label: string }[] = [
  { value: 'entretien_courant', label: 'Entretien courant' },
  { value: 'freinage_suspension', label: 'Freinage & suspension' },
  { value: 'pneumatiques', label: 'Pneumatiques' },
  { value: 'electricite_electronique', label: 'Électricité & électronique' },
  { value: 'climatisation_refroidissement', label: 'Climatisation & refroidissement' },
  { value: 'carrosserie', label: 'Carrosserie' },
  { value: 'diagnostic_controle', label: 'Diagnostic & contrôle' },
  { value: 'autre_divers', label: 'Autre/Divers' },
]

export interface GarageService {
  id: number
  garage_id: number
  name: string
  description: string
  category: ServiceCategory
  category_label: string
  price: string
  duration_minutes: number
  image_url: string | null
  is_active: boolean
  status: ReviewStatus
  rejection_reason: string | null
  is_publicly_visible: boolean
  created_at: string
  updated_at: string
}

// Payload de création/modification (hors image, gérée à part en FormData).
export interface ServiceFormPayload {
  name: string
  description: string
  category: ServiceCategory
  price: number
  duration_minutes: number
}
