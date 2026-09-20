<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import {
  deleteImage,
  fetchProfile,
  updateOpeningHours,
  updateProfile,
  uploadImages,
} from '@/api/professionalProfile'
import AppButton from '@/shared/components/AppButton.vue'
import LocationSelect from '@/shared/components/LocationSelect.vue'
import { homePathForRole, useAuthStore } from '@/stores/auth'
import { BENIN_PHONE_ERROR, normalizeBeninPhone } from '@/utils/beninPhone'
import type { OpeningHourPayload } from '@/api/professionalProfile'
import {
  MISSING_FIELD_LABELS,
  type ProfessionalProfile,
  type ProfessionalSpace,
} from '@/types/profile'
import type { ProfileStatus } from '@/types/user'
import { extractApiErrorMessage } from '@/utils/apiError'

// Écran de profil unique des deux espaces professionnels (Garagiste et Market
// Space) : la prop `space` ne change que le préfixe d'URL de l'API. Tant que
// le profil est incomplet, c'est la seule page accessible (CLAUDE.md §5,
// ajout v0.20) ; une fois complet, il sert d'écran « Mon profil ».
//
// Une section par endpoint backend (informations, horaires, photos), chacune
// avec son propre bouton « Enregistrer » et son propre message.
const props = defineProps<{ space: ProfessionalSpace }>()

const auth = useAuthStore()
const router = useRouter()

const profile = ref<ProfessionalProfile | null>(null)
const status = ref<ProfileStatus | null>(null)
const loadError = ref<string | null>(null)

// `reactive` : objet dont chaque propriété est réactive, pratique pour un
// formulaire (on écrit `info.name` directement, sans `.value`). Les champs
// numériques restent des chaînes tant qu'ils sont dans un <input> ; on les
// convertit à l'envoi.
const info = reactive({
  name: '',
  description: '',
  address: '',
  phone: '',
  latitude: '',
  longitude: '',
  department_id: null as number | null,
  commune_id: null as number | null,
  arrondissement_id: null as number | null,
  neighborhood: null as string | null,
})

// Message d'erreur propre au champ téléphone (affiché sous le champ).
const phoneError = ref<string | null>(null)

const hours = ref<OpeningHourPayload[]>([])
const DAY_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']

// État de chaque section : chargement du bouton, message de succès, erreur.
function sectionState() {
  return reactive({ saving: false, success: null as string | null, error: null as string | null })
}
const infoState = sectionState()
const hoursState = sectionState()
const photosState = sectionState()

const missingLabels = computed(() =>
  (status.value?.missing_fields ?? []).map((field) => MISSING_FIELD_LABELS[field] ?? field),
)

// « HH:MM:SS » (renvoyé par la base) -> « HH:MM » (attendu par <input type="time">).
function toTimeInput(value: string | null): string | null {
  return value ? value.slice(0, 5) : null
}

function applyProfile(loaded: ProfessionalProfile, loadedStatus: ProfileStatus): void {
  profile.value = loaded
  status.value = loadedStatus
  info.name = loaded.name
  info.description = loaded.description ?? ''
  info.address = loaded.address
  info.phone = loaded.phone ?? ''
  info.latitude = loaded.latitude ?? ''
  info.longitude = loaded.longitude ?? ''
  info.department_id = loaded.department_id
  info.commune_id = loaded.commune_id
  info.arrondissement_id = loaded.arrondissement_id
  info.neighborhood = loaded.neighborhood

  // Horaires : ceux déjà enregistrés, sinon une semaine type modifiable
  // (lundi-samedi 08:00-18:00, dimanche fermé) pour ne pas partir de zéro.
  hours.value = DAY_LABELS.map((_, index) => {
    const saved = loaded.opening_hours.find((hour) => hour.day_of_week === index + 1)
    if (saved) {
      return {
        day_of_week: saved.day_of_week,
        is_closed: saved.is_closed,
        opens_at: toTimeInput(saved.opens_at),
        closes_at: toTimeInput(saved.closes_at),
      }
    }
    const isSunday = index === 6
    return {
      day_of_week: index + 1,
      is_closed: isSunday,
      opens_at: isSunday ? null : '08:00',
      closes_at: isSunday ? null : '18:00',
    }
  })
}

// Recharge profil + statut après chaque enregistrement, met le store à jour
// (le menu et le garde de navigation en dépendent) et, à la première
// complétion, renvoie le professionnel vers son espace.
async function reload(): Promise<void> {
  const wasIncomplete = auth.mustCompleteProfile
  const { profile: loaded, status: loadedStatus } = await fetchProfile(props.space)
  applyProfile(loaded, loadedStatus)
  auth.updateProfileStatus(loadedStatus)

  if (wasIncomplete && loadedStatus.is_complete && auth.user) {
    await router.replace(homePathForRole(auth.user.role))
  }
}

onMounted(async () => {
  try {
    await reload()
  } catch (error) {
    loadError.value = extractApiErrorMessage(error, 'Impossible de charger le profil.')
  }
})

async function runSection(
  state: ReturnType<typeof sectionState>,
  successMessage: string,
  action: () => Promise<void>,
): Promise<void> {
  state.saving = true
  state.success = null
  state.error = null
  try {
    await action()
    await reload()
    state.success = successMessage
  } catch (error) {
    state.error = extractApiErrorMessage(error, 'Enregistrement impossible.')
  } finally {
    state.saving = false
  }
}

function saveInfo(): Promise<void> | void {
  // Validation avant envoi : le champ est un <input type="tel">, que le
  // navigateur ne vérifie pas (n'importe quel texte passe). On normalise
  // (espaces/tirets retirés) et on envoie la forme canonique.
  const phone = normalizeBeninPhone(info.phone)
  if (phone === null) {
    phoneError.value = BENIN_PHONE_ERROR
    return
  }
  phoneError.value = null
  info.phone = phone

  return runSection(infoState, 'Informations enregistrées.', async () => {
    await updateProfile(props.space, {
      name: info.name,
      description: info.description || null,
      address: info.address,
      phone,
      latitude: Number(info.latitude),
      longitude: Number(info.longitude),
      // Les champs de localisation sont validés côté serveur (422 si absents) ;
      // on les envoie tels quels plutôt que de les bloquer ici.
      department_id: info.department_id as number,
      commune_id: info.commune_id as number,
      arrondissement_id: info.arrondissement_id as number,
      neighborhood: info.neighborhood ?? '',
    })
  })
}

function saveHours(): Promise<void> {
  return runSection(hoursState, 'Horaires enregistrés.', () =>
    updateOpeningHours(
      props.space,
      hours.value.map((hour) => ({
        ...hour,
        opens_at: hour.is_closed ? null : hour.opens_at,
        closes_at: hour.is_closed ? null : hour.closes_at,
      })),
    ),
  )
}

async function onFilesSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  if (files.length === 0) return
  await runSection(photosState, 'Photo(s) ajoutée(s).', () => uploadImages(props.space, files))
}

function removeImage(imageId: number): Promise<void> {
  return runSection(photosState, 'Photo supprimée.', () => deleteImage(props.space, imageId))
}

// Position via l'API de géolocalisation du navigateur (nécessite l'accord de
// l'utilisateur ; https ou localhost). Pas de carte pour l'instant : c'est le
// point ouvert du CLAUDE.md §7 sur l'affichage cartographique.
const isLocating = ref(false)
function useMyPosition(): void {
  if (!navigator.geolocation) {
    infoState.error = "La géolocalisation n'est pas disponible sur ce navigateur."
    return
  }
  isLocating.value = true
  navigator.geolocation.getCurrentPosition(
    (position) => {
      info.latitude = position.coords.latitude.toFixed(7)
      info.longitude = position.coords.longitude.toFixed(7)
      isLocating.value = false
    },
    () => {
      infoState.error =
        'Position introuvable. Autorisez la géolocalisation ou saisissez les coordonnées.'
      isLocating.value = false
    },
  )
}

const inputClasses =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900'
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <p v-if="loadError" class="text-sm text-red-600">{{ loadError }}</p>

    <template v-if="profile && status">
      <!-- Bandeau de complétude -->
      <div
        v-if="!status.is_complete"
        class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
      >
        <p class="font-semibold">Complétez votre profil pour accéder à votre espace.</p>
        <p class="mt-1">Il manque encore : {{ missingLabels.join(', ') }}.</p>
      </div>
      <div v-else class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">
        Votre profil est complet.
      </div>

      <!-- Informations, localisation et position -->
      <form
        class="space-y-4 rounded-lg border border-slate-200 bg-white p-6"
        @submit.prevent="saveInfo"
      >
        <h2 class="text-lg font-semibold text-slate-900">Informations et localisation</h2>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            Nom
            <input v-model="info.name" type="text" required maxlength="255" :class="inputClasses" />
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Téléphone
            <input
              v-model="info.phone"
              type="tel"
              required
              maxlength="30"
              placeholder="+229 01 23 45 67 89"
              :aria-invalid="phoneError !== null"
              :class="[inputClasses, phoneError ? 'border-red-500' : '']"
              @input="phoneError = null"
            />
            <span v-if="phoneError" class="mt-1 block text-xs font-normal text-red-600">{{
              phoneError
            }}</span>
          </label>
          <label class="block text-sm font-medium text-slate-700 sm:col-span-2">
            Adresse
            <input
              v-model="info.address"
              type="text"
              required
              maxlength="255"
              :class="inputClasses"
            />
          </label>
          <label class="block text-sm font-medium text-slate-700 sm:col-span-2">
            Description (facultative)
            <textarea v-model="info.description" rows="3" maxlength="2000" :class="inputClasses" />
          </label>
        </div>

        <!-- Le composant ne se monte qu'ici, une fois le profil chargé, pour
             recevoir les valeurs déjà enregistrées dès son premier rendu. -->
        <LocationSelect
          v-model:department-id="info.department_id"
          v-model:commune-id="info.commune_id"
          v-model:arrondissement-id="info.arrondissement_id"
          v-model:neighborhood="info.neighborhood"
        />

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block text-sm font-medium text-slate-700">
            Latitude
            <input
              v-model="info.latitude"
              type="number"
              step="any"
              min="-90"
              max="90"
              required
              :class="inputClasses"
            />
          </label>
          <label class="block text-sm font-medium text-slate-700">
            Longitude
            <input
              v-model="info.longitude"
              type="number"
              step="any"
              min="-180"
              max="180"
              required
              :class="inputClasses"
            />
          </label>
        </div>
        <AppButton variant="secondary" :loading="isLocating" @click="useMyPosition">
          Utiliser ma position
        </AppButton>

        <div class="flex items-center gap-3">
          <AppButton type="submit" :loading="infoState.saving">Enregistrer</AppButton>
          <span v-if="infoState.success" class="text-sm text-green-700">{{
            infoState.success
          }}</span>
          <span v-if="infoState.error" class="text-sm text-red-600">{{ infoState.error }}</span>
        </div>
      </form>

      <!-- Horaires d'ouverture -->
      <form
        class="space-y-4 rounded-lg border border-slate-200 bg-white p-6"
        @submit.prevent="saveHours"
      >
        <h2 class="text-lg font-semibold text-slate-900">Horaires d'ouverture</h2>
        <p class="text-sm text-slate-500">
          Les 7 jours sont obligatoires : cochez « Fermé » pour un jour non travaillé.
        </p>
        <div
          v-for="(hour, index) in hours"
          :key="hour.day_of_week"
          class="grid items-center gap-3 sm:grid-cols-[8rem_auto_1fr_1fr]"
        >
          <span class="text-sm font-medium text-slate-700">{{ DAY_LABELS[index] }}</span>
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="hour.is_closed" type="checkbox" />
            Fermé
          </label>
          <input
            v-model="hour.opens_at"
            type="time"
            :disabled="hour.is_closed"
            :required="!hour.is_closed"
            :class="inputClasses"
          />
          <input
            v-model="hour.closes_at"
            type="time"
            :disabled="hour.is_closed"
            :required="!hour.is_closed"
            :class="inputClasses"
          />
        </div>
        <div class="flex items-center gap-3">
          <AppButton type="submit" :loading="hoursState.saving">Enregistrer les horaires</AppButton>
          <span v-if="hoursState.success" class="text-sm text-green-700">{{
            hoursState.success
          }}</span>
          <span v-if="hoursState.error" class="text-sm text-red-600">{{ hoursState.error }}</span>
        </div>
      </form>

      <!-- Photos -->
      <section class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Photos</h2>
        <p class="text-sm text-slate-500">
          Au moins une photo du local (JPG ou PNG, 5 Mo maximum chacune). La dernière photo ne peut
          pas être supprimée.
        </p>
        <div v-if="profile.images.length" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <figure v-for="image in profile.images" :key="image.id" class="space-y-1">
            <img
              :src="image.url"
              alt="Photo du local"
              class="h-28 w-full rounded-md object-cover"
            />
            <AppButton
              variant="danger"
              :disabled="photosState.saving"
              @click="removeImage(image.id)"
            >
              Supprimer
            </AppButton>
          </figure>
        </div>
        <input
          type="file"
          accept="image/jpeg,image/png"
          multiple
          :disabled="photosState.saving"
          class="block text-sm text-slate-700"
          @change="onFilesSelected"
        />
        <p v-if="photosState.success" class="text-sm text-green-700">{{ photosState.success }}</p>
        <p v-if="photosState.error" class="text-sm text-red-600">{{ photosState.error }}</p>
      </section>
    </template>
  </div>
</template>
