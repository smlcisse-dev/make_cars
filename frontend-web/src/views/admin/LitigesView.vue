<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchLitigeList } from '@/api/litiges'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Litige, LitigeStatus } from '@/types/litige'
import { extractApiErrorMessage } from '@/utils/apiError'
import { litigeStatusLabel, litigeStatusTone, litigeTargetTypeLabel } from '@/utils/litigeStatus'

const STATUS_FILTERS: { value: LitigeStatus; label: string }[] = [
  { value: 'submitted', label: 'Déposées' },
  { value: 'under_review', label: 'En instruction' },
  { value: 'resolved_founded', label: 'Fondées' },
  { value: 'resolved_rejected', label: 'Rejetées' },
  { value: 'closed', label: 'Clôturées' },
]

const columns: TableColumn[] = [
  { key: 'respondent', label: 'Cible' },
  { key: 'client', label: 'Client' },
  { key: 'reason', label: 'Motif' },
  { key: 'status', label: 'Statut' },
  { key: 'created_at', label: 'Date' },
]

const router = useRouter()
const route = useRoute()

const statusFilter = ref<LitigeStatus>('submitted')
const disputes = ref<Litige[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Message de confirmation transmis depuis la vue détail après une décision —
// même mécanisme que AvisView.vue/RegistrationsView.vue.
const flashMessage = ref<string | null>(null)

async function loadLitigeList(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchLitigeList({ status: statusFilter.value, page: currentPage.value })
    disputes.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les réclamations. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: LitigeStatus): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadLitigeList()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadLitigeList()
}

function goToDetail(dispute: Litige): void {
  router.push({ name: 'admin.litiges.show', params: { id: dispute.id } })
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

  loadLitigeList()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Réclamations / Litiges</h2>
      <p class="mt-1 text-sm text-slate-500">
        Contestations d'automobilistes sur une transaction terminée (devis facturé ou commande payée), instruites et
        tranchées avec un motif obligatoire.
      </p>
    </div>

    <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ flashMessage }}
    </p>

    <div class="flex flex-wrap gap-2">
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
      <AppTable :items="disputes" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucune réclamation {{ STATUS_FILTERS.find((f) => f.value === statusFilter)?.label.toLowerCase() }}.</template>
        <template #cell-respondent="{ item }">
          <div>{{ item.respondent.name }}</div>
          <div class="text-xs text-slate-400">{{ litigeTargetTypeLabel(item.respondent_type) }}</div>
        </template>
        <template #cell-client="{ item }">
          {{ item.client.name }}
        </template>
        <template #cell-reason="{ item }">
          <span class="line-clamp-1 max-w-xs text-slate-500">{{ item.reason }}</span>
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="litigeStatusLabel(item.status)" :tone="litigeStatusTone(item.status)" />
        </template>
        <template #cell-created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>
      </AppTable>

      <AppPagination
        v-if="disputes.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
