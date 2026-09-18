import type { ReviewStatus } from '@/types/review'

// Reflète App\Enums\ServiceCategory côté backend : liste fixe de catégories,
// pas de texte libre (CLAUDE.md §5, ajout v0.6) — toute évolution de cette
// liste est un changement de code, jamais une action d'administration.
export type ServiceCategory =
  | 'entretien_courant'
  | 'freinage_suspension'
  | 'pneumatiques'
  | 'electricite_electronique'
  | 'climatisation_refroidissement'
  | 'carrosserie'
  | 'diagnostic_controle'
  | 'autre_divers'

// Le backend renvoie en réalité un GarageResource complet ici (voir
// ServiceResource::toArray côté backend) ; seuls id/name sont utiles à cet
// écran de validation admin.
export interface ServiceGarageSummary {
  id: number
  name: string
}

// Reflète ServiceResource (backend/app/Http/Resources/ServiceResource.php).
// Un service de réparation est toujours rattaché à un garage, jamais au
// Market Space (CLAUDE.md §5, ajout v0.6).
export interface Service {
  id: number
  garage_id: number
  garage: ServiceGarageSummary
  name: string
  description: string
  category: ServiceCategory
  category_label: string
  price: string
  duration_minutes: number
  image_url: string | null
  is_active: boolean
  status: ReviewStatus
  rejection_reason: string | null
  is_publicly_visible: boolean
  created_at: string
  updated_at: string
}
