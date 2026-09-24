import http from '@/api/http'
import type { ProfessionalDashboard } from '@/types/dashboard'
import type { ProfessionalSpace } from '@/types/professionalSpace'

// Tous les chiffres du tableau de bord en un seul appel : avec la latence de
// la base (Supabase), un appel par carte ralentirait l'écran d'autant.
export async function fetchProfessionalDashboard(space: ProfessionalSpace): Promise<ProfessionalDashboard> {
  const response = await http.get<{ data: ProfessionalDashboard }>(`/${space}/dashboard`)
  return response.data.data
}
