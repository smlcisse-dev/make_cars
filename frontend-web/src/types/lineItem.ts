// Ligne affichable d'un devis ou d'une commande (champs communs à
// `QuoteLine` et `OrderLine`).
export interface LineItem {
  id: number
  label: string
  quantity: number
  unit_price: string
  line_total: string
}
