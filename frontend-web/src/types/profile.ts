import type { LocationValue } from '@/types/location'

// Espace professionnel concerné : détermine le préfixe d'URL de l'API
// (/garage/... ou /market-space/...). Les deux espaces exposent exactement
// les mêmes routes de profil (CLAUDE.md §5, ajout v0.20).
export type ProfessionalSpace = 'garage' | 'market-space'

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
// connecté). latitude/longitude reviennent en chaînes décimales.
export interface ProfessionalProfile extends LocationValue {
  id: number
  name: string
  description: string | null
  address: string
  phone: string | null
  latitude: string | null
  longitude: string | null
  opening_hours: OpeningHour[]
  images: ProfileImage[]
}

// Libellés des éléments manquants, indexés par les clés renvoyées par le
// backend (`missing_fields`).
export const MISSING_FIELD_LABELS: Record<string, string> = {
  name: 'Nom',
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
