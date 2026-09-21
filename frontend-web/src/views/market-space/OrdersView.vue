<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { fetchMarketSpaceOrders } from '@/api/marketSpaceOrders'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Order } from '@/types/order'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'
import { orderStatusLabel, orderStatusTone } from '@/utils/orderStatus'

const columns: TableColumn[] = [
  { key: 'client', label: 'Client' },
  { key: 'total', label: 'Total' },
  { key: 'status', label: 'Statut' },
  { key: 'created_at', label: 'Date' },
]

const router = useRouter()

const orders = ref<Order[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function loadOrders(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchMarketSpaceOrders(currentPage.value)
    orders.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les commandes. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadOrders()
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(loadOrders)
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Commandes</h2>
      <p class="mt-1 text-sm text-slate-500">
        Achats isolés de pièces passés par les automobilistes depuis votre boutique, sans prestation associée.
      </p>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable
        :items="orders"
        :columns="columns"
        @row-click="(order: Order) => router.push({ name: 'market-space.orders.show', params: { id: order.id } })"
      >
        <template #empty>Aucune commande pour l'instant.</template>
        <template #cell-client="{ item }">{{ item.client?.name }}</template>
        <template #cell-total="{ item }">{{ formatAmount(item.total) }}</template>
        <template #cell-status="{ item }">
          <StatusBadge :label="orderStatusLabel(item.status)" :tone="orderStatusTone(item.status)" />
        </template>
        <template #cell-created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppTable>

      <AppPagination
        v-if="orders.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
