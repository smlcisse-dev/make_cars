<script setup lang="ts">
import axios from 'axios'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useAuthStore } from '@/stores/auth'
import { extractApiErrorMessage } from '@/utils/apiError'

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
    if (axios.isAxiosError(error) && error.response?.status === 422) {
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
          <label for="password" class="block text-sm font-medium text-slate-700">Mot de passe</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
          />
        </div>

        <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ isSubmitting ? 'Connexion...' : 'Se connecter' }}
        </button>
      </form>
    </div>
  </div>
</template>
