import type { User } from '@/types/user'

export type MarketSpaceReviewStatus = 'visible' | 'hidden'

// Reflète ReviewResource pour l'espace Market Space. Une boutique ne reçoit
// des avis que sur des commandes payées : `transaction_type` vaut donc
// toujours 'order' en pratique, mais le type reste celui de la ressource.
export interface MarketSpaceReview {
  id: number
  transaction_type: 'order' | 'quote'
  transaction_id: number
  rating: number
  comment: string | null
  status: MarketSpaceReviewStatus
  moderation_reason: string | null
  moderated_at: string | null
  client: User
  created_at: string
}
