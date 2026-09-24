import axios from 'axios'

import type { QuoteStatus, QuoteVersionDecision } from '@/types/quote'

// Liens reçus par email (CLAUDE.md §5, ajout v0.30) : décision d'un devis
// par un client « compte express » et activation d'un compte express. Le
// backend place dans l'email un lien vers une page de ce site, avec le chemin
// signé de l'API en paramètre `link`, par exemple :
//   /devis/decision?link=%2Fapi%2Fquotes%2F12%2Fversions%2F3%2Femail-decision%3Fexpires%3D…%26signature%3D…
//
// Instance Axios dédiée, distincte de `http` : ces pages sont publiques. On
// n'envoie donc jamais le jeton d'un professionnel éventuellement connecté
// dans ce navigateur, et une erreur n'y déclenche aucune déconnexion ni
// redirection (intercepteurs de `http`).
const publicHttp = axios.create({ timeout: 30_000 })

// Construit l'URL complète de l'API à partir du chemin signé.
//
// Le chemin reçu commence déjà par `/api` (`/api/quotes/…`), et
// `VITE_API_BASE_URL` se termine aussi par `/api` (`http://127.0.0.1:8000/api`).
// Les coller bout à bout donnerait `…/api/api/quotes/…`. On ne garde donc de
// `VITE_API_BASE_URL` que son ORIGINE (protocole + hôte + port) : `new URL(chemin,
// base)` résout un chemin qui commence par `/` à partir de la racine de la
// base, exactement comme un lien `<a href="/…">` dans une page.
//
// La signature est relative (chemin + paramètres, sans hôte) : elle reste
// valable quel que soit l'hôte par lequel on joint l'API (`127.0.0.1` ou
// `localhost`).
//
// Sécurité : `link` vient de l'adresse de la page, donc n'importe qui peut en
// forger un. On refuse tout ce qui sortirait de l'API (autre origine, par
// exemple `//site-pirate.com/…`) ou viserait une autre route que celle attendue.
// Renvoie `null` si le lien est inutilisable.
export function signedApiUrl(link: unknown, expectedPath: RegExp): string | null {
  if (typeof link !== 'string' || !link.startsWith('/') || link.startsWith('//')) {
    return null
  }

  try {
    const apiOrigin = new URL(import.meta.env.VITE_API_BASE_URL, window.location.origin).origin
    const url = new URL(link, apiOrigin)
    if (url.origin !== apiOrigin || !expectedPath.test(url.pathname)) {
      return null
    }
    return url.href
  } catch {
    return null
  }
}

// --- Décision d'un devis -------------------------------------------------

export const QUOTE_DECISION_PATH = /^\/api\/quotes\/\d+\/versions\/\d+\/email-decision$/

export interface QuoteDecisionLine {
  label: string
  quantity: number
  unit_price: string
  line_total: string
}

export interface QuoteDecisionDetails {
  quote_id: number
  version_id: number
  version: number
  garage_name: string
  client_name: string
  lines: QuoteDecisionLine[]
  total: string
  status: QuoteStatus
  decision: QuoteVersionDecision | null
  decided_at: string | null
  // Vrai seulement si cette version peut encore être acceptée ou refusée.
  is_decidable: boolean
  expires_at: string | null
}

export type QuoteDecisionChoice = 'accept' | 'reject'

// Lecture seule : le backend ne change rien à l'ouverture du lien.
export async function fetchQuoteDecision(url: string): Promise<QuoteDecisionDetails> {
  const { data } = await publicHttp.get<{ data: QuoteDecisionDetails }>(url)
  return data.data
}

// Seul appel qui décide, sur action explicite du client.
export async function submitQuoteDecision(url: string, decision: QuoteDecisionChoice): Promise<void> {
  await publicHttp.post(url, { decision })
}

// --- Activation d'un compte express --------------------------------------

export const ACCOUNT_ACTIVATION_PATH = /^\/api\/express-clients\/\d+\/claim$/

export interface AccountActivationDetails {
  first_name: string
  // Adresse partiellement masquée (ex. `j***@gmail.com`).
  masked_email: string | null
}

export async function fetchAccountActivation(url: string): Promise<AccountActivationDetails> {
  const { data } = await publicHttp.get<{ data: AccountActivationDetails }>(url)
  return data.data
}

export async function activateAccount(
  url: string,
  payload: { password: string; password_confirmation: string },
): Promise<void> {
  await publicHttp.post(url, payload)
}

// Demande d'un nouveau lien d'activation, depuis un lien expiré
// (`POST /auth/express-claim`). Chemin fixe, résolu sur `VITE_API_BASE_URL`.
export async function requestNewActivationLink(email: string): Promise<void> {
  await publicHttp.post(`${import.meta.env.VITE_API_BASE_URL}/auth/express-claim`, { email })
}
