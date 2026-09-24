<script setup lang="ts">
import axios from 'axios'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'

import { resendSignupCode, verifySignupCode } from '@/api/signup'
import AppButton from '@/shared/components/AppButton.vue'
import CodeInput from '@/shared/components/CodeInput.vue'
import { useSignupStore } from '@/stores/signup'
import type { PendingSignup } from '@/types/signup'
import {
  extractApiErrorCode,
  extractApiErrorMessage,
  extractValidationErrors,
} from '@/utils/apiError'
import { CODE_FORMAT_ERROR, isWellFormedCode, useResendCountdown } from '@/utils/emailCode'

// Saisie du code reçu par email (CLAUDE.md §5, ajout v0.26). `id` vient de
// l'URL (/inscription/verification/:id, `props: true` dans le routeur) : même
// après un rechargement, qui vide le store (voir stores/signup.ts), la saisie
// du code et le renvoi restent possibles.
const props = defineProps<{ id: string }>()

const signup = useSignupStore()
const router = useRouter()

// Le store ne sert que s'il décrit bien CETTE demande (il peut être vide après
// un rechargement).
const pending = computed(() =>
  signup.pending?.verification_id === props.id ? signup.pending : null,
)
const emailLabel = computed(() => pending.value?.email ?? "l'adresse indiquée")

// Étape affichée : saisie du code, demande bloquée (trop d'essais ou code
// expiré : seuls « nouveau code » et « modifier l'email » restent proposés),
// ou demande introuvable.
type Step = 'code' | 'blocked' | 'not_found'
const step = ref<Step>('code')
const blockedMessage = ref('')

const code = ref('')
const codeError = ref<string | null>(null)
const infoMessage = ref<string | null>(null)
const isVerifying = ref(false)
const isResending = ref(false)

// Décompte avant le prochain renvoi possible (composable commun avec le mot
// de passe oublié, utils/emailCode.ts).
const { secondsLeft, waitUntil, waitSeconds } = useResendCountdown(
  pending.value ? Date.parse(pending.value.resend_available_at) : null,
)

function applyPending(fresh: PendingSignup): void {
  signup.setPending(fresh)
  waitUntil(Date.parse(fresh.resend_available_at))
}

async function handleVerify(): Promise<void> {
  codeError.value = null
  infoMessage.value = null
  if (!isWellFormedCode(code.value)) {
    codeError.value = CODE_FORMAT_ERROR
    return
  }

  isVerifying.value = true
  try {
    await verifySignupCode(props.id, code.value)
    // Compte créé : le mot de passe n'a plus rien à faire en mémoire.
    signup.reset()
    await router.push({ name: 'signup.done' })
  } catch (error) {
    const data = axios.isAxiosError(error)
      ? (error.response?.data as { remaining_attempts?: number } | undefined)
      : undefined

    switch (extractApiErrorCode(error)) {
      case 'verification_code_invalid':
        codeError.value = `Code incorrect. Il vous reste ${data?.remaining_attempts ?? 0} essai(s).`
        break
      case 'verification_locked':
        step.value = 'blocked'
        blockedMessage.value =
          "Trop d'essais. Demandez un nouveau code, ou modifiez votre email s'il est incorrect."
        break
      case 'verification_expired':
        step.value = 'blocked'
        blockedMessage.value = 'Ce code a expiré. Demandez-en un nouveau.'
        break
      case 'verification_not_found':
        step.value = 'not_found'
        break
      default: {
        // Erreur de validation : code mal formé, ou email/téléphone pris
        // entre-temps par un autre compte.
        const fieldErrors = extractValidationErrors(error)
        if (fieldErrors.code) {
          codeError.value = fieldErrors.code
        } else if (fieldErrors.email || fieldErrors.phone) {
          codeError.value = `${fieldErrors.email ?? fieldErrors.phone} Modifiez vos informations.`
        } else if (axios.isAxiosError(error) && error.response?.status === 429) {
          codeError.value = 'Trop de tentatives. Réessayez dans une minute.'
        } else {
          codeError.value = extractApiErrorMessage(error, 'Une erreur est survenue. Réessayez.')
        }
      }
    }
  } finally {
    isVerifying.value = false
  }
}

async function handleResend(): Promise<void> {
  codeError.value = null
  infoMessage.value = null
  isResending.value = true
  try {
    applyPending(await resendSignupCode(props.id))
    // Nouveau code : le compteur d'essais repart à zéro côté backend, le
    // formulaire réapparaît.
    code.value = ''
    step.value = 'code'
    infoMessage.value = 'Un nouveau code a été envoyé.'
  } catch (error) {
    const retryAfter = axios.isAxiosError(error)
      ? (error.response?.data as { retry_after_seconds?: number } | undefined)?.retry_after_seconds
      : undefined

    if (extractApiErrorCode(error) === 'resend_too_soon' && retryAfter !== undefined) {
      // Cas typique après un rechargement : le store ne connaissait plus le
      // délai, le backend le redonne.
      waitSeconds(retryAfter)
      infoMessage.value = `Nouvel envoi possible dans ${retryAfter} s.`
    } else if (extractApiErrorCode(error) === 'verification_not_found') {
      step.value = 'not_found'
    } else if (axios.isAxiosError(error) && error.response?.status === 429) {
      infoMessage.value = 'Trop de tentatives. Réessayez dans une minute.'
    } else {
      infoMessage.value = extractApiErrorMessage(error, "Le code n'a pas pu être renvoyé.")
    }
  } finally {
    isResending.value = false
  }
}

// Retour au formulaire du bon type, champs conservés, curseur dans le champ
// email. Après un rechargement, le type n'est plus connu : retour à la page
// de choix, formulaire vide (mot de passe à retaper, c'est voulu).
function editEmail(): void {
  if (signup.accountType === 'garagiste') {
    router.push({ name: 'signup.garagiste', query: { focus: 'email' } })
  } else if (signup.accountType === 'market_space') {
    router.push({ name: 'signup.market-space', query: { focus: 'email' } })
  } else {
    router.push({ name: 'signup' })
  }
}

</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-10 sm:items-center">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
      <h1 class="text-xl font-semibold text-slate-900">Vérifiez votre email</h1>

      <!-- Demande introuvable (déjà utilisée, ou de plus de 24 h). -->
      <template v-if="step === 'not_found'">
        <p class="mt-3 text-sm text-slate-600">
          Cette demande n'existe plus ou a expiré. Recommencez votre inscription.
        </p>
        <RouterLink
          :to="{ name: 'signup' }"
          class="mt-6 block w-full rounded-md bg-slate-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-slate-800"
        >
          Recommencer l'inscription
        </RouterLink>
      </template>

      <!-- Trop d'essais ou code expiré : le formulaire est masqué. -->
      <template v-else-if="step === 'blocked'">
        <p class="mt-3 text-sm text-red-600">{{ blockedMessage }}</p>
        <p v-if="infoMessage" class="mt-3 text-sm text-slate-600">{{ infoMessage }}</p>
        <div class="mt-6 space-y-3">
          <AppButton
            class="w-full"
            :loading="isResending"
            :disabled="secondsLeft > 0"
            @click="handleResend"
          >
            {{
              secondsLeft > 0 ? `Nouvel envoi possible dans ${secondsLeft} s` : 'Recevoir un nouveau code'
            }}
          </AppButton>
          <AppButton variant="secondary" class="w-full" @click="editEmail">
            Modifier l'email
          </AppButton>
        </div>
      </template>

      <template v-else>
        <p class="mt-3 text-sm text-slate-600">
          Nous avons envoyé un code à 6 chiffres à
          <span class="font-medium text-slate-900">{{ emailLabel }}</span>. Il est valable 15
          minutes.
        </p>

        <form class="mt-6 space-y-4" novalidate @submit.prevent="handleVerify">
          <div>
            <label for="code" class="block text-sm font-medium text-slate-700">Code</label>
            <CodeInput id="code" v-model="code" />
            <p v-if="codeError" class="mt-1 text-sm text-red-600">{{ codeError }}</p>
            <p v-else-if="infoMessage" class="mt-1 text-sm text-slate-600">{{ infoMessage }}</p>
          </div>

          <AppButton type="submit" :loading="isVerifying" class="w-full">Vérifier</AppButton>
        </form>

        <div class="mt-6 space-y-2 text-sm">
          <p>
            <button
              type="button"
              class="text-slate-900 underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline"
              :disabled="secondsLeft > 0 || isResending"
              @click="handleResend"
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
