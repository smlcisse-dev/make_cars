<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { approveProduct, fetchProduct, rejectProduct } from '@/api/products'
import AppButton from '@/shared/components/AppButton.vue'
import ReasonPromptModal from '@/shared/components/ReasonPromptModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Product } from '@/types/product'
import { extractApiErrorMessage } from '@/utils/apiError'
import { productSellableTypeLabel } from '@/utils/productSellableType'
import { reviewStatusLabel, reviewStatusTone } from '@/utils/reviewStatus'

const route = useRoute()
const router = useRouter()

// `route.params.id` est typé `string | string[]` par vue-router, l'API
// attend un nombre — conversion explicite une fois pour toute la vue.
const productId = Number(route.params.id)

const product = ref<Product | null>(null)
const isLoading = ref(false)
const loadErrorMessage = ref<string | null>(null)

const isApproving = ref(false)
const isRejecting = ref(false)
const actionErrorMessage = ref<string | null>(null)
const isRejectModalOpen = ref(false)

async function loadProduct(): Promise<void> {
  isLoading.value = true
  loadErrorMessage.value = null

  try {
    product.value = await fetchProduct(productId)
  } catch (error) {
    loadErrorMessage.value = extractApiErrorMessage(error, 'Impossible de charger ce produit. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

onMounted(loadProduct)

function backToList(flash?: string): void {
  router.push({ name: 'admin.products', query: flash ? { flash } : {} })
}

async function handleApprove(): Promise<void> {
  if (!product.value) {
    return
  }

  isApproving.value = true
  actionErrorMessage.value = null

  try {
    const approved = await approveProduct(product.value.id)
    backToList(`Produit "${approved.name}" validé.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, "L'approbation a échoué. Réessayez.")
  } finally {
    isApproving.value = false
  }
}

async function handleReject(reason: string): Promise<void> {
  if (!product.value) {
    return
  }

  isRejecting.value = true
  actionErrorMessage.value = null

  try {
    const rejected = await rejectProduct(product.value.id, reason)
    backToList(`Produit "${rejected.name}" rejeté.`)
  } catch (error) {
    actionErrorMessage.value = extractApiErrorMessage(error, 'Le rejet a échoué. Réessayez.')
    isRejectModalOpen.value = false
  } finally {
    isRejecting.value = false
  }
}

function formatPrice(price: string): string {
  return `${Number(price).toLocaleString('fr-FR')} FCFA`
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}
</script>

<template>
  <div class="space-y-4">
    <button type="button" class="text-sm text-slate-500 hover:text-slate-700" @click="backToList()">
      ← Retour aux produits
    </button>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="loadErrorMessage" class="text-sm text-rose-600">{{ loadErrorMessage }}</p>

    <template v-else-if="product">
      <div class="rounded-lg border border-slate-200 bg-white p-6">
        <div class="flex items-start justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ product.name }}</h2>
            <p class="mt-1 text-sm text-slate-500">
              {{ product.sellable.name }}
              <span class="text-slate-400">· {{ productSellableTypeLabel(product.sellable_type) }}</span>
            </p>
          </div>
          <StatusBadge :label="reviewStatusLabel(product.status)" :tone="reviewStatusTone(product.status)" />
        </div>

        <img
          v-if="product.image_url"
          :src="product.image_url"
          :alt="product.name"
          class="mt-4 h-48 w-full rounded-md object-cover sm:w-64"
        />

        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">SKU</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ product.sku ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Prix</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatPrice(product.price) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Stock</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ product.stock_quantity }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Date de soumission</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ formatDate(product.created_at) }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ product.description }}</dd>
          </div>
          <div v-if="product.status === 'rejected' && product.rejection_reason" class="sm:col-span-2">
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Motif de rejet</dt>
            <dd class="mt-1 text-sm text-slate-700">{{ product.rejection_reason }}</dd>
          </div>
        </dl>
      </div>

      <div v-if="product.status === 'pending'" class="rounded-lg border border-slate-200 bg-white p-6">
        <h3 class="text-sm font-semibold text-slate-900">Décision</h3>
        <p v-if="actionErrorMessage" class="mt-2 text-sm text-rose-600">{{ actionErrorMessage }}</p>
        <div class="mt-3 flex gap-3">
          <AppButton :loading="isApproving" @click="handleApprove">Approuver</AppButton>
          <AppButton variant="danger" @click="isRejectModalOpen = true">Rejeter</AppButton>
        </div>
      </div>
    </template>

    <ReasonPromptModal
      v-if="isRejectModalOpen && product"
      title="Rejeter ce produit"
      :description="`Le motif sera conservé et visible par ${product.sellable.name}.`"
      confirm-label="Rejeter"
      :loading="isRejecting"
      @cancel="isRejectModalOpen = false"
      @confirm="handleReject"
    />
  </div>
</template>
