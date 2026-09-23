<script setup lang="ts">
import { computed, ref } from 'vue'

import { requestReactivation } from '@/api/professionalProfile'
import AppButton from '@/shared/components/AppButton.vue'
import ReactivationRequestModal from '@/shared/components/ReactivationRequestModal.vue'
import { useAuthStore } from '@/stores/auth'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import { extractApiErrorMessage, extractValidationErrors } from '@/utils/apiError'

// Bandeau « compte suspendu » des deux espaces professionnels (CLAUDE.md §5,
// ajout v0.6), avec la demande de réactivation (ajout v0.28) : le
// professionnel explique ce qu'il a corrigé, seul l'administrateur décide.
// Tout est lu dans la session (store `auth`) : après l'envoi, `refreshUser()`
// relit /auth/me et le bandeau change de lui-même (réactivité).
const auth = useAuthStore()

// Espace de l'API déduit du rôle : ce bandeau vit dans DashboardShell, commun
// aux trois espaces, et n'est affiché que pour un professionnel suspendu.
const space = computed<ProfessionalSpace | null>(() => {
  if (auth.user?.role === 'garagiste') return 'garage'
  if (auth.user?.role === 'market_space') return 'market-space'
  return null
})

const latest = computed(() => auth.latestReactivationRequest)
const isPending = computed(() => latest.value?.status === 'pending')
const attachmentCount = computed(() => latest.value?.attachments?.length ?? 0)

const isModalOpen = ref(false)
const isSending = ref(false)
const errorMessage = ref<string | null>(null)
const fieldErrors = ref<Record<string, string>>({})

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { dateStyle: 'long' })
}

async function send(payload: { message: string; files: File[] }): Promise<void> {
  if (!space.value) return
  isSending.value = true
  errorMessage.value = null
  fieldErrors.value = {}
  try {
    await requestReactivation(space.value, payload.message, payload.files)
    isModalOpen.value = false
    await auth.refreshUser()
  } catch (error) {
    // 422 (fichier refusé…) : la fenêtre reste ouverte, les erreurs
    // s'affichent sous les champs et le professionnel corrige sa saisie.
    fieldErrors.value = extractValidationErrors(error)
    if (Object.keys(fieldErrors.value).length) return

    errorMessage.value = extractApiErrorMessage(error, "La demande n'a pas pu être envoyée.")
    isModalOpen.value = false
    // Ex. 409 « déjà en attente » : la session était périmée, on la relit.
    await auth.refreshUser().catch(() => undefined)
  } finally {
    isSending.value = false
  }
}

function openModal(): void {
  fieldErrors.value = {}
  isModalOpen.value = true
}
</script>

<template>
  <div class="border-b border-red-300 bg-red-50 px-6 py-3 text-sm text-red-900">
    <p>
      <span class="font-semibold"
        >Votre compte est suspendu : il n'est plus visible par les automobilistes.</span
      >
      <span v-if="auth.suspensionReason"> Motif : {{ auth.suspensionReason }}</span>
    </p>

    <p v-if="isPending && latest" class="mt-2">
      Demande de réactivation envoyée le {{ formatDate(latest.created_at) }}<span
        v-if="attachmentCount"
      >
        avec {{ attachmentCount }} pièce{{ attachmentCount > 1 ? 's' : '' }} jointe{{
          attachmentCount > 1 ? 's' : ''
        }}</span
      >, en cours d'examen.
    </p>
    <template v-else>
      <p v-if="latest?.status === 'refused'" class="mt-2">
        Votre demande de réactivation a été refusée.
        <span v-if="latest.response_reason">Motif : {{ latest.response_reason }}</span>
      </p>
      <div class="mt-2 flex flex-wrap items-center gap-3">
        <AppButton variant="secondary" @click="openModal">
          Demander la réactivation
        </AppButton>
        <span v-if="errorMessage" class="text-red-700">{{ errorMessage }}</span>
      </div>
    </template>

    <ReactivationRequestModal
      v-if="isModalOpen"
      :loading="isSending"
      :field-errors="fieldErrors"
      @cancel="isModalOpen = false"
      @confirm="send"
    />
  </div>
</template>
