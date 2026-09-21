// Le ProductResource d'un Market Space a exactement la même forme que celui de
// la mini-boutique Garage (même modèle `Product` polymorphe — CLAUDE.md §5,
// ajout v0.4) : on réutilise les types plutôt que de les dupliquer.
export type {
  GarageProduct as MarketSpaceProduct,
  ProductCreatePayload,
  ProductFormPayload,
  StockPayload,
} from '@/types/garageProduct'
