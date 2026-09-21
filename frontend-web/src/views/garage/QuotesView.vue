<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchQuotes } from '@/api/quotes'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Quote } from '@/types/quote'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'
import { documentTypeLabel, quoteStatusLabel, quoteStatusTone } from '@/utils/quoteStatus'

const columns: TableColumn[] = [
  { key: 'client', label: 'Client' },
  { key: 'status', label: 'Statut' },
  { key: 'version', label: 'Version courante' },
  { key: 'total', label: 'Total' },
  { key: 'created_at', label: 'Créé le' },
]

const router = useRouter()
const route = useRoute()

const quotes = ref<Quote[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const flashMessage = ref<string | null>(null)

async function loadQuotes(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchQuotes({ page: currentPage.value })
    quotes.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les devis. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadQuotes()
}

// La version courante est la plus récente (numéro le plus élevé).
function currentVersion(quote: Quote) {
  return quote.versions.reduce<Quote['versions'][number] | null>(
    (latest, version) => (latest === null || version.version > latest.version ? version : latest),
    null,
  )
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    router.replace({ query: {} })
  }

  loadQuotes()
})
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Devis et factures</h2>
        <p class="mt-1 text-sm text-slate-500">Devis émis pour vos clients, avec ou sans rendez-vous.</p>
      </div>
      <AppButton @click="router.push({ name: 'garage.quotes.new' })">Nouveau devis</AppButton>
    </div>

    <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ flashMessage }}</p>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable
        :items="quotes"
        :columns="columns"
        @row-click="(quote: Quote) => router.push({ name: 'garage.quotes.show', params: { id: quote.id } })"
      >
        <template #empty>Aucun devis pour l'instant.</template>
        <template #cell-client="{ item }">{{ item.client?.name }}</template>
        <template #cell-status="{ item }">
          <StatusBadge :label="quoteStatusLabel(item.status)" :tone="quoteStatusTone(item.status)" />
        </template>
        <template #cell-version="{ item }">
          <span v-if="currentVersion(item)">
            {{ documentTypeLabel(currentVersion(item)!.document_type) }} v{{ currentVersion(item)!.version }}
          </span>
        </template>
        <template #cell-total="{ item }">
          <span v-if="currentVersion(item)">{{ formatAmount(currentVersion(item)!.total) }}</span>
        </template>
        <template #cell-created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppTable>

      <AppPagination
        v-if="quotes.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
