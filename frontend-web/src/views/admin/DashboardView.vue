<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { fetchStatistics } from '@/api/adminStatistics'
import AppButton from '@/shared/components/AppButton.vue'
import StatusBadge, { type BadgeTone } from '@/shared/components/StatusBadge.vue'
import { useAuthStore } from '@/stores/auth'
import type { AdminStatistics } from '@/types/statistics'
import { extractApiErrorMessage } from '@/utils/apiError'
import { litigeStatusLabel, litigeStatusTone } from '@/utils/litigeStatus'

const auth = useAuthStore()

const stats = ref<AdminStatistics | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

// Filtre de période, propre au formulaire — distinct des valeurs
// effectivement appliquées (`stats.value.activity.period`), qui ne
// changent qu'après un nouvel appel réussi. Seule la section "Volume
// d'activité" respecte cette période (CLAUDE.md §5, ajout v0.18) ; le reste
// de l'écran est un instantané global, non affecté par ce filtre.
const startDate = ref('')
const endDate = ref('')

async function loadStatistics(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    stats.value = await fetchStatistics({
      start_date: startDate.value || undefined,
      end_date: endDate.value || undefined,
    })
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les statistiques. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function resetPeriod(): void {
  startDate.value = ''
  endDate.value = ''
  loadStatistics()
}

function formatAmount(amount: string): string {
  return `${Number(amount).toLocaleString('fr-FR')} FCFA`
}

// Ordre d'affichage des badges des cartes « Structures ». `profile_incomplete`
// = dossiers créés mais jamais soumis (profil ou informations légales à
// compléter).
const STRUCTURE_STATUSES = ['approved', 'pending', 'profile_incomplete', 'suspended', 'rejected'] as const
// `(typeof STRUCTURE_STATUSES)[number]` : type union des valeurs du tableau
// ci-dessus ('approved' | 'pending' | ...). `Record<K, V>` exige alors une
// entrée pour chacune : oublier un statut devient une erreur de compilation.
type StructureStatus = (typeof STRUCTURE_STATUSES)[number]

const STRUCTURE_STATUS_LABELS: Record<StructureStatus, string> = {
  approved: 'Approuvés',
  pending: 'En attente',
  profile_incomplete: 'Profil à compléter',
  suspended: 'Suspendus',
  rejected: 'Rejetés',
}

const STRUCTURE_STATUS_TONES: Record<StructureStatus, BadgeTone> = {
  approved: 'success',
  pending: 'warning',
  profile_incomplete: 'neutral',
  suspended: 'danger',
  rejected: 'neutral',
}

const GEOGRAPHY_COLUMNS = [
  { key: 'garagiste', label: 'Garagiste' },
  { key: 'market_space', label: 'Market Space' },
] as const

const DISPUTE_STATUSES = ['submitted', 'under_review', 'resolved_founded', 'resolved_rejected', 'closed'] as const

onMounted(loadStatistics)
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Bienvenue, {{ auth.user?.name }}</h2>
        <p class="mt-1 text-sm text-slate-500">
          Statistiques agrégées de la plateforme — utiles pour appuyer les politiques de
          régulation/formalisation du secteur auprès des autorités béninoises.
        </p>
      </div>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading && !stats" class="text-sm text-slate-500">Chargement...</p>

    <template v-else-if="stats">
      <!-- Structures -->
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Structures</h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div
            v-for="(counts, type) in { Garagistes: stats.structures.garagiste, 'Market Space': stats.structures.market_space }"
            :key="type"
            class="rounded-lg border border-slate-200 bg-white p-4"
          >
            <div class="flex items-baseline justify-between">
              <span class="text-sm font-medium text-slate-700">{{ type }}</span>
              <span class="text-2xl font-semibold text-slate-900">{{ counts.total }}</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
              <StatusBadge
                v-for="key in STRUCTURE_STATUSES"
                :key="key"
                :label="`${STRUCTURE_STATUS_LABELS[key]} : ${counts[key]}`"
                :tone="STRUCTURE_STATUS_TONES[key]"
              />
            </div>
          </div>
        </div>
      </section>

      <!-- Répartition géographique -->
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Répartition géographique</h3>
        <p class="text-xs text-slate-400">Structures approuvées uniquement, par département.</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div v-for="column in GEOGRAPHY_COLUMNS" :key="column.key" class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ column.label }} — Département
                  </th>
                  <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Nombre</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="row in stats.geography[column.key]" :key="row.department_id ?? 'unknown'">
                  <td class="px-4 py-2 text-sm text-slate-700">{{ row.department_name }}</td>
                  <td class="px-4 py-2 text-right text-sm text-slate-700">{{ row.count }}</td>
                </tr>
                <tr v-if="stats.geography[column.key].length === 0">
                  <td colspan="2" class="px-4 py-3 text-center text-sm text-slate-400">Aucune donnée.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- Volume d'activité -->
      <section class="space-y-3">
        <div class="flex items-end justify-between gap-4">
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Volume d'activité</h3>
            <p class="text-xs text-slate-400">
              Seule cette section respecte la période sélectionnée ci-dessous ; le reste de l'écran est un instantané
              global.
            </p>
          </div>
          <form class="flex flex-wrap items-end gap-2" @submit.prevent="loadStatistics">
            <label class="text-xs text-slate-500">
              Depuis
              <input v-model="startDate" type="date" class="mt-1 block rounded-md border border-slate-300 px-2 py-1 text-sm" />
            </label>
            <label class="text-xs text-slate-500">
              Jusqu'à
              <input v-model="endDate" type="date" class="mt-1 block rounded-md border border-slate-300 px-2 py-1 text-sm" />
            </label>
            <AppButton type="submit" variant="primary" :loading="isLoading">Appliquer</AppButton>
            <AppButton type="button" variant="secondary" :disabled="isLoading" @click="resetPeriod">
              Réinitialiser
            </AppButton>
          </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Rendez-vous</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.activity.appointments_count }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Devis émis</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.activity.quotes_issued_count }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Commandes</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.activity.orders_count }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Factures générées</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.activity.invoices_count }}</p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Montant total facturé</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">
              {{ formatAmount(stats.activity.total_invoiced_amount) }}
            </p>
          </div>
        </div>
      </section>

      <!-- Avis & Réclamations -->
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Avis & Réclamations</h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Avis visibles</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.reviews.count }}</p>
            <p class="mt-1 text-sm text-slate-500">
              Note moyenne :
              <span class="font-medium text-slate-700">
                {{ stats.reviews.average_rating !== null ? `${stats.reviews.average_rating} / 5` : '—' }}
              </span>
            </p>
          </div>
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-xs font-medium text-slate-500">Réclamations</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.disputes.total }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <StatusBadge
                v-for="status in DISPUTE_STATUSES"
                :key="status"
                :label="`${litigeStatusLabel(status)} : ${stats.disputes.by_status[status]}`"
                :tone="litigeStatusTone(status)"
              />
            </div>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
