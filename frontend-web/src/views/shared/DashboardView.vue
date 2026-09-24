<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import type { RouteLocationRaw } from 'vue-router'

import { fetchProfessionalDashboard } from '@/api/professionalDashboard'
import AppButton from '@/shared/components/AppButton.vue'
import type { ProfessionalDashboard } from '@/types/dashboard'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'

// Tableau de bord commun aux deux espaces professionnels (CLAUDE.md §4) :
// `space` choisit l'endpoint et le préfixe des noms de route. Tous les
// chiffres arrivent en une seule réponse (GET /{space}/dashboard).
const props = defineProps<{ space: ProfessionalSpace }>()

const dashboard = ref<ProfessionalDashboard | null>(null)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function loadDashboard(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    dashboard.value = await fetchProfessionalDashboard(props.space)
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger le tableau de bord. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

interface HandleCard {
  key: string
  label: string
  count: number
  to: RouteLocationRaw
}

// `computed` recalcule la liste chaque fois que `dashboard` change (au
// chargement, puis à chaque « Actualiser ») — c'est la réactivité de Vue :
// aucune mise à jour manuelle de l'affichage.
//
// Les compteurs propres au garage sont optionnels dans le type (absents pour
// un Market Space) : `?? 0` les ramène à zéro, et le filtre final ne garde
// que les cartes non nulles.
const handleCards = computed<HandleCard[]>(() => {
  const toHandle = dashboard.value?.to_handle
  if (!toHandle) return []

  const cards: HandleCard[] = [
    {
      key: 'pending_appointments',
      label: 'Rendez-vous en attente de votre réponse',
      count: toHandle.pending_appointments ?? 0,
      // La liste des RDV s'ouvre déjà sur le filtre « En attente ».
      to: { name: 'garage.appointments' },
    },
    {
      key: 'quotes_to_start',
      label: 'Devis acceptés, prestation à démarrer',
      count: toHandle.quotes_to_start ?? 0,
      to: { name: 'garage.quotes' },
    },
    {
      key: 'quotes_to_invoice',
      label: 'Prestations en cours, à facturer',
      count: toHandle.quotes_to_invoice ?? 0,
      to: { name: 'garage.quotes' },
    },
    {
      key: 'orders_to_collect',
      label: 'Commandes à encaisser',
      count: toHandle.orders_to_collect,
      to: { name: `${props.space}.orders` },
    },
    {
      key: 'rejected_services',
      label: "Services refusés par l'administrateur, à corriger",
      count: toHandle.rejected_services ?? 0,
      to: { name: 'garage.services' },
    },
    {
      key: 'rejected_products',
      label: "Produits refusés par l'administrateur, à corriger",
      count: toHandle.rejected_products,
      to: { name: `${props.space}.products` },
    },
    {
      key: 'open_disputes',
      label: 'Réclamations ouvertes',
      count: toHandle.open_disputes,
      to: { name: `${props.space}.disputes` },
    },
  ]

  return cards.filter((card) => card.count > 0)
})

const lowStock = computed(() => dashboard.value?.to_handle.low_stock_products ?? null)

const hasNothingToHandle = computed(
  () => handleCards.value.length === 0 && (lowStock.value?.count ?? 0) === 0,
)

// « septembre 2026 », à partir du premier jour du mois renvoyé par l'API.
// `T00:00:00` : sans heure, `new Date('2026-09-01')` serait lu en UTC et
// pourrait tomber la veille selon le fuseau du navigateur.
const monthLabel = computed(() => {
  const start = dashboard.value?.activity.month_start
  if (!start) return ''
  return new Date(`${start}T00:00:00`).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
})

const ratingLabel = computed(() => {
  const reviews = dashboard.value?.activity.reviews
  if (!reviews || reviews.average_rating === null) return null
  return reviews.average_rating.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
})

const pendingValidationLabel = computed(() => {
  const activity = dashboard.value?.activity
  if (!activity) return ''

  const parts: string[] = []
  if (activity.pending_services !== undefined) {
    parts.push(plural(activity.pending_services, 'service', 'services'))
  }
  parts.push(plural(activity.pending_products, 'produit', 'produits'))
  return parts.join(', ')
})

function plural(count: number, singular: string, pluralForm: string): string {
  return `${count} ${count > 1 ? pluralForm : singular}`
}

onMounted(loadDashboard)
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">
          Bienvenue<template v-if="dashboard">, {{ dashboard.structure_name }}</template>
        </h2>
        <p class="mt-1 text-sm text-slate-500">Ce qui demande votre attention et l'activité du mois.</p>
      </div>
      <AppButton variant="secondary" :loading="isLoading" class="self-start" @click="loadDashboard">
        Actualiser
      </AppButton>
    </div>

    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-else-if="isLoading && !dashboard" class="text-sm text-slate-500">Chargement...</p>

    <template v-if="dashboard">
      <!-- À traiter -->
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">À traiter</h3>

        <p
          v-if="hasNothingToHandle"
          class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-500"
        >
          Rien à traiter pour le moment.
        </p>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <RouterLink
            v-for="card in handleCards"
            :key="card.key"
            :to="card.to"
            class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4 hover:border-slate-400"
          >
            <span class="text-sm font-medium text-slate-700">{{ card.label }}</span>
            <span class="text-2xl font-semibold text-slate-900">{{ card.count }}</span>
          </RouterLink>

          <RouterLink
            v-if="lowStock && lowStock.count > 0"
            :to="{ name: `${space}.products` }"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 hover:border-amber-500"
          >
            <div class="flex items-center justify-between gap-4">
              <span class="text-sm font-medium text-amber-900">Produits en stock bas</span>
              <span class="text-2xl font-semibold text-amber-900">{{ lowStock.count }}</span>
            </div>
            <ul class="mt-3 space-y-1 text-sm text-amber-900">
              <li v-for="product in lowStock.items" :key="product.id" class="flex justify-between gap-2">
                <span class="truncate">{{ product.name }}</span>
                <span class="shrink-0">{{ product.stock_quantity }} (seuil {{ product.low_stock_threshold }})</span>
              </li>
            </ul>
            <p v-if="lowStock.count > lowStock.items.length" class="mt-2 text-xs text-amber-800">
              et {{ lowStock.count - lowStock.items.length }} autre(s)…
            </p>
          </RouterLink>
        </div>
      </section>

      <!-- Activité -->
      <section class="space-y-3">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Activité</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Facturé en {{ monthLabel }}</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">
              {{ formatAmount(dashboard.activity.invoiced_amount_this_month) }}
            </p>
          </div>

          <RouterLink
            :to="{ name: `${space}.reviews` }"
            class="rounded-lg border border-slate-200 bg-white p-4 hover:border-slate-400"
          >
            <p class="text-sm text-slate-500">Note moyenne</p>
            <p v-if="ratingLabel" class="mt-1 text-2xl font-semibold text-slate-900">
              {{ ratingLabel }} / 5
              <span class="text-sm font-normal text-slate-500">
                ({{ plural(dashboard.activity.reviews.count, 'avis', 'avis') }})
              </span>
            </p>
            <p v-else class="mt-1 text-sm text-slate-500">Aucun avis pour le moment.</p>
          </RouterLink>

          <RouterLink
            v-if="dashboard.activity.quotes_awaiting_client !== undefined"
            :to="{ name: 'garage.quotes' }"
            class="rounded-lg border border-slate-200 bg-white p-4 hover:border-slate-400"
          >
            <p class="text-sm text-slate-500">Devis en attente de réponse du client</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900">
              {{ dashboard.activity.quotes_awaiting_client }}
            </p>
          </RouterLink>

          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">En attente de validation par l'administrateur</p>
            <p class="mt-1 text-base font-semibold text-slate-900">{{ pendingValidationLabel }}</p>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>
