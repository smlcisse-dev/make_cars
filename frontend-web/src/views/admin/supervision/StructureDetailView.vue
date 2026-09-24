<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'

import { fetchSupervisedStructure } from '@/api/supervision'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import StructureProfileSections from '@/shared/components/StructureProfileSections.vue'
import type { StructureKind } from '@/types/supervision'
import { STRUCTURE_KIND_LABELS, useDetail } from '@/utils/supervision'

// Fiche d'un garage ou d'une boutique, en lecture seule. Le profil réutilise
// les blocs de la fiche d'examen d'un dossier (StructureProfileSections).
const props = defineProps<{ kind: StructureKind }>()

const BACK: Record<StructureKind, { route: string; label: string }> = {
  garage: { route: 'admin.garages', label: '← Retour aux garages' },
  market_space: { route: 'admin.boutiques', label: '← Retour aux boutiques' },
}

const route = useRoute()
const { item: structure, isLoading, errorMessage, load } = useDetail(
  () => fetchSupervisedStructure(props.kind, Number(route.params.id)),
  'Impossible de charger cette fiche. Réessayez.',
)

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <RouterLink :to="{ name: BACK[kind].route }" class="text-sm text-slate-500 hover:text-slate-700">
      {{ BACK[kind].label }}
    </RouterLink>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

    <template v-else-if="structure">
      <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ structure.name ?? 'Sans nom' }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ STRUCTURE_KIND_LABELS[kind] }}</p>
          </div>
          <StatusBadge v-if="structure.is_publicly_visible" label="Visible côté mobile" tone="success" />
          <StatusBadge v-else label="Suspendu" tone="danger" />
        </div>
        <!-- RDV : filtre `garage_id` accepté par le backend. -->
        <RouterLink
          v-if="kind === 'garage'"
          :to="{ name: 'admin.appointments', query: { garage_id: structure.id } }"
          class="mt-4 inline-block text-sm font-medium text-slate-900 underline"
        >
          Voir ses rendez-vous
        </RouterLink>
      </div>

      <StructureProfileSections :profile="structure" />
    </template>
  </div>
</template>
