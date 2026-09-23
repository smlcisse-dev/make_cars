import type { AvisStatus } from '@/types/avis'
import type { User } from '@/types/user'

// Reflète ReviewResource pour les deux espaces professionnels. `reviewable`
// (whenLoaded) n'est jamais chargé ici (son propre profil), omis. Une boutique
// Market Space ne reçoit des avis que sur des commandes payées :
// `transaction_type` vaut donc toujours 'order' pour elle en pratique, mais le
// type reste celui de la ressource.
export interface ProfessionalReview {
  id: number
  transaction_type: 'order' | 'quote'
  transaction_id: number
  rating: number
  comment: string | null
  status: AvisStatus
  moderation_reason: string | null
  moderated_at: string | null
  client: User
  created_at: string
}
