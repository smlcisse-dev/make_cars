<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

import { fetchSupervisedStructures } from '@/api/supervision'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { StructureKind, SupervisedStructure } from '@/types/supervision'
import { STRUCTURE_DETAIL_ROUTES, usePaginatedList } from '@/utils/supervision'

// Garages et boutiques Market Space au dossier approuvé, suspendus compris
// (supervision en lecture seule, CLAUDE.md §5 règle 8). Un seul écran pour
// les deux types, choisi par la prop `kind` posée par le routeur. Aucun
// filtre : le backend n'en accepte pas sur ces listes.
const props = defineProps<{ kind: StructureKind }>()

const TEXTS: Record<StructureKind, { title: string; intro: string; empty: string }> = {
  garage: {
    title: 'Garages',
    intro: 'Garages au dossier approuvé, suspendus compris. Les dossiers en cours se consultent dans Inscriptions.',
    empty: 'Aucun garage approuvé.',
  },
  market_space: {
    title: 'Boutiques',
    intro:
      'Boutiques Market Space au dossier approuvé, suspendues comprises. Les dossiers en cours se consultent dans Inscriptions.',
    empty: 'Aucune boutique approuvée.',
  },
}

const columns: TableColumn[] = [
  { key: 'name', label: 'Nom' },
  { key: 'address', label: 'Adresse' },
  { key: 'phone', label: 'Téléphone', class: 'whitespace-nowrap' },
  { key: 'visibility', label: 'Statut' },
]

const router = useRouter()
const { items, currentPage, lastPage, isLoading, errorMessage, load } = usePaginatedList(
  (page) => fetchSupervisedStructures(props.kind, page),
  'Impossible de charger la liste. Réessayez.',
)

function goToDetail(structure: SupervisedStructure): void {
  router.push({ name: STRUCTURE_DETAIL_ROUTES[props.kind], params: { id: structure.id } })
}

onMounted(() => load(1))
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">{{ TEXTS[kind].title }}</h2>
      <p class="mt-1 text-sm text-slate-500">{{ TEXTS[kind].intro }}</p>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="items" :columns="columns" @row-click="goToDetail">
        <template #empty>{{ TEXTS[kind].empty }}</template>
        <template #cell-name="{ item }">
          <span class="font-medium text-slate-900">{{ item.name ?? 'Sans nom' }}</span>
        </template>
        <template #cell-address="{ item }">
          <span class="line-clamp-1 max-w-xs text-slate-500">{{ item.address ?? '—' }}</span>
        </template>
        <template #cell-phone="{ item }">{{ item.phone ?? '—' }}</template>
        <!-- Liste limitée aux dossiers approuvés : invisible côté mobile =
             suspendu (CLAUDE.md §5, ajout v0.6). -->
        <template #cell-visibility="{ item }">
          <StatusBadge v-if="item.is_publicly_visible" label="Visible" tone="success" />
          <StatusBadge v-else label="Suspendu" tone="danger" />
        </template>
      </AppTable>

      <AppPagination v-if="items.length > 0" :current-page="currentPage" :last-page="lastPage" @update:current-page="load" />
    </template>
  </div>
</template>
