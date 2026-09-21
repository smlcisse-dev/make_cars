import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { OrderStatus } from '@/types/order'

const LABELS: Record<OrderStatus, string> = {
  pending: 'En attente de paiement',
  paid: 'Payée',
  cancelled: 'Annulée',
}

const TONES: Record<OrderStatus, BadgeTone> = {
  pending: 'warning',
  paid: 'success',
  cancelled: 'neutral',
}

export function orderStatusLabel(status: OrderStatus): string {
  return LABELS[status]
}

export function orderStatusTone(status: OrderStatus): BadgeTone {
  return TONES[status]
}
