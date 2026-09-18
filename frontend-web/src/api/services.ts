import { createAdminReviewApi } from '@/api/adminReview'
import type { Service } from '@/types/service'

const serviceReviewApi = createAdminReviewApi<Service>('services')

export const fetchServices = serviceReviewApi.fetchList
export const fetchService = serviceReviewApi.fetchOne
export const approveService = serviceReviewApi.approve
export const rejectService = serviceReviewApi.reject
