import axios from 'axios'

import { clearStoredSession, getStoredToken } from '@/lib/token'

// Client HTTP unique pour toute l'app : les trois espaces (Admin/Garage/
// Market Space) consomment la même API REST Laravel (CLAUDE.md §4).
//
// `timeout` : sans lui, une requête qui ne reçoit jamais de réponse (backend
// éteint, connexion réseau qui ne répond ni n'échoue franchement) reste en
// attente indéfiniment — le bouton "Se connecter" tourne pour toujours, sans
// message d'erreur, jusqu'à ce que le navigateur lui-même finisse par couper
// la page. 15 s laisse de la marge par rapport à la latence réseau observée
// vers Supabase (jusqu'à ~10 s mesurés), tout en bornant l'attente à une
// durée raisonnable pour l'utilisateur.
const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  timeout: 15_000,
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
//
// Important : `clearStoredSession()` efface token ET utilisateur. Un
// nettoyage partiel (token seul) laisserait le garde de /login (qui vérifie
// la présence d'un utilisateur) croire la session encore valide après ce
// rechargement, et rediriger aussitôt vers l'espace protégé — dont le
// prochain appel API échouerait en 401 à nouveau, etc.
http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      clearStoredSession()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

export default http
