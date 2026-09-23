import http from '@/api/http'
import type { PendingSignup, SignupAccountType, SignupForm } from '@/types/signup'

// Endpoints publics du parcours d'inscription professionnelle (CLAUDE.md §5,
// ajout v0.26). Aucun ne renvoie de token : le professionnel se connecte
// ensuite normalement via /login.

interface ApiEnvelope<T> {
  data: T
  message?: string
}

// `SignupForm & { ... }` : type intersection, l'objet doit avoir à la fois
// tous les champs du formulaire ET `account_type`.
export async function startSignup(
  payload: SignupForm & { account_type: SignupAccountType },
): Promise<PendingSignup> {
  const response = await http.post<ApiEnvelope<PendingSignup>>(
    '/auth/register/professionnel',
    payload,
  )
  return response.data.data
}

export async function verifySignupCode(verificationId: string, code: string): Promise<void> {
  await http.post(`/auth/register/professionnel/${verificationId}/verify`, { code })
}

export async function resendSignupCode(verificationId: string): Promise<PendingSignup> {
  const response = await http.post<ApiEnvelope<PendingSignup>>(
    `/auth/register/professionnel/${verificationId}/resend`,
  )
  return response.data.data
}
