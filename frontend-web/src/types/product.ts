import type { ReviewStatus } from '@/types/review'

// Reflète le discriminant polymorphe exposé par ProductResource : un produit
// appartient soit à un Garage (mini-boutique), soit à un compte Market
// Space — jamais aux deux (CLAUDE.md §5, ajout v0.4).
export type ProductSellableType = 'garage' | 'market_space'

// Le backend renvoie en réalité un GarageResource ou un
// MarketSpaceAccountResource complet ici selon `sellable_type` (voir
// ProductResource::toArray côté backend) ; seuls id/name sont utiles à cet
// écran de validation admin.
export interface ProductSellableSummary {
  id: number
  name: string
}

// Reflète ProductResource (backend/app/Http/Resources/ProductResource.php).
export interface Product {
  id: number
  sellable_type: ProductSellableType
  sellable_id: number
  sellable: ProductSellableSummary
  name: string
  description: string
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
