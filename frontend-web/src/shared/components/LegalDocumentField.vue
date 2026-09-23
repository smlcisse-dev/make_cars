<script setup lang="ts">
import { reactive, ref } from 'vue'

import AppButton from '@/shared/components/AppButton.vue'
import { extractApiErrorMessage } from '@/utils/apiError'

// Bloc « justificatif privé » du dossier d'inscription : un seul document
// actuel, envoyé dès sa sélection, consultable, remplaçable tant que le
// dossier n'est pas verrouillé. Utilisé pour le registre de commerce
// (ajout v0.26) et le Certificat d'Identification Personnelle (ajout v0.27) :
// seuls le titre, l'aide et les deux appels API changent.
//
// Passer des fonctions en props (`upload`, `fetchBlob`) plutôt que le type de
// document : le composant ignore tout des URL de l'API, c'est la page
// appelante qui choisit l'endpoint (et l'espace, `space`).
const props = defineProps<{
  title: string
  help: string
  hasDocument: boolean
  locked: boolean
  upload: (file: File) => Promise<void>
  fetchBlob: () => Promise<Blob>
}>()

// `defineEmits` déclare les événements que le composant peut émettre ; le
// parent écoute avec `@uploaded="..."` (ici pour recharger le profil).
// La syntaxe `{ uploaded: [] }` dit : événement sans argument.
const emit = defineEmits<{ uploaded: [] }>()

// Mêmes règles que UploadLegalDocumentRequest côté backend : pdf/jpg/png,
// 10 Mo max. On vérifie l'extension plutôt que le type MIME, que certains
// navigateurs laissent vide ; le backend reste l'autorité.
const MAX_BYTES = 10 * 1024 * 1024
const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png']

const state = reactive({ saving: false, success: null as string | null, error: null as string | null })
const isOpening = ref(false)

// « Template ref » : `ref="fileInput"` dans le template relie cette variable
// à l'élément <input> réel, pour ouvrir le sélecteur depuis un bouton.
const fileInput = ref<HTMLInputElement | null>(null)

function pick(): void {
  fileInput.value?.click()
}

async function onSelected(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  state.success = null
  state.error = null
  const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
  if (!EXTENSIONS.includes(extension)) {
    state.error = 'Format non accepté : PDF, JPG ou PNG uniquement.'
    return
  }
  if (file.size > MAX_BYTES) {
    state.error = 'Le document ne doit pas dépasser 10 Mo.'
    return
  }

  state.saving = true
  try {
    await props.upload(file)
    emit('uploaded')
    state.success = 'Document enregistré.'
  } catch (error) {
    state.error = extractApiErrorMessage(error, 'Enregistrement impossible.')
  } finally {
    state.saving = false
  }
}

// Fichier privé : blob authentifié -> URL locale temporaire -> nouvel onglet,
// comme pour les PDF de devis (une simple balise <a href> n'enverrait pas le
// token Sanctum).
async function open(): Promise<void> {
  isOpening.value = true
  state.error = null
  try {
    const objectUrl = URL.createObjectURL(await props.fetchBlob())
    window.open(objectUrl, '_blank')
    setTimeout(() => URL.revokeObjectURL(objectUrl), 60_000)
  } catch (error) {
    state.error = extractApiErrorMessage(error, 'Impossible de récupérer ce document.')
  } finally {
    isOpening.value = false
  }
}
</script>

<template>
  <div class="space-y-2 border-t border-slate-200 pt-4">
    <h3 class="text-sm font-semibold text-slate-900">{{ title }}</h3>
    <p class="text-xs text-slate-500">{{ help }}</p>
    <input
      ref="fileInput"
      type="file"
      accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
      class="hidden"
      @change="onSelected"
    />
    <div class="flex flex-wrap items-center gap-3">
      <template v-if="hasDocument">
        <span class="text-sm text-green-700">Document envoyé</span>
        <AppButton variant="secondary" :loading="isOpening" @click="open">
          Voir le document
        </AppButton>
        <AppButton v-if="!locked" variant="secondary" :loading="state.saving" @click="pick">
          Remplacer
        </AppButton>
      </template>
      <template v-else>
        <span class="text-sm text-slate-600">Aucun document envoyé.</span>
        <AppButton v-if="!locked" variant="secondary" :loading="state.saving" @click="pick">
          Choisir un fichier
        </AppButton>
      </template>
    </div>
    <p v-if="state.success" class="text-sm text-green-700">{{ state.success }}</p>
    <p v-if="state.error" class="text-sm text-red-600">{{ state.error }}</p>
  </div>
</template>
