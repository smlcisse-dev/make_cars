<script setup lang="ts">
import { CODE_LENGTH } from '@/utils/emailCode'

// Champ de saisie d'un code reçu par email (6 chiffres), commun à la
// vérification d'inscription et au mot de passe oublié. `v-model` du parent
// reçoit toujours une chaîne de chiffres seulement.
defineProps<{ id: string }>()

// `defineModel` (Vue 3.4+) : prop `modelValue` + événement de mise à jour,
// ce qui permet au parent d'écrire simplement `<CodeInput v-model="code" />`.
const code = defineModel<string>({ required: true })

// Seuls les chiffres sont gardés, au plus 6 (un code collé avec des espaces
// reste utilisable).
function onInput(event: Event): void {
  const input = event.target as HTMLInputElement
  code.value = input.value.replace(/\D/g, '').slice(0, CODE_LENGTH)
  input.value = code.value
}
</script>

<template>
  <!-- `inputmode="numeric"` : clavier numérique sur téléphone, sans les
       défauts d'un `type="number"` (flèches, zéros de tête perdus).
       `autocomplete="one-time-code"` : le téléphone peut proposer de remplir
       le code tout seul à partir du message reçu. L'exemple grisé est neutre
       (●●●●●●) : un exemple chiffré laissait croire que le code était déjà
       rempli. -->
  <input
    :id="id"
    :value="code"
    type="text"
    inputmode="numeric"
    autocomplete="one-time-code"
    :maxlength="CODE_LENGTH"
    placeholder="●●●●●●"
    class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2.5 text-center font-mono text-xl tracking-[0.5em] focus:border-slate-500 focus:outline-none"
    @input="onInput"
  />
</template>
