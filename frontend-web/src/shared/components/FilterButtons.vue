<script setup lang="ts" generic="V extends string">
import AppButton from '@/shared/components/AppButton.vue'

// Rangée de boutons de filtre (un seul actif à la fois). `null` = « Tous ».
// `generic` : la valeur garde le type exact du filtre de la page appelante
// (ex. `AppointmentStatus`), sans `any`. `defineModel` crée la liaison
// bidirectionnelle utilisée par `v-model` côté appelant.
defineProps<{
  options: { value: V; label: string }[]
  allLabel?: string
}>()

const model = defineModel<V | null>({ required: true })
</script>

<template>
  <div class="flex flex-wrap gap-2">
    <AppButton :variant="model === null ? 'primary' : 'secondary'" @click="model = null">
      {{ allLabel ?? 'Tous' }}
    </AppButton>
    <AppButton
      v-for="option in options"
      :key="option.value"
      :variant="model === option.value ? 'primary' : 'secondary'"
      @click="model = option.value"
    >
      {{ option.label }}
    </AppButton>
  </div>
</template>
