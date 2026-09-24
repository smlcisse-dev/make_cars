<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchSupervisedAppointments } from '@/api/supervision'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import FilterButtons from '@/shared/components/FilterButtons.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { AppointmentStatus } from '@/types/appointment'
import type { SupervisedAppointment } from '@/types/supervision'
import { appointmentStatusLabel, appointmentStatusTone } from '@/utils/appointmentStatus'
import { formatDate, formatDateTime, usePaginatedList } from '@/utils/supervision'

// Rendez-vous de tous les garages, en lecture seule (CLAUDE.md §5 règle 8).
// Filtres acceptés par le backend : `status` et `garage_id` (ce dernier
// posé par le lien « Voir ses rendez-vous » de la fiche d'un garage).
const STATUSES: AppointmentStatus[] = ['pending', 'confirmed', 'rescheduled', 'rejected', 'cancelled', 'completed']
const statusOptions = STATUSES.map((value) => ({ value, label: appointmentStatusLabel(value) }))

const columns: TableColumn[] = [
  { key: 'garage', label: 'Garage' },
  { key: 'client', label: 'Client' },
  { key: 'requested_at', label: 'Date demandée', class: 'whitespace-nowrap' },
  { key: 'status', label: 'Statut' },
  { key: 'created_at', label: 'Créé le', class: 'whitespace-nowrap' },
]

const route = useRoute()
const router = useRouter()

const statusFilter = ref<AppointmentStatus | null>(null)
// `computed` relu à chaque changement d'URL : retirer le filtre garage
// (lien ci-dessous) met la liste à jour.
const garageId = computed(() => {
  const value = Number(route.query.garage_id)
  return Number.isInteger(value) && value > 0 ? value : null
})

const { items, currentPage, lastPage, isLoading, errorMessage, load } = usePaginatedList(
  (page) =>
    fetchSupervisedAppointments({
      page,
      status: statusFilter.value ?? undefined,
      garage_id: garageId.value ?? undefined,
    }),
  'Impossible de charger les rendez-vous. Réessayez.',
)

// Nom du garage filtré, lu sur la première ligne (le backend ne renvoie
// que ses RDV).
const filteredGarageName = computed(() => (garageId.value ? (items.value[0]?.garage.name ?? null) : null))

watch([statusFilter, garageId], () => load(1))

function goToDetail(appointment: SupervisedAppointment): void {
  router.push({ name: 'admin.appointments.show', params: { id: appointment.id } })
}

onMounted(() => load(1))
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Rendez-vous</h2>
      <p class="mt-1 text-sm text-slate-500">Demandes de rendez-vous des automobilistes auprès des garages.</p>
    </div>

    <p v-if="garageId" class="flex flex-wrap items-center gap-2 rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700">
      Garage : {{ filteredGarageName ?? `n° ${garageId}` }}
      <RouterLink :to="{ name: 'admin.appointments' }" class="font-medium underline">Voir tous les garages</RouterLink>
    </p>

    <FilterButtons v-model="statusFilter" :options="statusOptions" />

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="items" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun rendez-vous.</template>
        <template #cell-garage="{ item }">{{ item.garage.name ?? '—' }}</template>
        <template #cell-client="{ item }">{{ item.user.name }}</template>
        <template #cell-requested_at="{ item }">{{ formatDateTime(item.requested_at) }}</template>
        <template #cell-status="{ item }">
          <StatusBadge :label="appointmentStatusLabel(item.status)" :tone="appointmentStatusTone(item.status)" />
        </template>
        <template #cell-created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppTable>

      <AppPagination v-if="items.length > 0" :current-page="currentPage" :last-page="lastPage" @update:current-page="load" />
    </template>
  </div>
</template>
