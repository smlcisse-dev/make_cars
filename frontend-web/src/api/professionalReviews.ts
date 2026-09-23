import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { ProfessionalReview } from '@/types/professionalReview'
import type { ProfessionalSpace } from '@/types/professionalSpace'

export async function fetchProfessionalReviews(
  space: ProfessionalSpace,
  page = 1,
): Promise<PaginatedResponse<ProfessionalReview>> {
  const response = await http.get<PaginatedResponse<ProfessionalReview>>(`/${space}/reviews`, { params: { page } })
  return response.data
}
