import type { ReviewStatus } from '@/types/review'

// Reflète ProductResource côté backend pour l'espace Garagiste : mêmes champs
// que types/product.ts (admin), SAUF `sellable` (jamais chargé ici) et
// `sellable_type`/`sellable_id` (toujours le garage authentifié lui-même,
// donc sans intérêt pour cet écran).
export interface GarageProduct {
  id: number
  name: string
  description: string | null
  sku: string | null
  price: string
  stock_quantity: number
  low_stock_threshold: number | null
  image_url: string | null
  status: ReviewStatus
  rejection_reason: string | null
  is_publicly_visible: boolean
  created_at: string
  updated_at: string
}

// Payload de création/modification du contenu (hors image et hors stock,
// gérés séparément — le stock a son propre endpoint, cf. api/garageProducts.ts).
export interface ProductFormPayload {
  name: string
  description: string | null
  sku: string | null
  price: number
}

// Le stock n'est demandé qu'à la création (`stock_quantity` obligatoire côté
// backend) ; ensuite il ne se corrige que via updateGarageProductStock.
export interface ProductCreatePayload extends ProductFormPayload {
  stock_quantity: number
  low_stock_threshold: number | null
}

export interface StockPayload {
  stock_quantity: number
  low_stock_threshold: number | null
}
