import http from '@/api/http'
import type { GarageReview } from '@/types/garageReview'
import type { PaginatedResponse } from '@/types/pagination'

export async function fetchGarageReviews(page = 1): Promise<PaginatedResponse<GarageReview>> {
  const response = await http.get<PaginatedResponse<GarageReview>>('/garage/reviews', { params: { page } })
  return response.data
}
