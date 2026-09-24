import type { Appointment } from '@/types/appointment'
import type { ChatMessage, Conversation } from '@/types/conversation'
import type { Order } from '@/types/order'
import type { ProductSellableType } from '@/types/product'
import type { Quote } from '@/types/quote'
import type { RegistrationProfile } from '@/types/registration'

// Écrans de supervision admin, en lecture seule (CLAUDE.md §5, règle 8). Ces
// types décrivent ce que renvoient les routes `/admin/...` : les mêmes
// ressources que les espaces professionnels, avec en plus les relations que
// seul l'admin charge (garage, vendeur...). `extends` reprend le type de
// base et y ajoute ces champs, sans le recopier.

// Garage ou boutique Market Space : deux types de structure, un seul écran.
export type StructureKind = 'garage' | 'market_space'

// Profil d'une structure (GarageResource / MarketSpaceAccountResource). Les
// noms des niveaux de localisation ne sont renseignés que sur la fiche. La
// note moyenne n'est pas calculée par ces routes admin : pas reprise ici.
export interface SupervisedStructure extends RegistrationProfile {
  is_publicly_visible: boolean
}

// Résumé d'une structure tel qu'embarqué dans un RDV, un devis, une
// conversation : seuls l'identifiant et le nom servent ici.
export interface StructureSummary {
  id: number
  name: string | null
}

export interface SupervisedAppointment extends Appointment {
  garage: StructureSummary
}

export interface SupervisedQuote extends Quote {
  garage: StructureSummary
}

export interface SupervisedOrder extends Order {
  sellable_type: ProductSellableType
  sellable_id: number
  seller: StructureSummary | null
}

export interface SupervisedConversation extends Conversation {
  sellable: StructureSummary
  // Chargés uniquement sur la fiche.
  messages?: ChatMessage[]
}
