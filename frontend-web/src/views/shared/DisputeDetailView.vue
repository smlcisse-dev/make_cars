<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchDispute, fetchDisputeAttachmentBlob, respondToDispute } from '@/api/disputes'
import AppButton from '@/shared/components/AppButton.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Dispute } from '@/types/dispute'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import { extractApiErrorMessage } from '@/utils/apiError'
import {
  disputeResolutionActionLabel,
  disputeStatusLabel,
  disputeStatusTone,
  isDisputeOpen,
} from '@/utils/disputeStatus'

// Même écran pour les deux espaces professionnels : `space` choisit le
// préfixe des endpoints et des noms de route (CLAUDE.md §4).
const props = defineProps<{ space: ProfessionalSpace }>()

const route = useRoute()
const router = useRouter()

const disputeId = Number(route.params.id)

const dispute = ref<Dispute | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

const canRespond = computed(() => dispute.value !== null && isDisputeOpen(dispute.value.status))

async function loadDispute(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    dispute.value = await fetchDispute(props.space, disputeId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger cette réclamation. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

// --- Pièces jointes : blob authentifié (disque privé) ---

const attachmentErrorById = ref<Record<number, string>>({})
const isOpeningAttachmentId = ref<number | null>(null)

async function openAttachment(attachmentId: number): Promise<void> {
  attachmentErrorById.value = { ...attachmentErrorById.value, [attachmentId]: '' }
  isOpeningAttachmentId.value = attachmentId

  try {
    const blob = await fetchDisputeAttachmentBlob(props.space, disputeId, attachmentId)
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

// --- Réponse dans l'espace d'échange ---

const replyBody = ref('')
const isReplying = ref(false)
const replyErrorMessage = ref<string | null>(null)

async function handleReply(): Promise<void> {
  if (!dispute.value || replyBody.value.trim() === '') {
    return
  }

  isReplying.value = true
  replyErrorMessage.value = null

  try {
    const message = await respondToDispute(props.space, disputeId, replyBody.value.trim())
    dispute.value.messages = [...(dispute.value.messages ?? []), message]
    // Une réponse fait passer la réclamation « en instruction » côté backend.
    if (dispute.value.status === 'submitted') {
      dispute.value.status = 'under_review'
    }
    replyBody.value = ''
  } catch (error) {
    replyErrorMessage.value = extractApiErrorMessage(error, "Impossible d'envoyer la réponse. Réessayez.")
  } finally {
    isReplying.value = false
  }
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}

onMounted(loadDispute)
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="router.push({ name: `${space}.disputes` })">
      ← Retour aux réclamations
    </button>

    <p v-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>
    <p v-else-if="isLoading && !dispute" class="text-sm text-slate-500">Chargement...</p>

    <template v-else-if="dispute">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Réclamation n° {{ dispute.id }}</h2>
          <p class="mt-1 text-sm text-slate-600">{{ dispute.client.name }}</p>
          <p class="text-xs text-slate-400">Déposée le {{ formatDateTime(dispute.created_at) }}</p>
        </div>
        <StatusBadge :label="disputeStatusLabel(dispute.status)" :tone="disputeStatusTone(dispute.status)" />
      </div>

      <section class="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-slate-900">Motif</h3>
        <p class="whitespace-pre-line text-sm text-slate-800">{{ dispute.reason }}</p>
        <p class="text-xs text-slate-500">
          Transaction concernée : {{ dispute.transaction_type === 'quote' ? 'devis' : 'commande' }} n°
          {{ dispute.transaction_id }}
        </p>
      </section>

      <section v-if="(dispute.attachments ?? []).length > 0" class="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-slate-900">Photos jointes</h3>
        <div class="flex flex-wrap gap-2">
          <div v-for="(attachment, index) in dispute.attachments ?? []" :key="attachment.id">
            <AppButton
              variant="secondary"
              :loading="isOpeningAttachmentId === attachment.id"
              @click="openAttachment(attachment.id)"
            >
              Photo {{ index + 1 }}
            </AppButton>
            <p v-if="attachmentErrorById[attachment.id]" class="mt-1 text-xs text-rose-600">
              {{ attachmentErrorById[attachment.id] }}
            </p>
          </div>
        </div>
      </section>

      <section
        v-if="dispute.status === 'resolved_founded' || dispute.status === 'resolved_rejected' || dispute.status === 'closed'"
        class="space-y-1 rounded-lg border border-slate-200 bg-white p-4"
      >
        <h3 class="text-sm font-semibold text-slate-900">Décision de l'administrateur</h3>
        <p v-if="dispute.resolution_action" class="text-sm text-slate-800">
          Action : {{ disputeResolutionActionLabel(dispute.resolution_action) }}
        </p>
        <p v-if="dispute.resolution_reason" class="whitespace-pre-line text-sm text-slate-800">
          {{ dispute.resolution_reason }}
        </p>
        <p v-if="dispute.decided_at" class="text-xs text-slate-400">Décidée le {{ formatDateTime(dispute.decided_at) }}</p>
      </section>

      <section class="space-y-3 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="text-sm font-semibold text-slate-900">Espace d'échange avec l'administrateur</h3>

        <p v-if="(dispute.messages ?? []).length === 0" class="text-sm text-slate-500">Aucun message pour l'instant.</p>
        <ul v-else class="space-y-3">
          <li v-for="message in dispute.messages ?? []" :key="message.id" class="rounded-md bg-slate-50 px-3 py-2">
            <p class="text-xs text-slate-500">
              {{ message.author?.name ?? 'Système' }} — {{ formatDateTime(message.created_at) }}
            </p>
            <p class="mt-1 whitespace-pre-line text-sm text-slate-800">{{ message.body }}</p>
          </li>
        </ul>

        <form v-if="canRespond" class="space-y-2" @submit.prevent="handleReply">
          <textarea
            v-model="replyBody"
            rows="3"
            maxlength="2000"
            placeholder="Votre réponse..."
            class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
          />
          <p v-if="replyErrorMessage" class="text-sm text-rose-600">{{ replyErrorMessage }}</p>
          <AppButton type="submit" :loading="isReplying" :disabled="replyBody.trim() === ''">Répondre</AppButton>
        </form>
      </section>
    </template>
  </div>
</template>
