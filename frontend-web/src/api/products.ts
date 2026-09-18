import { createAdminReviewApi } from '@/api/adminReview'
import type { Product } from '@/types/product'

const productReviewApi = createAdminReviewApi<Product>('products')

export const fetchProducts = productReviewApi.fetchList
export const fetchProduct = productReviewApi.fetchOne
export const approveProduct = productReviewApi.approve
export const rejectProduct = productReviewApi.reject
