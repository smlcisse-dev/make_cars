import type { User } from '@/types/user'

export type OrderStatus = 'pending' | 'paid' | 'cancelled'

// Reflète OrderLineResource.
export interface OrderLine {
  id: number
  product_id: number | null
  label: string
  unit_price: string
  quantity: number
  line_total: string
}

// Reflète OrderResource pour l'espace Garagiste. `sellable_type`/
// `sellable_id` sont toujours le garage authentifié lui-même, sans intérêt ici.
export interface Order {
  id: number
  user_id: number
  status: OrderStatus
  paid_at: string | null
  total: string
  lines: OrderLine[]
  client: User
  created_at: string
  updated_at: string
}
