import axios from 'axios'

import { clearStoredSession, getStoredToken, getStoredUser, setStoredUser } from '@/lib/token'

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
//
// TEMPORAIRE (2026-09-22) : relevé de 15 s à 30 s par confort, face à une
// latence Supabase anormalement élevée ce jour-là — ce n'est pas la correction
// d'un bug. À ramener à 15 s (valeur justifiée ci-dessus) dès que la latence
// Supabase est redevenue normale.
const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL,
  timeout: 30_000,
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

    // 403 `profile_incomplete` (profil incomplet, CLAUDE.md §5 ajout v0.20) et
    // 403 `registration_not_approved` (dossier pas encore approuvé, ajout
    // v0.26) : le backend ferme l'espace métier. Filet de sécurité si l'état
    // local est périmé (ex. session ouverte avant une décision de l'admin) :
    // on met à jour l'utilisateur enregistré avec ce que dit la réponse, puis
    // on recharge sur la page de profil, où le garde de navigation prend le
    // relais (`mustStayOnProfile`).
    //
    // 409 `registration_under_review` (profil verrouillé pendant l'examen) et
    // 409 `legal_info_locked` (informations légales d'un dossier approuvé) :
    // volontairement aucun traitement ici — pas de redirection, l'écran
    // affiche simplement le message du backend.
    const data = error.response?.data
    if (
      error.response?.status === 403 &&
      (data?.code === 'profile_incomplete' || data?.code === 'registration_not_approved')
    ) {
      const user = getStoredUser()
      const profilePath =
        user?.role === 'garagiste'
          ? '/garage/profile'
          : user?.role === 'market_space'
            ? '/market-space/profile'
            : null
      if (user && profilePath) {
        if (data.code === 'profile_incomplete') {
          setStoredUser({
            ...user,
            profile_status: { is_complete: false, missing_fields: data.missing_fields ?? [] },
          })
        } else if (user.professional_registration && data.registration_status) {
          // `...` (décomposition) copie l'objet existant pour n'en remplacer
          // que le statut, sans modifier l'original.
          setStoredUser({
            ...user,
            professional_registration: {
              ...user.professional_registration,
              status: data.registration_status,
            },
          })
        }
        if (window.location.pathname !== profilePath) {
          window.location.href = profilePath
        }
      }
    }

    return Promise.reject(error)
  },
)

export default http
