<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchServices } from '@/api/services'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Service } from '@/types/service'
import type { ReviewStatus } from '@/types/review'
import { extractApiErrorMessage } from '@/utils/apiError'
import { reviewStatusLabel, reviewStatusTone } from '@/utils/reviewStatus'

const STATUS_FILTERS: { value: ReviewStatus; label: string }[] = [
  { value: 'pending', label: 'En attente' },
  { value: 'approved', label: 'Approuvé' },
  { value: 'rejected', label: 'Rejeté' },
]

const columns: TableColumn[] = [
  { key: 'name', label: 'Service' },
  { key: 'garage', label: 'Garage' },
  { key: 'category_label', label: 'Catégorie' },
  { key: 'price', label: 'Prix' },
  { key: 'status', label: 'Statut' },
]

const router = useRouter()
const route = useRoute()

const statusFilter = ref<ReviewStatus>('pending')
const services = ref<Service[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Message de confirmation transmis depuis la vue détail après une action
// (approbation/rejet) via les query params de la navigation retour — même
// mécanisme que RegistrationsView.vue.
const flashMessage = ref<string | null>(null)

async function loadServices(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchServices({ status: statusFilter.value, page: currentPage.value })
    services.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les services. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: ReviewStatus): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadServices()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadServices()
}

function goToDetail(service: Service): void {
  router.push({ name: 'admin.services.show', params: { id: service.id } })
}

function formatPrice(price: string): string {
  return `${Number(price).toLocaleString('fr-FR')} FCFA`
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    router.replace({ query: {} })
  }

  loadServices()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Services de réparation</h2>
      <p class="mt-1 text-sm text-slate-500">
        Catalogue des garages, soumis à validation avant d'être visible côté mobile.
      </p>
    </div>

    <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ flashMessage }}
    </p>

    <div class="flex gap-2">
      <AppButton
        v-for="filter in STATUS_FILTERS"
        :key="filter.value"
        :variant="statusFilter === filter.value ? 'primary' : 'secondary'"
        @click="selectStatus(filter.value)"
      >
        {{ filter.label }}
      </AppButton>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="services" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun service {{ STATUS_FILTERS.find((f) => f.value === statusFilter)?.label.toLowerCase() }}.</template>
        <template #cell-garage="{ item }">
          {{ item.garage.name }}
        </template>
        <template #cell-price="{ item }">
          {{ formatPrice(item.price) }}
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="reviewStatusLabel(item.status)" :tone="reviewStatusTone(item.status)" />
        </template>
      </AppTable>

      <AppPagination
        v-if="services.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
