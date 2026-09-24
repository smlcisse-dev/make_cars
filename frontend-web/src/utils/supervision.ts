import { ref, type Ref } from 'vue'

import type { PaginatedResponse } from '@/types/pagination'
import type { StructureKind } from '@/types/supervision'
import { extractApiErrorMessage } from '@/utils/apiError'

// Outils communs aux écrans de supervision admin (lecture seule, CLAUDE.md
// §5 règle 8).

export const STRUCTURE_KIND_LABELS: Record<StructureKind, string> = {
  garage: 'Garage',
  market_space: 'Boutique',
}

// Noms des routes de la fiche de chaque type de structure (src/router).
export const STRUCTURE_DETAIL_ROUTES: Record<StructureKind, string> = {
  garage: 'admin.garages.show',
  market_space: 'admin.boutiques.show',
}

// Une conversation porte le nom de classe Laravel de son vendeur
// (`App\Models\Garage` ou `App\Models\MarketSpaceAccount`).
export function conversationStructureKind(sellableType: string): StructureKind {
  return sellableType.endsWith('\\Garage') ? 'garage' : 'market_space'
}

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString('fr-FR', { dateStyle: 'long', timeStyle: 'short' })
}

// Liste paginée : chargement, erreur et page courante, communs à toutes les
// listes de supervision. `loader` reçoit la page à charger ; la page
// appelante y ajoute ses filtres.
//
// C'est un « composable » (fonction `useXxx` qui crée des refs) : chaque
// écran qui l'appelle obtient ses propres valeurs réactives. `Ref<T[]>`
// annonce à TypeScript le type des lignes, pour garder l'autocomplétion
// dans le template.
export function usePaginatedList<T>(
  loader: (page: number) => Promise<PaginatedResponse<T>>,
  errorFallback: string,
) {
  const items = ref([]) as Ref<T[]>
  const currentPage = ref(1)
  const lastPage = ref(1)
  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(page = currentPage.value): Promise<void> {
    isLoading.value = true
    errorMessage.value = null
    try {
      const response = await loader(page)
      items.value = response.data
      currentPage.value = response.meta.current_page
      lastPage.value = response.meta.last_page
    } catch (error) {
      errorMessage.value = extractApiErrorMessage(error, errorFallback)
    } finally {
      isLoading.value = false
    }
  }

  return { items, currentPage, lastPage, isLoading, errorMessage, load }
}

// Fiche : chargement et erreur d'une seule ressource.
export function useDetail<T>(loader: () => Promise<T>, errorFallback: string) {
  const item = ref(null) as Ref<T | null>
  const isLoading = ref(false)
  const errorMessage = ref<string | null>(null)

  async function load(): Promise<void> {
    isLoading.value = true
    errorMessage.value = null
    try {
      item.value = await loader()
    } catch (error) {
      errorMessage.value = extractApiErrorMessage(error, errorFallback)
    } finally {
      isLoading.value = false
    }
  }

  return { item, isLoading, errorMessage, load }
}
