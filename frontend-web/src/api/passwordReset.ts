import http from '@/api/http'

// Mot de passe oublié par code email (CLAUDE.md §5, ajout v0.29), endpoints
// publics. La demande répond toujours le même message, que le compte existe
// ou non ; aucun token n'est renvoyé après le changement.

export async function requestPasswordResetCode(email: string): Promise<void> {
  await http.post('/auth/password/forgot', { email })
}

export interface PasswordResetPayload {
  email: string
  code: string
  password: string
  password_confirmation: string
}

export async function resetPassword(payload: PasswordResetPayload): Promise<void> {
  await http.post('/auth/password/reset', payload)
}
