<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import { fetchSupervisedQuotes } from '@/api/supervision'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import FilterButtons from '@/shared/components/FilterButtons.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { QuoteStatus } from '@/types/quote'
import type { SupervisedQuote } from '@/types/supervision'
import { formatAmount } from '@/utils/money'
import { quoteStatusLabel, quoteStatusTone } from '@/utils/quoteStatus'
import { formatDate, usePaginatedList } from '@/utils/supervision'

// Devis et factures de tous les garages, en lecture seule (CLAUDE.md §5
// règle 8). Seul filtre accepté par le backend : `status`.
const STATUSES: QuoteStatus[] = ['draft', 'sent', 'negotiating', 'accepted', 'rejected', 'in_progress', 'invoiced', 'abandoned']
const statusOptions = STATUSES.map((value) => ({ value, label: quoteStatusLabel(value) }))

const columns: TableColumn[] = [
  { key: 'id', label: 'N°' },
  { key: 'garage', label: 'Garage' },
  { key: 'client', label: 'Client' },
  { key: 'status', label: 'Statut' },
  { key: 'amount', label: 'Montant', class: 'whitespace-nowrap text-right' },
  { key: 'created_at', label: 'Créé le', class: 'whitespace-nowrap' },
]

const router = useRouter()
const statusFilter = ref<QuoteStatus | null>(null)

const { items, currentPage, lastPage, isLoading, errorMessage, load } = usePaginatedList(
  (page) => fetchSupervisedQuotes({ page, status: statusFilter.value ?? undefined }),
  'Impossible de charger les devis. Réessayez.',
)

watch(statusFilter, () => load(1))

// Montant de la dernière version (celle qui fait foi).
function latestTotal(quote: SupervisedQuote): string | null {
  const latest = quote.versions.reduce<SupervisedQuote['versions'][number] | null>(
    (current, version) => (current === null || version.version > current.version ? version : current),
    null,
  )
  return latest ? formatAmount(latest.total) : null
}

function goToDetail(quote: SupervisedQuote): void {
  router.push({ name: 'admin.quotes.show', params: { id: quote.id } })
}

onMounted(() => load(1))
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Devis</h2>
      <p class="mt-1 text-sm text-slate-500">Devis et factures des garages, avec leurs versions et les décisions des clients.</p>
    </div>

    <FilterButtons v-model="statusFilter" :options="statusOptions" />

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="items" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun devis.</template>
        <template #cell-garage="{ item }">{{ item.garage.name ?? '—' }}</template>
        <template #cell-client="{ item }">{{ item.client.name }}</template>
        <template #cell-status="{ item }">
          <StatusBadge :label="quoteStatusLabel(item.status)" :tone="quoteStatusTone(item.status)" />
        </template>
        <template #cell-amount="{ item }">{{ latestTotal(item) ?? '—' }}</template>
        <template #cell-created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppTable>

      <AppPagination v-if="items.length > 0" :current-page="currentPage" :last-page="lastPage" @update:current-page="load" />
    </template>
  </div>
</template>
