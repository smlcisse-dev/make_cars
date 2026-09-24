// Réponse de GET /garage/dashboard et GET /market-space/dashboard (ajout
// 2026-09-24). Les champs marqués `?` n'existent que pour un garage
// (réparation : RDV, devis, services) : ils sont absents pour un Market Space.

export interface LowStockProduct {
  id: number
  name: string
  stock_quantity: number
  low_stock_threshold: number
}

export interface DashboardToHandle {
  pending_appointments?: number
  quotes_to_start?: number
  quotes_to_invoice?: number
  orders_to_collect: number
  low_stock_products: { count: number; items: LowStockProduct[] }
  rejected_services?: number
  rejected_products: number
  open_disputes: number
}

export interface DashboardActivity {
  // Premier jour du mois en cours (AAAA-MM-JJ, heure du Bénin).
  month_start: string
  // Chaîne décimale ("12500.00"), comme les autres montants de l'API.
  invoiced_amount_this_month: string
  reviews: { count: number; average_rating: number | null }
  quotes_awaiting_client?: number
  pending_services?: number
  pending_products: number
}

export interface ProfessionalDashboard {
  structure_name: string
  to_handle: DashboardToHandle
  activity: DashboardActivity
}
