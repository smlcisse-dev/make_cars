import type { User } from '@/types/user'

export type ReviewStatus = 'visible' | 'hidden'

// Reflète ReviewResource pour l'espace Garagiste. `reviewable` (whenLoaded)
// n'est jamais chargé ici (son propre profil), omis.
export interface GarageReview {
  id: number
  transaction_type: 'order' | 'quote'
  transaction_id: number
  rating: number
  comment: string | null
  status: ReviewStatus
  moderation_reason: string | null
  moderated_at: string | null
  client: User
  created_at: string
}
