import type { User } from '@/types/user'

// Persistance de la session (token Sanctum + utilisateur) en localStorage
// (CLAUDE.md §4 : "gère elle-même l'auth [stockage du token Sanctum]").
// Volontairement séparé du store Pinia : le client HTTP (src/api/http.ts) a
// juste besoin de lire/effacer ces valeurs brutes, pas de réactivité Vue —
// lui faire dépendre du store créerait un import circulaire (store -> http
// -> store) pour aucun bénéfice.
const TOKEN_STORAGE_KEY = 'make_cars_token'
const USER_STORAGE_KEY = 'make_cars_user'

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_STORAGE_KEY)
}

export function setStoredToken(token: string): void {
  localStorage.setItem(TOKEN_STORAGE_KEY, token)
}

export function getStoredUser(): User | null {
  const raw = localStorage.getItem(USER_STORAGE_KEY)
  if (!raw) {
    return null
  }

  try {
    return JSON.parse(raw) as User
  } catch {
    return null
  }
}

export function setStoredUser(user: User): void {
  localStorage.setItem(USER_STORAGE_KEY, JSON.stringify(user))
}

// Efface token ET utilisateur ensemble — jamais l'un sans l'autre. Un 401
// qui n'effacerait que le token laisserait un `user` orphelin en
// localStorage ; le garde de navigation (router/index.ts), lu au prochain
// chargement, croirait alors la session encore active (il ne vérifie que la
// présence d'un utilisateur pour rester sur /login) alors qu'aucun appel API
// ne peut plus aboutir — l'app resterait bloquée dans un état incohérent.
export function clearStoredSession(): void {
  localStorage.removeItem(TOKEN_STORAGE_KEY)
  localStorage.removeItem(USER_STORAGE_KEY)
}
