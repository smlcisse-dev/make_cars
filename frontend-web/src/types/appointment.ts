import type { User } from '@/types/user'

export type AppointmentStatus = 'pending' | 'confirmed' | 'rejected' | 'rescheduled' | 'cancelled' | 'completed'

// Sous-ensemble utile du service lié — le RDV peut ne pas en avoir un
// (description libre seule acceptée, CLAUDE.md §5 ajout v0.7).
export interface AppointmentServiceSummary {
  id: number
  name: string
  category_label: string
  price: string
}

// Reflète AppointmentResource (backend/app/Http/Resources/AppointmentResource.php).
// `garage` (whenLoaded) n'est jamais chargé côté Garagiste (son propre
// garage, inutile de l'afficher) — volontairement omis ici.
export interface Appointment {
  id: number
  garage_id: number
  user_id: number
  repair_service_id: number | null
  description: string | null
  requested_at: string
  proposed_at: string | null
  confirmed_at: string | null
  status: AppointmentStatus
  rejection_reason: string | null
  repair_service: AppointmentServiceSummary | null
  user: User
  created_at: string
  updated_at: string
}
