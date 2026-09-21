import type { User } from '@/types/user'

export type DisputeStatus = 'submitted' | 'under_review' | 'resolved_founded' | 'resolved_rejected' | 'closed'
export type DisputeResolutionAction = 'warning' | 'suspension'

export interface DisputeAttachment {
  id: number
  position: number
}

export interface DisputeMessage {
  id: number
  author: User | null
  body: string
  created_at: string
}

// Reflète DisputeResource pour l'espace Garagiste. `respondent` (whenLoaded)
// n'est jamais chargé ici (le garage lui-même), omis.
export interface Dispute {
  id: number
  transaction_type: 'order' | 'quote'
  transaction_id: number
  reason: string
  status: DisputeStatus
  response_requested_at: string | null
  resolution_reason: string | null
  resolution_action: DisputeResolutionAction | null
  decided_at: string | null
  closed_at: string | null
  client: User
  attachments: DisputeAttachment[]
  messages: DisputeMessage[]
  created_at: string
}
