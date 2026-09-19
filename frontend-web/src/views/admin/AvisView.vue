<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchAvisList } from '@/api/avis'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Avis, AvisStatus } from '@/types/avis'
import { extractApiErrorMessage } from '@/utils/apiError'
import { avisStatusLabel, avisStatusTone, avisTargetTypeLabel } from '@/utils/avisStatus'

const STATUS_FILTERS: { value: AvisStatus; label: string }[] = [
  { value: 'visible', label: 'Visibles' },
  { value: 'hidden', label: 'Masqués' },
]

const columns: TableColumn[] = [
  { key: 'reviewable', label: 'Cible' },
  { key: 'client', label: 'Client' },
  { key: 'rating', label: 'Note' },
  { key: 'comment', label: 'Commentaire' },
  { key: 'status', label: 'Statut' },
  { key: 'created_at', label: 'Date' },
]

const router = useRouter()
const route = useRoute()

const statusFilter = ref<AvisStatus>('visible')
const reviews = ref<Avis[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Message de confirmation transmis depuis la vue détail après un masquage —
// même mécanisme que ServicesView.vue/RegistrationsView.vue.
const flashMessage = ref<string | null>(null)

async function loadAvisList(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchAvisList({ status: statusFilter.value, page: currentPage.value })
    reviews.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les avis. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: AvisStatus): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadAvisList()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadAvisList()
}

function goToDetail(avis: Avis): void {
  router.push({ name: 'admin.avis.show', params: { id: avis.id } })
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    router.replace({ query: {} })
  }

  loadAvisList()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Avis clients</h2>
      <p class="mt-1 text-sm text-slate-500">
        Avis laissés par les automobilistes après une transaction terminée, visibles dès leur création — seul un
        masquage motivé peut les retirer de la vue publique (CLAUDE.md §5, ajout v0.10).
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
      <AppTable :items="reviews" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun avis {{ STATUS_FILTERS.find((f) => f.value === statusFilter)?.label.toLowerCase() }}.</template>
        <template #cell-reviewable="{ item }">
          <div>{{ item.reviewable.name }}</div>
          <div class="text-xs text-slate-400">{{ avisTargetTypeLabel(item.reviewable_type) }}</div>
        </template>
        <template #cell-client="{ item }">
          {{ item.client.name }}
        </template>
        <template #cell-rating="{ item }">{{ item.rating }} / 5</template>
        <template #cell-comment="{ item }">
          <span class="line-clamp-1 max-w-xs text-slate-500">{{ item.comment ?? '—' }}</span>
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="avisStatusLabel(item.status)" :tone="avisStatusTone(item.status)" />
        </template>
        <template #cell-created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>
      </AppTable>

      <AppPagination
        v-if="reviews.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
