<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

import {
  QUOTE_DECISION_PATH,
  fetchQuoteDecision,
  signedApiUrl,
  submitQuoteDecision,
  type QuoteDecisionChoice,
  type QuoteDecisionDetails,
} from '@/api/emailLinks'
import AppButton from '@/shared/components/AppButton.vue'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'
import { quoteStatusLabel } from '@/utils/quoteStatus'

// Décision d'un devis par un client « compte express », depuis le lien reçu
// par email (CLAUDE.md §5, ajouts v0.9 et v0.30). Page publique, sans
// connexion, pensée d'abord pour le téléphone.
//
// Règle de sécurité : ouvrir cette page ne décide RIEN. Les messageries et
// antivirus ouvrent souvent les liens des emails pour les analyser ; seule
// la lecture (GET) est donc faite au chargement. Le paramètre `choix` de
// l'adresse présélectionne le bouton, mais seul un clic explicite du client
// sur « Confirmer » envoie la décision (POST).

// État de la page. Une union de chaînes (`'loading' | …`) : TypeScript refuse
// toute autre valeur, et le template teste l'état sans risque de faute de frappe.
type PageState = 'loading' | 'invalid' | 'error' | 'ready' | 'done'
const state = ref<PageState>('loading')

const details = ref<QuoteDecisionDetails | null>(null)
const errorMessage = ref<string | null>(null)
const submitError = ref<string | null>(null)
// Message affiché quand le devis avait déjà reçu une décision au moment du clic.
const conflictMessage = ref<string | null>(null)
const isSubmitting = ref(false)

const route = useRoute()

// URL complète de l'API, ou `null` si le lien est absent ou falsifié.
const apiUrl = signedApiUrl(route.query.link, QUOTE_DECISION_PATH)

// `choix` présélectionne l'action ; `null` = aucune, le client choisit ici.
function choiceFromQuery(value: unknown): QuoteDecisionChoice | null {
  if (value === 'accepter') return 'accept'
  if (value === 'refuser') return 'reject'
  return null
}
const choice = ref<QuoteDecisionChoice | null>(choiceFromQuery(route.query.choix))
const submittedChoice = ref<QuoteDecisionChoice | null>(null)

// 403 : signature invalide ou expirée ; 404 : devis ou version introuvable.
function isInvalidLink(error: unknown): boolean {
  return axios.isAxiosError(error) && [403, 404].includes(error.response?.status ?? 0)
}

async function load(): Promise<void> {
  if (!apiUrl) {
    state.value = 'invalid'
    return
  }

  state.value = 'loading'
  errorMessage.value = null
  try {
    details.value = await fetchQuoteDecision(apiUrl)
    state.value = 'ready'
  } catch (error) {
    if (isInvalidLink(error)) {
      state.value = 'invalid'
    } else {
      errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger le devis. Réessayez.')
      state.value = 'error'
    }
  }
}

async function confirm(): Promise<void> {
  if (!apiUrl || !choice.value) return

  submitError.value = null
  isSubmitting.value = true
  try {
    await submitQuoteDecision(apiUrl, choice.value)
    submittedChoice.value = choice.value
    state.value = 'done'
  } catch (error) {
    if (axios.isAxiosError(error) && error.response?.status === 409) {
      // 409 `quote_version_not_decidable` : une décision a déjà été prise
      // (autre onglet, double clic…). On relit le devis pour l'afficher.
      conflictMessage.value = 'Ce devis a déjà reçu une décision.'
      await load()
    } else if (isInvalidLink(error)) {
      state.value = 'invalid'
    } else {
      submitError.value = extractApiErrorMessage(error, "Votre décision n'a pas pu être envoyée. Réessayez.")
    }
  } finally {
    isSubmitting.value = false
  }
}

// `computed` : valeur recalculée automatiquement quand `details` change.
const decidedText = computed(() => {
  const current = details.value
  if (!current) return ''
  if (current.decision === 'accepted') {
    return `Ce devis a été accepté${current.decided_at ? ` le ${formatDate(current.decided_at)}` : ''}.`
  }
  if (current.decision === 'rejected') {
    return `Ce devis a été refusé${current.decided_at ? ` le ${formatDate(current.decided_at)}` : ''}.`
  }
  return `Ce devis n'attend plus de réponse (statut : ${quoteStatusLabel(current.status).toLowerCase()}).`
})

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
}

onMounted(load)
</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-8 sm:items-center sm:py-10">
    <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
      <p class="text-sm font-medium text-slate-500">Make Cars</p>

      <p v-if="state === 'loading'" class="mt-4 text-base text-slate-600">Chargement du devis…</p>

      <template v-else-if="state === 'invalid'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Lien non valide</h1>
        <p class="mt-3 text-base text-slate-600">
          Ce lien a expiré ou n'est plus valide. Contactez le garage pour recevoir un nouveau devis.
        </p>
      </template>

      <template v-else-if="state === 'error'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Devis indisponible</h1>
        <p class="mt-3 text-base text-red-600">{{ errorMessage }}</p>
        <AppButton class="mt-6 w-full py-3 text-base" @click="load">Réessayer</AppButton>
      </template>

      <template v-else-if="state === 'done'">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">Merci</h1>
        <p class="mt-3 text-base text-slate-700">Merci, votre décision a été transmise au garage.</p>
        <p class="mt-2 text-sm text-slate-500">
          {{ submittedChoice === 'accept' ? 'Vous avez accepté ce devis.' : 'Vous avez refusé ce devis.' }}
          Vous pouvez fermer cette page.
        </p>
      </template>

      <template v-else-if="details">
        <h1 class="mt-2 text-xl font-semibold text-slate-900">
          Devis de {{ details.garage_name }}
        </h1>
        <p class="mt-1 text-sm text-slate-500">
          Pour {{ details.client_name }}<template v-if="details.version > 1">
            · version {{ details.version }}</template
          >
        </p>

        <!-- Lignes en liste plutôt qu'en tableau : lisible sur un écran étroit. -->
        <ul class="mt-5 divide-y divide-slate-100 border-y border-slate-100">
          <li v-for="(line, index) in details.lines" :key="index" class="flex gap-3 py-3">
            <div class="min-w-0 flex-1">
              <p class="text-base text-slate-900">{{ line.label }}</p>
              <p class="text-sm text-slate-500">
                {{ line.quantity }} × {{ formatAmount(line.unit_price) }}
              </p>
            </div>
            <p class="shrink-0 text-base font-medium text-slate-900">
              {{ formatAmount(line.line_total) }}
            </p>
          </li>
        </ul>
        <div class="flex items-baseline justify-between py-3">
          <p class="text-base font-semibold text-slate-900">Total</p>
          <p class="text-lg font-semibold text-slate-900">{{ formatAmount(details.total) }}</p>
        </div>
        <p class="text-sm text-slate-500">Le devis complet est joint à l'email que vous avez reçu.</p>

        <!-- Déjà décidé (ou plus en attente) : on affiche l'état, sans bouton. -->
        <div v-if="!details.is_decidable" class="mt-6 rounded-md bg-slate-100 p-4">
          <p v-if="conflictMessage" class="text-sm font-medium text-slate-900">{{ conflictMessage }}</p>
          <p class="text-base text-slate-700">{{ decidedText }}</p>
        </div>

        <!-- Aucun choix transmis par le lien : le client choisit ici. -->
        <div v-else-if="choice === null" class="mt-6 space-y-3">
          <p class="text-base text-slate-700">Quelle est votre réponse ?</p>
          <AppButton class="w-full py-3 text-base" @click="choice = 'accept'">
            Accepter le devis
          </AppButton>
          <AppButton variant="secondary" class="w-full py-3 text-base" @click="choice = 'reject'">
            Refuser le devis
          </AppButton>
        </div>

        <!-- Choix présélectionné : rien n'est envoyé avant ce clic explicite. -->
        <div v-else class="mt-6 space-y-3">
          <p class="text-base text-slate-700">
            {{
              choice === 'accept'
                ? 'Vous êtes sur le point d’accepter ce devis. Le garage pourra alors démarrer la prestation.'
                : 'Vous êtes sur le point de refuser ce devis.'
            }}
          </p>
          <AppButton
            :variant="choice === 'accept' ? 'primary' : 'danger'"
            :loading="isSubmitting"
            class="w-full py-3 text-base"
            @click="confirm"
          >
            {{ choice === 'accept' ? "Confirmer l'acceptation du devis" : 'Confirmer le refus' }}
          </AppButton>
          <p v-if="submitError" class="text-sm text-red-600">{{ submitError }}</p>
          <p class="text-center">
            <button
              type="button"
              class="py-2 text-base text-slate-700 underline disabled:text-slate-400"
              :disabled="isSubmitting"
              @click="choice = choice === 'accept' ? 'reject' : 'accept'"
            >
              {{ choice === 'accept' ? 'Je préfère refuser' : 'Je préfère accepter' }}
            </button>
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
