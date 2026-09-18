import type { ProductSellableType } from '@/types/product'

// Libellé du vendeur polymorphe d'un produit — mini-boutique Garage ou
// Market Space, jamais les deux (CLAUDE.md §5, ajout v0.4).
const LABEL_BY_SELLABLE_TYPE: Record<ProductSellableType, string> = {
  garage: 'Garage',
  market_space: 'Market Space',
}

export function productSellableTypeLabel(type: ProductSellableType): string {
  return LABEL_BY_SELLABLE_TYPE[type]
}
