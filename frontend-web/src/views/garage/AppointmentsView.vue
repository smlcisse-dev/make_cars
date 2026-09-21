<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchAppointments } from '@/api/appointments'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Appointment, AppointmentStatus } from '@/types/appointment'
import { appointmentStatusLabel, appointmentStatusTone } from '@/utils/appointmentStatus'
import { extractApiErrorMessage } from '@/utils/apiError'

const STATUS_FILTERS: AppointmentStatus[] = ['pending', 'confirmed', 'rescheduled', 'rejected', 'cancelled', 'completed']

const columns: TableColumn[] = [
  { key: 'client', label: 'Client' },
  { key: 'service', label: 'Service' },
  { key: 'requested_at', label: 'Date demandée' },
  { key: 'status', label: 'Statut' },
]

const router = useRouter()
const route = useRoute()

const statusFilter = ref<AppointmentStatus>('pending')
const appointments = ref<Appointment[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const flashMessage = ref<string | null>(null)

async function loadAppointments(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchAppointments({ status: statusFilter.value, page: currentPage.value })
    appointments.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les rendez-vous. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: AppointmentStatus): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadAppointments()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadAppointments()
}

function goToDetail(appointment: Appointment): void {
  router.push({ name: 'garage.appointments.show', params: { id: appointment.id } })
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    router.replace({ query: {} })
  }

  loadAppointments()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Rendez-vous</h2>
      <p class="mt-1 text-sm text-slate-500">Demandes de rendez-vous des automobilistes pour votre garage.</p>
    </div>

    <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ flashMessage }}
    </p>

    <div class="flex flex-wrap gap-2">
      <AppButton
        v-for="status in STATUS_FILTERS"
        :key="status"
        :variant="statusFilter === status ? 'primary' : 'secondary'"
        @click="selectStatus(status)"
      >
        {{ appointmentStatusLabel(status) }}
      </AppButton>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="appointments" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun rendez-vous « {{ appointmentStatusLabel(statusFilter).toLowerCase() }} ».</template>
        <template #cell-client="{ item }">
          {{ item.user.name }}
        </template>
        <template #cell-service="{ item }">
          <span v-if="item.repair_service">{{ item.repair_service.name }}</span>
          <span v-else class="text-slate-400">Description libre</span>
        </template>
        <template #cell-requested_at="{ item }">
          {{ formatDateTime(item.requested_at) }}
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="appointmentStatusLabel(item.status)" :tone="appointmentStatusTone(item.status)" />
        </template>
      </AppTable>

      <AppPagination
        v-if="appointments.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
