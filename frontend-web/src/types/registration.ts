import type { ReviewStatus } from '@/types/review'
import type { AccountType } from '@/types/user'

// Reflète App\Enums\RegistrationStatus côté backend — mêmes valeurs que
// ReviewStatus (partagé avec les services et produits, voir src/types/review.ts).
export type RegistrationStatus = ReviewStatus

// Reflète App\Enums\RegistrationDocumentType côté backend.
export type RegistrationDocumentType = 'business_registration' | 'premises_photo'

// Reflète RegistrationDocumentResource. Le fichier lui-même n'est jamais
// public : `download_url` pointe vers un endpoint protégé par Sanctum, à
// appeler avec le token (pas une simple balise <a href>, voir l'appel API
// dédié dans src/api/registrations.ts).
export interface RegistrationDocument {
  id: number
  type: RegistrationDocumentType
  type_label: string
  download_url: string
}

// Reflète ProfessionalRegistrationResource (backend/app/Http/Resources).
export interface ProfessionalRegistration {
  id: number
  account_type: AccountType
  account_type_label: string
  structure_name: string
  address: string
  business_registration_number: string
  status: RegistrationStatus
  status_label: string
  rejection_reason: string | null
  reviewed_at: string | null
  is_suspended: boolean
  suspension_reason: string | null
  suspended_at: string | null
  documents: RegistrationDocument[]
  created_at: string
}
