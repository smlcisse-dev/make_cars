import http from '@/api/http'
import type { User } from '@/types/user'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface ExpressClientPayload {
  name: string
  email: string
  phone: string
}

export interface ExpressClientResult {
  client: User
  message: string
}

// Crée ou rattache un compte automobiliste existant (CLAUDE.md §5, ajout
// v0.9) — le téléphone doit déjà être normalisé au format béninois
// (+229XXXXXXXXXX) avant l'appel (utils/beninPhone.ts).
export async function createExpressClient(payload: ExpressClientPayload): Promise<ExpressClientResult> {
  const response = await http.post<ApiEnvelope<User>>('/garage/clients/express', payload)
  return { client: response.data.data, message: response.data.message ?? 'Client rattaché.' }
}
