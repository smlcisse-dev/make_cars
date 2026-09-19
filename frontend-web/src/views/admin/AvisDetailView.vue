<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchAvis, moderateAvis } from '@/api/avis'
import AppButton from '@/shared/components/AppButton.vue'
import ReasonPromptModal from '@/shared/components/ReasonPromptModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Avis } from '@/types/avis'
import { extractApiErrorMessage } from '@/utils/apiError'
import { avisStatusLabel, avisStatusTone, avisTargetTypeLabel, avisTransactionTypeLabel } from '@/utils/avisStatus'

const route = useRoute()
const router = useRouter()

// `route.params.id` est typé `string | string[]` par vue-router, l'API
// attend un nombre — conversion explicite une fois pour toute la vue.
const avisId = Number(route.params.id)

const avis = ref<Avis | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

const isModerating = ref(false)
const actionErrorMessage = ref<string | null>(null)
const isModerateModalOpen = ref(false)

async function loadAvis(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    avis.value = await fetchAvis(avisId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger cet avis. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadAvis)

function backToList(flash?: string): void {
  router.push({ name: 'admin.avis', query: flash ? { flash } : {} })
}

async function handleModerate(reason: string): Promise<void> {
  if (!avis.value) {
    return
  }

  isModerating.value = true
  actionErrorMessage.value = null

  try {
    await moderateAvis(avis.value.id, reason)
    backToList(`Avis de ${avis.value.client.name} masqué.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Le masquage a échoué. Réessayez.')
    isModerateModalOpen.value = false
  } finally {
    isModerating.value = false
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="backToList()">
      ← Retour aux avis
    </button>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>

    <template v-else-if="avis">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ avis.reviewable.name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ avisTargetTypeLabel(avis.reviewable_type) }}</p>
          </div>
          <StatusBadge :label="avisStatusLabel(avis.status)" :tone="avisStatusTone(avis.status)" />
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Client</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ avis.client.name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Note</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ avis.rating }} / 5</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Transaction d'origine</dt>
            <dd class="mt-1 text-sm text-slate-700">
              {{ avisTransactionTypeLabel(avis.transaction_type) }} #{{ avis.transaction_id }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date de l'avis</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(avis.created_at) }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Commentaire</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ avis.comment ?? 'Aucun commentaire.' }}</dd>
          </div>
          <div v-if="avis.status === 'hidden'" class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif de masquage</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ avis.moderation_reason }}</dd>
            <dd class="mt-1 text-xs text-slate-400">Masqué le {{ avis.moderated_at ? formatDate(avis.moderated_at) : '—' }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="avis.status === 'visible'" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Modération</h3>
        <p class="mt-1 text-sm text-slate-500">
          Masquer un avis abusif ou diffamatoire de la vue publique — action tracée, jamais une suppression.
        </p>
        <p v-if="actionErrorMessage" class="mt-2 text-sm text-rose-600">{{ actionErrorMessage }}</p>
        <div class="mt-3">
          <AppButton variant="danger" @click="isModerateModalOpen = true">Masquer cet avis</AppButton>
        </div>
      </div>
    </template>

    <ReasonPromptModal
      v-if="isModerateModalOpen && avis"
      title="Masquer cet avis"
      :description="`Le motif sera conservé pour audit et ne sera pas visible par ${avis.client.name}.`"
      confirm-label="Masquer"
      :loading="isModerating"
      @cancel="isModerateModalOpen = false"
      @confirm="handleModerate"
    />
  </div>
</template>
