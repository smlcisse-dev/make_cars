<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { fetchProfessionalReviews } from '@/api/professionalReviews'
import AppPagination from '@/shared/components/AppPagination.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { ProfessionalReview } from '@/types/professionalReview'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import { extractApiErrorMessage } from '@/utils/apiError'

// Même écran pour les deux espaces professionnels : `space` choisit le
// préfixe des endpoints et des noms de route (CLAUDE.md §4).
const props = defineProps<{ space: ProfessionalSpace }>()

const reviews = ref<ProfessionalReview[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function loadReviews(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchProfessionalReviews(props.space, currentPage.value)
    reviews.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les avis. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadReviews()
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

onMounted(loadReviews)
</script>

<template>
  <div class="max-w-3xl space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Avis reçus</h2>
      <p class="mt-1 text-sm text-slate-500">
        Consultation seule. Les avis masqués par la modération restent visibles ici, avec leur motif, par transparence.
      </p>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <p v-if="reviews.length === 0" class="text-sm text-slate-500">Aucun avis pour l'instant.</p>

      <ul v-else class="space-y-3">
        <li v-for="review in reviews" :key="review.id" class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-slate-900">{{ review.rating }}/5</span>
              <span class="text-amber-500" aria-hidden="true">{{ '★'.repeat(review.rating) }}{{ '☆'.repeat(5 - review.rating) }}</span>
              <StatusBadge v-if="review.status === 'hidden'" label="Masqué" tone="danger" />
            </div>
            <span class="text-xs text-slate-400">{{ formatDate(review.created_at) }}</span>
          </div>
          <p class="mt-1 text-sm text-slate-600">{{ review.client.name }}</p>
          <p v-if="review.comment" class="mt-2 whitespace-pre-line text-sm text-slate-800">{{ review.comment }}</p>
          <p v-else class="mt-2 text-sm italic text-slate-400">Aucun commentaire.</p>
          <p v-if="review.status === 'hidden'" class="mt-2 rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-700">
            Motif de modération : {{ review.moderation_reason }}
          </p>
        </li>
      </ul>

      <AppPagination
        v-if="reviews.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>
  </div>
</template>
