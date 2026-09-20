import http from '@/api/http'
import type { AdminStatistics, StatisticsPeriodParams } from '@/types/statistics'

// Réponse ponctuelle enveloppée dans { data, message } — voir
// app/Http/Controllers/Api/Controller.php::success() côté backend, même
// schéma que src/api/litiges.ts.
interface ApiEnvelope<T> {
  data: T
  message?: string
}

// Seule la section `activity` de la réponse respecte start_date/end_date —
// le reste (structures, géographie, avis, réclamations) est un instantané
// global (CLAUDE.md §5, ajout v0.18).
export async function fetchStatistics(params: StatisticsPeriodParams): Promise<AdminStatistics> {
  const response = await http.get<ApiEnvelope<AdminStatistics>>('/admin/statistics', { params })
  return response.data.data
}
