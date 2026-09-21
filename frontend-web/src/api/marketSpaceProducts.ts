import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type {
  MarketSpaceProduct,
  ProductCreatePayload,
  ProductFormPayload,
  StockPayload,
} from '@/types/marketSpaceProduct'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

// Le message de confirmation vient du backend (« en attente de validation
// admin ») — renvoyé avec le produit pour être affiché tel quel.
export interface ProductMutationResult {
  product: MarketSpaceProduct
  message: string
}

export async function fetchMarketSpaceProducts(page = 1): Promise<PaginatedResponse<MarketSpaceProduct>> {
  const response = await http.get<PaginatedResponse<MarketSpaceProduct>>('/market-space/products', {
    params: { page },
  })
  return response.data
}

// `description` et `sku` sont TOUJOURS envoyés, même vides : côté Laravel une
// chaîne vide d'un formulaire multipart devient `null`, ce qui permet à
// l'édition d'EFFACER une valeur existante. Les omettre quand ils sont vides
// laisserait l'ancienne valeur en base sans que le garagiste ne le sache.
function appendCommonFields(formData: FormData, payload: ProductFormPayload): void {
  formData.append('name', payload.name)
  formData.append('description', payload.description ?? '')
  formData.append('sku', payload.sku ?? '')
  formData.append('price', String(payload.price))
}

export async function createMarketSpaceProduct(
  payload: ProductCreatePayload,
  image: File | null,
): Promise<ProductMutationResult> {
  const formData = new FormData()
  appendCommonFields(formData, payload)
  formData.append('stock_quantity', String(payload.stock_quantity))
  if (payload.low_stock_threshold !== null) {
    formData.append('low_stock_threshold', String(payload.low_stock_threshold))
  }
  if (image) {
    formData.append('image', image)
  }

  const response = await http.post<ApiEnvelope<MarketSpaceProduct>>('/market-space/products', formData)
  return { product: response.data.data, message: response.data.message ?? 'Produit ajouté.' }
}

// PUT + fichier : POST avec `_method=PUT` (spoofing Laravel), comme pour les
// services — PHP ne parse pas fiablement un PUT multipart natif.
export async function updateMarketSpaceProduct(
  id: number,
  payload: ProductFormPayload,
  image: File | null,
): Promise<ProductMutationResult> {
  const formData = new FormData()
  appendCommonFields(formData, payload)
  if (image) {
    formData.append('image', image)
  }
  formData.append('_method', 'PUT')

  const response = await http.post<ApiEnvelope<MarketSpaceProduct>>(`/market-space/products/${id}`, formData)
  return { product: response.data.data, message: response.data.message ?? 'Produit mis à jour.' }
}

// JSON classique : `low_stock_threshold: null` efface le seuil (pas d'alerte).
export async function updateMarketSpaceProductStock(id: number, payload: StockPayload): Promise<ProductMutationResult> {
  const response = await http.put<ApiEnvelope<MarketSpaceProduct>>(`/market-space/products/${id}/stock`, payload)
  return { product: response.data.data, message: response.data.message ?? 'Stock mis à jour.' }
}

export async function deleteMarketSpaceProduct(id: number): Promise<void> {
  await http.delete(`/market-space/products/${id}`)
}
