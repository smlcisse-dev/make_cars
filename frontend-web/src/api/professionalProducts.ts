import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type {
  ProductCreatePayload,
  ProductFormPayload,
  ProfessionalProduct,
  StockPayload,
} from '@/types/professionalProduct'
import type { ProfessionalSpace } from '@/types/professionalSpace'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

// Le message de confirmation vient du backend (« en attente de validation
// admin ») — renvoyé avec le produit pour être affiché tel quel.
export interface ProductMutationResult {
  product: ProfessionalProduct
  message: string
}

export async function fetchProfessionalProducts(
  space: ProfessionalSpace,
  page = 1,
): Promise<PaginatedResponse<ProfessionalProduct>> {
  const response = await http.get<PaginatedResponse<ProfessionalProduct>>(`/${space}/products`, {
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

export async function createProfessionalProduct(
  space: ProfessionalSpace,
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

  const response = await http.post<ApiEnvelope<ProfessionalProduct>>(`/${space}/products`, formData)
  return { product: response.data.data, message: response.data.message ?? 'Produit ajouté.' }
}

// PUT + fichier : POST avec `_method=PUT` (spoofing Laravel), comme pour les
// services — PHP ne parse pas fiablement un PUT multipart natif.
export async function updateProfessionalProduct(
  space: ProfessionalSpace,
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

  const response = await http.post<ApiEnvelope<ProfessionalProduct>>(`/${space}/products/${id}`, formData)
  return { product: response.data.data, message: response.data.message ?? 'Produit mis à jour.' }
}

// JSON classique : `low_stock_threshold: null` efface le seuil (pas d'alerte).
export async function updateProfessionalProductStock(
  space: ProfessionalSpace,
  id: number,
  payload: StockPayload,
): Promise<ProductMutationResult> {
  const response = await http.put<ApiEnvelope<ProfessionalProduct>>(`/${space}/products/${id}/stock`, payload)
  return { product: response.data.data, message: response.data.message ?? 'Stock mis à jour.' }
}

export async function deleteProfessionalProduct(space: ProfessionalSpace, id: number): Promise<void> {
  await http.delete(`/${space}/products/${id}`)
}

// Catalogue complet (sans pagination visible) pour le sélecteur de lignes de
// devis — le backend plafonne `per_page` à 100. Le filtrage par statut
// (`approved`, actif) se fait côté appelant : le backend renvoie tout ce qui
// appartient au garage.
// Volontairement sans paramètre `space` (URL /garage/... en dur) : seuls les
// devis, propres au Garagiste, l'utilisent, et le backend Market Space ignore
// `per_page` — l'appeler pour une boutique renverrait silencieusement les 15
// premiers produits seulement.
export async function fetchAllGarageProducts(): Promise<ProfessionalProduct[]> {
  const response = await http.get<PaginatedResponse<ProfessionalProduct>>('/garage/products', {
    params: { per_page: 100 },
  })
  return response.data.data
}
