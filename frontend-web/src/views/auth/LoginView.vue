<script setup lang="ts">
import axios from 'axios'
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { extractApiErrorMessage } from '@/utils/apiError'
import { useResendCountdown } from '@/utils/emailCode'

// `ref()` crée une valeur réactive : Vue suit qui la lit (ici le template,
// via `v-model`) et republie automatiquement l'affichage quand elle change.
// On lit/écrit sa valeur avec `.value` en script ; le template, lui, n'a pas
// besoin du `.value` (Vue le "déballe" automatiquement dans les templates).
const email = ref('')
const password = ref('')
const isSubmitting = ref(false)
const errorMessage = ref<string | null>(null)

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

// Retour de « Mot de passe oublié » (`?reset=1`, posé par
// ForgotPasswordView) : confirmation affichée au-dessus du formulaire.
const passwordWasReset = computed(() => route.query.reset === '1')

// Trop de tentatives (429, limite de débit côté backend — CLAUDE.md §4) :
// le backend indique le délai d'attente (`retry_after`, en secondes). Le
// décompte est affiché en direct et le bouton reste désactivé jusqu'à zéro.
const { secondsLeft: throttleSecondsLeft, waitSeconds } = useResendCountdown()
const isThrottled = computed(() => throttleSecondsLeft.value > 0)

async function handleSubmit(): Promise<void> {
  isSubmitting.value = true
  errorMessage.value = null

  try {
    await auth.login(email.value, password.value)

    // `redirect` est posé par la garde de navigation (src/router/index.ts)
    // quand une route protégée a renvoyé ici faute de session active.
    const redirect = route.query.redirect
    const target = typeof redirect === 'string' ? redirect : null
    await router.push(target ?? '/')
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 429) {
      const retryAfter = (error.response.data as { retry_after?: number } | undefined)?.retry_after
      waitSeconds(retryAfter ?? 60)
    } else if (axios.isAxiosError(error) && error.response?.status === 422) {
      errorMessage.value = (error.response.data as { message?: string }).message ?? 'Identifiants invalides.'
    } else {
      errorMessage.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-slate-50 px-4">
    <div class="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="text-xl font-semibold text-slate-900">Make Cars</h1>
      <p class="mt-1 text-sm text-slate-500">Espace Garagiste, Market Space et Administrateur.</p>

      <p
        v-if="passwordWasReset"
        class="mt-4 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800"
      >
        Votre mot de passe a été modifié. Connectez-vous avec votre nouveau mot de passe.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="email"
            required
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
          />
        </div>

        <div>
          <div class="flex items-baseline justify-between">
            <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
            <RouterLink
              :to="{ name: 'password.forgot' }"
              class="text-sm text-slate-600 underline hover:text-slate-900"
            >
              Mot de passe oublié ?
            </RouterLink>
          </div>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
          />
        </div>

        <p v-if="isThrottled" class="text-sm text-red-600" role="alert">
          Trop de tentatives. Réessayez dans {{ throttleSecondsLeft }} secondes.
        </p>
        <p v-else-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>

        <button
          type="submit"
          :disabled="isSubmitting || isThrottled"
          class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ isSubmitting ? 'Connexion...' : 'Se connecter' }}
        </button>
      </form>

      <p class="mt-6 text-sm text-slate-500">
        Vous êtes un garage ou un vendeur de pièces ?
        <RouterLink :to="{ name: 'signup' }" class="font-medium text-slate-900 underline">
          Créer un compte professionnel
        </RouterLink>
      </p>
    </div>
  </div>
</template>
