<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import {
  approveRegistration,
  fetchRegistration,
  fetchRegistrationDocumentBlob,
  reactivateRegistration,
  rejectRegistration,
  suspendRegistration,
} from '@/api/registrations'
import AppButton from '@/shared/components/AppButton.vue'
import ReasonPromptModal from '@/shared/components/ReasonPromptModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { ProfessionalRegistration, RegistrationDocument } from '@/types/registration'
import { extractApiErrorMessage } from '@/utils/apiError'
import { registrationStatusTone } from '@/utils/registrationStatus'

const route = useRoute()
const router = useRouter()

// `route.params.id` est typé `string | string[]` par vue-router (un segment
// d'URL est toujours une chaîne) ; l'API, elle, attend un nombre — d'où la
// conversion explicite ici, une fois pour toute la vue.
const registrationId = Number(route.params.id)

const registration = ref<ProfessionalRegistration | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

const isApproving = ref(false)
const isRejecting = ref(false)
const isSuspending = ref(false)
const isReactivating = ref(false)
const actionErrorMessage = ref<string | null>(null)
const isRejectModalOpen = ref(false)
const isSuspendModalOpen = ref(false)

const documentErrorById = ref<Record<number, string>>({})
const isOpeningDocumentId = ref<number | null>(null)

async function loadRegistration(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    registration.value = await fetchRegistration(registrationId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger ce dossier. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadRegistration)

function backToList(flash?: string): void {
  router.push({ name: 'admin.registrations', query: flash ? { flash } : {} })
}

async function handleApprove(): Promise<void> {
  if (!registration.value) {
    return
  }

  isApproving.value = true
  actionErrorMessage.value = null

  try {
    const approved = await approveRegistration(registration.value.id)
    backToList(`Dossier "${approved.structure_name}" approuvé.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, "L'approbation a échoué. Réessayez.")
  } finally {
    isApproving.value = false
  }
}

async function handleReject(reason: string): Promise<void> {
  if (!registration.value) {
    return
  }

  isRejecting.value = true
  actionErrorMessage.value = null

  try {
    const rejected = await rejectRegistration(registration.value.id, reason)
    backToList(`Dossier "${rejected.structure_name}" rejeté.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Le rejet a échoué. Réessayez.')
    isRejectModalOpen.value = false
  } finally {
    isRejecting.value = false
  }
}

async function handleSuspend(reason: string): Promise<void> {
  if (!registration.value) {
    return
  }

  isSuspending.value = true
  actionErrorMessage.value = null

  try {
    const suspended = await suspendRegistration(registration.value.id, reason)
    backToList(`Compte "${suspended.structure_name}" suspendu.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La suspension a échoué. Réessayez.')
    isSuspendModalOpen.value = false
  } finally {
    isSuspending.value = false
  }
}

// Réactivation : effet immédiat, aucun motif requis — pas de modale, comme
// pour l'approbation (CLAUDE.md §5, ajout v0.6).
async function handleReactivate(): Promise<void> {
  if (!registration.value) {
    return
  }

  isReactivating.value = true
  actionErrorMessage.value = null

  try {
    const reactivated = await reactivateRegistration(registration.value.id)
    backToList(`Compte "${reactivated.structure_name}" réactivé.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La réactivation a échoué. Réessayez.')
  } finally {
    isReactivating.value = false
  }
}

// Consultation d'un justificatif : le fichier est privé, on le récupère en
// "blob" authentifié (voir le commentaire de fetchRegistrationDocumentBlob)
// puis on l'ouvre dans un nouvel onglet via une URL locale temporaire
// (`URL.createObjectURL`), révoquée un peu plus tard pour libérer la mémoire.
async function openDocument(document: RegistrationDocument): Promise<void> {
  documentErrorById.value = { ...documentErrorById.value, [document.id]: '' }
  isOpeningDocumentId.value = document.id

  try {
    const blob = await fetchRegistrationDocumentBlob(document.download_url)
    const objectUrl = URL.createObjectURL(blob)
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    documentErrorById.value = {
      ...documentErrorById.value,
      [document.id]: extractApiErrorMessage(error, 'Impossible de récupérer ce document.'),
    }
  } finally {
    isOpeningDocumentId.value = null
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="backToList()">
      ← Retour aux inscriptions
    </button>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>

    <template v-else-if="registration">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ registration.structure_name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ registration.account_type_label }}</p>
          </div>
          <div class="flex items-center gap-2">
            <StatusBadge :label="registration.status_label" :tone="registrationStatusTone(registration.status)" />
            <StatusBadge v-if="registration.is_suspended" label="Suspendu" tone="danger" />
          </div>
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Adresse</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ registration.address }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">N° IFU-RCCM</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ registration.business_registration_number }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date de soumission</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(registration.created_at) }}</dd>
          </div>
          <div v-if="registration.status === 'rejected' && registration.rejection_reason">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif de rejet</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ registration.rejection_reason }}</dd>
          </div>
          <div v-if="registration.is_suspended && registration.suspension_reason">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif de suspension</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ registration.suspension_reason }}</dd>
          </div>
          <div v-if="registration.is_suspended && registration.suspended_at">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Suspendu depuis le</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(registration.suspended_at) }}</dd>
          </div>
        </dl>
      </div>

      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Justificatifs</h3>
        <ul class="mt-3 space-y-2">
          <li
            v-for="document in registration.documents"
            :key="document.id"
            class="flex items-center justify-between rounded-md border border-slate-200 px-4 py-3"
          >
            <span class="text-sm text-slate-700">{{ document.type_label }}</span>
            <div class="flex items-center gap-3">
              <span v-if="documentErrorById[document.id]" class="text-sm text-rose-600">
                {{ documentErrorById[document.id] }}
              </span>
              <AppButton
                variant="secondary"
                :loading="isOpeningDocumentId === document.id"
                @click="openDocument(document)"
              >
                Consulter
              </AppButton>
            </div>
          </li>
        </ul>
      </div>

      <div v-if="registration.status === 'pending'" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Décision</h3>
        <p v-if="actionErrorMessage" class="mt-2 text-sm text-rose-600">{{ actionErrorMessage }}</p>
        <div class="mt-3 flex gap-3">
          <AppButton :loading="isApproving" @click="handleApprove">Approuver</AppButton>
          <AppButton variant="danger" @click="isRejectModalOpen = true">Rejeter</AppButton>
        </div>
      </div>

      <div v-else-if="registration.status === 'approved'" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Compte</h3>
        <p v-if="actionErrorMessage" class="mt-2 text-sm text-rose-600">{{ actionErrorMessage }}</p>
        <div class="mt-3 flex gap-3">
          <AppButton v-if="!registration.is_suspended" variant="danger" @click="isSuspendModalOpen = true">
            Suspendre
          </AppButton>
          <AppButton v-else :loading="isReactivating" @click="handleReactivate">Réactiver</AppButton>
        </div>
      </div>
    </template>

    <ReasonPromptModal
      v-if="isRejectModalOpen && registration"
      title="Rejeter ce dossier"
      :description="`Le motif sera conservé et visible par ${registration.structure_name}.`"
      confirm-label="Rejeter"
      :loading="isRejecting"
      @cancel="isRejectModalOpen = false"
      @confirm="handleReject"
    />

    <ReasonPromptModal
      v-if="isSuspendModalOpen && registration"
      title="Suspendre ce compte"
      :description="`${registration.structure_name} deviendra invisible côté mobile jusqu'à réactivation. Les données, services/produits et avis sont conservés.`"
      confirm-label="Suspendre"
      :loading="isSuspending"
      @cancel="isSuspendModalOpen = false"
      @confirm="handleSuspend"
    />
  </div>
</template>
