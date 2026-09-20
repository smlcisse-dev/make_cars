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

  // Vrai pour un professionnel approuvé dont le profil est incomplet : tout
  // son espace est alors verrouillé sauf la page de profil (le backend
  // applique la même règle, cette redirection n'est qu'un confort).
  const mustCompleteProfile = computed(
    () => user.value?.profile_status != null && !user.value.profile_status.is_complete,
  )

  function updateProfileStatus(status: ProfileStatus): void {
    if (!user.value) return
    user.value = { ...user.value, profile_status: status }
    setStoredUser(user.value)
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
    mustCompleteProfile,
    login,
    logout,
    setSession,
    clearSession,
    updateProfileStatus,
  }
})
