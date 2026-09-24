import type { User } from '@/types/user'

export type QuoteStatus =
  | 'draft'
  | 'sent'
  | 'accepted'
  | 'rejected'
  | 'negotiating'
  | 'in_progress'
  | 'invoiced'
  | 'abandoned'

export type QuoteVersionDecision = 'accepted' | 'rejected'
export type QuoteDocumentType = 'quote' | 'invoice'
export type QuoteLineType = 'diagnosis_fee' | 'custom_charge' | 'service' | 'product'

// Reflète QuoteLineResource. `repair_service_id`/`product_id` non-null selon
// `type` ; jamais les deux à la fois.
export interface QuoteLine {
  id: number
  type: QuoteLineType
  repair_service_id: number | null
  product_id: number | null
  label: string
  unit_price: string
  quantity: number
  line_total: string
}

// Reflète QuoteVersionResource.
export interface QuoteVersion {
  id: number
  quote_id: number
  version: number
  document_type: QuoteDocumentType
  is_sent: boolean
  sent_at: string | null
  decision: QuoteVersionDecision | null
  // Auteur de la décision (identifiant du compte) : toujours le client.
  decided_by: number | null
  decided_at: string | null
  total: string
  lines: QuoteLine[]
  created_at: string
}

// Reflète QuoteResource pour l'espace Garagiste. `garage` (whenLoaded) n'est
// jamais chargé ici (son propre garage), volontairement omis.
export interface Quote {
  id: number
  garage_id: number
  user_id: number
  appointment_id: number | null
  status: QuoteStatus
  paid_at: string | null
  client: User
  versions: QuoteVersion[]
  created_at: string
  updated_at: string
}

// Payload d'une ligne à créer/mettre à jour — jamais de prix/libellé pour
// service/produit (relus depuis le catalogue côté backend).
export type QuoteLineInput =
  | { type: 'diagnosis_fee'; label: string; unit_price: number; quantity: number }
  | { type: 'custom_charge'; label: string; unit_price: number; quantity: number }
  | { type: 'service'; repair_service_id: number; quantity: number }
  | { type: 'product'; product_id: number; quantity: number }
