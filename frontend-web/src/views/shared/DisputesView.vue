<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { fetchDisputes } from '@/api/disputes'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Dispute, DisputeStatus } from '@/types/dispute'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import { extractApiErrorMessage } from '@/utils/apiError'
import { disputeStatusLabel, disputeStatusTone } from '@/utils/disputeStatus'

// Même écran pour les deux espaces professionnels : `space` choisit le
// préfixe des endpoints et des noms de route (CLAUDE.md §4).
const props = defineProps<{ space: ProfessionalSpace }>()

// Phrase d'introduction propre à chaque espace. `Record<K, V>` (TypeScript) est
// un objet dont les clés sont exactement les valeurs de K, chacune associée à
// une valeur de type V : si un troisième espace était ajouté à
// ProfessionalSpace, la compilation échouerait tant que son texte manque ici.
const INTRO: Record<ProfessionalSpace, string> = {
  garage: "Contestations de clients vous concernant. Vous pouvez répondre ; la décision revient à l'administrateur.",
  'market-space':
    "Contestations de clients concernant votre boutique. Vous pouvez répondre ; la décision revient à l'administrateur.",
}

const STATUS_FILTERS: { value: DisputeStatus | null; label: string }[] = [
  { value: null, label: 'Toutes' },
  { value: 'submitted', label: 'Déposées' },
  { value: 'under_review', label: 'En instruction' },
  { value: 'resolved_founded', label: 'Fondées' },
  { value: 'resolved_rejected', label: 'Rejetées' },
  { value: 'closed', label: 'Clôturées' },
]

const columns: TableColumn[] = [
  { key: 'client', label: 'Client' },
  { key: 'reason', label: 'Motif' },
  { key: 'status', label: 'Statut' },
  { key: 'created_at', label: 'Date' },
]

const router = useRouter()

const disputes = ref<Dispute[]>([])
const statusFilter = ref<DisputeStatus | null>(null)
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function loadDisputes(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchDisputes(props.space, { status: statusFilter.value ?? undefined, page: currentPage.value })
    disputes.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les réclamations. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: DisputeStatus | null): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadDisputes()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadDisputes()
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

onMounted(loadDisputes)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Réclamations</h2>
      <p class="mt-1 text-sm text-slate-500">
        {{ INTRO[space] }}
      </p>
    </div>

    <div class="flex flex-wrap gap-2">
      <AppButton
        v-for="filter in STATUS_FILTERS"
        :key="filter.label"
        :variant="statusFilter === filter.value ? 'primary' : 'secondary'"
        @click="selectStatus(filter.value)"
      >
        {{ filter.label }}
      </AppButton>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable
        :items="disputes"
        :columns="columns"
        @row-click="(dispute: Dispute) => router.push({ name: `${space}.disputes.show`, params: { id: dispute.id } })"
      >
        <template #empty>Aucune réclamation.</template>
        <template #cell-client="{ item }">{{ item.client?.name }}</template>
        <template #cell-reason="{ item }">
          <span class="line-clamp-1 max-w-xs text-slate-500">{{ item.reason }}</span>
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="disputeStatusLabel(item.status)" :tone="disputeStatusTone(item.status)" />
        </template>
        <template #cell-created_at="{ item }">{{ formatDate(item.created_at) }}</template>
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
