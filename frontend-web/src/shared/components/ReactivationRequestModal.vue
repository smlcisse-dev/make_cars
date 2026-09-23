<script setup lang="ts">
import { computed, ref } from 'vue'

import AppButton from '@/shared/components/AppButton.vue'
import BaseModal from '@/shared/components/BaseModal.vue'

// Fenêtre « Demander la réactivation » (CLAUDE.md §5, ajout v0.28) : message
// obligatoire et pièces jointes facultatives (preuves de ce qui a été
// corrigé). Composant dédié plutôt qu'une surcharge de ReasonPromptModal,
// qui reste un simple formulaire de motif.
//
// Les vérifications ci-dessous (nombre, format, taille) évitent un aller-retour
// inutile ; le backend reste l'autorité, et ses erreurs 422 reçues par le
// parent arrivent ici par `fieldErrors`.
const props = withDefaults(
  defineProps<{
    loading?: boolean
    fieldErrors?: Record<string, string>
  }>(),
  { loading: false, fieldErrors: () => ({}) },
)

const emit = defineEmits<{ confirm: [payload: { message: string; files: File[] }]; cancel: [] }>()

const MAX_FILES = 5
const MAX_BYTES = 5 * 1024 * 1024
const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf']

const message = ref('')
const touched = ref(false)
// `ref<File[]>([])` : le paramètre de type indique à TypeScript que la liste
// contiendra des `File` (objets du navigateur), sans quoi il déduirait un
// tableau vide `never[]` dans lequel on ne pourrait rien ajouter.
const files = ref<File[]>([])
const fileError = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const isMessageValid = computed(() => message.value.trim().length > 0)

// Erreurs 422 du backend sur les pièces jointes : `attachments` (nombre) ou
// `attachments.0`, `attachments.1`… (un fichier précis). On les regroupe sous
// la liste des fichiers, sans doublon.
const backendFileErrors = computed(() => [
  ...new Set(
    Object.entries(props.fieldErrors)
      .filter(([field]) => field === 'attachments' || field.startsWith('attachments.'))
      .map(([, text]) => text),
  ),
])

function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} Ko`
  return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} Mo`
}

function handleFilesSelected(event: Event): void {
  const input = event.target as HTMLInputElement
  // `input.files` est une `FileList` (pas un vrai tableau) : `Array.from` la
  // convertit pour pouvoir utiliser `filter`/`forEach`.
  const selected = Array.from(input.files ?? [])
  // Vider la valeur permet de re-choisir le même fichier après l'avoir retiré.
  input.value = ''
  fileError.value = null

  const rejected: string[] = []
  for (const file of selected) {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? ''
    if (!ALLOWED_EXTENSIONS.includes(extension)) {
      rejected.push(`« ${file.name} » : format non accepté (photo jpg/png ou PDF uniquement).`)
    } else if (file.size > MAX_BYTES) {
      rejected.push(`« ${file.name} » : dépasse 5 Mo.`)
    } else if (files.value.length >= MAX_FILES) {
      rejected.push(`« ${file.name} » : ${MAX_FILES} pièces jointes au maximum.`)
    } else {
      files.value.push(file)
    }
  }

  if (rejected.length) fileError.value = rejected.join(' ')
}

function removeFile(index: number): void {
  files.value.splice(index, 1)
  fileError.value = null
}

function handleConfirm(): void {
  touched.value = true
  if (!isMessageValid.value) return
  emit('confirm', { message: message.value.trim(), files: [...files.value] })
}
</script>

<template>
  <BaseModal title="Demander la réactivation" @close="emit('cancel')">
    <p class="text-sm text-slate-500">
      L'équipe Make Cars examinera votre demande et décidera de réactiver ou non votre compte.
    </p>

    <label for="reactivation-message" class="mt-3 block text-sm font-medium text-slate-700">
      Message <span class="text-rose-600">*</span>
    </label>
    <textarea
      id="reactivation-message"
      v-model="message"
      rows="3"
      maxlength="2000"
      class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      placeholder="Expliquez ce que vous avez corrigé"
    />
    <p v-if="touched && !isMessageValid" class="mt-1 text-sm text-rose-600">
      Le champ « Message » est obligatoire.
    </p>
    <p v-else-if="fieldErrors.message" class="mt-1 text-sm text-rose-600">{{ fieldErrors.message }}</p>

    <p class="mt-4 text-sm font-medium text-slate-700">Pièces jointes (facultatif)</p>
    <p class="text-xs text-slate-500">Photos ou PDF, 5 fichiers maximum, 5 Mo chacun.</p>

    <ul v-if="files.length" class="mt-2 space-y-1">
      <li
        v-for="(file, index) in files"
        :key="`${file.name}-${index}`"
        class="flex items-center justify-between gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm"
      >
        <span class="truncate text-slate-700">{{ file.name }}</span>
        <span class="flex shrink-0 items-center gap-2">
          <span class="text-xs text-slate-500">{{ formatSize(file.size) }}</span>
          <button
            type="button"
            class="text-xs font-medium text-rose-600 hover:underline"
            :disabled="loading"
            @click="removeFile(index)"
          >
            Retirer
          </button>
        </span>
      </li>
    </ul>

    <input
      ref="fileInput"
      type="file"
      multiple
      accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
      class="hidden"
      @change="handleFilesSelected"
    />
    <AppButton
      class="mt-2"
      variant="secondary"
      :disabled="loading || files.length >= MAX_FILES"
      @click="fileInput?.click()"
    >
      Ajouter des fichiers
    </AppButton>

    <p v-if="fileError" class="mt-1 text-sm text-rose-600">{{ fileError }}</p>
    <p v-for="text in backendFileErrors" :key="text" class="mt-1 text-sm text-rose-600">{{ text }}</p>

    <template #footer>
      <AppButton variant="secondary" @click="emit('cancel')">Annuler</AppButton>
      <AppButton :loading="props.loading" @click="handleConfirm">Envoyer la demande</AppButton>
    </template>
  </BaseModal>
</template>
