<script setup lang="ts">
import { computed } from 'vue'

import type { RegistrationProfile } from '@/types/registration'
import { displayValue } from '@/utils/displayValue'

// Profil public d'une structure (garage ou boutique) : informations et
// localisation, horaires, photos. Partagé entre la fiche d'examen d'un
// dossier d'inscription et la fiche de supervision d'une structure, pour ne
// jamais dupliquer cet affichage. Lecture seule.
const props = defineProps<{
  profile: RegistrationProfile | null | undefined
  // Textes d'aide propres à l'écran appelant (facultatifs).
  photosHint?: string
  mapHint?: string
}>()

// Lien OpenStreetMap vers la position déclarée : simple lien externe, aucune
// carte intégrée (point ouvert CLAUDE.md §7). `null` sans coordonnées.
const mapUrl = computed(() => {
  const profile = props.profile
  if (!profile?.latitude || !profile.longitude) {
    return null
  }
  const { latitude: lat, longitude: lon } = profile
  return `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=17/${lat}/${lon}`
})

// Photos triées par position (la première est la photo principale).
const profileImages = computed(() => [...(props.profile?.images ?? [])].sort((a, b) => a.position - b.position))

// "08:00:00" → "08:00".
function formatTime(time: string | null): string {
  return time ? time.slice(0, 5) : '—'
}
</script>

<template>
  <!-- Structure (profil public) -->
  <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-slate-900">Structure (profil public)</h3>
    <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nom</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.name) }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Téléphone</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.phone) }}</dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Adresse</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.address) }}</dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Description</dt>
        <dd class="mt-1 whitespace-pre-line text-sm text-slate-700">
          {{ profile?.description || 'Aucune description' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Département</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.department_name) }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Commune</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.commune_name) }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Arrondissement</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.arrondissement_name) }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Quartier</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.neighborhood) }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Latitude</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.latitude) }}</dd>
      </div>
      <div>
        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Longitude</dt>
        <dd class="mt-1 text-sm text-slate-700">{{ displayValue(profile?.longitude) }}</dd>
      </div>
    </dl>
    <div v-if="mapUrl" class="mt-4">
      <!-- Lien externe simple : rel="noopener" empêche l'onglet ouvert
           d'agir sur celui-ci (bonne pratique avec target="_blank"). -->
      <a
        :href="mapUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
      >
        Voir sur la carte ↗
      </a>
      <p v-if="mapHint" class="mt-1 text-xs text-slate-500">{{ mapHint }}</p>
    </div>
  </section>

  <!-- Horaires -->
  <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-slate-900">Horaires</h3>
    <p v-if="!profile?.opening_hours?.length" class="mt-3 text-sm text-slate-500">Non fournis.</p>
    <ul v-else class="mt-3 divide-y divide-slate-100">
      <li
        v-for="hour in profile.opening_hours"
        :key="hour.day_of_week"
        class="flex justify-between py-2 text-sm"
      >
        <span class="text-slate-700">{{ hour.day_label }}</span>
        <span v-if="hour.is_closed" class="text-slate-500">Fermé</span>
        <span v-else class="text-slate-700">{{ formatTime(hour.opens_at) }} – {{ formatTime(hour.closes_at) }}</span>
      </li>
    </ul>
  </section>

  <!-- Photos du profil -->
  <section class="rounded-lg border border-slate-200 bg-white p-4 sm:p-6">
    <h3 class="text-sm font-semibold text-slate-900">Photos du profil</h3>
    <p class="mt-1 text-xs text-slate-500">{{ photosHint ?? 'Cliquez pour agrandir.' }}</p>
    <p v-if="!profileImages.length" class="mt-3 text-sm text-slate-500">Aucune photo.</p>
    <div v-else class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
      <a
        v-for="image in profileImages"
        :key="image.id"
        :href="image.url"
        target="_blank"
        rel="noopener noreferrer"
        class="block overflow-hidden rounded-md border border-slate-200"
      >
        <img :src="image.url" alt="Photo du local" class="aspect-square w-full object-cover hover:opacity-90" />
      </a>
    </div>
  </section>
</template>
