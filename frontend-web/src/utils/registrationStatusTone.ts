import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { RegistrationStatus } from '@/types/registration'

// Le backend fournit déjà le libellé (`status_label`) mais pas de couleur —
// la tonalité du badge est un pur choix d'affichage, propre au frontend.
const TONE_BY_STATUS: Record<RegistrationStatus, BadgeTone> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
}

export function registrationStatusTone(status: RegistrationStatus): BadgeTone {
  return TONE_BY_STATUS[status]
}
