import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { ReviewStatus } from '@/types/review'

// Tonalité de badge et libellé pour un statut de validation admin —
// inscriptions, services et produits partagent les mêmes trois valeurs (voir
// ReviewStatus). Pur choix d'affichage frontend : le backend ne fournit un
// libellé traduit que pour les inscriptions (`status_label` sur
// ProfessionalRegistrationResource) ; ServiceResource/ProductResource ne
// renvoient que la valeur brute, d'où ce libellé calculé côté frontend pour
// ces deux-là.
const TONE_BY_REVIEW_STATUS: Record<ReviewStatus, BadgeTone> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
}

const LABEL_BY_REVIEW_STATUS: Record<ReviewStatus, string> = {
  pending: 'En attente',
  approved: 'Approuvé',
  rejected: 'Rejeté',
}

export function reviewStatusTone(status: ReviewStatus): BadgeTone {
  return TONE_BY_REVIEW_STATUS[status]
}

export function reviewStatusLabel(status: ReviewStatus): string {
  return LABEL_BY_REVIEW_STATUS[status]
}
