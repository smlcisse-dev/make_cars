import type { AccountType } from '@/types/user'

// Reflète App\Enums\RegistrationStatus côté backend (CLAUDE.md §5, ajout
// v0.26). Ce type était auparavant un simple alias de ReviewStatus (services et
// produits), car les deux partageaient les mêmes trois valeurs. Le dossier
// d'inscription a désormais un statut de plus, `profile_incomplete` (profil
// ou informations légales encore à compléter, dossier jamais soumis), que les
// services et produits n'ont pas : on découple donc les deux types. Sans ce
// découplage, ajouter la valeur ici l'aurait aussi ajoutée à ReviewStatus, et
// TypeScript aurait exigé de la gérer partout où un service ou un produit est
// affiché, alors qu'elle ne peut jamais s'y présenter.
export type RegistrationStatus = 'profile_incomplete' | 'pending' | 'approved' | 'rejected'

// Reflète App\Enums\RegistrationDocumentType côté backend.
// `identity_certificate` : Certificat d'Identification Personnelle (v0.27).
export type RegistrationDocumentType =
  | 'business_registration'
  | 'premises_photo'
  | 'identity_certificate'

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
  // Lus depuis le profil (v0.26) : `null` tant que le professionnel ne les a
  // pas saisis, ou quand le profil n'est pas chargé par le backend.
  structure_name: string | null
  address: string | null
  business_registration_number: string | null
  status: RegistrationStatus
  status_label: string
  rejection_reason: string | null
  // Date de la dernière soumission pour validation (null si jamais soumis).
  submitted_at: string | null
  reviewed_at: string | null
  is_suspended: boolean
  suspension_reason: string | null
  suspended_at: string | null
  documents: RegistrationDocument[]
  created_at: string
}

// Même ressource, telle qu'embarquée dans l'utilisateur connecté (/auth/me,
// connexion) : le backend n'y charge ni le compte ni les documents, donc ces
// clés sont absentes de la réponse. `Omit<T, K>` construit un nouveau type à
// partir de T en retirant les propriétés K : TypeScript refusera ainsi qu'on
// lise `documents` sur cet objet, au lieu de nous laisser croire qu'il existe.
export type SessionRegistration = Omit<
  ProfessionalRegistration,
  'account_type' | 'account_type_label' | 'documents'
>
