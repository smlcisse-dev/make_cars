<script setup lang="ts">
import axios from 'axios'
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'

import {
  ACCOUNT_ACTIVATION_PATH,
  activateAccount,
  fetchAccountActivation,
  requestNewActivationLink,
  signedApiUrl,
  type AccountActivationDetails,
} from '@/api/emailLinks'
import AppButton from '@/shared/components/AppButton.vue'
import { extractApiErrorMessage, extractValidationErrors } from '@/utils/apiError'
import { PASSWORD_MIN_LENGTH } from '@/utils/password'

// Activation d'un compte « express » (créé par un garage pour un client venu
// sans l'application), depuis le lien reçu par email (CLAUDE.md §5, ajouts
// v0.17 et v0.30). Page publique, sans connexion, pensée d'abord pour le
// téléphone. Ouvrir la page ne change rien : seul l'envoi du formulaire
// définit le mot de passe.
//
// Pas de lien vers /login à la fin : ce site est réservé aux professionnels,
// l'automobiliste se connecte depuis l'application mobile.

type PageState = 'loading' | 'invalid' | 'claimed' | 'error' | 'ready' | 'done'
const state = ref<PageState>('loading')

const details = ref<AccountActivationDetails | null>(null)
const errorMessage = ref<string | null>(null)
const generalError = ref<string | null>(null)
const errors = ref<Record<string, string>>({})
const form = reactive({ password: '', password_confirmation: '' })
const isSubmitting = ref(false)

const route = useRoute()
const apiUrl = signedApiUrl(route.query.link, ACCOUNT_ACTIVATION_PATH)

function httpStatus(error: unknown): number | undefined {
  return axios.isAxiosError(error) ? error.response?.status : undefined
}

async function load(): Promise<void> {
  if (!apiUrl) {
    state.value = 'invalid'
    return
  }

  state.value = 'loading'
  try {
    details.value = await fetchAccountActivation(apiUrl)
    state.value = 'ready'
  } catch (error) {
    const status = httpStatus(error)
    if (status === 409) {
      state.value = 'claimed'
    } else if (status === 403 || status === 404) {
      state.value = 'invalid'
    } else {
      errorMessage.value = extractApiErrorMessage(error, 'Impossible de vérifier ce lien. Réessayez.')
      state.value = 'error'
    }
  }
}

function validate(): Record<string, string> {
  const found: Record<string, string> = {}
  if (form.password.length < PASSWORD_MIN_LENGTH) {
    found.password = `Le mot de passe doit contenir au moins ${PASSWORD_MIN_LENGTH} caractères.`
  }
  if (form.password_confirmation !== form.password) {
    found.password_confirmation = 'Les deux mots de passe ne correspondent pas.'
  }
  return found
}

async function submit(): Promise<void> {
  if (!apiUrl) return

  generalError.value = null
  errors.value = validate()
  if (Object.keys(errors.value).length > 0) return

  isSubmitting.value = true
  try {
    await activateAccount(apiUrl, { ...form })
    // Le mot de passe ne sert plus : on ne le garde pas en mémoire.
    form.password = ''
    form.password_confirmation = ''
    state.value = 'done'
  } catch (error) {
    const status = httpStatus(error)
    const fieldErrors = extractValidationErrors(error)
    if (Object.keys(fieldErrors).length > 0) {
      errors.value = fieldErrors
    } else if (status === 409) {
      state.value = 'claimed'
    } else if (status === 403 || status === 404) {
      state.value = 'invalid'
    } else {
      generalError.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
    }
  } finally {
    isSubmitting.value = false
  }
}

// --- Nouveau lien, depuis un lien expiré ---------------------------------
//
// Le message affiché après l'envoi est le même que l'adresse corresponde ou
// non à un compte à activer : la page ne doit pas permettre de deviner quels
// emails ont un compte. C'est pourquoi une erreur 422 du backend (qui, lui,
// distingue encore ce cas) aboutit au même message que la réussite ; le
// format de l'adresse est donc vérifié ici, avant l'envoi.
const NEW_LINK_SENT_MESSAGE =
  "Si un compte à activer existe avec cette adresse, un nouveau lien vient d'y être envoyé. Pensez à regarder dans les courriers indésirables."
const newLinkEmail = ref('')
const newLinkError = ref<string | null>(null)
const newLinkSent = ref(false)
const isRequestingNewLink = ref(false)

async function requestNewLink(): Promise<void> {
  const email = newLinkEmail.value.trim()
  newLinkError.value = null
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    newLinkError.value = 'Saisissez une adresse email valide.'
    return
  }

  isRequestingNewLink.value = true
  try {
    await requestNewActivationLink(email)
    newLinkSent.value = true
  } catch (error) {
    if (httpStatus(error) === 422) {
      newLinkSent.value = true
    } else {
      newLinkError.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
    }
  } finally {
    isRequestingNewLink.value = false
  }
}

// `text-base` : en dessous de 16 px, Safari iOS zoome sur le champ à la saisie.
const inputClass =
  'mt-1 w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none'

onMounted(load)
</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-8 sm:items-center sm:py-10">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
      <p class="text-sm font-medium text-slate-500">Make Cars</p>

      <p v-if="state === 'loading'" class="mt-4 text-base text-slate-600">Vérification du lien…</p>

      <template v-else-if="state === 'invalid'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Lien non valide</h1>
        <p class="mt-3 text-base text-slate-600">
          Ce lien a expiré ou n'est plus valide.
        </p>

        <p v-if="newLinkSent" class="mt-6 rounded-md bg-slate-100 p-3 text-base text-slate-700">
          {{ NEW_LINK_SENT_MESSAGE }}
        </p>
        <form v-else class="mt-6 space-y-4" novalidate @submit.prevent="requestNewLink">
          <div>
            <label for="new-link-email" class="block text-sm font-medium text-slate-700">
              Votre adresse email
            </label>
            <input
              id="new-link-email"
              v-model="newLinkEmail"
              type="email"
              autocomplete="email"
              inputmode="email"
              required
              :class="inputClass"
            />
            <p v-if="newLinkError" class="mt-1 text-sm text-red-600">{{ newLinkError }}</p>
          </div>

          <AppButton type="submit" :loading="isRequestingNewLink" class="w-full py-3 text-base">
            Recevoir un nouveau lien
          </AppButton>
        </form>
      </template>

      <template v-else-if="state === 'claimed'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Compte déjà activé</h1>
        <p class="mt-3 text-base text-slate-600">
          Ce compte a déjà été activé. Connectez-vous depuis l'application mobile Make Cars avec
          votre email et votre mot de passe.
        </p>
      </template>

      <template v-else-if="state === 'error'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Vérification impossible</h1>
        <p class="mt-3 text-base text-red-600">{{ errorMessage }}</p>
        <AppButton class="mt-6 w-full py-3 text-base" @click="load">Réessayer</AppButton>
      </template>

      <template v-else-if="state === 'done'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Votre compte est prêt</h1>
        <p class="mt-3 text-base text-slate-700">
          Votre compte est prêt. Connectez-vous depuis l'application mobile Make Cars avec votre
          email et ce mot de passe.
        </p>
      </template>

      <template v-else-if="details">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Activez votre compte</h1>
        <p class="mt-3 text-base text-slate-700">
          Bonjour {{ details.first_name }}, choisissez un mot de passe pour votre compte<template
            v-if="details.masked_email"
          >
            ({{ details.masked_email }})</template
          >.
        </p>

        <form class="mt-6 space-y-4" novalidate @submit.prevent="submit">
          <div>
            <label for="password" class="block text-sm font-medium text-slate-700">
              Mot de passe
            </label>
            <input
              id="password"
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              required
              :class="inputClass"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password }}</p>
            <p v-else class="mt-1 text-xs text-slate-500">
              Au moins {{ PASSWORD_MIN_LENGTH }} caractères.
            </p>
          </div>

          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-700">
              Confirmation du mot de passe
            </label>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              :class="inputClass"
            />
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">
              {{ errors.password_confirmation }}
            </p>
          </div>

          <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

          <AppButton type="submit" :loading="isSubmitting" class="w-full py-3 text-base">
            Activer mon compte
          </AppButton>
        </form>
      </template>
    </div>
  </div>
</template>
