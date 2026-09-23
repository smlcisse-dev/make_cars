<script setup lang="ts">
import { ref } from 'vue'

import { fetchRegistrationDocumentBlob } from '@/api/registrations'
import AppButton from '@/shared/components/AppButton.vue'
import type { ReactivationRequestAttachment } from '@/types/registration'
import { extractApiErrorMessage } from '@/utils/apiError'

// Pièces jointes d'une demande de réactivation (CLAUDE.md §5, ajout v0.28),
// affichées sur la fiche admin : dans la demande en attente et dans
// l'historique. Fichiers privés : ouverture en blob authentifié, même
// mécanisme que les justificatifs du dossier.
defineProps<{ attachments: ReactivationRequestAttachment[] }>()

const openingId = ref<number | null>(null)
const errorById = ref<Record<number, string>>({})

async function open(attachment: ReactivationRequestAttachment): Promise<void> {
  errorById.value = { ...errorById.value, [attachment.id]: '' }
  openingId.value = attachment.id
  try {
    const blob = await fetchRegistrationDocumentBlob(attachment.download_url)
    const objectUrl = URL.createObjectURL(blob)
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    errorById.value = {
      ...errorById.value,
      [attachment.id]: extractApiErrorMessage(error, 'Impossible de récupérer cette pièce jointe.'),
    }
  } finally {
    openingId.value = null
  }
}
</script>

<template>
  <div v-if="attachments.length" class="mt-3">
    <p class="text-xs font-medium text-slate-600">Pièces jointes ({{ attachments.length }})</p>
    <ul class="mt-1 space-y-1">
      <li
        v-for="attachment in attachments"
        :key="attachment.id"
        class="flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5"
      >
        <span class="truncate text-sm text-slate-700">{{ attachment.original_name }}</span>
        <span class="flex shrink-0 items-center gap-2">
          <span v-if="errorById[attachment.id]" class="text-xs text-rose-600">{{ errorById[attachment.id] }}</span>
          <AppButton variant="secondary" :loading="openingId === attachment.id" @click="open(attachment)">
            Consulter
          </AppButton>
        </span>
      </li>
    </ul>
  </div>
</template>
