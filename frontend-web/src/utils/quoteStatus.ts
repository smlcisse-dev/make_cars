import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { QuoteDocumentType, QuoteLineType, QuoteStatus } from '@/types/quote'

const STATUS_LABELS: Record<QuoteStatus, string> = {
  draft: 'Brouillon',
  sent: 'Envoyé',
  accepted: 'Accepté',
  rejected: 'Refusé',
  negotiating: 'En négociation',
  in_progress: 'Prestation en cours',
  invoiced: 'Facturé',
  abandoned: 'Abandonné',
}

const STATUS_TONES: Record<QuoteStatus, BadgeTone> = {
  draft: 'neutral',
  sent: 'warning',
  accepted: 'success',
  rejected: 'danger',
  negotiating: 'warning',
  in_progress: 'warning',
  invoiced: 'success',
  abandoned: 'neutral',
}

const LINE_TYPE_LABELS: Record<QuoteLineType, string> = {
  diagnosis_fee: 'Frais de diagnostic',
  service: 'Service',
  product: 'Pièce',
}

export function quoteStatusLabel(status: QuoteStatus): string {
  return STATUS_LABELS[status]
}

export function quoteStatusTone(status: QuoteStatus): BadgeTone {
  return STATUS_TONES[status]
}

export function documentTypeLabel(type: QuoteDocumentType): string {
  return type === 'invoice' ? 'Facture' : 'Devis'
}

export function lineTypeLabel(type: QuoteLineType): string {
  return LINE_TYPE_LABELS[type]
}
