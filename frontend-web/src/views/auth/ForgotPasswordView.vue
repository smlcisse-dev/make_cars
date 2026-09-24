<script setup lang="ts">
import axios from 'axios'
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { requestPasswordResetCode, resetPassword } from '@/api/passwordReset'
import AppButton from '@/shared/components/AppButton.vue'
import CodeInput from '@/shared/components/CodeInput.vue'
import {
  extractApiErrorCode,
  extractApiErrorMessage,
  extractValidationErrors,
} from '@/utils/apiError'
import { CODE_FORMAT_ERROR, isWellFormedCode, useResendCountdown } from '@/utils/emailCode'
import { PASSWORD_MIN_LENGTH } from '@/utils/password'

// Mot de passe oublié (CLAUDE.md §5, ajout v0.29), en deux étapes sur la
// même page : l'email, puis le code reçu et le nouveau mot de passe. Tout
// reste dans l'état de ce composant (jamais dans le stockage du navigateur) :
// un rechargement ramène simplement à la première étape.

// Délai entre deux envois, identique au backend (config/email_codes.php).
const RESEND_COOLDOWN_SECONDS = 60

// Étape affichée : saisie de l'email, saisie du code et du nouveau mot de
// passe, ou demande bloquée (trop d'essais ou code expiré : seuls « nouveau
// code » et « modifier l'email » restent proposés).
type Step = 'email' | 'code' | 'blocked'
const step = ref<Step>('email')
const blockedMessage = ref('')

const email = ref('')
const code = ref('')
// `reactive()` : comme `ref()`, mais pour un objet entier dont chaque champ
// est suivi ; on écrit `form.password` sans `.value`.
const form = reactive({ password: '', password_confirmation: '' })

// Messages d'erreur par champ, et messages généraux.
const errors = ref<Record<string, string>>({})
const infoMessage = ref<string | null>(null)
const generalError = ref<string | null>(null)

const isRequesting = ref(false)
const isResetting = ref(false)

const { secondsLeft, waitSeconds } = useResendCountdown()

const router = useRouter()

function tooManyRequests(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 429
}

async function requestCode(): Promise<void> {
  errors.value = {}
  generalError.value = null
  infoMessage.value = null

  if (email.value.trim() === '') {
    errors.value = { email: 'Saisissez votre adresse email.' }
    return
  }

  isRequesting.value = true
  try {
    await requestPasswordResetCode(email.value.trim())
    // Réponse volontairement identique que le compte existe ou non : on ne
    // peut pas savoir ici si un email est vraiment parti.
    infoMessage.value =
      "Si un compte existe avec cette adresse, un code vient d'être envoyé. Il est valable 15 minutes."
    code.value = ''
    step.value = 'code'
    waitSeconds(RESEND_COOLDOWN_SECONDS)
  } catch (error) {
    const fieldErrors = extractValidationErrors(error)
    if (fieldErrors.email) {
      errors.value = { email: fieldErrors.email }
    } else if (tooManyRequests(error)) {
      generalError.value = 'Trop de tentatives. Réessayez dans une minute.'
    } else {
      generalError.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
    }
  } finally {
    isRequesting.value = false
  }
}

function validateResetForm(): Record<string, string> {
  const found: Record<string, string> = {}
  if (!isWellFormedCode(code.value)) {
    found.code = CODE_FORMAT_ERROR
  }
  if (form.password.length < PASSWORD_MIN_LENGTH) {
    found.password = `Le mot de passe doit contenir au moins ${PASSWORD_MIN_LENGTH} caractères.`
  }
  if (form.password_confirmation !== form.password) {
    found.password_confirmation = 'Les deux mots de passe ne correspondent pas.'
  }
  return found
}

async function handleReset(): Promise<void> {
  generalError.value = null
  infoMessage.value = null
  errors.value = validateResetForm()
  if (Object.keys(errors.value).length > 0) {
    return
  }

  isResetting.value = true
  try {
    await resetPassword({
      email: email.value.trim(),
      code: code.value,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    await router.push({ name: 'login', query: { reset: '1' } })
  } catch (error) {
    const remaining = axios.isAxiosError(error)
      ? (error.response?.data as { remaining_attempts?: number } | undefined)?.remaining_attempts
      : undefined

    switch (extractApiErrorCode(error)) {
      case 'reset_code_invalid':
        errors.value = { code: `Code incorrect. Il vous reste ${remaining ?? 0} essai(s).` }
        break
      case 'reset_code_locked':
        step.value = 'blocked'
        blockedMessage.value =
          "Trop d'essais. Demandez un nouveau code, ou modifiez votre email s'il est incorrect."
        break
      case 'reset_code_expired':
        step.value = 'blocked'
        blockedMessage.value = 'Ce code a expiré. Demandez-en un nouveau.'
        break
      default: {
        const fieldErrors = extractValidationErrors(error)
        if (Object.keys(fieldErrors).length > 0) {
          errors.value = fieldErrors
        } else if (tooManyRequests(error)) {
          generalError.value = 'Trop de tentatives. Réessayez dans une minute.'
        } else {
          generalError.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
        }
      }
    }
  } finally {
    isResetting.value = false
  }
}

// Retour à la première étape, email conservé et modifiable.
function editEmail(): void {
  errors.value = {}
  generalError.value = null
  infoMessage.value = null
  code.value = ''
  step.value = 'email'
}

// `text-base` sur téléphone : en dessous de 16 px, Safari iOS zoome sur le
// champ à chaque saisie (même réglage que l'inscription).
const inputClass =
  'mt-1 w-full rounded-md border border-slate-300 px-3 py-2.5 text-base focus:border-slate-500 focus:outline-none sm:py-2 sm:text-sm'
</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-10 sm:items-center">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
      <RouterLink :to="{ name: 'login' }" class="text-sm text-slate-500 hover:text-slate-700">
        ← Retour à la connexion
      </RouterLink>
      <h1 class="mt-3 text-xl font-semibold text-slate-900">Mot de passe oublié</h1>

      <!-- Étape 1 : email. -->
      <template v-if="step === 'email'">
        <p class="mt-1 text-sm text-slate-500">
          Saisissez l'adresse email de votre compte : nous vous enverrons un code pour choisir un
          nouveau mot de passe.
        </p>

        <form class="mt-6 space-y-4" novalidate @submit.prevent="requestCode">
          <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              required
              :class="inputClass"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
          </div>

          <p v-if="generalError" class="text-sm text-red-600">{{ generalError }}</p>

          <AppButton type="submit" :loading="isRequesting" class="w-full">
            Recevoir un code
          </AppButton>
        </form>
      </template>

      <!-- Trop d'essais ou code expiré : le formulaire est masqué. -->
      <template v-else-if="step === 'blocked'">
        <p class="mt-3 text-sm text-red-600">{{ blockedMessage }}</p>
        <p v-if="generalError" class="mt-3 text-sm text-red-600">{{ generalError }}</p>
        <div class="mt-6 space-y-3">
          <AppButton
            class="w-full"
            :loading="isRequesting"
            :disabled="secondsLeft > 0"
            @click="requestCode"
          >
            {{
              secondsLeft > 0
                ? `Nouvel envoi possible dans ${secondsLeft} s`
                : 'Recevoir un nouveau code'
            }}
          </AppButton>
          <AppButton variant="secondary" class="w-full" @click="editEmail">
            Modifier l'email
          </AppButton>
        </div>
      </template>

      <!-- Étape 2 : code et nouveau mot de passe. -->
      <template v-else>
        <p v-if="infoMessage" class="mt-3 text-sm text-slate-600">{{ infoMessage }}</p>
        <p class="mt-1 text-sm text-slate-500">
          Adresse : <span class="font-medium text-slate-900">{{ email }}</span>
        </p>

        <form class="mt-6 space-y-4" novalidate @submit.prevent="handleReset">
          <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
            <CodeInput id="code" v-model="code" />
            <p v-if="errors.code" class="mt-1 text-sm text-red-600">{{ errors.code }}</p>
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-slate-700">
              Nouveau mot de passe
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

          <AppButton type="submit" :loading="isResetting" class="w-full">
            Changer mon mot de passe
          </AppButton>
        </form>

        <div class="mt-6 space-y-2 text-sm">
          <p>
            <button
              type="button"
              class="text-slate-900 underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
              :disabled="secondsLeft > 0 || isRequesting"
              @click="requestCode"
            >
              Je n'ai pas reçu le code
            </button>
            <span v-if="secondsLeft > 0" class="ml-1 text-slate-500">
              (nouvel envoi possible dans {{ secondsLeft }} s)
            </span>
          </p>
          <p class="text-slate-500">
            Adresse incorrecte ?
            <button type="button" class="text-slate-900 underline" @click="editEmail">
              Modifier l'email
            </button>
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
