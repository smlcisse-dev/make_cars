import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { LitigeResolutionAction, LitigeStatus, LitigeTargetType } from '@/types/litige'

const TONE_BY_LITIGE_STATUS: Record<LitigeStatus, BadgeTone> = {
  submitted: 'warning',
  under_review: 'info',
  resolved_founded: 'danger',
  resolved_rejected: 'neutral',
  closed: 'neutral',
}

const LABEL_BY_LITIGE_STATUS: Record<LitigeStatus, string> = {
  submitted: 'Déposée',
  under_review: 'En instruction',
  resolved_founded: 'Fondée',
  resolved_rejected: 'Rejetée',
  closed: 'Clôturée',
}

export function litigeStatusTone(status: LitigeStatus): BadgeTone {
  return TONE_BY_LITIGE_STATUS[status]
}

export function litigeStatusLabel(status: LitigeStatus): string {
  return LABEL_BY_LITIGE_STATUS[status]
}

const LABEL_BY_TARGET_TYPE: Record<LitigeTargetType, string> = {
  garage: 'Garage',
  market_space: 'Market Space',
}

export function litigeTargetTypeLabel(type: LitigeTargetType): string {
  return LABEL_BY_TARGET_TYPE[type]
}

const LABEL_BY_RESOLUTION_ACTION: Record<LitigeResolutionAction, string> = {
  warning: 'Avertissement',
  suspension: 'Suspension du compte',
}

export function litigeResolutionActionLabel(action: LitigeResolutionAction): string {
  return LABEL_BY_RESOLUTION_ACTION[action]
}
