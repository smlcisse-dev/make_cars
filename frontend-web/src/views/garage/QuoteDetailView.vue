<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import {
  abandonQuote,
  createNextQuoteVersion,
  fetchQuote,
  fetchQuoteVersionPdfBlob,
  markQuotePaid,
  sendQuoteVersion,
  startQuote,
  updateQuoteVersionLines,
} from '@/api/quotes'
import AppButton from '@/shared/components/AppButton.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Quote, QuoteLine, QuoteLineInput, QuoteVersion } from '@/types/quote'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'
import { documentTypeLabel, lineTypeLabel, quoteStatusLabel, quoteStatusTone } from '@/utils/quoteStatus'
import QuoteLineEditor from '@/views/garage/QuoteLineEditor.vue'

const route = useRoute()
const router = useRouter()

const quoteId = Number(route.params.id)

const quote = ref<Quote | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
const flashMessage = ref<string | null>(null)
const busyAction = ref<string | null>(null)
const pdfBusyVersionId = ref<number | null>(null)

// Lignes en cours d'édition (brouillon ou nouvelle version) et validité
// signalée par l'éditeur.
const editLines = ref<QuoteLineInput[]>([])
const editValid = ref(false)
const savedSnapshot = ref('[]')

// `computed` : valeur dérivée, recalculée automatiquement quand `quote`
// change — on ne la met jamais à jour à la main.
const versions = computed(() => [...(quote.value?.versions ?? [])].sort((a, b) => a.version - b.version))
const lastVersion = computed<QuoteVersion | null>(() => versions.value.at(-1) ?? null)

// Une dernière version non envoyée est un brouillon éditable, que le devis
// soit `draft` (1re version) ou `rejected` (nouvelle version après refus,
// pas encore envoyée — le statut ne passe à `negotiating` qu'à l'envoi).
const hasDraftVersion = computed(() => lastVersion.value !== null && !lastVersion.value.is_sent)
const canProposeNewVersion = computed(() => quote.value?.status === 'rejected' && !hasDraftVersion.value)
const isDirty = computed(() => JSON.stringify(editLines.value) !== savedSnapshot.value)

const statusMessage = computed(() => {
  switch (quote.value?.status) {
    case 'sent':
    case 'negotiating':
      return 'En attente de la décision du client.'
    case 'invoiced':
      return 'Prestation payée : la facture a été générée.'
    case 'abandoned':
      return 'Négociation abandonnée : aucune prestation ni facture.'
    default:
      return null
  }
})

// Les lignes catalogue sont ré-envoyées par identifiant (le backend relit
// prix et libellé). Une ligne dont le service/produit a été supprimé du
// catalogue n'a plus d'identifiant : elle ne peut pas être reprise.
function lineToInput(line: QuoteLine): QuoteLineInput | null {
  if (line.type === 'diagnosis_fee') {
    return { type: 'diagnosis_fee', label: line.label, unit_price: Number(line.unit_price), quantity: line.quantity }
  }
  if (line.type === 'service' && line.repair_service_id !== null) {
    return { type: 'service', repair_service_id: line.repair_service_id, quantity: line.quantity }
  }
  if (line.type === 'product' && line.product_id !== null) {
    return { type: 'product', product_id: line.product_id, quantity: line.quantity }
  }
  return null
}

// À appeler après chaque chargement : réaligne l'éditeur sur les lignes
// enregistrées du brouillon (vide pour une nouvelle version).
function resetEditor(): void {
  const draft = hasDraftVersion.value ? lastVersion.value : null
  editLines.value = (draft?.lines ?? []).map(lineToInput).filter((line): line is QuoteLineInput => line !== null)
  savedSnapshot.value = JSON.stringify(editLines.value)
  editValid.value = editLines.value.length > 0
}

async function loadQuote(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    quote.value = await fetchQuote(quoteId)
    resetEditor()
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger ce devis. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    router.replace({ query: {} })
  }

  loadQuote()
})

// Toute action réussie recharge le devis complet plutôt que de fusionner sa
// réponse dans l'état local (versions/lignes imbriquées).
async function runAction(name: string, action: () => Promise<unknown>, success: string): Promise<void> {
  busyAction.value = name
  actionErrorMessage.value = null

  try {
    await action()
    flashMessage.value = success
    await loadQuote()
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'L\'action a échoué. Réessayez.')
  } finally {
    busyAction.value = null
  }
}

function handleSaveDraft(): Promise<void> {
  return runAction(
    'save',
    () => updateQuoteVersionLines(quoteId, lastVersion.value!.id, editLines.value),
    'Brouillon enregistré.',
  )
}

function handleSend(): Promise<void> {
  return runAction('send', () => sendQuoteVersion(quoteId, lastVersion.value!.id), 'Devis envoyé au client.')
}

function handleCreateNextVersion(): Promise<void> {
  return runAction(
    'next',
    () => createNextQuoteVersion(quoteId, editLines.value),
    'Nouvelle version créée en brouillon : envoyez-la au client quand elle est prête.',
  )
}

function handleStart(): Promise<void> {
  return runAction('start', () => startQuote(quoteId), 'Prestation démarrée.')
}

function handleMarkPaid(): Promise<void> {
  if (!window.confirm('Marquer ce devis comme payé ? La facture sera générée, cette action est irréversible.')) {
    return Promise.resolve()
  }
  return runAction('paid', () => markQuotePaid(quoteId), 'Paiement enregistré, facture générée.')
}

function handleAbandon(): Promise<void> {
  if (!window.confirm('Abandonner ce devis ? Aucune prestation ni facture ne sera créée.')) {
    return Promise.resolve()
  }
  return runAction('abandon', () => abandonQuote(quoteId), 'Devis abandonné.')
}

// Fichier privé : blob authentifié → URL locale temporaire → nouvel onglet,
// comme pour les justificatifs d'inscription côté Admin.
async function openPdf(version: QuoteVersion): Promise<void> {
  pdfBusyVersionId.value = version.id
  actionErrorMessage.value = null

  try {
    const blob = await fetchQuoteVersionPdfBlob(quoteId, version.id)
    const objectUrl = URL.createObjectURL(blob)
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Impossible de récupérer ce PDF.')
  } finally {
    pdfBusyVersionId.value = null
  }
}

function decisionLabel(version: QuoteVersion): string {
  if (!version.is_sent) {
    return 'Non envoyé'
  }
  if (version.decision === 'accepted') {
    return 'Accepté par le client'
  }
  if (version.decision === 'rejected') {
    return 'Refusé par le client'
  }
  return version.document_type === 'invoice' ? 'Envoyée' : 'En attente de réponse'
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="router.push({ name: 'garage.quotes' })">
      ← Retour aux devis
    </button>

    <p v-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>
    <p v-else-if="isLoading && !quote" class="text-sm text-slate-500">Chargement...</p>

    <template v-else-if="quote">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Devis n° {{ quote.id }}</h2>
          <p class="mt-1 text-sm text-slate-600">
            {{ quote.client.name }}<span v-if="quote.client.phone"> — {{ quote.client.phone }}</span>
          </p>
        </div>
        <StatusBadge :label="quoteStatusLabel(quote.status)" :tone="quoteStatusTone(quote.status)" />
      </div>

      <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ flashMessage }}</p>
      <p v-if="actionErrorMessage" class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ actionErrorMessage }}</p>

      <section class="space-y-4">
        <h3 class="text-sm font-medium text-slate-700">Historique des versions</h3>

        <div v-for="version in versions" :key="version.id" class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <span class="text-sm font-medium text-slate-900">
                {{ documentTypeLabel(version.document_type) }} — version {{ version.version }}
              </span>
              <span class="ml-2 text-xs text-slate-500">{{ decisionLabel(version) }}</span>
              <p v-if="version.sent_at" class="text-xs text-slate-400">Envoyé le {{ formatDateTime(version.sent_at) }}</p>
              <p v-if="version.decided_at" class="text-xs text-slate-400">Décision le {{ formatDateTime(version.decided_at) }}</p>
            </div>
            <AppButton
              v-if="version.is_sent"
              variant="secondary"
              :loading="pdfBusyVersionId === version.id"
              @click="openPdf(version)"
            >
              Télécharger le PDF
            </AppButton>
          </div>

          <table v-if="version.lines.length > 0" class="mt-3 min-w-full text-sm">
            <thead>
              <tr class="text-left text-xs uppercase text-slate-500">
                <th class="py-1 pr-3 font-medium">Type</th>
                <th class="py-1 pr-3 font-medium">Libellé</th>
                <th class="py-1 pr-3 text-right font-medium">Prix unitaire</th>
                <th class="py-1 pr-3 text-right font-medium">Qté</th>
                <th class="py-1 text-right font-medium">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="line in version.lines" :key="line.id">
                <td class="py-1.5 pr-3 text-slate-500">{{ lineTypeLabel(line.type) }}</td>
                <td class="py-1.5 pr-3 text-slate-900">{{ line.label }}</td>
                <td class="py-1.5 pr-3 text-right">{{ formatAmount(line.unit_price) }}</td>
                <td class="py-1.5 pr-3 text-right">{{ line.quantity }}</td>
                <td class="py-1.5 text-right">{{ formatAmount(line.line_total) }}</td>
              </tr>
            </tbody>
          </table>
          <p class="mt-3 text-right text-sm font-semibold text-slate-900">Total : {{ formatAmount(version.total) }}</p>
        </div>
      </section>

      <p v-if="statusMessage" class="text-sm text-slate-500">{{ statusMessage }}</p>

      <!-- Brouillon (1re version ou nouvelle version après refus) : édition et envoi séparés. -->
      <section v-if="hasDraftVersion" class="space-y-3">
        <h3 class="text-sm font-medium text-slate-700">Modifier le brouillon (version {{ lastVersion?.version }})</h3>
        <QuoteLineEditor :key="lastVersion?.id" v-model="editLines" @update:valid="editValid = $event" />
        <p v-if="isDirty" class="text-xs text-amber-700">Modifications non enregistrées : enregistrez avant d'envoyer.</p>
        <div class="flex gap-3">
          <AppButton
            variant="secondary"
            :loading="busyAction === 'save'"
            :disabled="!editValid || !isDirty || busyAction !== null"
            @click="handleSaveDraft"
          >
            Enregistrer le brouillon
          </AppButton>
          <AppButton
            :loading="busyAction === 'send'"
            :disabled="isDirty || busyAction !== null"
            @click="handleSend"
          >
            Envoyer au client
          </AppButton>
        </div>
      </section>

      <section v-else-if="canProposeNewVersion" class="space-y-3">
        <h3 class="text-sm font-medium text-slate-700">Le client a refusé ce devis</h3>
        <p class="text-sm text-slate-500">Proposez une nouvelle version, ou abandonnez la négociation.</p>
        <QuoteLineEditor v-model="editLines" @update:valid="editValid = $event" />
        <div class="flex gap-3">
          <AppButton
            :loading="busyAction === 'next'"
            :disabled="!editValid || busyAction !== null"
            @click="handleCreateNextVersion"
          >
            Proposer une nouvelle version
          </AppButton>
          <AppButton variant="danger" :loading="busyAction === 'abandon'" :disabled="busyAction !== null" @click="handleAbandon">
            Abandonner
          </AppButton>
        </div>
      </section>

      <section v-else-if="quote.status === 'accepted'">
        <AppButton :loading="busyAction === 'start'" :disabled="busyAction !== null" @click="handleStart">
          Démarrer la prestation
        </AppButton>
      </section>

      <section v-else-if="quote.status === 'in_progress'">
        <AppButton :loading="busyAction === 'paid'" :disabled="busyAction !== null" @click="handleMarkPaid">
          Marquer comme payé
        </AppButton>
      </section>
    </template>
  </div>
</template>
