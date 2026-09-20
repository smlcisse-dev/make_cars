<script setup lang="ts">
import { onMounted, ref } from 'vue'

import http from '@/api/http'
import AppButton from '@/shared/components/AppButton.vue'
import LocationSelect from '@/shared/components/LocationSelect.vue'
import type { LocationValue } from '@/types/location'
import { extractApiErrorMessage } from '@/utils/apiError'

// Page de TEST temporaire pour vérifier les selects en cascade (CLAUDE.md §5,
// ajout v0.19). À supprimer quand <LocationSelect> sera intégré au vrai écran
// de profil Garagiste : ce dernier réutilisera le composant tel quel.
//
// Le backend exige `name` et `address` sur PUT /garage/profile (mise à jour
// complète du profil) : on relit donc le profil et on les renvoie inchangés,
// avec la localisation modifiée.
interface GarageProfile extends LocationValue {
  name: string
  description: string | null
  address: string
  latitude: string | null
  longitude: string | null
  phone: string | null
}

const profile = ref<GarageProfile | null>(null)
const isSaving = ref(false)
const errorMessage = ref<string | null>(null)
const successMessage = ref<string | null>(null)

onMounted(async () => {
  try {
    const response = await http.get<{ data: GarageProfile }>('/garage/profile')
    profile.value = response.data.data
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Impossible de charger le profil garage.')
  }
})

async function save(): Promise<void> {
  if (!profile.value) return

  isSaving.value = true
  errorMessage.value = null
  successMessage.value = null

  try {
    const { name, description, address, latitude, longitude, phone } = profile.value
    const response = await http.put<{ data: GarageProfile }>('/garage/profile', {
      name,
      description,
      address,
      latitude,
      longitude,
      phone,
      department_id: profile.value.department_id,
      commune_id: profile.value.commune_id,
      arrondissement_id: profile.value.arrondissement_id,
      neighborhood: profile.value.neighborhood,
    })
    profile.value = response.data.data
    successMessage.value = 'Localisation enregistrée.'
  } catch (error) {
    errorMessage.value = extractApiErrorMessage(error, 'Enregistrement impossible.')
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl space-y-4 rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Localisation (page de test)</h2>
    <p class="text-sm text-slate-500">
      Vérification des selects en cascade Département → Commune → Arrondissement.
    </p>

    <!-- v-if : le composant ne se monte qu'une fois le profil chargé, pour
         qu'il reçoive les valeurs déjà enregistrées dès son premier rendu. -->
    <form v-if="profile" class="space-y-4" @submit.prevent="save">
      <LocationSelect
        v-model:department-id="profile.department_id"
        v-model:commune-id="profile.commune_id"
        v-model:arrondissement-id="profile.arrondissement_id"
        v-model:neighborhood="profile.neighborhood"
      />
      <AppButton type="submit" :loading="isSaving">Enregistrer</AppButton>
    </form>

    <p v-if="successMessage" class="text-sm text-green-700">{{ successMessage }}</p>
    <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
  </div>
</template>
