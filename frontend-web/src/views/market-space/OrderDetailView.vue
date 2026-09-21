<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchMarketSpaceOrder, fetchMarketSpaceOrderPdfBlob, markMarketSpaceOrderPaid } from '@/api/marketSpaceOrders'
import AppButton from '@/shared/components/AppButton.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Order } from '@/types/order'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'
import { orderStatusLabel, orderStatusTone } from '@/utils/orderStatus'

const route = useRoute()
const router = useRouter()

const orderId = Number(route.params.id)

const order = ref<Order | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)
const actionErrorMessage = ref<string | null>(null)
const flashMessage = ref<string | null>(null)
const isPaying = ref(false)
const isPdfLoading = ref(false)

async function loadOrder(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    order.value = await fetchMarketSpaceOrder(orderId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger cette commande. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

async function handleMarkPaid(): Promise<void> {
  if (
    !window.confirm(
      'Marquer cette commande comme payée ? Le stock sera décrémenté et la facture générée, cette action est irréversible.',
    )
  ) {
    return
  }

  isPaying.value = true
  actionErrorMessage.value = null

  try {
    await markMarketSpaceOrderPaid(orderId)
    flashMessage.value = 'Paiement enregistré, facture générée.'
    await loadOrder()
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'L\'action a échoué. Réessayez.')
  } finally {
    isPaying.value = false
  }
}

// Fichier privé : blob authentifié → URL locale temporaire → nouvel onglet.
async function openPdf(): Promise<void> {
  isPdfLoading.value = true
  actionErrorMessage.value = null

  try {
    const blob = await fetchMarketSpaceOrderPdfBlob(orderId)
    const objectUrl = URL.createObjectURL(blob)
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Impossible de récupérer cette facture.')
  } finally {
    isPdfLoading.value = false
  }
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}

onMounted(loadOrder)
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="router.push({ name: 'market-space.orders' })">
      ← Retour aux commandes
    </button>

    <p v-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>
    <p v-else-if="isLoading && !order" class="text-sm text-slate-500">Chargement...</p>

    <template v-else-if="order">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">Commande n° {{ order.id }}</h2>
          <p class="mt-1 text-sm text-slate-600">
            {{ order.client.name }}<span v-if="order.client.phone"> — {{ order.client.phone }}</span>
          </p>
          <p class="text-xs text-slate-400">Passée le {{ formatDateTime(order.created_at) }}</p>
          <p v-if="order.paid_at" class="text-xs text-slate-400">Payée le {{ formatDateTime(order.paid_at) }}</p>
        </div>
        <StatusBadge :label="orderStatusLabel(order.status)" :tone="orderStatusTone(order.status)" />
      </div>

      <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ flashMessage }}</p>
      <p v-if="actionErrorMessage" class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ actionErrorMessage }}</p>

      <section class="rounded-lg border border-slate-200 bg-white p-4">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase text-slate-500">
              <th class="py-1 pr-3 font-medium">Type</th>
              <th class="py-1 pr-3 font-medium">Libellé</th>
              <th class="py-1 pr-3 text-right font-medium">Prix unitaire</th>
              <th class="py-1 pr-3 text-right font-medium">Qté</th>
              <th class="py-1 text-right font-medium">Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="line in order.lines" :key="line.id">
              <td class="py-1.5 pr-3 text-slate-500">Pièce</td>
              <td class="py-1.5 pr-3 text-slate-900">{{ line.label }}</td>
              <td class="py-1.5 pr-3 text-right">{{ formatAmount(line.unit_price) }}</td>
              <td class="py-1.5 pr-3 text-right">{{ line.quantity }}</td>
              <td class="py-1.5 text-right">{{ formatAmount(line.line_total) }}</td>
            </tr>
          </tbody>
        </table>
        <p class="mt-3 text-right text-sm font-semibold text-slate-900">Total : {{ formatAmount(order.total) }}</p>
      </section>

      <div class="flex flex-wrap gap-3">
        <AppButton v-if="order.status === 'pending'" :loading="isPaying" @click="handleMarkPaid">
          Marquer comme payé
        </AppButton>
        <AppButton v-if="order.status === 'paid'" variant="secondary" :loading="isPdfLoading" @click="openPdf">
          Télécharger la facture
        </AppButton>
      </div>
    </template>
  </div>
</template>
