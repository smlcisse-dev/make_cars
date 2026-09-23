import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

import * as authApi from '@/api/auth'
import {
  clearStoredSession,
  getStoredToken,
  getStoredUser,
  setStoredToken,
  setStoredUser,
} from '@/lib/token'
import type { ProfileRegistrationMeta } from '@/types/profile'
import type { RegistrationStatus, SessionRegistration } from '@/types/registration'
import type { AccountType, ProfileStatus, User } from '@/types/user'

// Chemin d'accueil de chaque espace, une fois connecté (CLAUDE.md §3) —
// l'app mobile Flutter reste le seul accès pour un automobiliste, ce SPA ne
// gère jamais ce rôle.
const HOME_PATH_BY_ROLE: Record<Exclude<AccountType, 'automobiliste'>, string> = {
  admin: '/admin',
  garagiste: '/garage',
  market_space: '/market-space',
}

// Page de profil de chaque espace professionnel : seule route accessible tant
// que le profil est incomplet (CLAUDE.md §5, ajout v0.20).
const PROFILE_PATH_BY_ROLE: Partial<Record<AccountType, string>> = {
  garagiste: '/garage/profile',
  market_space: '/market-space/profile',
}

export function profilePathForRole(role: AccountType): string | null {
  return PROFILE_PATH_BY_ROLE[role] ?? null
}

export function homePathForRole(role: AccountType): string {
  return role === 'automobiliste' ? '/login' : HOME_PATH_BY_ROLE[role]
}

// Store Pinia en "Composition API" (setup store) : on écrit son contenu
// comme un composable Vue normal — `ref()` pour l'état réactif, `computed()`
// pour les valeurs dérivées, des fonctions pour les actions — plutôt que
// l'ancienne syntaxe { state, getters, actions }. Pinia détecte que c'est un
// store grâce à `defineStore`, mais à l'intérieur c'est de la réactivité Vue
// ordinaire : `token.value` change -> tout composant qui lit `token` (ou
// `isAuthenticated`, qui en dépend) se met à jour automatiquement.
export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getStoredToken())
  const user = ref<User | null>(getStoredUser())

  const isAuthenticated = computed(() => token.value !== null && user.value !== null)

  function setSession(newUser: User, newToken: string): void {
    user.value = newUser
    token.value = newToken
    setStoredToken(newToken)
    setStoredUser(newUser)
  }

  const isProfessional = computed(
    () => user.value?.role === 'garagiste' || user.value?.role === 'market_space',
  )

  // Statut du dossier d'inscription (CLAUDE.md §5, ajout v0.26) ; `null` pour
  // admin et automobiliste, qui n'ont pas de dossier. `?.` (chaînage
  // optionnel) s'arrête et renvoie `undefined` dès qu'un maillon manque, au
  // lieu de lever une erreur ; `?? null` remplace alors ce `undefined`.
  const registrationStatus = computed<RegistrationStatus | null>(() =>
    isProfessional.value ? (user.value?.professional_registration?.status ?? null) : null,
  )

  // Suspension (CLAUDE.md §5, ajout v0.6) : état superposé à un dossier
  // `approved`, qui ne bloque pas l'accès au dashboard — seul un bandeau
  // l'affiche (DashboardShell).
  const isSuspended = computed(() => user.value?.professional_registration?.is_suspended === true)
  const suspensionReason = computed(
    () => user.value?.professional_registration?.suspension_reason ?? null,
  )

  // Vrai tant qu'un professionnel doit rester sur « Mon profil » : dossier non
  // approuvé OU profil incomplet. Remplace l'ancien calcul de complétude seule
  // (v0.20), qui ne regardait que la complétude du profil : depuis v0.26, un
  // profil complet n'est plus un compte validé — le dossier doit encore être
  // soumis puis approuvé par l'administrateur, et le backend ferme toutes les
  // routes métier d'ici là (403 `registration_not_approved`). Les deux
  // conditions restent nécessaires : un compte approuvé avant v0.26 peut
  // encore avoir un profil incomplet. Comme pour l'ancien calcul, le garde de
  // navigation et les menus ne sont qu'un confort ; le backend reste la
  // protection.
  const mustStayOnProfile = computed(
    () =>
      isProfessional.value &&
      (registrationStatus.value !== 'approved' ||
        user.value?.profile_status?.is_complete === false),
  )

  function updateProfileStatus(status: ProfileStatus): void {
    if (!user.value) return
    user.value = { ...user.value, profile_status: status }
    setStoredUser(user.value)
  }

  // Reporte dans la session l'état du dossier lu par « Mon profil »
  // (`meta.registration` de GET /{space}/profile). Filet de sécurité : si le
  // rafraîchissement de session au démarrage (/auth/me) a échoué, afficher
  // « Mon profil » suffit à débloquer un compte approuvé entre-temps, puisque
  // `mustStayOnProfile` lit ce statut. Seules les clés présentes dans `meta`
  // sont reportées ; les autres (id, dates...) sont conservées.
  function updateRegistrationFromProfile(meta: ProfileRegistrationMeta): void {
    if (!user.value) return
    const patch: Partial<SessionRegistration> = {
      status: meta.status,
      status_label: meta.status_label,
      rejection_reason: meta.rejection_reason,
      submitted_at: meta.submitted_at,
    }
    // `!== undefined` : ne reporte la suspension que si le backend l'a
    // envoyée (propriétés facultatives du type).
    if (meta.is_suspended !== undefined) patch.is_suspended = meta.is_suspended
    if (meta.suspension_reason !== undefined) patch.suspension_reason = meta.suspension_reason
    if (meta.suspended_at !== undefined) patch.suspended_at = meta.suspended_at
    user.value = {
      ...user.value,
      // `as SessionRegistration` : une session enregistrée avant v0.26 n'a pas
      // encore de dossier ; l'objet partiel ainsi créé porte au moins le
      // statut, seule donnée lue par le garde et les menus. Le prochain
      // /auth/me le remplace par la version complète.
      professional_registration: {
        ...(user.value.professional_registration ?? {}),
        ...patch,
      } as SessionRegistration,
    }
    setStoredUser(user.value)
  }

  // Relit l'utilisateur connecté depuis le backend (/auth/me) : statut du
  // dossier, suspension et complétude du profil peuvent avoir changé depuis
  // la connexion (décision de l'administrateur). Le token, lui, ne change pas.
  async function refreshUser(): Promise<void> {
    const freshUser = await authApi.fetchCurrentUser()
    user.value = freshUser
    setStoredUser(freshUser)
  }

  function clearSession(): void {
    user.value = null
    token.value = null
    clearStoredSession()
  }

  async function login(email: string, password: string): Promise<void> {
    const { user: loggedInUser, token: issuedToken } = await authApi.login({ email, password })
    setSession(loggedInUser, issuedToken)
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      // Le nettoyage local a lieu même si l'appel réseau échoue (token déjà
      // expiré côté serveur, hors ligne...) — se déconnecter localement ne
      // doit jamais rester bloqué par un problème réseau.
      clearSession()
    }
  }

  return {
    token,
    user,
    isAuthenticated,
    registrationStatus,
    isSuspended,
    suspensionReason,
    mustStayOnProfile,
    login,
    refreshUser,
    logout,
    setSession,
    clearSession,
    updateProfileStatus,
    updateRegistrationFromProfile,
  }
})
