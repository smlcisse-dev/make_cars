import type { User } from '@/types/user'

// Cible d'une réclamation : Garage ou Market Space (CLAUDE.md §5, ajout v0.11).
export type LitigeTargetType = 'garage' | 'market_space'

// Le backend renvoie en réalité un GarageResource/MarketSpaceAccountResource
// complet ici (voir DisputeResource::toArray côté backend) ; seuls id/name
// sont utiles à cet écran admin — même simplification que AvisTargetSummary
// dans types/avis.ts.
export interface LitigeTargetSummary {
  id: number
  name: string
}

export type LitigeTransactionType = 'order' | 'quote'

// Sous-ensemble de QuoteResource/OrderResource utile à l'affichage admin —
// même principe de simplification que LitigeTargetSummary ci-dessus. `total`
// n'existe que pour une commande (Order a un montant direct) ; pour un
// devis, le montant se lit sur la dernière version envoyée (`versions`,
// chaque `total` n'étant présent que parce que le backend charge `lines`
// pour la fiche détail — DisputeController::show).
export interface LitigeTransactionSummary {
  id: number
  status: string
  paid_at: string | null
  total?: string | null
  versions?: { id: number; document_type: string; total: string | null }[]
}

// Cycle de vie d'une réclamation (CLAUDE.md §5, ajout v0.11). "under_review"
// est atteint dès qu'une réponse est demandée par l'admin ou envoyée par le
// professionnel ; "closed" est un pas distinct de la décision.
export type LitigeStatus = 'submitted' | 'under_review' | 'resolved_founded' | 'resolved_rejected' | 'closed'

// Suite donnée à une réclamation jugée fondée — choisie librement par
// l'admin, aucune sanction automatique (CLAUDE.md §5, ajout v0.11).
export type LitigeResolutionAction = 'warning' | 'suspension'

// Reflète DisputeMessageResource — un message de l'espace d'échange dédié
// entre l'admin et le professionnel concerné.
export interface LitigeMessage {
  id: number
  author: User | null
  body: string
  created_at: string
}

// Reflète DisputeAttachmentResource — jusqu'à 5 photos jointes en preuve,
// téléchargées via un endpoint authentifié dédié (disque privé partagé avec
// le chat, jamais d'URL publique — CLAUDE.md §5, ajout v0.8/v0.11).
export interface LitigeAttachment {
  id: number
  position: number
}

// Reflète DisputeResource (backend/app/Http/Resources/DisputeResource.php).
// Nommé "Litige" côté frontend (vocabulaire courant du CLAUDE.md §8) alors
// que le backend garde le nom anglais "Dispute" — même écart volontaire que
// Avis/Review (types/avis.ts vs types/review.ts).
export interface Litige {
  id: number
  respondent_type: LitigeTargetType
  respondent_id: number
  respondent: LitigeTargetSummary
  transaction_type: LitigeTransactionType
  transaction_id: number
  // Absent dans la liste (non chargé côté backend pour /admin/disputes),
  // toujours présent dans la fiche détail.
  transaction?: LitigeTransactionSummary
  reason: string
  status: LitigeStatus
  response_requested_at: string | null
  resolution_reason: string | null
  resolution_action: LitigeResolutionAction | null
  decided_at: string | null
  closed_at: string | null
  client: User
  decided_by: User | null
  attachments: LitigeAttachment[]
  messages: LitigeMessage[]
  created_at: string
}
