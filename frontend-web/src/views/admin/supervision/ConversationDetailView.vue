<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

import { fetchSupervisedConversation } from '@/api/supervision'
import ConversationMessageList from '@/shared/components/ConversationMessageList.vue'
import DetailField from '@/shared/components/DetailField.vue'
import {
  STRUCTURE_DETAIL_ROUTES,
  STRUCTURE_KIND_LABELS,
  conversationStructureKind,
  useDetail,
} from '@/utils/supervision'

// Fiche d'une conversation, en lecture seule : l'admin ne peut ni écrire ni
// ouvrir les photos (aucun endpoint admin ne les sert).
const route = useRoute()
const { item: conversation, isLoading, errorMessage, load } = useDetail(
  () => fetchSupervisedConversation(Number(route.params.id)),
  'Impossible de charger cette conversation. Réessayez.',
)

// La relation n'est pas triée côté backend : ordre chronologique ici.
const messages = computed(() =>
  [...(conversation.value?.messages ?? [])].sort((a, b) => a.created_at.localeCompare(b.created_at) || a.id - b.id),
)

const kind = computed(() => (conversation.value ? conversationStructureKind(conversation.value.sellable_type) : null))

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <RouterLink :to="{ name: 'admin.conversations' }" class="text-sm text-slate-500 hover:text-slate-700">
      ← Retour aux conversations
    </RouterLink>

    <p class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
      Conversation privée entre le client et le professionnel, consultée à des fins de supervision.
    </p>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

    <template v-else-if="conversation && kind">
      <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <DetailField :label="STRUCTURE_KIND_LABELS[kind]">
            <RouterLink
              :to="{ name: STRUCTURE_DETAIL_ROUTES[kind], params: { id: conversation.sellable_id } }"
              class="font-medium text-slate-900 underline"
            >
              {{ conversation.sellable.name ?? `n° ${conversation.sellable_id}` }}
            </RouterLink>
          </DetailField>
          <DetailField label="Client" :value="conversation.user.name" />
        </dl>
      </div>

      <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <h3 class="text-sm font-semibold text-slate-900">Messages</h3>
        <ConversationMessageList class="mt-3" :messages="messages" />
      </section>
    </template>
  </div>
</template>
