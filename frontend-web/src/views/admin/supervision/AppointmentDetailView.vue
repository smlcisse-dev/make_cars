<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'

import { fetchSupervisedAppointment } from '@/api/supervision'
import DetailField from '@/shared/components/DetailField.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import { appointmentStatusLabel, appointmentStatusTone } from '@/utils/appointmentStatus'
import { formatAmount } from '@/utils/money'
import { formatDateTime, useDetail } from '@/utils/supervision'

// Fiche d'un rendez-vous, en lecture seule. Le backend ne conserve pas
// d'historique des changements de statut : les dates clés (demande,
// contre-proposition, confirmation) en tiennent lieu.
const route = useRoute()
const { item: appointment, isLoading, errorMessage, load } = useDetail(
  () => fetchSupervisedAppointment(Number(route.params.id)),
  'Impossible de charger ce rendez-vous. Réessayez.',
)

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <RouterLink :to="{ name: 'admin.appointments' }" class="text-sm text-slate-500 hover:text-slate-700">
      ← Retour aux rendez-vous
    </RouterLink>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

    <template v-else-if="appointment">
      <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Rendez-vous n° {{ appointment.id }}</h2>
          <StatusBadge :label="appointmentStatusLabel(appointment.status)" :tone="appointmentStatusTone(appointment.status)" />
        </div>
        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <DetailField label="Garage">
            <RouterLink
              :to="{ name: 'admin.garages.show', params: { id: appointment.garage_id } }"
              class="font-medium text-slate-900 underline"
            >
              {{ appointment.garage.name ?? `Garage n° ${appointment.garage_id}` }}
            </RouterLink>
          </DetailField>
          <DetailField label="Client" :value="appointment.user.name" />
          <DetailField label="Email du client" :value="appointment.user.email" />
          <DetailField label="Téléphone du client" :value="appointment.user.phone" />
        </dl>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-slate-900">Besoin</h3>
        <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <DetailField
            label="Service choisi"
            :value="
              appointment.repair_service
                ? `${appointment.repair_service.name} (${appointment.repair_service.category_label}, ${formatAmount(appointment.repair_service.price)})`
                : 'Aucun (description libre)'
            "
          />
          <div class="sm:col-span-2">
            <DetailField label="Description" :value="appointment.description" />
          </div>
        </dl>
      </section>

      <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-slate-900">Dates</h3>
        <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <DetailField label="Demande envoyée le" :value="formatDateTime(appointment.created_at)" />
          <DetailField label="Date demandée" :value="formatDateTime(appointment.requested_at)" />
          <DetailField
            label="Date proposée par le garage"
            :value="appointment.proposed_at ? formatDateTime(appointment.proposed_at) : null"
          />
          <DetailField label="Date confirmée" :value="appointment.confirmed_at ? formatDateTime(appointment.confirmed_at) : null" />
          <DetailField label="Dernière mise à jour" :value="formatDateTime(appointment.updated_at)" />
          <div v-if="appointment.rejection_reason" class="sm:col-span-2">
            <DetailField label="Motif du refus" :value="appointment.rejection_reason" />
          </div>
        </dl>
      </section>
    </template>
  </div>
</template>
