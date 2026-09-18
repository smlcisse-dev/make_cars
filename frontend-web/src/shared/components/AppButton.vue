<script setup lang="ts">
// Bouton générique (variantes visuelles + état de chargement), pensé pour
// être réutilisé dans tous les espaces (Admin/Garage/Market Space) plutôt
// que redéfini page par page.
type Variant = 'primary' | 'secondary' | 'danger' | 'ghost'

withDefaults(
  defineProps<{
    variant?: Variant
    loading?: boolean
    disabled?: boolean
    type?: 'button' | 'submit'
  }>(),
  {
    variant: 'primary',
    loading: false,
    disabled: false,
    type: 'button',
  },
)

// `$attrs` (ex. @click posé par l'appelant) doit atterrir sur le <button>
// natif, pas sur la racine du composant (qui EST déjà ce <button>) : on
// désactive l'héritage automatique pour le contrôler nous-mêmes via
// `v-bind="$attrs"` dans le template.
defineOptions({ inheritAttrs: false })

const variantClasses: Record<Variant, string> = {
  primary: 'bg-slate-900 text-white hover:bg-slate-800',
  secondary: 'border border-slate-300 text-slate-700 hover:bg-slate-100',
  danger: 'bg-rose-600 text-white hover:bg-rose-500',
  ghost: 'text-slate-600 hover:bg-slate-100',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    v-bind="$attrs"
    :class="[
      'inline-flex items-center justify-center gap-2 rounded-md px-3.5 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-60',
      variantClasses[variant],
    ]"
  >
    <span
      v-if="loading"
      class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
      aria-hidden="true"
    />
    <slot />
  </button>
</template>
