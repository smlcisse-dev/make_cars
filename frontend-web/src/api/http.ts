import axios from 'axios'

import { clearStoredToken, getStoredToken } from '@/lib/token'

// Client HTTP unique pour toute l'app : les trois espaces (Admin/Garage/
// Market Space) consomment la même API REST Laravel (CLAUDE.md §4).
const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
})

// Ajoute le token Sanctum courant à chaque requête, s'il existe. Sanctum
// n'est pas concerné par *comment* l'utilisateur s'est authentifié (email/mot
// de passe ici, jamais Google côté web — CLAUDE.md §5, ajout v0.5), seulement
// par la vérification de ce token une fois émis.
http.interceptors.request.use((config) => {
  const token = getStoredToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Un 401 signifie que le token n'est plus valide (expiré, révoqué,
// déconnexion ailleurs) : on nettoie la session et on repart sur /login avec
// un rechargement complet, plutôt qu'une navigation Vue Router — ça garantit
// un état front entièrement propre (stores réinitialisés) sans avoir à
// importer le router ici (et créer un import circulaire avec les guards de
// route, qui dépendent eux-mêmes du store d'auth).
http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearStoredToken()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default http
