<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

import { fetchSupervisedOrders } from '@/api/supervision'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import FilterButtons from '@/shared/components/FilterButtons.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { OrderStatus } from '@/types/order'
import type { SupervisedOrder } from '@/types/supervision'
import { formatAmount } from '@/utils/money'
import { orderStatusLabel, orderStatusTone } from '@/utils/orderStatus'
import { STRUCTURE_KIND_LABELS, formatDate, usePaginatedList } from '@/utils/supervision'

// Commandes (achats isolés de pièces, mini-boutique Garage ou Market Space),
// en lecture seule (CLAUDE.md §5 règle 8). Seul filtre accepté par le
// backend : `status`.
const STATUSES: OrderStatus[] = ['pending', 'paid', 'cancelled']
const statusOptions = STATUSES.map((value) => ({ value, label: orderStatusLabel(value) }))

const columns: TableColumn[] = [
  { key: 'id', label: 'N°' },
  { key: 'seller', label: 'Vendeur' },
  { key: 'client', label: 'Client' },
  { key: 'status', label: 'Statut' },
  { key: 'total', label: 'Total', class: 'whitespace-nowrap text-right' },
  { key: 'created_at', label: 'Date', class: 'whitespace-nowrap' },
]

const router = useRouter()
const statusFilter = ref<OrderStatus | null>(null)

const { items, currentPage, lastPage, isLoading, errorMessage, load } = usePaginatedList(
  (page) => fetchSupervisedOrders({ page, status: statusFilter.value ?? undefined }),
  'Impossible de charger les commandes. Réessayez.',
)

watch(statusFilter, () => load(1))

function goToDetail(order: SupervisedOrder): void {
  router.push({ name: 'admin.orders.show', params: { id: order.id } })
}

onMounted(() => load(1))
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Commandes</h2>
      <p class="mt-1 text-sm text-slate-500">Achats de pièces sans prestation, auprès d'un garage ou d'une boutique.</p>
    </div>

    <FilterButtons v-model="statusFilter" :options="statusOptions" all-label="Toutes" />

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="items" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucune commande.</template>
        <template #cell-seller="{ item }">
          <div>{{ item.seller?.name ?? '—' }}</div>
          <div class="text-xs text-slate-400">{{ STRUCTURE_KIND_LABELS[item.sellable_type] }}</div>
        </template>
        <template #cell-client="{ item }">{{ item.client.name }}</template>
        <template #cell-status="{ item }">
          <StatusBadge :label="orderStatusLabel(item.status)" :tone="orderStatusTone(item.status)" />
        </template>
        <template #cell-total="{ item }">{{ formatAmount(item.total) }}</template>
        <template #cell-created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </AppTable>

      <AppPagination v-if="items.length > 0" :current-page="currentPage" :last-page="lastPage" @update:current-page="load" />
    </template>
  </div>
</template>
