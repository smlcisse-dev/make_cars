<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { approveService, fetchService, rejectService } from '@/api/services'
import AppButton from '@/shared/components/AppButton.vue'
import ReasonPromptModal from '@/shared/components/ReasonPromptModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Service } from '@/types/service'
import { extractApiErrorMessage } from '@/utils/apiError'
import { reviewStatusLabel, reviewStatusTone } from '@/utils/reviewStatus'

const route = useRoute()
const router = useRouter()

// `route.params.id` est typé `string | string[]` par vue-router, l'API
// attend un nombre — conversion explicite une fois pour toute la vue.
const serviceId = Number(route.params.id)

const service = ref<Service | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

const isApproving = ref(false)
const isRejecting = ref(false)
const actionErrorMessage = ref<string | null>(null)
const isRejectModalOpen = ref(false)

async function loadService(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    service.value = await fetchService(serviceId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger ce service. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadService)

function backToList(flash?: string): void {
  router.push({ name: 'admin.services', query: flash ? { flash } : {} })
}

async function handleApprove(): Promise<void> {
  if (!service.value) {
    return
  }

  isApproving.value = true
  actionErrorMessage.value = null

  try {
    const approved = await approveService(service.value.id)
    backToList(`Service "${approved.name}" validé.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, "L'approbation a échoué. Réessayez.")
  } finally {
    isApproving.value = false
  }
}

async function handleReject(reason: string): Promise<void> {
  if (!service.value) {
    return
  }

  isRejecting.value = true
  actionErrorMessage.value = null

  try {
    const rejected = await rejectService(service.value.id, reason)
    backToList(`Service "${rejected.name}" rejeté.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Le rejet a échoué. Réessayez.')
    isRejectModalOpen.value = false
  } finally {
    isRejecting.value = false
  }
}

function formatPrice(price: string): string {
  return `${Number(price).toLocaleString('fr-FR')} FCFA`
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="backToList()">
      ← Retour aux services
    </button>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>

    <template v-else-if="service">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ service.name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ service.garage.name }}</p>
          </div>
          <div class="flex items-center gap-2">
            <StatusBadge v-if="!service.is_active" label="Inactif" tone="neutral" />
            <StatusBadge :label="reviewStatusLabel(service.status)" :tone="reviewStatusTone(service.status)" />
          </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Catégorie</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ service.category_label }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Prix</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatPrice(service.price) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Durée estimée</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ service.duration_minutes }} min</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date de soumission</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(service.created_at) }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ service.description }}</dd>
          </div>
          <div v-if="service.status === 'rejected' && service.rejection_reason" class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif de rejet</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ service.rejection_reason }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="service.status === 'pending'" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Décision</h3>
        <p v-if="actionErrorMessage" class="mt-2 text-sm text-rose-600">{{ actionErrorMessage }}</p>
        <div class="mt-3 flex gap-3">
          <AppButton :loading="isApproving" @click="handleApprove">Approuver</AppButton>
          <AppButton variant="danger" @click="isRejectModalOpen = true">Rejeter</AppButton>
        </div>
      </div>
    </template>

    <ReasonPromptModal
      v-if="isRejectModalOpen && service"
      title="Rejeter ce service"
      :description="`Le motif sera conservé et visible par ${service.garage.name}.`"
      confirm-label="Rejeter"
      :loading="isRejecting"
      @cancel="isRejectModalOpen = false"
      @confirm="handleReject"
    />
  </div>
</template>
