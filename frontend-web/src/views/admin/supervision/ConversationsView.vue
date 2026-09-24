<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

import { fetchSupervisedConversations } from '@/api/supervision'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import type { SupervisedConversation } from '@/types/supervision'
import { STRUCTURE_KIND_LABELS, conversationStructureKind, formatDateTime, usePaginatedList } from '@/utils/supervision'

// Conversations client ↔ professionnel, en lecture seule (CLAUDE.md §5
// règle 8), la plus récemment active en premier. Aucun filtre : le backend
// n'en accepte pas sur cette liste.
const columns: TableColumn[] = [
  { key: 'professional', label: 'Professionnel' },
  { key: 'client', label: 'Client' },
  { key: 'last_message_at', label: 'Dernier message', class: 'whitespace-nowrap' },
]

const router = useRouter()
const { items, currentPage, lastPage, isLoading, errorMessage, load } = usePaginatedList(
  fetchSupervisedConversations,
  'Impossible de charger les conversations. Réessayez.',
)

function goToDetail(conversation: SupervisedConversation): void {
  router.push({ name: 'admin.conversations.show', params: { id: conversation.id } })
}

onMounted(() => load(1))
</script>

<template>
  <div class="space-y-4">
    <div>
      <h2 class="text-lg font-semibold text-slate-900">Conversations</h2>
      <p class="mt-1 text-sm text-slate-500">
        Échanges privés entre clients et professionnels, consultables à des fins de supervision.
      </p>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="items" :columns="columns" @row-click="goToDetail">
        <template #empty>Aucune conversation.</template>
        <template #cell-professional="{ item }">
          <div>{{ item.sellable.name ?? '—' }}</div>
          <div class="text-xs text-slate-400">{{ STRUCTURE_KIND_LABELS[conversationStructureKind(item.sellable_type)] }}</div>
        </template>
        <template #cell-client="{ item }">{{ item.user.name }}</template>
        <template #cell-last_message_at="{ item }">
          {{ item.last_message_at ? formatDateTime(item.last_message_at) : 'Aucun message' }}
        </template>
      </AppTable>

      <AppPagination v-if="items.length > 0" :current-page="currentPage" :last-page="lastPage" @update:current-page="load" />
    </template>
  </div>
</template>
