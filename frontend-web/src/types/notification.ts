export type PushNotificationStatus = 'created' | 'sent' | 'failed'

// Reflète PushNotificationResource — aucun champ whenLoaded, toujours
// complet quelle que soit l'action.
export interface PushNotification {
  id: number
  type: string
  title: string
  body: string
  data: Record<string, unknown> | null
  status: PushNotificationStatus
  read_at: string | null
  sent_at: string | null
  created_at: string
}
