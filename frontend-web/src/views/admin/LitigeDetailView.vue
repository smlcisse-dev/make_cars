<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import {
  closeLitige,
  fetchLitige,
  fetchLitigeAttachmentBlob,
  fetchLitigeConversation,
  postLitigeMessage,
  rejectLitige,
  requestLitigeResponse,
  resolveLitige,
  type LitigeConversation,
  type LitigeDetail,
} from '@/api/litiges'
import AppButton from '@/shared/components/AppButton.vue'
import BaseModal from '@/shared/components/BaseModal.vue'
import ReasonPromptModal from '@/shared/components/ReasonPromptModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { LitigeResolutionAction } from '@/types/litige'
import { extractApiErrorMessage } from '@/utils/apiError'
import { avisTargetTypeLabel } from '@/utils/avisStatus'
import { litigeResolutionActionLabel, litigeStatusLabel, litigeStatusTone, litigeTargetTypeLabel } from '@/utils/litigeStatus'

const route = useRoute()
const router = useRouter()

// `route.params.id` est typé `string | string[]` par vue-router, l'API
// attend un nombre — conversion explicite une fois pour toute la vue.
const disputeId = Number(route.params.id)

const detail = ref<LitigeDetail | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)

// Un dossier tranché (fondé/rejeté) ou clôturé n'accepte plus aucune
// décision ni message — même règle que Dispute::isDecided() côté backend.
const isDecided = computed(
  () => detail.value !== null && ['resolved_founded', 'resolved_rejected', 'closed'].includes(detail.value.dispute.status),
)

// Montant de la transaction contestée : direct pour une commande (Order a un
// total unique), lu sur la dernière version envoyée pour un devis (une seule
// réclamation possible, toujours sur un devis déjà `invoiced` — la dernière
// version en est donc la facture). `null` si l'information n'a pas pu être
// chargée (ex. devis de test sans version, aucune facture réelle générée).
const transactionAmountLabel = computed(() => {
  const transaction = detail.value?.dispute.transaction
  if (!transaction) {
    return null
  }
  const total =
    detail.value?.dispute.transaction_type === 'order'
      ? transaction.total ?? null
      : (transaction.versions ?? []).at(-1)?.total ?? null

  return total !== null ? `${Number(total).toLocaleString('fr-FR')} FCFA` : null
})

async function loadDetail(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    detail.value = await fetchLitige(disputeId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger cette réclamation. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadDetail)

function backToList(flash?: string): void {
  router.push({ name: 'admin.litiges', query: flash ? { flash } : {} })
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}

// --- Demande de réponse au professionnel (message optionnel, une seule fois) ---

const requestMessage = ref('')
const isRequestingResponse = ref(false)

async function handleRequestResponse(): Promise<void> {
  if (!detail.value) {
    return
  }

  isRequestingResponse.value = true
  actionErrorMessage.value = null

  try {
    detail.value.dispute = await requestLitigeResponse(disputeId, requestMessage.value.trim() || undefined)
    requestMessage.value = ''
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La demande a échoué. Réessayez.')
  } finally {
    isRequestingResponse.value = false
  }
}

// --- Espace d'échange : messages libres, répétables des deux côtés ---

const newMessageBody = ref('')
const isSendingMessage = ref(false)

async function handleSendMessage(): Promise<void> {
  if (!detail.value || newMessageBody.value.trim().length === 0) {
    return
  }

  isSendingMessage.value = true
  actionErrorMessage.value = null

  try {
    const message = await postLitigeMessage(disputeId, newMessageBody.value.trim())
    detail.value.dispute.messages = [...detail.value.dispute.messages, message]
    newMessageBody.value = ''
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, "L'envoi du message a échoué. Réessayez.")
  } finally {
    isSendingMessage.value = false
  }
}

// --- Conversation Garage↔Automobiliste liée : résumé déjà chargé, détail à la demande ---

const conversation = ref<LitigeConversation | null>(null)
const isLoadingConversation = ref(false)
const conversationErrorMessage = ref<string | null>(null)

async function loadConversation(): Promise<void> {
  if (!detail.value?.conversation) {
    return
  }

  isLoadingConversation.value = true
  conversationErrorMessage.value = null

  try {
    conversation.value = await fetchLitigeConversation(detail.value.conversation.id)
  } catch (error) {
    conversationErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger la conversation.')
  } finally {
    isLoadingConversation.value = false
  }
}

// --- Pièces jointes : téléchargement authentifié (disque privé) ---

const attachmentErrorById = ref<Record<number, string>>({})
const isOpeningAttachmentId = ref<number | null>(null)

async function openAttachment(attachmentId: number): Promise<void> {
  attachmentErrorById.value = { ...attachmentErrorById.value, [attachmentId]: '' }
  isOpeningAttachmentId.value = attachmentId

  try {
    const blob = await fetchLitigeAttachmentBlob(disputeId, attachmentId)
    const objectUrl = URL.createObjectURL(blob)
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    attachmentErrorById.value = {
      ...attachmentErrorById.value,
      [attachmentId]: extractApiErrorMessage(error, 'Impossible de récupérer cette photo.'),
    }
  } finally {
    isOpeningAttachmentId.value = null
  }
}

// --- Décision : rejet (motif seul) ou fondée (motif + action) ---

const isRejectModalOpen = ref(false)
const isRejecting = ref(false)

async function handleReject(reason: string): Promise<void> {
  if (!detail.value) {
    return
  }

  isRejecting.value = true
  actionErrorMessage.value = null

  try {
    await rejectLitige(disputeId, reason)
    backToList('Réclamation rejetée.')
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Le rejet a échoué. Réessayez.')
    isRejectModalOpen.value = false
  } finally {
    isRejecting.value = false
  }
}

const isResolveModalOpen = ref(false)
const isResolving = ref(false)
const resolveReason = ref('')
const resolveAction = ref<LitigeResolutionAction>('warning')
const resolveTouched = ref(false)
const isResolveReasonValid = computed(() => resolveReason.value.trim().length > 0)

function openResolveModal(): void {
  resolveReason.value = ''
  resolveAction.value = 'warning'
  resolveTouched.value = false
  isResolveModalOpen.value = true
}

async function handleResolve(): Promise<void> {
  resolveTouched.value = true
  if (!isResolveReasonValid.value || !detail.value) {
    return
  }

  isResolving.value = true
  actionErrorMessage.value = null

  try {
    await resolveLitige(disputeId, resolveReason.value.trim(), resolveAction.value)
    backToList('Réclamation jugée fondée.')
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La décision a échoué. Réessayez.')
    isResolveModalOpen.value = false
  } finally {
    isResolving.value = false
  }
}

// --- Clôture : effet immédiat, aucun motif requis (pas de modale) ---

const isClosing = ref(false)

async function handleClose(): Promise<void> {
  if (!detail.value) {
    return
  }

  isClosing.value = true
  actionErrorMessage.value = null

  try {
    await closeLitige(disputeId)
    backToList('Réclamation clôturée.')
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La clôture a échoué. Réessayez.')
  } finally {
    isClosing.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="backToList()">
      ← Retour aux réclamations
    </button>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>

    <template v-else-if="detail">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ detail.dispute.respondent.name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ litigeTargetTypeLabel(detail.dispute.respondent_type) }}</p>
          </div>
          <StatusBadge :label="litigeStatusLabel(detail.dispute.status)" :tone="litigeStatusTone(detail.dispute.status)" />
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Client</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ detail.dispute.client.name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Transaction contestée</dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ detail.dispute.transaction_type === 'order' ? 'Commande' : 'Devis' }} #{{ detail.dispute.transaction_id }}
              <span v-if="transactionAmountLabel"> — {{ transactionAmountLabel }}</span>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date de dépôt</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(detail.dispute.created_at) }}</dd>
          </div>
          <div v-if="detail.dispute.response_requested_at">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Réponse demandée le</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(detail.dispute.response_requested_at) }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ detail.dispute.reason }}</dd>
          </div>
          <div v-if="detail.dispute.resolution_reason" class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">
              Décision{{ detail.dispute.decided_by ? ` — ${detail.dispute.decided_by.name}` : '' }}
            </dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ detail.dispute.resolution_action ? litigeResolutionActionLabel(detail.dispute.resolution_action) + ' — ' : '' }}
              {{ detail.dispute.resolution_reason }}
            </dd>
            <dd class="mt-1 text-xs text-slate-400">
              Tranchée le {{ detail.dispute.decided_at ? formatDate(detail.dispute.decided_at) : '—' }}
            </dd>
          </div>
        </dl>
      </div>

      <div v-if="detail.dispute.attachments.length > 0" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Photos jointes</h3>
        <ul class="mt-3 flex flex-wrap gap-3">
          <li v-for="attachment in detail.dispute.attachments" :key="attachment.id">
            <AppButton
              variant="secondary"
              :loading="isOpeningAttachmentId === attachment.id"
              @click="openAttachment(attachment.id)"
            >
              Photo {{ attachment.position + 1 }}
            </AppButton>
            <p v-if="attachmentErrorById[attachment.id]" class="mt-1 text-xs text-rose-600">
              {{ attachmentErrorById[attachment.id] }}
            </p>
          </li>
        </ul>
      </div>

      <div v-if="detail.conversation" class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-slate-900">Conversation liée</h3>
          <AppButton v-if="!conversation" variant="secondary" :loading="isLoadingConversation" @click="loadConversation">
            Charger la conversation
          </AppButton>
        </div>
        <p v-if="conversationErrorMessage" class="mt-2 text-sm text-rose-600">{{ conversationErrorMessage }}</p>

        <ul v-if="conversation" class="mt-3 space-y-3">
          <li v-if="conversation.messages.length === 0" class="text-sm text-slate-500">Aucun message échangé.</li>
          <li v-for="message in conversation.messages" :key="message.id" class="rounded-md bg-slate-50 px-3 py-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
              <span>{{ message.is_system ? 'Message système' : message.sender?.name ?? 'Automobiliste' }}</span>
              <span>{{ formatDate(message.created_at) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-700">{{ message.body ?? (message.has_image ? '[Photo jointe]' : '') }}</p>
          </li>
        </ul>
      </div>

      <div v-if="detail.reviews.length > 0" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">
          Avis reçus par {{ detail.dispute.respondent.name }} ({{ avisTargetTypeLabel(detail.dispute.respondent_type) }})
        </h3>
        <ul class="mt-3 space-y-3">
          <li v-for="review in detail.reviews" :key="review.id" class="rounded-md border border-slate-200 px-3 py-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
              <span>{{ review.client.name }} — {{ review.rating }} / 5</span>
              <span>{{ formatDate(review.created_at) }}</span>
            </div>
            <p v-if="review.comment" class="mt-1 text-sm text-slate-700">{{ review.comment }}</p>
          </li>
        </ul>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Espace d'échange avec le professionnel</h3>
        <p class="mt-1 text-sm text-slate-500">
          L'admin et le professionnel peuvent s'y exprimer à plusieurs reprises avant la décision — jamais le chat
          Garage↔Automobiliste.
        </p>

        <ul class="mt-3 space-y-3">
          <li v-if="detail.dispute.messages.length === 0" class="text-sm text-slate-500">Aucun message pour l'instant.</li>
          <li v-for="message in detail.dispute.messages" :key="message.id" class="rounded-md bg-slate-50 px-3 py-2">
            <div class="flex items-center justify-between text-xs text-slate-400">
              <span>{{ message.author?.name ?? '—' }}</span>
              <span>{{ formatDate(message.created_at) }}</span>
            </div>
            <p class="mt-1 text-sm text-slate-700">{{ message.body }}</p>
          </li>
        </ul>

        <div v-if="!isDecided" class="mt-4">
          <textarea
            v-model="newMessageBody"
            rows="2"
            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
            placeholder="Écrire un message au professionnel..."
          />
          <div class="mt-2 flex justify-end">
            <AppButton
              variant="secondary"
              :loading="isSendingMessage"
              :disabled="newMessageBody.trim().length === 0"
              @click="handleSendMessage"
            >
              Envoyer
            </AppButton>
          </div>
        </div>
      </div>

      <p v-if="actionErrorMessage" class="text-sm text-rose-600">{{ actionErrorMessage }}</p>

      <div v-if="detail.dispute.status === 'submitted'" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Demander une réponse au professionnel</h3>
        <p class="mt-1 text-sm text-slate-500">
          Optionnel — l'admin peut aussi trancher directement si les preuves jointes suffisent.
        </p>
        <textarea
          v-model="requestMessage"
          rows="2"
          class="mt-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
          placeholder="Message optionnel..."
        />
        <div class="mt-2 flex justify-end">
          <AppButton variant="secondary" :loading="isRequestingResponse" @click="handleRequestResponse">
            Demander une réponse
          </AppButton>
        </div>
      </div>

      <div v-if="!isDecided" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Décision</h3>
        <p class="mt-1 text-sm text-slate-500">Motivée et tracée, aucune sanction automatique.</p>
        <div class="mt-3 flex gap-3">
          <AppButton variant="danger" @click="openResolveModal">Juger fondée</AppButton>
          <AppButton variant="secondary" @click="isRejectModalOpen = true">Rejeter</AppButton>
        </div>
      </div>

      <div
        v-else-if="detail.dispute.status === 'resolved_founded' || detail.dispute.status === 'resolved_rejected'"
        class="rounded-lg border border-slate-200 bg-white p-6"
      >
        <h3 class="text-sm font-semibold text-slate-900">Clôture</h3>
        <p class="mt-1 text-sm text-slate-500">Clôture le dossier une fois la décision (et son éventuelle suite) actée.</p>
        <div class="mt-3">
          <AppButton :loading="isClosing" @click="handleClose">Clôturer le dossier</AppButton>
        </div>
      </div>
    </template>

    <ReasonPromptModal
      v-if="isRejectModalOpen && detail"
      title="Rejeter cette réclamation"
      :description="`Le motif sera conservé pour audit et communiqué à ${detail.dispute.client.name}.`"
      confirm-label="Rejeter"
      :loading="isRejecting"
      @cancel="isRejectModalOpen = false"
      @confirm="handleReject"
    />

    <BaseModal v-if="isResolveModalOpen" title="Réclamation jugée fondée" @close="isResolveModalOpen = false">
      <p class="text-sm text-slate-500">
        Choisissez librement la suite selon la gravité — aucune sanction n'est automatique.
      </p>

      <label for="resolve-action-select" class="mt-3 block text-sm font-medium text-slate-700">Action</label>
      <select
        id="resolve-action-select"
        v-model="resolveAction"
        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      >
        <option value="warning">Avertissement</option>
        <option value="suspension">Suspension du compte</option>
      </select>

      <label for="resolve-reason-textarea" class="mt-3 block text-sm font-medium text-slate-700">
        Motif <span class="text-rose-600">*</span>
      </label>
      <textarea
        id="resolve-reason-textarea"
        v-model="resolveReason"
        rows="3"
        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
        placeholder="Expliquez pourquoi cette réclamation est fondée..."
      />
      <p v-if="resolveTouched && !isResolveReasonValid" class="mt-1 text-sm text-rose-600">Le motif est obligatoire.</p>

      <template #footer>
        <AppButton variant="secondary" @click="isResolveModalOpen = false">Annuler</AppButton>
        <AppButton variant="danger" :loading="isResolving" @click="handleResolve">Confirmer</AppButton>
      </template>
    </BaseModal>
  </div>
</template>
