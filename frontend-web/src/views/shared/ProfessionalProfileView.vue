<script setup lang="ts">
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'

import {
  deleteImage,
  fetchBusinessRegistrationDocumentBlob,
  fetchIdentityCertificateDocumentBlob,
  fetchProfile,
  submitRegistration,
  updateLegalInfo,
  updateOpeningHours,
  updateProfile,
  uploadBusinessRegistrationDocument,
  uploadIdentityCertificateDocument,
  uploadImages,
} from '@/api/professionalProfile'
import AppButton from '@/shared/components/AppButton.vue'
import LegalDocumentField from '@/shared/components/LegalDocumentField.vue'
import LocationSelect from '@/shared/components/LocationSelect.vue'
import { useAuthStore } from '@/stores/auth'
import { BENIN_PHONE_ERROR, normalizeBeninPhone } from '@/utils/beninPhone'
import type { LoadedProfile, OpeningHourPayload } from '@/api/professionalProfile'
import {
  MISSING_FIELD_LABELS,
  MISSING_LEGAL_FIELD_LABELS,
  type LegalInfo,
  type LegalStatus,
  type ProfessionalProfile,
  type ProfileRegistrationMeta,
} from '@/types/profile'
import type { ProfessionalSpace } from '@/types/professionalSpace'
import type { ProfileStatus } from '@/types/user'
import { extractApiErrorMessage, extractValidationErrors } from '@/utils/apiError'

// Écran de profil unique des deux espaces professionnels (Garagiste et Market
// Space) : la prop `space` ne change que le préfixe d'URL de l'API. C'est la
// seule page accessible tant que le dossier d'inscription n'est pas approuvé
// ou que le profil est incomplet (CLAUDE.md §5, ajouts v0.20 et v0.26) ; il
// porte aussi le dossier lui-même (informations légales, soumission). Une
// fois le compte validé, il sert d'écran « Mon profil ».
//
// Une section par endpoint backend (informations, horaires, photos,
// informations légales, soumission), chacune avec son propre bouton et son
// propre message.
const props = defineProps<{ space: ProfessionalSpace }>()

const auth = useAuthStore()

const profile = ref<ProfessionalProfile | null>(null)
const status = ref<ProfileStatus | null>(null)
const legalStatus = ref<LegalStatus | null>(null)
const registration = ref<ProfileRegistrationMeta | null>(null)
const legal = ref<LegalInfo | null>(null)
const loadError = ref<string | null>(null)

// Pendant l'examen (`pending`), tout le profil est en lecture seule : le
// backend refuse de toute façon chaque écriture (409
// `registration_under_review`), l'interface évite juste d'y inviter.
const isUnderReview = computed(() => registration.value?.status === 'pending')
// Informations légales figées pendant l'examen, puis définitivement après
// approbation (409 `legal_info_locked` côté backend).
const isLegalLocked = computed(
  () => registration.value?.status === 'pending' || registration.value?.status === 'approved',
)
// La soumission n'a de sens que depuis ces deux statuts (isSubmittable() côté
// backend).
const canSubmit = computed(
  () =>
    registration.value?.status === 'profile_incomplete' ||
    registration.value?.status === 'rejected',
)
const isReadyToSubmit = computed(
  () => status.value?.is_complete === true && legalStatus.value?.is_complete === true,
)

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

// Informations légales privées (CLAUDE.md §5, ajout v0.26). Même règles que
// le backend (UpdateLegalInfoRequest), vérifiées avant l'envoi ; le backend
// reste l'autorité.
const legalForm = reactive({
  business_registration_number: '',
  ifu: '',
  npi: '',
})
// Erreur à afficher sous chaque champ, indexée par nom de champ.
const legalErrors = ref<Record<string, string>>({})
const IFU_PATTERN = /^\d{13}$/
const NPI_PATTERN = /^\d{10}$/
const DAY_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche']

// État de chaque section : chargement du bouton, message de succès, erreur.
function sectionState() {
  return reactive({ saving: false, success: null as string | null, error: null as string | null })
}
const infoState = sectionState()
const hoursState = sectionState()
const photosState = sectionState()
const legalState = sectionState()
const submitState = sectionState()

const missingLabels = computed(() =>
  (status.value?.missing_fields ?? []).map((field) => MISSING_FIELD_LABELS[field] ?? field),
)
const missingLegalLabels = computed(() =>
  (legalStatus.value?.missing_fields ?? []).map(
    (field) => MISSING_LEGAL_FIELD_LABELS[field] ?? field,
  ),
)
// Tout ce qui manque encore avant de pouvoir soumettre (profil ET dossier).
const allMissingLabels = computed(() => [...missingLabels.value, ...missingLegalLabels.value])

function formatDateTime(iso: string | null): string {
  return iso
    ? new Date(iso).toLocaleString('fr-FR', {
        dateStyle: 'long',
        timeStyle: 'short',
      })
    : ''
}

// « HH:MM:SS » (renvoyé par la base) -> « HH:MM » (attendu par <input type="time">).
function toTimeInput(value: string | null): string | null {
  return value ? value.slice(0, 5) : null
}

function applyProfile(data: LoadedProfile): void {
  const loaded = data.profile
  profile.value = loaded
  status.value = data.status
  legalStatus.value = data.legalStatus
  registration.value = data.registration
  legal.value = data.legal
  legalForm.business_registration_number = data.legal?.business_registration_number ?? ''
  legalForm.ifu = data.legal?.ifu ?? ''
  legalForm.npi = data.legal?.npi ?? ''

  // `?? ''` : un profil tout juste créé a encore `name`/`address` à null.
  info.name = loaded.name ?? ''
  info.description = loaded.description ?? ''
  info.address = loaded.address ?? ''
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

// Recharge profil + statuts après chaque enregistrement et met le store à
// jour (le menu et le garde de navigation en dépendent). Pas de redirection
// vers l'accueil quand le profil devient complet : depuis v0.26, un profil
// complet n'est pas un compte validé — le dossier doit encore être soumis et
// approuvé. Le menu complet réapparaît de lui-même à l'approbation
// (`mustStayOnProfile` est un `computed`).
async function reload(): Promise<void> {
  const loaded = await fetchProfile(props.space)
  applyProfile(loaded)
  auth.updateProfileStatus(loaded.status)
  if (loaded.registration) auth.updateRegistrationFromProfile(loaded.registration)
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

// Variantes `disabled:` de Tailwind : `disabled:bg-slate-100` n'applique le
// fond gris que lorsque le champ est désactivé (pseudo-classe CSS
// `:disabled`). Elle s'applique aussi quand c'est un <fieldset disabled>
// parent qui désactive le champ : un seul attribut sur le fieldset suffit à
// griser tous les champs du profil verrouillé, sans liaison supplémentaire.
// Mêmes classes que les <select> de LocationSelect, pour un rendu identique.
const inputClasses =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400'

function validateLegal(): Record<string, string> {
  const errors: Record<string, string> = {}
  const rccm = legalForm.business_registration_number.trim()
  if (rccm === '') {
    errors.business_registration_number = 'Le numéro RCCM est obligatoire.'
  } else if (rccm.length > 100) {
    errors.business_registration_number = 'Le numéro RCCM ne doit pas dépasser 100 caractères.'
  }
  if (!IFU_PATTERN.test(legalForm.ifu.trim())) {
    errors.ifu = "L'IFU doit comporter exactement 13 chiffres."
  }
  if (!NPI_PATTERN.test(legalForm.npi.trim())) {
    errors.npi = 'Le NPI doit comporter exactement 10 chiffres.'
  }
  return errors
}

// Pas `runSection` ici : en plus du message général, un 422 doit afficher ses
// erreurs sous chaque champ.
async function saveLegal(): Promise<void> {
  legalErrors.value = validateLegal()
  // `Object.keys` liste les clés d'un objet : au moins une erreur -> on
  // n'envoie rien.
  if (Object.keys(legalErrors.value).length > 0) return

  legalState.saving = true
  legalState.success = null
  legalState.error = null
  try {
    await updateLegalInfo(props.space, {
      business_registration_number: legalForm.business_registration_number.trim(),
      ifu: legalForm.ifu.trim(),
      npi: legalForm.npi.trim(),
    })
    await reload()
    legalState.success = 'Informations légales enregistrées.'
  } catch (error) {
    legalErrors.value = extractValidationErrors(error)
    legalState.error = extractApiErrorMessage(error, 'Enregistrement impossible.')
  } finally {
    legalState.saving = false
  }
}

// Justificatifs privés (registre de commerce, CIP) : chaque bloc
// LegalDocumentField gère son propre envoi ; on recharge ensuite le profil
// pour mettre à jour `meta.legal` et la liste de ce qui manque.
async function onLegalDocumentUploaded(): Promise<void> {
  try {
    await reload()
  } catch (error) {
    loadError.value = extractApiErrorMessage(error, 'Impossible de recharger le profil.')
  }
}

// Éléments manquants renvoyés par un 422 `registration_incomplete` (état du
// serveur, qui peut différer de l'état affiché si la page est ancienne).
const submitMissingLabels = ref<string[]>([])

async function submitDossier(): Promise<void> {
  if (
    !window.confirm(
      "Une fois soumis, votre profil ne pourra plus être modifié pendant l'examen. Soumettre votre dossier ?",
    )
  ) {
    return
  }

  submitState.saving = true
  submitState.success = null
  submitState.error = null
  submitMissingLabels.value = []
  try {
    await submitRegistration(props.space)
    await reload()
    // Met à jour le statut du dossier dans le store (menu, garde).
    await auth.refreshUser()
    submitState.success = 'Votre dossier a été soumis pour validation.'
  } catch (error) {
    submitState.error = extractApiErrorMessage(error, 'Soumission impossible.')
    const data = axios.isAxiosError(error) ? error.response?.data : undefined
    if (data?.code === 'registration_incomplete') {
      submitMissingLabels.value = [
        ...((data.missing_fields ?? []) as string[]).map(
          (field) => MISSING_FIELD_LABELS[field] ?? field,
        ),
        ...((data.missing_legal_fields ?? []) as string[]).map(
          (field) => MISSING_LEGAL_FIELD_LABELS[field] ?? field,
        ),
      ]
    }
  } finally {
    submitState.saving = false
  }
}

// Bouton « Actualiser » du bandeau « en cours d'examen » : relit l'utilisateur
// (statut du dossier) puis le profil. Si l'admin a approuvé entre-temps, le
// menu complet réapparaît tout seul (réactivité), sans redirection forcée.
const isRefreshing = ref(false)
const refreshError = ref<string | null>(null)
async function refreshDossier(): Promise<void> {
  isRefreshing.value = true
  refreshError.value = null
  try {
    await auth.refreshUser()
    await reload()
  } catch (error) {
    refreshError.value = extractApiErrorMessage(error, 'Actualisation impossible.')
  } finally {
    isRefreshing.value = false
  }
}
</script>

<template>
  <div class="max-w-3xl space-y-6">
    <p v-if="loadError" class="text-sm text-red-600">{{ loadError }}</p>

    <template v-if="profile && status">
      <!-- Bandeau d'état du dossier d'inscription (CLAUDE.md §5, ajout v0.26) -->
      <div
        v-if="registration?.status === 'profile_incomplete'"
        class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
      >
        <p class="font-semibold">
          Complétez votre profil et vos informations légales, puis soumettez votre dossier pour
          validation.
        </p>
        <p v-if="allMissingLabels.length" class="mt-1">
          Il manque encore : {{ allMissingLabels.join(', ') }}.
        </p>
      </div>
      <div
        v-else-if="registration?.status === 'pending'"
        class="rounded-lg border border-sky-300 bg-sky-50 p-4 text-sm text-sky-900"
      >
        <p class="font-semibold">
          Votre dossier a été soumis le
          {{ formatDateTime(registration.submitted_at) }} et est en cours d'examen. Réponse sous 24
          h.
        </p>
        <p class="mt-1">Votre profil ne peut pas être modifié pendant l'examen.</p>
        <div class="mt-3 flex items-center gap-3">
          <AppButton variant="secondary" :loading="isRefreshing" @click="refreshDossier">
            Actualiser
          </AppButton>
          <span v-if="refreshError" class="text-sm text-red-600">{{ refreshError }}</span>
        </div>
      </div>
      <div
        v-else-if="registration?.status === 'rejected'"
        class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900"
      >
        <p class="font-semibold">Votre dossier a été refusé.</p>
        <p v-if="registration.rejection_reason" class="mt-2 rounded-md bg-white p-3 text-red-800">
          <span class="font-semibold">Motif :</span>
          {{ registration.rejection_reason }}
        </p>
        <p class="mt-2">Corrigez les éléments concernés et soumettez à nouveau.</p>
      </div>
      <!-- Dossier approuvé (ou absent) : bandeau de complétude du profil. -->
      <template v-else>
        <div
          v-if="!status.is_complete"
          class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
        >
          <p class="font-semibold">Complétez votre profil pour accéder à votre espace.</p>
          <p class="mt-1">Il manque encore : {{ missingLabels.join(', ') }}.</p>
        </div>
        <div
          v-else
          class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800"
        >
          Votre profil est complet.
        </div>
      </template>

      <!-- Informations, localisation et position -->
      <form
        class="space-y-4 rounded-lg border border-slate-200 bg-white p-6"
        @submit.prevent="saveInfo"
      >
        <h2 class="text-lg font-semibold text-slate-900">Informations et localisation</h2>

        <!-- <fieldset> regroupe des champs de formulaire ; son attribut natif
             `disabled` désactive d'un coup tous les champs et boutons qu'il
             contient, sans avoir à le répéter sur chacun. `:disabled="..."`
             (liaison Vue) le rend dynamique : vrai pendant l'examen. -->
        <fieldset :disabled="isUnderReview" class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">
              Nom
              <input
                v-model="info.name"
                type="text"
                required
                maxlength="255"
                :class="inputClasses"
              />
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
              <textarea
                v-model="info.description"
                rows="3"
                maxlength="2000"
                :class="inputClasses"
              />
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
        </fieldset>

        <div v-if="!isUnderReview" class="flex items-center gap-3">
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
        <fieldset :disabled="isUnderReview" class="space-y-4">
          <div
            v-for="(hour, index) in hours"
            :key="hour.day_of_week"
            class="grid items-center gap-3 sm:grid-cols-[8rem_auto_1fr_1fr]"
          >
            <span class="text-sm font-medium text-slate-700">{{ DAY_LABELS[index] }}</span>
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="hour.is_closed" type="checkbox" class="disabled:cursor-not-allowed" />
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
        </fieldset>
        <div v-if="!isUnderReview" class="flex items-center gap-3">
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
              v-if="!isUnderReview"
              variant="danger"
              :disabled="photosState.saving"
              @click="removeImage(image.id)"
            >
              Supprimer
            </AppButton>
          </figure>
        </div>
        <input
          v-if="!isUnderReview"
          type="file"
          accept="image/jpeg,image/png"
          multiple
          :disabled="photosState.saving"
          class="block text-sm text-slate-700"
          @change="onFilesSelected"
        />
        <p v-if="photosState.success" class="text-sm text-green-700">
          {{ photosState.success }}
        </p>
        <p v-if="photosState.error" class="text-sm text-red-600">
          {{ photosState.error }}
        </p>
      </section>

      <!-- Informations légales (CLAUDE.md §5, ajout v0.26) -->
      <form
        v-if="legal"
        class="space-y-4 rounded-lg border border-slate-200 bg-white p-6"
        @submit.prevent="saveLegal"
      >
        <h2 class="text-lg font-semibold text-slate-900">Informations légales</h2>
        <p class="text-sm text-slate-500">
          Ces informations ne sont visibles que par l'équipe Make Cars.
        </p>
        <p v-if="registration?.status === 'approved'" class="text-sm text-slate-600">
          Ces informations ne peuvent plus être modifiées depuis votre espace.
        </p>

        <fieldset :disabled="isLegalLocked" class="space-y-4">
          <label class="block text-sm font-medium text-slate-700">
            Numéro RCCM
            <input
              v-model="legalForm.business_registration_number"
              type="text"
              maxlength="100"
              :aria-invalid="!!legalErrors.business_registration_number"
              :class="[
                inputClasses,
                legalErrors.business_registration_number ? 'border-red-500' : '',
              ]"
            />
            <span
              v-if="legalErrors.business_registration_number"
              class="mt-1 block text-xs font-normal text-red-600"
              >{{ legalErrors.business_registration_number }}</span
            >
          </label>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">
              IFU
              <input
                v-model="legalForm.ifu"
                type="text"
                inputmode="numeric"
                maxlength="13"
                :aria-invalid="!!legalErrors.ifu"
                :class="[inputClasses, legalErrors.ifu ? 'border-red-500' : '']"
              />
              <span class="mt-1 block text-xs font-normal text-slate-500">
                13 chiffres, figurant sur votre attestation d'immatriculation IFU
              </span>
              <span v-if="legalErrors.ifu" class="mt-1 block text-xs font-normal text-red-600">{{
                legalErrors.ifu
              }}</span>
            </label>
            <label class="block text-sm font-medium text-slate-700">
              NPI
              <input
                v-model="legalForm.npi"
                type="text"
                inputmode="numeric"
                maxlength="10"
                :aria-invalid="!!legalErrors.npi"
                :class="[inputClasses, legalErrors.npi ? 'border-red-500' : '']"
              />
              <span class="mt-1 block text-xs font-normal text-slate-500">
                10 chiffres, en rouge sur votre Certificat d'Identification Personnelle (ANIP) — pas
                le « N° » à 14 chiffres en haut à gauche
              </span>
              <span v-if="legalErrors.npi" class="mt-1 block text-xs font-normal text-red-600">{{
                legalErrors.npi
              }}</span>
            </label>
          </div>
        </fieldset>

        <div v-if="!isLegalLocked" class="flex items-center gap-3">
          <AppButton type="submit" :loading="legalState.saving">
            Enregistrer les informations légales
          </AppButton>
          <span v-if="legalState.success" class="text-sm text-green-700">{{
            legalState.success
          }}</span>
          <span v-if="legalState.error" class="text-sm text-red-600">{{ legalState.error }}</span>
        </div>

        <!-- Justificatifs : envoyés dès leur sélection, comme les photos
             (endpoints séparés des champs ci-dessus). -->
        <LegalDocumentField
          title="Document du registre de commerce"
          help="PDF, JPG ou PNG, 10 Mo maximum."
          :has-document="legal.has_business_registration_document"
          :locked="isLegalLocked"
          :upload="(file) => uploadBusinessRegistrationDocument(space, file)"
          :fetch-blob="() => fetchBusinessRegistrationDocumentBlob(space)"
          @uploaded="onLegalDocumentUploaded"
        />
        <LegalDocumentField
          title="Certificat d'Identification Personnelle (CIP)"
          help="Le certificat délivré par l'ANIP, sur lequel figure votre NPI. Il sert uniquement à vérifier votre NPI et n'est visible que par l'équipe Make Cars. PDF, JPG ou PNG, 10 Mo maximum."
          :has-document="legal.has_identity_certificate_document"
          :locked="isLegalLocked"
          :upload="(file) => uploadIdentityCertificateDocument(space, file)"
          :fetch-blob="() => fetchIdentityCertificateDocumentBlob(space)"
          @uploaded="onLegalDocumentUploaded"
        />
      </form>

      <!-- Soumission du dossier : seulement depuis `profile_incomplete` ou
           `rejected` (isSubmittable() côté backend). -->
      <section v-if="canSubmit" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Soumettre mon dossier</h2>
        <p class="text-sm text-slate-500">
          L'équipe Make Cars examine votre profil et vos informations légales, puis valide votre
          compte ou vous indique ce qu'il faut corriger.
        </p>
        <p v-if="!isReadyToSubmit" class="text-sm text-amber-800">
          Avant de soumettre, complétez : {{ allMissingLabels.join(', ') }}.
        </p>
        <div class="flex items-center gap-3">
          <AppButton
            :disabled="!isReadyToSubmit"
            :loading="submitState.saving"
            @click="submitDossier"
          >
            Soumettre pour validation
          </AppButton>
          <span v-if="submitState.error" class="text-sm text-red-600">{{ submitState.error }}</span>
        </div>
        <p v-if="submitMissingLabels.length" class="text-sm text-red-600">
          Éléments manquants : {{ submitMissingLabels.join(', ') }}.
        </p>
      </section>
      <p v-if="submitState.success" class="text-sm text-green-700">
        {{ submitState.success }}
      </p>
    </template>
  </div>
</template>
