<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import {
  createGarageService,
  deleteGarageService,
  fetchGarageServices,
  updateGarageService,
  updateGarageServiceAvailability,
} from '@/api/garageServices'
import AppButton from '@/shared/components/AppButton.vue'
import AppPagination from '@/shared/components/AppPagination.vue'
import AppTable from '@/shared/components/AppTable.vue'
import type { TableColumn } from '@/shared/components/AppTable.vue'
import BaseModal from '@/shared/components/BaseModal.vue'
import StatusBadge from '@/shared/components/StatusBadge.vue'
import { SERVICE_CATEGORY_OPTIONS } from '@/types/garageService'
import type { GarageService, ServiceCategory } from '@/types/garageService'
import { extractApiErrorMessage } from '@/utils/apiError'
import { reviewStatusLabel, reviewStatusTone } from '@/utils/reviewStatus'
import { confirmAction } from '@/utils/confirmDialog'

const columns: TableColumn[] = [
  { key: 'name', label: 'Nom' },
  { key: 'category', label: 'Catégorie' },
  { key: 'price', label: 'Prix' },
  { key: 'is_active', label: 'Disponibilité' },
  { key: 'status', label: 'Statut' },
  { key: 'actions', label: '' },
]

const services = ref<GarageService[]>([])
const currentPage = ref(1)
const lastPage = ref(1)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)
const flashMessage = ref<string | null>(null)

async function loadServices(): Promise<void> {
  isLoading.value = true
  errorMessage.value = null

  try {
    const response = await fetchGarageServices(currentPage.value)
    services.value = response.data
    currentPage.value = response.meta.current_page
    lastPage.value = response.meta.last_page
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger les services. Réessayez.')
  } finally {
    isLoading.value = false
  }
}

function goToPage(page: number): void {
  currentPage.value = page
  loadServices()
}

onMounted(loadServices)

function formatPrice(price: string): string {
  return `${Number(price).toLocaleString('fr-FR')} FCFA`
}

// --- Disponibilité : indépendante de la validation admin (CLAUDE.md §5, v0.6).
const togglingServiceId = ref<number | null>(null)

async function handleToggleAvailability(service: GarageService): Promise<void> {
  togglingServiceId.value = service.id
  errorMessage.value = null

  try {
    const updated = await updateGarageServiceAvailability(service.id, !service.is_active)
    // On remplace la ligne en mémoire plutôt que de recharger toute la liste.
    services.value = services.value.map((item) => (item.id === updated.id ? updated : item))
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'La disponibilité n\'a pas pu être modifiée. Réessayez.')
  } finally {
    togglingServiceId.value = null
  }
}

// --- Suppression : simple confirmation, pas de motif à saisir.
async function handleDelete(service: GarageService): Promise<void> {
  const confirmed = await confirmAction({
    title: 'Supprimer le service',
    message: `Supprimer le service « ${service.name} » ?`,
    confirmLabel: 'Supprimer',
    variant: 'danger',
  })
  if (!confirmed) {
    return
  }

  errorMessage.value = null

  try {
    await deleteGarageService(service.id)
    flashMessage.value = 'Service supprimé.'
    // Si on vient de vider la dernière page, on recule d'une page.
    if (services.value.length === 1 && currentPage.value > 1) {
      currentPage.value -= 1
    }
    await loadServices()
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'La suppression a échoué. Réessayez.')
  }
}

// --- Modale de création / édition. `editingService` vaut null en création ;
// en édition, le formulaire est pré-rempli depuis la ligne en mémoire (il
// n'existe pas de GET /garage/services/{id}).
const isFormOpen = ref(false)
const editingService = ref<GarageService | null>(null)
const isSubmitting = ref(false)
const formErrorMessage = ref<string | null>(null)
const formTouched = ref(false)

const form = ref({
  name: '',
  description: '',
  category: '' as ServiceCategory | '',
  price: '' as number | '',
  duration_minutes: '' as number | '',
})
const imageFile = ref<File | null>(null)

const isEditing = computed(() => editingService.value !== null)

// Image obligatoire à la création ; en édition, l'image déjà présente
// suffit — pas besoin d'en re-uploader une (CLAUDE.md §5, ajout v0.23).
const isImageRequired = computed(() => !isEditing.value || !editingService.value?.image_url)
const imageHint = computed(() => {
  if (!isEditing.value) {
    return 'obligatoire à la création, JPG/PNG, 5 Mo max'
  }
  return isImageRequired.value ? 'obligatoire, JPG/PNG, 5 Mo max' : 'facultative, JPG/PNG, 5 Mo max'
})

// Champs requis non vides, prix ≥ 0, durée entière ≥ 1 (le backend revalide).
const fieldErrors = computed(() => ({
  name: form.value.name.trim() === '' ? 'Le nom est obligatoire.' : null,
  description: form.value.description.trim() === '' ? 'La description est obligatoire.' : null,
  category: form.value.category === '' ? 'Choisissez une catégorie.' : null,
  price:
    form.value.price === '' || Number(form.value.price) < 0 ? 'Indiquez un prix supérieur ou égal à 0.' : null,
  duration_minutes:
    form.value.duration_minutes === '' ||
    !Number.isInteger(Number(form.value.duration_minutes)) ||
    Number(form.value.duration_minutes) < 1
      ? 'Indiquez une durée d\'au moins 1 minute.'
      : null,
  image: isImageRequired.value && !imageFile.value ? 'Une image est obligatoire.' : null,
}))
const isFormValid = computed(() => Object.values(fieldErrors.value).every((error) => error === null))

function openCreateModal(): void {
  editingService.value = null
  form.value = { name: '', description: '', category: '', price: '', duration_minutes: '' }
  imageFile.value = null
  formErrorMessage.value = null
  formTouched.value = false
  isFormOpen.value = true
}

function openEditModal(service: GarageService): void {
  editingService.value = service
  form.value = {
    name: service.name,
    description: service.description,
    category: service.category,
    price: Number(service.price),
    duration_minutes: service.duration_minutes,
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

  const payload = {
    name: form.value.name.trim(),
    description: form.value.description.trim(),
    category: form.value.category as ServiceCategory,
    price: Number(form.value.price),
    duration_minutes: Number(form.value.duration_minutes),
  }

  try {
    const result = editingService.value
      ? await updateGarageService(editingService.value.id, payload, imageFile.value)
      : await createGarageService(payload, imageFile.value)

    isFormOpen.value = false
    flashMessage.value = result.message
    await loadServices()
  } catch (error) {
    formErrorMessage.value = extractApiErrorMessage(error, 'L\'enregistrement a échoué. Réessayez.')
  } finally {
    isSubmitting.value = false
  }
}

const inputClasses =
  'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none'
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-lg font-semibold text-slate-900">Services</h2>
        <p class="mt-1 text-sm text-slate-500">
          Votre catalogue de réparation. Toute création ou modification est soumise à validation admin avant d'être
          visible côté application.
        </p>
      </div>
      <AppButton @click="openCreateModal">Ajouter un service</AppButton>
    </div>

    <p v-if="flashMessage" class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ flashMessage }}
    </p>
    <p v-if="errorMessage" class="text-sm text-rose-600">{{ errorMessage }}</p>
    <p v-if="isLoading" class="text-sm text-slate-500">Chargement...</p>

    <template v-else>
      <AppTable :items="services" :columns="columns" @row-click="openEditModal">
        <template #empty>Aucun service pour le moment.</template>
        <template #cell-category="{ item }">
          {{ item.category_label }}
        </template>
        <template #cell-price="{ item }">
          {{ formatPrice(item.price) }}
        </template>
        <template #cell-is_active="{ item }">
          <!-- .stop : cliquer sur le toggle ne doit pas ouvrir la modale d'édition
               (l'événement de clic remonterait sinon jusqu'à la ligne <tr>). -->
          <label class="inline-flex cursor-pointer items-center gap-2" @click.stop>
            <input
              type="checkbox"
              class="h-4 w-4 rounded border-slate-300"
              :checked="item.is_active"
              :disabled="togglingServiceId === item.id"
              @change="handleToggleAvailability(item)"
            />
            <span class="text-xs text-slate-500">{{ item.is_active ? 'Disponible' : 'Indisponible' }}</span>
          </label>
        </template>
        <template #cell-status="{ item }">
          <div class="flex flex-wrap items-center gap-1">
            <StatusBadge :label="reviewStatusLabel(item.status)" :tone="reviewStatusTone(item.status)" />
            <StatusBadge v-if="!item.is_active" label="Inactif" tone="neutral" />
          </div>
          <p v-if="item.status === 'rejected' && item.rejection_reason" class="mt-1 text-xs text-rose-600">
            {{ item.rejection_reason }}
          </p>
        </template>
        <template #cell-actions="{ item }">
          <button
            type="button"
            class="text-sm text-rose-600 hover:text-rose-700"
            @click.stop="handleDelete(item)"
          >
            Supprimer
          </button>
        </template>
      </AppTable>

      <AppPagination
        v-if="services.length > 0"
        :current-page="currentPage"
        :last-page="lastPage"
        @update:current-page="goToPage"
      />
    </template>

    <BaseModal
      v-if="isFormOpen"
      :title="isEditing ? 'Modifier le service' : 'Ajouter un service'"
      @close="isFormOpen = false"
    >
      <p v-if="isEditing" class="mb-3 text-sm text-slate-500">
        Toute modification remet le service en attente de validation admin.
      </p>

      <label for="service-name" class="block text-sm font-medium text-slate-700">Nom</label>
      <input id="service-name" v-model="form.name" type="text" maxlength="255" :class="inputClasses" />
      <p v-if="formTouched && fieldErrors.name" class="mt-1 text-sm text-rose-600">{{ fieldErrors.name }}</p>

      <label for="service-description" class="mt-3 block text-sm font-medium text-slate-700">Description</label>
      <textarea
        id="service-description"
        v-model="form.description"
        rows="3"
        maxlength="500"
        :class="inputClasses"
      />
      <p v-if="formTouched && fieldErrors.description" class="mt-1 text-sm text-rose-600">
        {{ fieldErrors.description }}
      </p>

      <label for="service-category" class="mt-3 block text-sm font-medium text-slate-700">Catégorie</label>
      <select id="service-category" v-model="form.category" :class="inputClasses">
        <option value="" disabled>Choisir...</option>
        <option v-for="option in SERVICE_CATEGORY_OPTIONS" :key="option.value" :value="option.value">
          {{ option.label }}
        </option>
      </select>
      <p v-if="formTouched && fieldErrors.category" class="mt-1 text-sm text-rose-600">{{ fieldErrors.category }}</p>

      <div class="mt-3 grid grid-cols-2 gap-3">
        <div>
          <label for="service-price" class="block text-sm font-medium text-slate-700">Prix (FCFA)</label>
          <!-- v-model.number : convertit la saisie en nombre (sinon une chaîne). -->
          <input id="service-price" v-model.number="form.price" type="number" min="0" :class="inputClasses" />
          <p v-if="formTouched && fieldErrors.price" class="mt-1 text-sm text-rose-600">{{ fieldErrors.price }}</p>
        </div>
        <div>
          <label for="service-duration" class="block text-sm font-medium text-slate-700">Durée (minutes)</label>
          <input
            id="service-duration"
            v-model.number="form.duration_minutes"
            type="number"
            min="1"
            :class="inputClasses"
          />
          <p v-if="formTouched && fieldErrors.duration_minutes" class="mt-1 text-sm text-rose-600">
            {{ fieldErrors.duration_minutes }}
          </p>
        </div>
      </div>

      <label for="service-image" class="mt-3 block text-sm font-medium text-slate-700">
        Image <span :class="isImageRequired ? 'text-rose-600' : 'text-slate-400'">({{ imageHint }})</span>
      </label>
      <img
        v-if="editingService?.image_url && !imageFile"
        :src="editingService.image_url"
        alt="Image actuelle du service"
        class="mt-2 h-24 w-24 rounded-md border border-slate-200 object-cover"
      />
      <input
        id="service-image"
        type="file"
        accept="image/png,image/jpeg"
        class="mt-2 block w-full text-sm text-slate-600"
        @change="handleImageChange"
      />
      <p v-if="formTouched && fieldErrors.image" class="mt-1 text-sm text-rose-600">{{ fieldErrors.image }}</p>

      <p v-if="formErrorMessage" class="mt-3 text-sm text-rose-600">{{ formErrorMessage }}</p>

      <template #footer>
        <AppButton variant="secondary" @click="isFormOpen = false">Annuler</AppButton>
        <AppButton :loading="isSubmitting" @click="handleSubmit">
          {{ isEditing ? 'Enregistrer' : 'Ajouter' }}
        </AppButton>
      </template>
    </BaseModal>
  </div>
</template>
