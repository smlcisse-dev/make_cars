<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { confirmAppointment, fetchAppointment, rejectAppointment, rescheduleAppointment } from '@/api/appointments'
import AppButton from '@/shared/components/AppButton.vue'
import BaseModal from '@/shared/components/BaseModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Appointment } from '@/types/appointment'
import { appointmentStatusLabel, appointmentStatusTone } from '@/utils/appointmentStatus'
import { extractApiErrorMessage } from '@/utils/apiError'

const route = useRoute()
const router = useRouter()

// `route.params.id` est une chaîne côté vue-router ; l'API attend un nombre.
const appointmentId = Number(route.params.id)

const appointment = ref<Appointment | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)

const isConfirming = ref(false)
const isRejecting = ref(false)
const isRescheduling = ref(false)

const isRejectModalOpen = ref(false)
const rejectReason = ref('')

const isRescheduleModalOpen = ref(false)
const proposedAt = ref('')
const rescheduleTouched = ref(false)

// Seul un RDV `pending` autorise une action du garagiste (CLAUDE.md §5, v0.7).
const canAct = computed(() => appointment.value?.status === 'pending')

// Motif de refus obligatoire (CLAUDE.md §5, v0.24) : validation front miroir
// de la règle backend (`RejectAppointmentRequest`).
const isRejectReasonValid = computed(() => rejectReason.value.trim().length > 0)

// La valeur d'un <input type="datetime-local"> ("2026-09-25T10:00") est
// interprétée par `new Date()` en heure locale du navigateur ; vide → NaN.
const isProposedAtValid = computed(() => {
  const time = new Date(proposedAt.value).getTime()
  return !Number.isNaN(time) && time > Date.now()
})

async function loadAppointment(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    appointment.value = await fetchAppointment(appointmentId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger ce rendez-vous. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadAppointment)

function backToList(flash?: string): void {
  router.push({ name: 'garage.appointments', query: flash ? { flash } : {} })
}

async function handleConfirm(): Promise<void> {
  isConfirming.value = true
  actionErrorMessage.value = null

  try {
    await confirmAppointment(appointmentId)
    backToList('Rendez-vous confirmé.')
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La confirmation a échoué. Réessayez.')
  } finally {
    isConfirming.value = false
  }
}

// Motif obligatoire (CLAUDE.md §5, v0.24) : le bouton "Refuser" de la
// modale est désactivé tant que le champ est vide (cf. `isRejectReasonValid`).
async function handleReject(): Promise<void> {
  isRejecting.value = true
  actionErrorMessage.value = null

  try {
    await rejectAppointment(appointmentId, rejectReason.value.trim())
    backToList('Rendez-vous refusé.')
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Le refus a échoué. Réessayez.')
    isRejectModalOpen.value = false
  } finally {
    isRejecting.value = false
  }
}

async function handleReschedule(): Promise<void> {
  rescheduleTouched.value = true
  if (!isProposedAtValid.value) {
    return
  }

  isRescheduling.value = true
  actionErrorMessage.value = null

  try {
    // toISOString() convertit l'heure locale saisie en UTC, sans ambiguïté
    // de fuseau pour le backend.
    await rescheduleAppointment(appointmentId, new Date(proposedAt.value).toISOString())
    backToList('Nouvelle date proposée au client.')
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'La proposition a échoué. Réessayez.')
    isRescheduleModalOpen.value = false
  } finally {
    isRescheduling.value = false
  }
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="backToList()">
      ← Retour aux rendez-vous
    </button>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>

    <template v-else-if="appointment">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Rendez-vous de {{ appointment.user.name }}</h2>
            <p v-if="appointment.user.phone" class="mt-1 text-sm text-slate-500">{{ appointment.user.phone }}</p>
          </div>
          <StatusBadge
            :label="appointmentStatusLabel(appointment.status)"
            :tone="appointmentStatusTone(appointment.status)"
          />
        </div>

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Service demandé</dt>
            <dd class="mt-1 text-sm text-slate-700">
              <template v-if="appointment.repair_service">
                {{ appointment.repair_service.name }}
                <span class="text-slate-400">({{ appointment.repair_service.category_label }})</span>
              </template>
              <template v-else>Aucun service précis</template>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date demandée</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDateTime(appointment.requested_at) }}</dd>
          </div>
          <div v-if="appointment.description" class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</dt>
            <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ appointment.description }}</dd>
          </div>
          <div v-if="appointment.proposed_at">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date proposée</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDateTime(appointment.proposed_at) }}</dd>
          </div>
          <div v-if="appointment.confirmed_at">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Confirmé le</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDateTime(appointment.confirmed_at) }}</dd>
          </div>
          <div v-if="appointment.status === 'rejected' && appointment.rejection_reason">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif de refus</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ appointment.rejection_reason }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="canAct" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Décision</h3>
        <p v-if="actionErrorMessage" class="mt-2 text-sm text-rose-600">{{ actionErrorMessage }}</p>
        <div class="mt-3 flex flex-wrap gap-3">
          <AppButton :loading="isConfirming" @click="handleConfirm">Confirmer</AppButton>
          <AppButton variant="secondary" @click="isRescheduleModalOpen = true">Proposer une autre date</AppButton>
          <AppButton variant="danger" @click="isRejectModalOpen = true">Refuser</AppButton>
        </div>
      </div>
    </template>

    <BaseModal v-if="isRejectModalOpen" title="Refuser ce rendez-vous" @close="isRejectModalOpen = false">
      <label for="reject-reason-textarea" class="block text-sm font-medium text-slate-700">
        Motif <span class="text-rose-600">*</span>
      </label>
      <textarea
        id="reject-reason-textarea"
        v-model="rejectReason"
        rows="3"
        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
        placeholder="Ex. agenda complet ce jour-là..."
      />

      <template #footer>
        <AppButton variant="secondary" @click="isRejectModalOpen = false">Annuler</AppButton>
        <AppButton variant="danger" :disabled="!isRejectReasonValid" :loading="isRejecting" @click="handleReject">
          Refuser
        </AppButton>
      </template>
    </BaseModal>

    <BaseModal v-if="isRescheduleModalOpen" title="Proposer une autre date" @close="isRescheduleModalOpen = false">
      <p class="text-sm text-slate-500">
        Le client pourra accepter cette date ou annuler. Une seule contre-proposition est possible.
      </p>

      <label for="reschedule-datetime-input" class="mt-3 block text-sm font-medium text-slate-700">
        Nouvelle date et heure <span class="text-rose-600">*</span>
      </label>
      <input
        id="reschedule-datetime-input"
        v-model="proposedAt"
        type="datetime-local"
        class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      />
      <p v-if="rescheduleTouched && !isProposedAtValid" class="mt-1 text-sm text-rose-600">
        Choisissez une date et une heure dans le futur.
      </p>

      <template #footer>
        <AppButton variant="secondary" @click="isRescheduleModalOpen = false">Annuler</AppButton>
        <AppButton :loading="isRescheduling" @click="handleReschedule">Proposer</AppButton>
      </template>
    </BaseModal>
  </div>
</template>
