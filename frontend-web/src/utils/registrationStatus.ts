import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { RegistrationStatus } from '@/types/registration'

// Tonalité de badge par statut de dossier d'inscription. Le libellé, lui,
// vient du backend (`status_label`). Distinct de reviewStatusTone (services et
// produits) depuis que RegistrationStatus a sa propre valeur
// `profile_incomplete` (CLAUDE.md §5, ajout v0.26) : un `Record` indexé par le
// type union oblige TypeScript à vérifier qu'aucun statut n'est oublié.
const TONE_BY_REGISTRATION_STATUS: Record<RegistrationStatus, BadgeTone> = {
  profile_incomplete: 'neutral',
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
}

export function registrationStatusTone(status: RegistrationStatus): BadgeTone {
  return TONE_BY_REGISTRATION_STATUS[status]
}
