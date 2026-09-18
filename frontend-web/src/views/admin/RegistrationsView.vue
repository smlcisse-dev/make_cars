<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchRegistrations } from '@/api/registrations'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { ProfessionalRegistration, RegistrationStatus } from '@/types/registration'
import { extractApiErrorMessage } from '@/utils/apiError'
import { registrationStatusTone } from '@/utils/registrationStatusTone'

const STATUS_FILTERS: { value: RegistrationStatus; label: string }[] = [
  { value: 'pending', label: 'En attente' },
  { value: 'approved', label: 'Approuvé' },
  { value: 'rejected', label: 'Rejeté' },
]

// Colonnes déclaratives passées au tableau générique (src/shared/components/
// AppTable.vue) : ce composant n'a besoin que de ça pour afficher l'entête,
// le contenu de chaque cellule étant personnalisé via les slots `cell-*`
// ci-dessous (badge de statut, date formatée...).
const columns: TableColumn[] = [
  { key: 'structure_name', label: 'Structure' },
  { key: 'account_type_label', label: 'Type de compte' },
  { key: 'created_at', label: 'Date de soumission' },
  { key: 'status', label: 'Statut' },
]

const router = useRouter()
const route = useRoute()

const statusFilter = ref<RegistrationStatus>('pending')
const registrations = ref<ProfessionalRegistration[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Message de confirmation transmis depuis la vue détail après une action
// (approbation/rejet) via les query params de la navigation retour — voir
// RegistrationDetailView.vue. Affiché une fois puis nettoyé de l'URL.
const flashMessage = ref<string | null>(null)

async function loadRegistrations(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchRegistrations({ status: statusFilter.value, page: currentPage.value })
    registrations.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les inscriptions. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: RegistrationStatus): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadRegistrations()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadRegistrations()
}

function goToDetail(registration: ProfessionalRegistration): void {
  router.push({ name: 'admin.registrations.show', params: { id: registration.id } })
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    // Nettoie l'URL pour que le message ne réapparaisse pas sur un rechargement
    // ou un retour arrière du navigateur.
    router.replace({ query: {} })
  }

  loadRegistrations()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Inscriptions professionnelles</h2>
      <p class="mt-1 text-sm text-slate-500">
        Dossiers Garagiste et Market Space soumis à validation avant d'être visibles côté mobile.
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
      <AppTable :items="registrations" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun dossier {{ STATUS_FILTERS.find((f) => f.value === statusFilter)?.label.toLowerCase() }}.</template>
        <template #cell-created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="item.status_label" :tone="registrationStatusTone(item.status)" />
        </template>
      </AppTable>

      <AppPagination
        v-if="registrations.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
