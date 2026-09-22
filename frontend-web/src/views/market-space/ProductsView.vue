<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import {
  createMarketSpaceProduct,
  deleteMarketSpaceProduct,
  fetchMarketSpaceProducts,
  updateMarketSpaceProduct,
  updateMarketSpaceProductStock,
} from '@/api/marketSpaceProducts'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import BaseModal from '@/shared/components/BaseModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import type { MarketSpaceProduct } from '@/types/marketSpaceProduct'
import { extractApiErrorMessage } from '@/utils/apiError'
import { reviewStatusLabel, reviewStatusTone } from '@/utils/reviewStatus'

const columns: TableColumn[] = [
  { key: 'name', label: 'Nom' },
  { key: 'sku', label: 'SKU' },
  { key: 'price', label: 'Prix' },
  { key: 'stock_quantity', label: 'Stock' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

const products = ref<MarketSpaceProduct[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const flashMessage = ref<string | null>(null)

async function loadProducts(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchMarketSpaceProducts(currentPage.value)
    products.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les produits. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadProducts()
}

onMounted(loadProducts)

function formatPrice(price: string): string {
  return `${Number(price).toLocaleString('fr-FR')} FCFA`
}

// Stock à réapprovisionner : seuil renseigné ET stock <= seuil (même règle que
// Product::isAtOrBelowLowStockThreshold côté backend). Sans seuil, jamais.
function isLowStock(product: MarketSpaceProduct): boolean {
  return product.low_stock_threshold !== null && product.stock_quantity <= product.low_stock_threshold
}

// Champs numériques : `v-model.number` donne '' quand le champ est vidé.
function isNonNegativeInteger(value: number | ''): boolean {
  return value !== '' && Number.isInteger(Number(value)) && Number(value) >= 0
}

const inputClasses =
  'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'

// --- Suppression
async function handleDelete(product: MarketSpaceProduct): Promise<void> {
  if (!window.confirm(`Supprimer le produit « ${product.name} » ?`)) {
    return
  }

  errorMessage.value = null

  try {
    await deleteMarketSpaceProduct(product.id)
    flashMessage.value = 'Produit supprimé.'
    if (products.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1
    }
    await loadProducts()
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'La suppression a échoué. Réessayez.')
  }
}

// --- Modale 1 : création / modification du CONTENU. `editingProduct` vaut
// null en création ; en édition le formulaire est pré-rempli depuis la ligne
// en mémoire (il n'existe pas de GET /market-space/products/{id}). Le stock n'y
// figure qu'à la création : modifier le contenu ne touche jamais le stock.
const isFormOpen = ref(false)
const editingProduct = ref<MarketSpaceProduct | null>(null)
const isSubmitting = ref(false)
const formErrorMessage = ref<string | null>(null)
const formTouched = ref(false)

const form = ref({
  name: '',
  description: '',
  sku: '',
  price: '' as number | '',
  stock_quantity: '' as number | '',
  low_stock_threshold: '' as number | '',
})
const imageFile = ref<File | null>(null)

const isEditing = computed(() => editingProduct.value !== null)

// Image obligatoire à la création ; en édition, l'image déjà présente
// suffit — pas besoin d'en re-uploader une (CLAUDE.md §5, ajout v0.23).
const isImageRequired = computed(() => !isEditing.value || !editingProduct.value?.image_url)
const imageHint = computed(() => {
  if (!isEditing.value) {
    return 'obligatoire à la création, JPG/PNG, 5 Mo max'
  }
  return isImageRequired.value ? 'obligatoire, JPG/PNG, 5 Mo max' : 'facultative, JPG/PNG, 5 Mo max'
})

const formErrors = computed(() => ({
  name: form.value.name.trim() === '' ? 'Le nom est obligatoire.' : null,
  price: form.value.price === '' || Number(form.value.price) < 0 ? 'Indiquez un prix supérieur ou égal à 0.' : null,
  // Stock initial et seuil : uniquement contrôlés en création.
  stock_quantity:
    !isEditing.value && !isNonNegativeInteger(form.value.stock_quantity)
      ? 'Indiquez un stock initial (entier, 0 ou plus).'
      : null,
  low_stock_threshold:
    !isEditing.value && form.value.low_stock_threshold !== '' && !isNonNegativeInteger(form.value.low_stock_threshold)
      ? 'Le seuil doit être un entier, 0 ou plus.'
      : null,
  image: isImageRequired.value && !imageFile.value ? 'Une image est obligatoire.' : null,
}))
const isFormValid = computed(() => Object.values(formErrors.value).every((error) => error === null))

function openCreateModal(): void {
  editingProduct.value = null
  form.value = { name: '', description: '', sku: '', price: '', stock_quantity: '', low_stock_threshold: '' }
  imageFile.value = null
  formErrorMessage.value = null
  formTouched.value = false
  isFormOpen.value = true
}

function openEditModal(product: MarketSpaceProduct): void {
  editingProduct.value = product
  form.value = {
    name: product.name,
    description: product.description ?? '',
    sku: product.sku ?? '',
    price: Number(product.price),
    stock_quantity: '',
    low_stock_threshold: '',
  }
  imageFile.value = null
  formErrorMessage.value = null
  formTouched.value = false
  isFormOpen.value = true
}

function handleImageChange(event: Event): void {
  const input = event.target as HTMLInputElement
  imageFile.value = input.files?.[0] ?? null
}

async function handleSubmit(): Promise<void> {
  formTouched.value = true
  if (!isFormValid.value) {
    return
  }

  isSubmitting.value = true
  formErrorMessage.value = null

  const content = {
    name: form.value.name.trim(),
    description: form.value.description.trim() || null,
    sku: form.value.sku.trim() || null,
    price: Number(form.value.price),
  }

  try {
    const result = editingProduct.value
      ? await updateMarketSpaceProduct(editingProduct.value.id, content, imageFile.value)
      : await createMarketSpaceProduct(
          {
            ...content,
            stock_quantity: Number(form.value.stock_quantity),
            low_stock_threshold:
              form.value.low_stock_threshold === '' ? null : Number(form.value.low_stock_threshold),
          },
          imageFile.value,
        )

    isFormOpen.value = false
    flashMessage.value = result.message
    await loadProducts()
  } catch (error) {
    formErrorMessage.value = extractApiErrorMessage(error, "L'enregistrement a échoué. Réessayez.")
  } finally {
    isSubmitting.value = false
  }
}

// --- Modale 2 : ajustement du STOCK (endpoint distinct, sans revalidation
// admin : CLAUDE.md §5, ajout v0.12). Seuil vide = pas d'alerte.
const stockProduct = ref<MarketSpaceProduct | null>(null)
const isStockSubmitting = ref(false)
const stockErrorMessage = ref<string | null>(null)
const stockTouched = ref(false)
const stockForm = ref({ stock_quantity: '' as number | '', low_stock_threshold: '' as number | '' })

const stockErrors = computed(() => ({
  stock_quantity: !isNonNegativeInteger(stockForm.value.stock_quantity)
    ? 'Indiquez un stock (entier, 0 ou plus).'
    : null,
  low_stock_threshold:
    stockForm.value.low_stock_threshold !== '' && !isNonNegativeInteger(stockForm.value.low_stock_threshold)
      ? 'Le seuil doit être un entier, 0 ou plus.'
      : null,
}))
const isStockFormValid = computed(() => Object.values(stockErrors.value).every((error) => error === null))

function openStockModal(product: MarketSpaceProduct): void {
  stockProduct.value = product
  stockForm.value = {
    stock_quantity: product.stock_quantity,
    low_stock_threshold: product.low_stock_threshold ?? '',
  }
  stockErrorMessage.value = null
  stockTouched.value = false
}

async function handleStockSubmit(): Promise<void> {
  stockTouched.value = true
  if (!stockProduct.value || !isStockFormValid.value) {
    return
  }

  isStockSubmitting.value = true
  stockErrorMessage.value = null

  try {
    const result = await updateMarketSpaceProductStock(stockProduct.value.id, {
      stock_quantity: Number(stockForm.value.stock_quantity),
      low_stock_threshold: stockForm.value.low_stock_threshold === '' ? null : Number(stockForm.value.low_stock_threshold),
    })
    // Le stock ne change pas le statut de validation : on remplace la ligne
    // en mémoire plutôt que de recharger toute la liste.
    products.value = products.value.map((item) => (item.id === result.product.id ? result.product : item))
    stockProduct.value = null
    flashMessage.value = result.message
  } catch (error) {
    stockErrorMessage.value = extractApiErrorMessage(error, 'Le stock n\'a pas pu être mis à jour. Réessayez.')
  } finally {
    isStockSubmitting.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Produits</h2>
        <p class="mt-1 text-sm text-slate-500">
          Le catalogue et le stock de votre boutique. Toute création ou modification de contenu est soumise à
          validation admin ; les corrections de stock ne le sont pas.
        </p>
      </div>
      <AppButton @click="openCreateModal">Ajouter un produit</AppButton>
    </div>

    <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ flashMessage }}
    </p>
    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="products" :columns="columns" @row-click="openEditModal">
        <template #empty>Aucun produit pour le moment.</template>
        <template #cell-sku="{ item }">
          <span v-if="item.sku">{{ item.sku }}</span>
          <span v-else class="text-slate-400">—</span>
        </template>
        <template #cell-price="{ item }">
          {{ formatPrice(item.price) }}
        </template>
        <template #cell-stock_quantity="{ item }">
          <span :class="isLowStock(item) ? 'font-semibold text-orange-600' : ''">{{ item.stock_quantity }}</span>
          <span v-if="isLowStock(item)" class="ml-1 text-xs text-orange-600">
            (seuil {{ item.low_stock_threshold }} — à réapprovisionner)
          </span>
        </template>
        <template #cell-status="{ item }">
          <StatusBadge :label="reviewStatusLabel(item.status)" :tone="reviewStatusTone(item.status)" />
          <p v-if="item.status === 'rejected' && item.rejection_reason" class="mt-1 text-xs text-rose-600">
            {{ item.rejection_reason }}
          </p>
        </template>
        <template #cell-actions="{ item }">
          <!-- .stop : ces boutons ne doivent pas déclencher l'ouverture de la
               modale d'édition portée par le clic sur la ligne <tr>. -->
          <div class="flex items-center gap-3">
            <button type="button" class="text-sm text-slate-700 hover:text-slate-900" @click.stop="openStockModal(item)">
              Ajuster le stock
            </button>
            <button type="button" class="text-sm text-rose-600 hover:text-rose-700" @click.stop="handleDelete(item)">
              Supprimer
            </button>
          </div>
        </template>
      </AppTable>

      <AppPagination
        v-if="products.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>

    <BaseModal
      v-if="isFormOpen"
      :title="isEditing ? 'Modifier le produit' : 'Ajouter un produit'"
      @close="isFormOpen = false"
    >
      <p v-if="isEditing" class="mb-3 text-sm text-slate-500">
        Toute modification remet le produit en attente de validation admin. Le stock se corrige via « Ajuster le
        stock ».
      </p>

      <label for="product-name" class="block text-sm font-medium text-slate-700">Nom</label>
      <input id="product-name" v-model="form.name" type="text" maxlength="255" :class="inputClasses" />
      <p v-if="formTouched && formErrors.name" class="mt-1 text-sm text-rose-600">{{ formErrors.name }}</p>

      <label for="product-description" class="mt-3 block text-sm font-medium text-slate-700">
        Description <span class="text-slate-400">(facultative)</span>
      </label>
      <textarea id="product-description" v-model="form.description" rows="3" maxlength="2000" :class="inputClasses" />

      <div class="mt-3 grid grid-cols-2 gap-3">
        <div>
          <label for="product-sku" class="block text-sm font-medium text-slate-700">
            SKU <span class="text-slate-400">(facultatif)</span>
          </label>
          <input id="product-sku" v-model="form.sku" type="text" maxlength="100" :class="inputClasses" />
        </div>
        <div>
          <label for="product-price" class="block text-sm font-medium text-slate-700">Prix (FCFA)</label>
          <input id="product-price" v-model.number="form.price" type="number" min="0" :class="inputClasses" />
          <p v-if="formTouched && formErrors.price" class="mt-1 text-sm text-rose-600">{{ formErrors.price }}</p>
        </div>
      </div>

      <div v-if="!isEditing" class="mt-3 grid grid-cols-2 gap-3">
        <div>
          <label for="product-initial-stock" class="block text-sm font-medium text-slate-700">Stock initial</label>
          <input
            id="product-initial-stock"
            v-model.number="form.stock_quantity"
            type="number"
            min="0"
            :class="inputClasses"
          />
          <p v-if="formTouched && formErrors.stock_quantity" class="mt-1 text-sm text-rose-600">
            {{ formErrors.stock_quantity }}
          </p>
        </div>
        <div>
          <label for="product-initial-threshold" class="block text-sm font-medium text-slate-700">
            Seuil d'alerte <span class="text-slate-400">(facultatif)</span>
          </label>
          <input
            id="product-initial-threshold"
            v-model.number="form.low_stock_threshold"
            type="number"
            min="0"
            :class="inputClasses"
          />
          <p v-if="formTouched && formErrors.low_stock_threshold" class="mt-1 text-sm text-rose-600">
            {{ formErrors.low_stock_threshold }}
          </p>
        </div>
      </div>

      <label for="product-image" class="mt-3 block text-sm font-medium text-slate-700">
        Image <span :class="isImageRequired ? 'text-rose-600' : 'text-slate-400'">({{ imageHint }})</span>
      </label>
      <img
        v-if="editingProduct?.image_url && !imageFile"
        :src="editingProduct.image_url"
        alt="Image actuelle du produit"
        class="mt-2 h-24 w-24 rounded-md border border-slate-200 object-cover"
      />
      <input
        id="product-image"
        type="file"
        accept="image/png,image/jpeg"
        class="mt-2 block w-full text-sm text-slate-600"
        @change="handleImageChange"
      />
      <p v-if="formTouched && formErrors.image" class="mt-1 text-sm text-rose-600">{{ formErrors.image }}</p>

      <p v-if="formErrorMessage" class="mt-3 text-sm text-rose-600">{{ formErrorMessage }}</p>

      <template #footer>
        <AppButton variant="secondary" @click="isFormOpen = false">Annuler</AppButton>
        <AppButton :loading="isSubmitting" @click="handleSubmit">
          {{ isEditing ? 'Enregistrer' : 'Ajouter' }}
        </AppButton>
      </template>
    </BaseModal>

    <BaseModal v-if="stockProduct" title="Ajuster le stock" @close="stockProduct = null">
      <p class="text-sm text-slate-500">
        {{ stockProduct.name }} — cette correction ne remet pas le produit en validation admin.
      </p>

      <label for="stock-quantity" class="mt-3 block text-sm font-medium text-slate-700">Quantité en stock</label>
      <input id="stock-quantity" v-model.number="stockForm.stock_quantity" type="number" min="0" :class="inputClasses" />
      <p v-if="stockTouched && stockErrors.stock_quantity" class="mt-1 text-sm text-rose-600">
        {{ stockErrors.stock_quantity }}
      </p>

      <label for="stock-threshold" class="mt-3 block text-sm font-medium text-slate-700">
        Seuil d'alerte <span class="text-slate-400">(vide = pas d'alerte)</span>
      </label>
      <input
        id="stock-threshold"
        v-model.number="stockForm.low_stock_threshold"
        type="number"
        min="0"
        :class="inputClasses"
      />
      <p v-if="stockTouched && stockErrors.low_stock_threshold" class="mt-1 text-sm text-rose-600">
        {{ stockErrors.low_stock_threshold }}
      </p>

      <p v-if="stockErrorMessage" class="mt-3 text-sm text-rose-600">{{ stockErrorMessage }}</p>

      <template #footer>
        <AppButton variant="secondary" @click="stockProduct = null">Annuler</AppButton>
        <AppButton :loading="isStockSubmitting" @click="handleStockSubmit">Enregistrer</AppButton>
      </template>
    </BaseModal>
  </div>
</template>
