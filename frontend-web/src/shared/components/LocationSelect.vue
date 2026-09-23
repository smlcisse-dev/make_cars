<script setup lang="ts">
import { onMounted, ref, type Ref } from 'vue'

import { fetchArrondissements, fetchCommunes, fetchDepartments } from '@/api/locations'
import type { LocationOption } from '@/types/location'
import { extractApiErrorMessage } from '@/utils/apiError'

// Sélecteurs en cascade Département → Commune → Arrondissement + quartier
// libre (CLAUDE.md §5, ajout v0.19). Composant réutilisable : à intégrer tel
// quel dans le futur écran de profil Garagiste/Market Space.
//
// `defineModel` (Vue 3.4+) déclare une prop + son événement `update:xxx` en
// une ligne : le parent écrit `v-model:department-id="form.department_id"`
// et reste propriétaire de la valeur ; ici on lit/écrit `departmentId.value`
// comme un simple `ref`.
const departmentId = defineModel<number | null>('departmentId', {
  default: null,
})
const communeId = defineModel<number | null>('communeId', { default: null })
const arrondissementId = defineModel<number | null>('arrondissementId', {
  default: null,
})
const neighborhood = defineModel<string | null>('neighborhood', {
  default: null,
})

const departments = ref<LocationOption[]>([])
const communes = ref<LocationOption[]>([])
const arrondissements = ref<LocationOption[]>([])
const errorMessage = ref<string | null>(null)

// Un indicateur de chargement par niveau : la base distante peut mettre
// plusieurs secondes à répondre, et un select vide sans explication donne
// l'impression que la liste est vide ou cassée.
const isLoadingDepartments = ref(false)
const isLoadingCommunes = ref(false)
const isLoadingArrondissements = ref(false)

// Garde contre les réponses qui arrivent dans le désordre : si l'utilisateur
// change de département avant que la première réponse ne soit revenue, la
// réponse « périmée » ne doit pas écraser la liste du choix courant. Chaque
// chargement prend un numéro ; seule la réponse portant le dernier numéro
// est appliquée.
let communesRequestId = 0
let arrondissementsRequestId = 0

async function loadInto(
  target: Ref<LocationOption[]>,
  loading: Ref<boolean>,
  load: () => Promise<LocationOption[]>,
  isCurrent: () => boolean = () => true,
): Promise<void> {
  errorMessage.value = null
  loading.value = true
  try {
    const result = await load()
    if (isCurrent()) target.value = result
  } catch (error) {
    if (isCurrent()) {
      errorMessage.value = extractApiErrorMessage(
        error,
        'Impossible de charger la liste. Réessayez.',
      )
    }
  } finally {
    if (isCurrent()) loading.value = false
  }
}

function loadCommunes(id: number): Promise<void> {
  const requestId = ++communesRequestId
  return loadInto(
    communes,
    isLoadingCommunes,
    () => fetchCommunes(id),
    () => requestId === communesRequestId,
  )
}

function loadArrondissements(id: number): Promise<void> {
  const requestId = ++arrondissementsRequestId
  return loadInto(
    arrondissements,
    isLoadingArrondissements,
    () => fetchArrondissements(id),
    () => requestId === arrondissementsRequestId,
  )
}

// Chargement initial : on remplit chaque liste selon les valeurs déjà
// présentes (profil existant), sans rien réinitialiser — d'où l'usage de
// gestionnaires @change ci-dessous plutôt que de `watch`, qui se
// déclencherait aussi sur ces valeurs initiales et les effacerait. Les trois
// chargements partent en parallèle (ils ne dépendent que des valeurs déjà
// connues), au lieu de s'attendre les uns les autres.
onMounted(() => {
  loadInto(departments, isLoadingDepartments, fetchDepartments)
  if (departmentId.value !== null) loadCommunes(departmentId.value)
  if (communeId.value !== null) loadArrondissements(communeId.value)
})

// Valeur choisie lue directement dans l'événement du <select> : avec
// `defineModel`, quand le parent contrôle la valeur (v-model), écrire
// `departmentId.value = x` ne fait qu'émettre `update:departmentId` — la
// lecture suivante de `departmentId.value` renvoie encore l'ANCIENNE valeur
// jusqu'au prochain rendu du parent. Lire le modèle dans un gestionnaire
// juste après un changement donnerait donc la valeur périmée (c'était le bug :
// aucune liste enfant ne se chargeait). L'événement, lui, porte toujours la
// valeur réellement choisie.
function selectedId(event: Event): number | null {
  const raw = (event.target as HTMLSelectElement).value
  return raw === '' ? null : Number(raw)
}

// Un changement de niveau invalide tout ce qui est en dessous (ex. une
// commune n'a de sens que dans son département) : on vide les niveaux
// enfants, puis on charge la liste du niveau suivant. Incrémenter le numéro
// de requête invalide aussi toute réponse encore en vol pour ce niveau.
function onDepartmentChange(event: Event): void {
  const id = selectedId(event)
  departmentId.value = id
  communeId.value = null
  arrondissementId.value = null
  communes.value = []
  arrondissements.value = []
  arrondissementsRequestId++
  isLoadingArrondissements.value = false
  if (id !== null) {
    loadCommunes(id)
  } else {
    communesRequestId++
    isLoadingCommunes.value = false
  }
}

function onCommuneChange(event: Event): void {
  const id = selectedId(event)
  communeId.value = id
  arrondissementId.value = null
  arrondissements.value = []
  if (id !== null) {
    loadArrondissements(id)
  } else {
    arrondissementsRequestId++
    isLoadingArrondissements.value = false
  }
}

function onArrondissementChange(event: Event): void {
  arrondissementId.value = selectedId(event)
}

const selectClasses =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400'
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <label class="block text-sm font-medium text-slate-700">
      Département
      <select :class="selectClasses" :disabled="isLoadingDepartments" @change="onDepartmentChange">
        <option value="">
          {{ isLoadingDepartments ? 'Chargement...' : '— Choisir —' }}
        </option>
        <option
          v-for="option in departments"
          :key="option.id"
          :value="option.id"
          :selected="option.id === departmentId"
        >
          {{ option.name }}
        </option>
      </select>
    </label>

    <label class="block text-sm font-medium text-slate-700">
      Commune
      <select
        :class="selectClasses"
        :disabled="departmentId === null || isLoadingCommunes"
        @change="onCommuneChange"
      >
        <option value="">
          {{ isLoadingCommunes ? 'Chargement...' : '— Choisir —' }}
        </option>
        <option
          v-for="option in communes"
          :key="option.id"
          :value="option.id"
          :selected="option.id === communeId"
        >
          {{ option.name }}
        </option>
      </select>
    </label>

    <label class="block text-sm font-medium text-slate-700">
      Arrondissement
      <select
        :class="selectClasses"
        :disabled="communeId === null || isLoadingArrondissements"
        @change="onArrondissementChange"
      >
        <option value="">
          {{ isLoadingArrondissements ? 'Chargement...' : '— Choisir —' }}
        </option>
        <option
          v-for="option in arrondissements"
          :key="option.id"
          :value="option.id"
          :selected="option.id === arrondissementId"
        >
          {{ option.name }}
        </option>
      </select>
    </label>

    <label class="block text-sm font-medium text-slate-700">
      Quartier
      <input
        v-model="neighborhood"
        type="text"
        maxlength="255"
        placeholder="Texte libre"
        :class="selectClasses"
      />
    </label>

    <p v-if="errorMessage" class="text-sm text-red-600 sm:col-span-2">
      {{ errorMessage }}
    </p>
  </div>
</template>
