import http from '@/api/http'
import type { Avis } from '@/types/avis'
import type { Litige, LitigeMessage, LitigeResolutionAction, LitigeStatus, LitigeTargetSummary } from '@/types/litige'
import type { PaginatedResponse } from '@/types/pagination'

// Réponses ponctuelles enveloppées dans { data, message } — voir
// app/Http/Controllers/Api/Controller.php::success() côté backend, même
// schéma que src/api/avis.ts.
interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface LitigeListParams {
  status?: LitigeStatus
  page?: number
}

export async function fetchLitigeList(params: LitigeListParams): Promise<PaginatedResponse<Litige>> {
  const response = await http.get<PaginatedResponse<Litige>>('/admin/disputes', { params })
  return response.data
}

// Message d'une conversation Garage↔Automobiliste — sous-ensemble de
// MessageResource utile à l'affichage admin (même simplification que
// LitigeTargetSummary dans types/litige.ts).
export interface LitigeConversationMessage {
  id: number
  sender: { id: number; name: string } | null
  is_system: boolean
  body: string | null
  has_image: boolean
  created_at: string
}

// Résumé de la conversation embarqué directement dans la fiche réclamation
// (DisputeController::show ne charge pas les messages — seulement `garage`)
// : juste assez pour proposer d'ouvrir l'historique complet à la demande.
export interface LitigeConversationSummary {
  id: number
  garage: LitigeTargetSummary
  last_message_at: string | null
}

export interface LitigeConversation extends LitigeConversationSummary {
  messages: LitigeConversationMessage[]
}

// Fiche d'instruction complète : la réclamation, la conversation
// Garage↔Automobiliste associée (résumé sans messages — voir
// DisputeController::show), et les avis déjà laissés sur le professionnel
// visé (CLAUDE.md §5, ajout v0.11).
export interface LitigeDetail {
  dispute: Litige
  conversation: LitigeConversationSummary | null
  reviews: Avis[]
}

export async function fetchLitige(id: number): Promise<LitigeDetail> {
  const response = await http.get<ApiEnvelope<LitigeDetail>>(`/admin/disputes/${id}`)
  return response.data.data
}

// Historique complet d'une conversation, chargé à la demande depuis la
// fiche réclamation — la fiche elle-même n'embarque qu'un résumé sans les
// messages (voir fetchLitige ci-dessus) ; l'admin ouvre le détail via ce
// même endpoint de supervision que l'onglet Conversations (à venir).
export async function fetchLitigeConversation(conversationId: number): Promise<LitigeConversation> {
  const response = await http.get<ApiEnvelope<LitigeConversation>>(`/admin/conversations/${conversationId}`)
  return response.data.data
}

// Demande de réponse/défense au professionnel — message optionnel,
// possible une seule fois (dossier encore `submitted`, CLAUDE.md §5, ajout
// v0.11).
export async function requestLitigeResponse(id: number, message?: string): Promise<Litige> {
  const response = await http.post<ApiEnvelope<Litige>>(`/admin/disputes/${id}/request-response`, {
    message: message || undefined,
  })
  return response.data.data
}

// Message libre de l'admin dans l'espace d'échange — répétable, à la
// différence de requestLitigeResponse ci-dessus.
export async function postLitigeMessage(id: number, body: string): Promise<LitigeMessage> {
  const response = await http.post<ApiEnvelope<LitigeMessage>>(`/admin/disputes/${id}/messages`, { body })
  return response.data.data
}

export async function rejectLitige(id: number, reason: string): Promise<Litige> {
  const response = await http.post<ApiEnvelope<Litige>>(`/admin/disputes/${id}/reject`, { reason })
  return response.data.data
}

// Réclamation jugée fondée — motif obligatoire et action librement choisie
// (avertissement ou suspension), aucune sanction automatique (CLAUDE.md §5,
// ajout v0.11).
export async function resolveLitige(id: number, reason: string, action: LitigeResolutionAction): Promise<Litige> {
  const response = await http.post<ApiEnvelope<Litige>>(`/admin/disputes/${id}/resolve`, { reason, action })
  return response.data.data
}

export async function closeLitige(id: number): Promise<Litige> {
  const response = await http.post<ApiEnvelope<Litige>>(`/admin/disputes/${id}/close`)
  return response.data.data
}

// Le fichier est privé (disque non public côté backend) : l'endpoint exige
// le token Sanctum, donc une simple balise <a href> échouerait — on récupère
// un blob via notre client Axios (qui, lui, ajoute le token), ouvert ensuite
// dans un nouvel onglet — même mécanisme que fetchRegistrationDocumentBlob
// (src/api/registrations.ts).
export async function fetchLitigeAttachmentBlob(disputeId: number, attachmentId: number): Promise<Blob> {
  const response = await http.get<Blob>(`/admin/disputes/${disputeId}/attachments/${attachmentId}/download`, {
    responseType: 'blob',
  })
  return response.data
}
