import type { LocationValue } from '@/types/location'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import type { RegistrationStatus } from '@/types/registration'

export interface OpeningHour {
  day_of_week: number
  day_label: string
  is_closed: boolean
  opens_at: string | null
  closes_at: string | null
}

export interface ProfileImage {
  id: number
  url: string
  position: number
}

// Reflète GarageResource / MarketSpaceAccountResource (profil du professionnel
// connecté). latitude/longitude reviennent en chaînes décimales. `name` et
// `address` sont `null` tant que le professionnel ne les a pas saisis : le
// profil est créé vide à la vérification de l'email (CLAUDE.md §5, v0.26).
export interface ProfessionalProfile extends LocationValue {
  id: number
  name: string | null
  description: string | null
  address: string | null
  phone: string | null
  latitude: string | null
  longitude: string | null
  opening_hours: OpeningHour[]
  images: ProfileImage[]
}

// Le champ `name` est le nom de la STRUCTURE (vu par les automobilistes),
// pas celui de la personne : son libellé dépend donc de l'espace.
export const STRUCTURE_NAME_LABELS: Record<ProfessionalSpace, string> = {
  garage: 'Nom du garage',
  'market-space': 'Nom de la boutique',
}

// Libellés des éléments manquants, indexés par les clés renvoyées par le
// backend (`missing_fields`). `name` n'y figure pas : voir
// `missingFieldLabel`, qui le prend dans STRUCTURE_NAME_LABELS.
export const MISSING_FIELD_LABELS: Record<string, string> = {
  address: 'Adresse',
  phone: 'Téléphone',
  latitude: 'Latitude',
  longitude: 'Longitude',
  department_id: 'Département',
  commune_id: 'Commune',
  arrondissement_id: 'Arrondissement',
  neighborhood: 'Quartier',
  opening_hours: "Horaires d'ouverture",
  images: 'Au moins une photo',
}

export function missingFieldLabel(field: string, space: ProfessionalSpace): string {
  if (field === 'name') {
    return STRUCTURE_NAME_LABELS[space]
  }
  return MISSING_FIELD_LABELS[field] ?? field
}

// Libellés des informations légales manquantes, indexés par les clés renvoyées
// par le backend (`legal_status.missing_fields`, `missing_legal_fields`).
export const MISSING_LEGAL_FIELD_LABELS: Record<string, string> = {
  business_registration_number: 'Numéro RCCM',
  business_registration_document: 'Document du registre de commerce',
  ifu: 'IFU',
  npi: 'NPI',
  identity_certificate_document: "Certificat d'Identification Personnelle (CIP)",
}

// Complétude des informations légales privées (`meta.legal_status`), même
// forme que ProfileStatus.
export interface LegalStatus {
  is_complete: boolean
  missing_fields: string[]
}

// État du dossier tel que renvoyé dans `meta.registration` de
// GET /{space}/profile.
export interface ProfileRegistrationMeta {
  status: RegistrationStatus
  status_label: string
  rejection_reason: string | null
  submitted_at: string | null
  // Suspension : absente de la réponse actuelle du backend, prise en compte
  // si elle y figure (propriétés facultatives, `?`).
  is_suspended?: boolean
  suspension_reason?: string | null
  suspended_at?: string | null
}

// Informations légales privées (`meta.legal`) : seul endroit où le
// professionnel les relit, jamais exposées publiquement.
export interface LegalInfo {
  business_registration_number: string | null
  ifu: string | null
  npi: string | null
  has_business_registration_document: boolean
  // Certificat d'Identification Personnelle (ajout v0.27).
  has_identity_certificate_document: boolean
}
