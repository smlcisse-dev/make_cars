import http from '@/api/http'
import type { PaginatedResponse } from '@/types/pagination'
import type { Quote, QuoteLineInput, QuoteVersion } from '@/types/quote'

interface ApiEnvelope<T> {
  data: T
  message?: string
}

export interface QuoteListParams {
  page?: number
}

export async function fetchQuotes(params: QuoteListParams): Promise<PaginatedResponse<Quote>> {
  const response = await http.get<PaginatedResponse<Quote>>('/garage/quotes', { params })
  return response.data
}

export async function fetchQuote(id: number): Promise<Quote> {
  const response = await http.get<ApiEnvelope<Quote>>(`/garage/quotes/${id}`)
  return response.data.data
}

export async function createQuote(clientId: number, lines: QuoteLineInput[]): Promise<Quote> {
  const response = await http.post<ApiEnvelope<Quote>>('/garage/quotes', { client_id: clientId, lines })
  return response.data.data
}

export async function createQuoteForAppointment(appointmentId: number, lines: QuoteLineInput[]): Promise<Quote> {
  const response = await http.post<ApiEnvelope<Quote>>(`/garage/appointments/${appointmentId}/quote`, { lines })
  return response.data.data
}

export async function updateQuoteVersionLines(
  quoteId: number,
  versionId: number,
  lines: QuoteLineInput[],
): Promise<QuoteVersion> {
  const response = await http.put<ApiEnvelope<QuoteVersion>>(`/garage/quotes/${quoteId}/versions/${versionId}`, {
    lines,
  })
  return response.data.data
}

export async function sendQuoteVersion(quoteId: number, versionId: number): Promise<QuoteVersion> {
  const response = await http.post<ApiEnvelope<QuoteVersion>>(`/garage/quotes/${quoteId}/versions/${versionId}/send`)
  return response.data.data
}

export async function createNextQuoteVersion(quoteId: number, lines: QuoteLineInput[]): Promise<QuoteVersion> {
  const response = await http.post<ApiEnvelope<QuoteVersion>>(`/garage/quotes/${quoteId}/versions`, { lines })
  return response.data.data
}

export async function startQuote(id: number): Promise<Quote> {
  const response = await http.post<ApiEnvelope<Quote>>(`/garage/quotes/${id}/start`)
  return response.data.data
}

export async function markQuotePaid(id: number): Promise<Quote> {
  const response = await http.post<ApiEnvelope<Quote>>(`/garage/quotes/${id}/mark-paid`)
  return response.data.data
}

export async function abandonQuote(id: number): Promise<Quote> {
  const response = await http.post<ApiEnvelope<Quote>>(`/garage/quotes/${id}/abandon`)
  return response.data.data
}

// Fichier privé : même mécanisme blob authentifié que
// fetchRegistrationDocumentBlob (api/registrations.ts, espace Admin).
export async function fetchQuoteVersionPdfBlob(quoteId: number, versionId: number): Promise<Blob> {
  const response = await http.get<Blob>(`/garage/quotes/${quoteId}/versions/${versionId}/pdf`, {
    responseType: 'blob',
  })
  return response.data
}
