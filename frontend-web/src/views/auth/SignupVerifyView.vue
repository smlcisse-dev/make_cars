<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { resendSignupCode, verifySignupCode } from '@/api/signup'
import AppButton from '@/shared/components/AppButton.vue'
import { useSignupStore } from '@/stores/signup'
import type { PendingSignup } from '@/types/signup'
import { extractApiErrorMessage, extractValidationErrors } from '@/utils/apiError'

// Saisie du code reçu par email (CLAUDE.md §5, ajout v0.26). `id` vient de
// l'URL (/inscription/verification/:id, `props: true` dans le routeur) : même
// après un rechargement, qui vide le store (voir stores/signup.ts), la saisie
// du code et le renvoi restent possibles.
const props = defineProps<{ id: string }>()

const CODE_LENGTH = 6

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

// Décompte avant le prochain renvoi possible. `now` est relu chaque seconde :
// `secondsLeft`, qui en dépend, est alors recalculé et l'affichage suit.
const now = ref(Date.now())
const resendAvailableAt = ref<number | null>(
  pending.value ? Date.parse(pending.value.resend_available_at) : null,
)
const secondsLeft = computed(() =>
  resendAvailableAt.value === null
    ? 0
    : Math.max(0, Math.ceil((resendAvailableAt.value - now.value) / 1000)),
)

// `ReturnType<typeof setInterval>` : le type exact renvoyé par setInterval,
// sans avoir à savoir s'il s'agit d'un nombre (navigateur) ou d'un objet.
let timer: ReturnType<typeof setInterval> | undefined
onMounted(() => {
  timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})
// Arrêter le minuteur en quittant la page, sinon il tournerait indéfiniment.
onUnmounted(() => clearInterval(timer))

function errorCode(error: unknown): string | undefined {
  if (!axios.isAxiosError(error)) return undefined
  return (error.response?.data as { code?: string } | undefined)?.code
}

function applyPending(fresh: PendingSignup): void {
  signup.setPending(fresh)
  resendAvailableAt.value = Date.parse(fresh.resend_available_at)
}

async function handleVerify(): Promise<void> {
  codeError.value = null
  infoMessage.value = null
  if (!new RegExp(`^\\d{${CODE_LENGTH}}$`).test(code.value)) {
    codeError.value = `Le code doit comporter ${CODE_LENGTH} chiffres.`
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

    switch (errorCode(error)) {
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

    if (errorCode(error) === 'resend_too_soon' && retryAfter !== undefined) {
      // Cas typique après un rechargement : le store ne connaissait plus le
      // délai, le backend le redonne.
      resendAvailableAt.value = Date.now() + retryAfter * 1000
      infoMessage.value = `Nouvel envoi possible dans ${retryAfter} s.`
    } else if (errorCode(error) === 'verification_not_found') {
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

// Seuls les chiffres sont gardés, au plus 6 (un code collé avec des espaces
// reste utilisable).
function onCodeInput(event: Event): void {
  const input = event.target as HTMLInputElement
  code.value = input.value.replace(/\D/g, '').slice(0, CODE_LENGTH)
  input.value = code.value
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
            <!-- `inputmode="numeric"` : clavier numérique sur téléphone, sans
                 les défauts d'un `type="number"` (flèches, zéros de tête
                 perdus). `autocomplete="one-time-code"` : le téléphone peut
                 proposer de remplir le code tout seul à partir du message
                 reçu. -->
            <input
              id="code"
              :value="code"
              type="text"
              inputmode="numeric"
              autocomplete="one-time-code"
              :maxlength="CODE_LENGTH"
              placeholder="123456"
              class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2.5 text-center font-mono text-xl tracking-[0.5em] focus:border-slate-500 focus:outline-none"
              @input="onCodeInput"
            />
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
