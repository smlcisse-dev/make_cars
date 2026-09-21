import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { DisputeResolutionAction, DisputeStatus } from '@/types/dispute'

const LABELS: Record<DisputeStatus, string> = {
  submitted: 'Déposée',
  under_review: 'En instruction',
  resolved_founded: 'Fondée',
  resolved_rejected: 'Rejetée',
  closed: 'Clôturée',
}

const TONES: Record<DisputeStatus, BadgeTone> = {
  submitted: 'warning',
  under_review: 'info',
  resolved_founded: 'danger',
  resolved_rejected: 'success',
  closed: 'neutral',
}

const ACTION_LABELS: Record<DisputeResolutionAction, string> = {
  warning: 'Avertissement',
  suspension: 'Suspension du compte',
}

export function disputeStatusLabel(status: DisputeStatus): string {
  return LABELS[status]
}

export function disputeStatusTone(status: DisputeStatus): BadgeTone {
  return TONES[status]
}

export function disputeResolutionActionLabel(action: DisputeResolutionAction): string {
  return ACTION_LABELS[action]
}

// Une réclamation tranchée ou clôturée n'accepte plus de réponse.
export function isDisputeOpen(status: DisputeStatus): boolean {
  return status === 'submitted' || status === 'under_review'
}
