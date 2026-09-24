<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

import { fetchSupervisedQuote } from '@/api/supervision'
import DetailField from '@/shared/components/DetailField.vue'
import LineItemsTable from '@/shared/components/LineItemsTable.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { QuoteLine, QuoteVersion } from '@/types/quote'
import { documentTypeLabel, lineTypeLabel, quoteStatusLabel, quoteStatusTone } from '@/utils/quoteStatus'
import { formatDateTime, useDetail } from '@/utils/supervision'

// Fiche d'un devis, en lecture seule : toutes ses versions (la plus récente
// en premier), leurs lignes figées et la décision du client (qui, quand).
// Pas de lien PDF : aucun endpoint admin ne sert le document.
const route = useRoute()
const { item: quote, isLoading, errorMessage, load } = useDetail(
  () => fetchSupervisedQuote(Number(route.params.id)),
  'Impossible de charger ce devis. Réessayez.',
)

const versions = computed(() => [...(quote.value?.versions ?? [])].sort((a, b) => b.version - a.version))

// Seul le client décide d'une version (depuis l'app ou le lien email).
function decidedByLabel(version: QuoteVersion): string | null {
  if (version.decided_by === null || !quote.value) {
    return null
  }
  return version.decided_by === quote.value.user_id ? quote.value.client.name : `compte n° ${version.decided_by}`
}

function lineType(line: QuoteLine): string {
  return lineTypeLabel(line.type)
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <RouterLink :to="{ name: 'admin.quotes' }" class="text-sm text-slate-500 hover:text-slate-700">← Retour aux devis</RouterLink>

    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>
    <p v-else-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>

    <template v-else-if="quote">
      <div class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <h2 class="text-lg font-semibold text-slate-900">Devis n° {{ quote.id }}</h2>
          <StatusBadge :label="quoteStatusLabel(quote.status)" :tone="quoteStatusTone(quote.status)" />
        </div>
        <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <DetailField label="Garage">
            <RouterLink :to="{ name: 'admin.garages.show', params: { id: quote.garage_id } }" class="font-medium text-slate-900 underline">
              {{ quote.garage.name ?? `Garage n° ${quote.garage_id}` }}
            </RouterLink>
          </DetailField>
          <DetailField label="Client">
            {{ quote.client.name }}<span v-if="quote.client.is_express" class="text-slate-500"> (compte express)</span>
          </DetailField>
          <DetailField label="Rendez-vous">
            <RouterLink
              v-if="quote.appointment_id"
              :to="{ name: 'admin.appointments.show', params: { id: quote.appointment_id } }"
              class="font-medium text-slate-900 underline"
            >
              Rendez-vous n° {{ quote.appointment_id }}
            </RouterLink>
            <span v-else>Sans rendez-vous</span>
          </DetailField>
          <DetailField label="Créé le" :value="formatDateTime(quote.created_at)" />
          <DetailField label="Payé le" :value="quote.paid_at ? formatDateTime(quote.paid_at) : null" />
        </dl>
      </div>

      <section
        v-for="version in versions"
        :key="version.id"
        class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold text-slate-900">
            {{ documentTypeLabel(version.document_type) }} — version {{ version.version }}
          </h3>
          <StatusBadge v-if="version.decision === 'accepted'" label="Accepté par le client" tone="success" />
          <StatusBadge v-else-if="version.decision === 'rejected'" label="Refusé par le client" tone="danger" />
          <StatusBadge v-else-if="!version.is_sent" label="Non envoyé" tone="neutral" />
        </div>
        <p class="mt-1 text-xs text-slate-500">
          <template v-if="version.sent_at">Envoyé le {{ formatDateTime(version.sent_at) }}</template>
          <template v-else>Créé le {{ formatDateTime(version.created_at) }}</template>
          <template v-if="version.decided_at">
            · Décision le {{ formatDateTime(version.decided_at) }}<template v-if="decidedByLabel(version)">
              par {{ decidedByLabel(version) }}</template
            >
          </template>
        </p>
        <LineItemsTable class="mt-3" :lines="version.lines" :total="version.total" :type-label="lineType" />
      </section>
    </template>
  </div>
</template>
