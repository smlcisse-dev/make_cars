<script setup lang="ts">
import { computed, ref } from 'vue'

import AppButton from '@/shared/components/AppButton.vue'
import BaseModal from '@/shared/components/BaseModal.vue'

// Modale "motif obligatoire" générique : le cahier des charges impose ce
// même schéma (texte de motif requis, confirmation explicite) à plusieurs
// actions de modération admin — rejet d'inscription (ici), suspension de
// compte, modération d'avis, décision de réclamation (CLAUDE.md §5). Plutôt
// que de réécrire un formulaire + une validation à chaque fois, ces futurs
// modules réutiliseront ce composant en changeant juste les libellés.
//
// Le libellé du champ, son texte d'aide et la couleur du bouton sont
// paramétrables pour servir aussi hors modération. (La demande de
// réactivation, qui accepte des pièces jointes, a sa propre fenêtre :
// ReactivationRequestModal.)
// `withDefaults` fournit la valeur utilisée quand le parent omet la prop.
const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    confirmLabel?: string
    loading?: boolean
    fieldLabel?: string
    placeholder?: string
    confirmVariant?: 'primary' | 'danger'
  }>(),
  {
    description: undefined,
    confirmLabel: 'Confirmer',
    loading: false,
    fieldLabel: 'Motif',
    placeholder: 'Expliquez la raison de cette décision...',
    confirmVariant: 'danger',
  },
)

const emit = defineEmits<{ confirm: [reason: string]; cancel: [] }>()

const reason = ref('')
const touched = ref(false)

// `computed()` définit une valeur dérivée réactive : `isValid` se recalcule
// automatiquement dès que `reason` change, et le template s'actualise avec
// elle — pas besoin de la recalculer "à la main" à chaque frappe.
const isValid = computed(() => reason.value.trim().length > 0)

function handleConfirm(): void {
  touched.value = true
  if (!isValid.value) {
    return
  }
  emit('confirm', reason.value.trim())
}
</script>

<template>
  <BaseModal :title="title" @close="emit('cancel')">
    <p v-if="description" class="text-sm text-slate-500">{{ description }}</p>

    <label for="reason-prompt-textarea" class="mt-3 block text-sm font-medium text-slate-700">
      {{ props.fieldLabel }} <span class="text-rose-600">*</span>
    </label>
    <textarea
      id="reason-prompt-textarea"
      v-model="reason"
      rows="3"
      class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-slate-500 focus:outline-none"
      :placeholder="props.placeholder"
    />
    <p v-if="touched && !isValid" class="mt-1 text-sm text-rose-600">
      Le champ « {{ props.fieldLabel }} » est obligatoire.
    </p>

    <template #footer>
      <AppButton variant="secondary" @click="emit('cancel')">Annuler</AppButton>
      <AppButton :variant="props.confirmVariant" :loading="loading" @click="handleConfirm">{{
        confirmLabel
      }}</AppButton>
    </template>
  </BaseModal>
</template>
