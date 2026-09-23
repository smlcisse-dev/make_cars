// Reflète AdminStatisticsService::generate() côté backend (CLAUDE.md §5,
// ajout v0.18) — endpoint GET /admin/statistics. Seule `activity` respecte
// la période optionnelle transmise en paramètres ; le reste est un instantané
// global (structures, géographie, avis, réclamations).

export interface StructureStatusCounts {
  approved: number
  pending: number
  // Dossiers jamais soumis : profil ou informations légales à compléter
  // (ajout v0.26).
  profile_incomplete: number
  suspended: number
  rejected: number
  total: number
}

export interface StatisticsStructures {
  garagiste: StructureStatusCounts
  market_space: StructureStatusCounts
}

// Une ligne par département, séparément pour les garages et pour les Market
// Space (CLAUDE.md §5, ajout v0.19). `department_id` null = profils sans
// localisation renseignée ("Non renseigné"), toujours en dernier.
export interface DepartmentBucket {
  department_id: number | null
  department_name: string
  count: number
}

export interface StatisticsGeography {
  garagiste: DepartmentBucket[]
  market_space: DepartmentBucket[]
}

export interface StatisticsActivity {
  period: { start: string | null; end: string | null }
  appointments_count: number
  quotes_issued_count: number
  orders_count: number
  invoices_count: number
  total_invoiced_amount: string
}

export interface StatisticsReviews {
  count: number
  average_rating: number | null
}

export interface StatisticsDisputes {
  total: number
  by_status: {
    submitted: number
    under_review: number
    resolved_founded: number
    resolved_rejected: number
    closed: number
  }
}

export interface AdminStatistics {
  structures: StatisticsStructures
  geography: StatisticsGeography
  activity: StatisticsActivity
  reviews: StatisticsReviews
  disputes: StatisticsDisputes
}

export interface StatisticsPeriodParams {
  start_date?: string
  end_date?: string
}
