// Persistance du token Sanctum en localStorage (CLAUDE.md §4 : "gère
// elle-même l'auth [stockage du token Sanctum]"). Volontairement séparé du
// store Pinia : le client HTTP (src/api/http.ts) a juste besoin de lire la
// valeur brute à chaque requête, pas de réactivité Vue — lui faire dépendre
// du store créerait un import circulaire (store -> http -> store) pour
// aucun bénéfice.
const TOKEN_STORAGE_KEY = 'make_cars_token'

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_STORAGE_KEY)
}

export function setStoredToken(token: string): void {
  localStorage.setItem(TOKEN_STORAGE_KEY, token)
}

export function clearStoredToken(): void {
  localStorage.removeItem(TOKEN_STORAGE_KEY)
}
