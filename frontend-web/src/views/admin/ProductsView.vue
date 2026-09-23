<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { fetchProducts } from '@/api/products'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { Product } from '@/types/product'
import type { ReviewStatus } from '@/types/review'
import { extractApiErrorMessage } from '@/utils/apiError'
import { productSellableTypeLabel } from '@/utils/productSellableType'
import { reviewStatusLabel, reviewStatusTone } from '@/utils/reviewStatus'

const STATUS_FILTERS: { value: ReviewStatus; label: string }[] = [
  { value: 'pending', label: 'En attente' },
  { value: 'approved', label: 'Approuvé' },
  { value: 'rejected', label: 'Rejeté' },
]

const columns: TableColumn[] = [
  { key: 'name', label: 'Produit' },
  { key: 'sellable', label: 'Vendeur' },
  { key: 'sku', label: 'SKU' },
  { key: 'price', label: 'Prix' },
  { key: 'stock_quantity', label: 'Stock' },
  { key: 'status', label: 'Statut' },
]

const router = useRouter()
const route = useRoute()

const statusFilter = ref<ReviewStatus>('pending')
const products = ref<Product[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Message de confirmation transmis depuis la vue détail après une action
// (approbation/rejet) via les query params de la navigation retour — même
// mécanisme que RegistrationsView.vue.
const flashMessage = ref<string | null>(null)

async function loadProducts(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchProducts({ status: statusFilter.value, page: currentPage.value })
    products.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les produits. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function selectStatus(status: ReviewStatus): void {
  if (status === statusFilter.value) {
    return
  }
  statusFilter.value = status
  currentPage.value = 1
  loadProducts()
}

function goToPage(page: number): void {
  currentPage.value = page
  loadProducts()
}

function goToDetail(product: Product): void {
  router.push({ name: 'admin.products.show', params: { id: product.id } })
}

function formatPrice(price: string): string {
  return `${Number(price).toLocaleString('fr-FR')} FCFA`
}

onMounted(() => {
  const flash = route.query.flash
  if (typeof flash === 'string') {
    flashMessage.value = flash
    router.replace({ query: {} })
  }

  loadProducts()
})
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Produits</h2>
      <p class="mt-1 text-sm text-slate-500">
        Mini-boutiques Garage et Market Space confondus, soumis à validation avant d'être visibles côté mobile.
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
      <AppTable :items="products" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucun produit {{ STATUS_FILTERS.find((f) => f.value === statusFilter)?.label.toLowerCase() }}.</template>
        <template #cell-sellable="{ item }">
          <div>
            <!-- item.sellable est null si la ligne référencée (Garage/MarketSpaceAccount)
                 n'existe plus en base : une anomalie de données, pas une absence normale,
                 d'où un texte de repli explicite plutôt qu'un tiret neutre. -->
            <p>{{ item.sellable?.name ?? 'Vendeur supprimé' }}</p>
            <p class="text-xs text-slate-400">{{ productSellableTypeLabel(item.sellable_type) }}</p>
          </div>
        </template>
        <template #cell-price="{ item }">
          {{ formatPrice(item.price) }}
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="reviewStatusLabel(item.status)" :tone="reviewStatusTone(item.status)" />
        </template>
      </AppTable>

      <AppPagination
        v-if="products.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
