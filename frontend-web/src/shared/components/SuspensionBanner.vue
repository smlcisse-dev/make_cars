<script setup lang="ts">
import { computed, ref } from 'vue'

import { requestReactivation } from '@/api/professionalProfile'
import AppButton from '@/shared/components/AppButton.vue'
import ReasonPromptModal from '@/shared/components/ReasonPromptModal.vue'
import { useAuthStore } from '@/stores/auth'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import { extractApiErrorMessage } from '@/utils/apiError'

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

const isModalOpen = ref(false)
const isSending = ref(false)
const errorMessage = ref<string | null>(null)

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { dateStyle: 'long' })
}

async function send(message: string): Promise<void> {
  if (!space.value) return
  isSending.value = true
  errorMessage.value = null
  try {
    await requestReactivation(space.value, message)
    isModalOpen.value = false
    await auth.refreshUser()
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, "La demande n'a pas pu être envoyée.")
    isModalOpen.value = false
    // Ex. 409 « déjà en attente » : la session était périmée, on la relit.
    await auth.refreshUser().catch(() => undefined)
  } finally {
    isSending.value = false
  }
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
      Demande de réactivation envoyée le {{ formatDate(latest.created_at) }}, en cours d'examen.
    </p>
    <template v-else>
      <p v-if="latest?.status === 'refused'" class="mt-2">
        Votre demande de réactivation a été refusée.
        <span v-if="latest.response_reason">Motif : {{ latest.response_reason }}</span>
      </p>
      <div class="mt-2 flex flex-wrap items-center gap-3">
        <AppButton variant="secondary" @click="isModalOpen = true">
          Demander la réactivation
        </AppButton>
        <span v-if="errorMessage" class="text-red-700">{{ errorMessage }}</span>
      </div>
    </template>

    <ReasonPromptModal
      v-if="isModalOpen"
      title="Demander la réactivation"
      description="L'équipe Make Cars examinera votre demande et décidera de réactiver ou non votre compte."
      field-label="Message"
      placeholder="Expliquez ce que vous avez corrigé"
      confirm-label="Envoyer la demande"
      confirm-variant="primary"
      :loading="isSending"
      @cancel="isModalOpen = false"
      @confirm="send"
    />
  </div>
</template>
