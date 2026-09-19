import type { User } from '@/types/user'

// Cible d'un avis : Garage ou Market Space (CLAUDE.md §5, ajout v0.10).
export type AvisTargetType = 'garage' | 'market_space'

// Le backend renvoie en réalité un GarageResource/MarketSpaceAccountResource
// complet ici (voir ReviewResource::toArray côté backend) ; seuls id/name
// sont utiles à cet écran admin — même simplification que
// ServiceGarageSummary dans types/service.ts.
export interface AvisTargetSummary {
  id: number
  name: string
}

// Distinct de `ReviewStatus` (types/review.ts, pending/approved/rejected) :
// un avis est visible dès sa création, sans validation admin préalable, seul
// un masquage a posteriori le fait basculer en `hidden` (CLAUDE.md §5, ajout
// v0.10). Les deux statuts ne se recouvrent jamais, mais le nom "avis" évite
// toute confusion avec l'usine de validation admin (src/api/adminReview.ts)
// qui porte déjà le mot "review" pour un tout autre concept.
export type AvisStatus = 'visible' | 'hidden'

export type AvisTransactionType = 'order' | 'quote'

// Reflète ReviewResource (backend/app/Http/Resources/ReviewResource.php).
export interface Avis {
  id: number
  reviewable_type: AvisTargetType
  reviewable_id: number
  reviewable: AvisTargetSummary
  transaction_type: AvisTransactionType
  transaction_id: number
  rating: number
  comment: string | null
  status: AvisStatus
  moderation_reason: string | null
  moderated_at: string | null
  client: User
  created_at: string
}
