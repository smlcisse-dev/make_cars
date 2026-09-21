import http from '@/api/http'
import type { MarketSpaceReview } from '@/types/marketSpaceReview'
import type { PaginatedResponse } from '@/types/pagination'

export async function fetchMarketSpaceReviews(page = 1): Promise<PaginatedResponse<MarketSpaceReview>> {
  const response = await http.get<PaginatedResponse<MarketSpaceReview>>('/market-space/reviews', { params: { page } })
  return response.data
}
