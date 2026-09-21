import type { BadgeTone } from '@/shared/components/StatusBadge.vue'
import type { AppointmentStatus } from '@/types/appointment'

const LABELS: Record<AppointmentStatus, string> = {
  pending: 'En attente',
  confirmed: 'Confirmé',
  rejected: 'Refusé',
  rescheduled: 'Nouvelle date proposée',
  cancelled: 'Annulé',
  completed: 'Terminé',
}

const TONES: Record<AppointmentStatus, BadgeTone> = {
  pending: 'warning',
  confirmed: 'success',
  rejected: 'danger',
  rescheduled: 'warning',
  cancelled: 'neutral',
  completed: 'success',
}

export function appointmentStatusLabel(status: AppointmentStatus): string {
  return LABELS[status]
}

export function appointmentStatusTone(status: AppointmentStatus): BadgeTone {
  return TONES[status]
}
