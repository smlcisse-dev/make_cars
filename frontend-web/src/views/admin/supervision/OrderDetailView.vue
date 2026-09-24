<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'

import { fetchSupervisedOrder } from '@/api/supervision'
import DetailField from '@/shared/components/DetailField.vue'
import LineItemsTable from '@/shared/components/LineItemsTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import { orderStatusLabel, orderStatusTone } from '@/utils/orderStatus'
import { STRUCTURE_DETAIL_ROUTES, STRUCTURE_KIND_LABELS, formatDateTime, useDetail } from '@/utils/supervision'

// Fiche d'une commande, en lecture seule : vendeur, client, lignes, total.
const route = useRoute()
const { item: order, isLoading, errorMessage, load } = useDetail(
  () => fetchSupervisedOrder(Number(route.params.id)),
  'Impossible de charger cette commande. Réessayez.',
)

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <RouterLink :to="{ name: 'admin.orders' }" class="text-sm text-slate-500 hover:text-slate-700">
      ← Retour aux commandes
    </RouterLink>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

    <template v-else-if="order">
      <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Commande n° {{ order.id }}</h2>
          <StatusBadge :label="orderStatusLabel(order.status)" :tone="orderStatusTone(order.status)" />
        </div>
        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <DetailField :label="`Vendeur (${STRUCTURE_KIND_LABELS[order.sellable_type].toLowerCase()})`">
            <RouterLink
              :to="{ name: STRUCTURE_DETAIL_ROUTES[order.sellable_type], params: { id: order.sellable_id } }"
              class="font-medium text-slate-900 underline"
            >
              {{ order.seller?.name ?? `n° ${order.sellable_id}` }}
            </RouterLink>
          </DetailField>
          <DetailField label="Client" :value="order.client.name" />
          <DetailField label="Email du client" :value="order.client.email" />
          <DetailField label="Téléphone du client" :value="order.client.phone" />
          <DetailField label="Commandée le" :value="formatDateTime(order.created_at)" />
          <DetailField label="Payée le" :value="order.paid_at ? formatDateTime(order.paid_at) : null" />
        </dl>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-slate-900">Lignes</h3>
        <LineItemsTable class="mt-3" :lines="order.lines" :total="order.total" />
      </section>
    </template>
  </div>
</template>
