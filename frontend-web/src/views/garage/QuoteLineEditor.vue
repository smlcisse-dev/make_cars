<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

import { fetchAllGarageProducts } from '@/api/garageProducts'
import { fetchAllGarageServices } from '@/api/garageServices'
import AppButton from '@/shared/components/AppButton.vue'
import type { GarageProduct } from '@/types/garageProduct'
import type { GarageService } from '@/types/garageService'
import type { QuoteLineInput, QuoteLineType } from '@/types/quote'
import { extractApiErrorMessage } from '@/utils/apiError'
import { formatAmount } from '@/utils/money'
import { lineTypeLabel } from '@/utils/quoteStatus'

// Éditeur de lignes partagé (création, brouillon, nouvelle version).
//
// Concept Vue : ce composant est un "v-model personnalisé". Le parent écrit
// `<QuoteLineEditor v-model="lines" />`, ce qui équivaut à passer la prop
// `modelValue` et à écouter l'événement `update:modelValue`.
//
// Pourquoi un état interne (`rows`) au lieu de travailler directement sur
// `modelValue` ? Une ligne en cours de saisie est *incomplète* (type « Service »
// choisi mais aucun service sélectionné) et ne correspond donc à aucun
// `QuoteLineInput` valide. On garde les lignes brouillon ici, et on n'émet vers
// le parent que les lignes complètes, plus un booléen `valid` qui dit si
// toutes les lignes le sont. `modelValue` ne sert que de valeur initiale.
interface EditorRow {
  key: number
  type: QuoteLineType
  label: string
  unitPrice: number | null
  serviceId: number | null
  productId: number | null
  quantity: number
}

const props = defineProps<{ modelValue: QuoteLineInput[] }>()
const emit = defineEmits<{
  'update:modelValue': [lines: QuoteLineInput[]]
  'update:valid': [valid: boolean]
}>()

let nextKey = 1

function rowFromInput(line: QuoteLineInput): EditorRow {
  const base = { key: nextKey++, label: '', unitPrice: null, serviceId: null, productId: null }
  if (line.type === 'diagnosis_fee') {
    return { ...base, type: line.type, label: line.label, unitPrice: line.unit_price, quantity: line.quantity }
  }
  if (line.type === 'service') {
    return { ...base, type: line.type, serviceId: line.repair_service_id, quantity: line.quantity }
  }
  return { ...base, type: line.type, productId: line.product_id, quantity: line.quantity }
}

const rows = ref<EditorRow[]>(props.modelValue.map(rowFromInput))
const services = ref<GarageService[]>([])
const products = ref<GarageProduct[]>([])
const isLoadingCatalog = ref(false)
const catalogError = ref<string | null>(null)
const newLineType = ref<QuoteLineType>('service')

onMounted(async () => {
  isLoadingCatalog.value = true
  try {
    const [allServices, allProducts] = await Promise.all([fetchAllGarageServices(), fetchAllGarageProducts()])
    services.value = allServices
    products.value = allProducts
  } catch (error) {
    catalogError.value = extractApiErrorMessage(error, 'Impossible de charger votre catalogue. Réessayez.')
  } finally {
    isLoadingCatalog.value = false
  }
})

// Seuls les éléments publiables sont proposés ; l'élément déjà sélectionné
// reste affiché même s'il a perdu son statut depuis (brouillon existant).
function serviceOptions(row: EditorRow): GarageService[] {
  return services.value.filter((s) => (s.status === 'approved' && s.is_active) || s.id === row.serviceId)
}

function productOptions(row: EditorRow): GarageProduct[] {
  return products.value.filter((p) => p.status === 'approved' || p.id === row.productId)
}

function findService(id: number | null): GarageService | undefined {
  return services.value.find((s) => s.id === id)
}

function findProduct(id: number | null): GarageProduct | undefined {
  return products.value.find((p) => p.id === id)
}

function maxQuantity(row: EditorRow): number | undefined {
  return row.type === 'product' ? findProduct(row.productId)?.stock_quantity : undefined
}

function addRow(): void {
  rows.value.push({
    key: nextKey++,
    type: newLineType.value,
    label: '',
    unitPrice: null,
    serviceId: null,
    productId: null,
    quantity: 1,
  })
}

function removeRow(key: number): void {
  rows.value = rows.value.filter((row) => row.key !== key)
}

function toInput(row: EditorRow): QuoteLineInput | null {
  const quantity = Number.isInteger(row.quantity) && row.quantity >= 1 ? row.quantity : null
  if (quantity === null) {
    return null
  }
  if (row.type === 'diagnosis_fee') {
    return row.unitPrice !== null && row.unitPrice >= 0
      ? { type: 'diagnosis_fee', label: row.label.trim(), unit_price: row.unitPrice, quantity }
      : null
  }
  if (row.type === 'service') {
    return row.serviceId !== null ? { type: 'service', repair_service_id: row.serviceId, quantity } : null
  }
  return row.productId !== null ? { type: 'product', product_id: row.productId, quantity } : null
}

function unitPriceOf(row: EditorRow): number {
  if (row.type === 'diagnosis_fee') {
    return row.unitPrice ?? 0
  }
  const price = row.type === 'service' ? findService(row.serviceId)?.price : findProduct(row.productId)?.price
  return Number(price ?? 0)
}

// Total indicatif recalculé localement : la référence reste `total` renvoyé
// par le backend après enregistrement.
const runningTotal = computed(() =>
  rows.value.reduce((sum, row) => sum + unitPriceOf(row) * (row.quantity > 0 ? row.quantity : 0), 0),
)

// `watch` (avec `deep`) réexécute la fonction dès qu'une propriété imbriquée
// de `rows` change, y compris la frappe dans un champ.
watch(
  rows,
  (current) => {
    const inputs = current.map(toInput)
    emit(
      'update:modelValue',
      inputs.filter((line): line is QuoteLineInput => line !== null),
    )
    emit('update:valid', current.length > 0 && inputs.every((line) => line !== null))
  },
  { deep: true, immediate: true },
)

const inputClasses =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'
</script>

<template>
  <div class="space-y-3">
    <p v-if="catalogError" class="text-sm text-rose-600">{{ catalogError }}</p>
    <p v-else-if="isLoadingCatalog" class="text-sm text-slate-500">Chargement du catalogue...</p>

    <p v-if="rows.length === 0" class="rounded-md border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500">
      Aucune ligne. Ajoutez un service, une pièce ou des frais de diagnostic.
    </p>

    <div v-for="row in rows" :key="row.key" class="rounded-md border border-slate-200 bg-white p-3">
      <div class="flex items-start justify-between gap-3">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ lineTypeLabel(row.type) }}</span>
        <button type="button" class="text-xs text-rose-600 hover:underline" @click="removeRow(row.key)">
          Supprimer
        </button>
      </div>

      <div class="mt-2 grid gap-3 sm:grid-cols-[1fr_7rem]">
        <template v-if="row.type === 'diagnosis_fee'">
          <div class="grid gap-3 sm:grid-cols-2">
            <input v-model="row.label" type="text" maxlength="255" placeholder="Libellé (défaut : Frais de diagnostic)" :class="inputClasses" />
            <input v-model.number="row.unitPrice" type="number" min="0" step="any" placeholder="Prix (FCFA)" :class="inputClasses" />
          </div>
        </template>

        <select v-else-if="row.type === 'service'" v-model.number="row.serviceId" :class="inputClasses">
          <option :value="null" disabled>Choisir un service...</option>
          <option v-for="service in serviceOptions(row)" :key="service.id" :value="service.id">
            {{ service.name }} — {{ formatAmount(service.price) }}
          </option>
        </select>

        <select v-else v-model.number="row.productId" :class="inputClasses">
          <option :value="null" disabled>Choisir une pièce...</option>
          <option v-for="product in productOptions(row)" :key="product.id" :value="product.id">
            {{ product.name }} — {{ formatAmount(product.price) }} (stock : {{ product.stock_quantity }})
          </option>
        </select>

        <input
          v-model.number="row.quantity"
          type="number"
          min="1"
          :max="maxQuantity(row)"
          step="1"
          aria-label="Quantité"
          :class="inputClasses"
        />
      </div>

      <p v-if="maxQuantity(row) !== undefined && row.quantity > (maxQuantity(row) ?? 0)" class="mt-2 text-xs text-rose-600">
        Quantité supérieure au stock disponible ({{ maxQuantity(row) }}).
      </p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <select v-model="newLineType" aria-label="Type de ligne à ajouter" class="rounded-md border border-slate-300 px-3 py-2 text-sm">
          <option value="service">{{ lineTypeLabel('service') }}</option>
          <option value="product">{{ lineTypeLabel('product') }}</option>
          <option value="diagnosis_fee">{{ lineTypeLabel('diagnosis_fee') }}</option>
        </select>
        <AppButton variant="secondary" @click="addRow">Ajouter une ligne</AppButton>
      </div>
      <p class="text-sm font-medium text-slate-900">Total : {{ formatAmount(runningTotal) }}</p>
    </div>
  </div>
</template>
