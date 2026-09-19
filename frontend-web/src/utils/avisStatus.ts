import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { AvisStatus, AvisTargetType, AvisTransactionType } from '@/types/avis'

// Distinct de utils/reviewStatus.ts (statuts pending/approved/rejected de la
// validation admin) : un avis n'a que deux statuts, visible dès création,
// masqué uniquement après modération (CLAUDE.md §5, ajout v0.10).
const TONE_BY_AVIS_STATUS: Record<AvisStatus, BadgeTone> = {
  visible: 'success',
  hidden: 'neutral',
}

const LABEL_BY_AVIS_STATUS: Record<AvisStatus, string> = {
  visible: 'Visible',
  hidden: 'Masqué',
}

export function avisStatusTone(status: AvisStatus): BadgeTone {
  return TONE_BY_AVIS_STATUS[status]
}

export function avisStatusLabel(status: AvisStatus): string {
  return LABEL_BY_AVIS_STATUS[status]
}

const LABEL_BY_TARGET_TYPE: Record<AvisTargetType, string> = {
  garage: 'Garage',
  market_space: 'Market Space',
}

export function avisTargetTypeLabel(type: AvisTargetType): string {
  return LABEL_BY_TARGET_TYPE[type]
}

const LABEL_BY_TRANSACTION_TYPE: Record<AvisTransactionType, string> = {
  quote: 'Devis facturé',
  order: 'Commande payée',
}

export function avisTransactionTypeLabel(type: AvisTransactionType): string {
  return LABEL_BY_TRANSACTION_TYPE[type]
}
