// Reflète l'enveloppe de pagination standard de Laravel (ResourceCollection
// paginée : { data, links, meta }) — utilisée par toutes les listes admin
// (registrations, produits, services...), pas seulement celle-ci.
export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}
