import type { User } from '@/types/user'

// Reflète MessageResource. `attachment_type` reflète la colonne backend,
// non documentée plus précisément ici — traité comme une chaîne opaque,
// seul `has_image` pilote l'affichage.
export interface ChatMessage {
  id: number
  conversation_id: number
  is_system: boolean
  sender: User | null
  body: string | null
  has_image: boolean
  attachment_type: string | null
  quote_version_id: number | null
  // Absent (pas null) pour un message sans devis associé.
  quote_id?: number | null
  created_at: string
}

// Reflète ConversationResource pour un espace professionnel. `sellable`
// (whenLoaded, Garage ou Market Space) n'est jamais chargé ici (son propre
// vendeur), omis.
export interface Conversation {
  id: number
  sellable_type: string
  sellable_id: number
  user_id: number
  last_message_at: string | null
  user: User
  created_at: string
}
