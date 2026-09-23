import type { ReviewStatus } from '@/types/review'

// Reflète ProductResource côté backend pour les deux espaces professionnels
// (mini-boutique Garage et Market Space partagent le même modèle `Product`
// polymorphe — CLAUDE.md §5, ajout v0.4) : mêmes champs que types/product.ts
// (admin), SAUF `sellable` (jamais chargé ici) et `sellable_type`/`sellable_id`
// (toujours le professionnel authentifié lui-même, garage ou boutique, donc
// sans intérêt pour cet écran).
export interface ProfessionalProduct {
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
// gérés séparément — le stock a son propre endpoint, cf. api/professionalProducts.ts).
export interface ProductFormPayload {
  name: string
  description: string | null
  sku: string | null
  price: number
}

// Le stock n'est demandé qu'à la création (`stock_quantity` obligatoire côté
// backend) ; ensuite il ne se corrige que via updateProfessionalProductStock.
export interface ProductCreatePayload extends ProductFormPayload {
  stock_quantity: number
  low_stock_threshold: number | null
}

export interface StockPayload {
  stock_quantity: number
  low_stock_threshold: number | null
}
