<script setup lang="ts">
// Champ d'une fiche en lecture seule (libellé + valeur), à placer dans un
// `<dl>`. La valeur passe par `value` ou, pour un contenu riche (lien,
// badge), par le slot par défaut.
defineProps<{
  label: string
  value?: string | number | null
}>()
</script>

<template>
  <div>
    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ label }}</dt>
    <dd class="mt-1 break-words text-sm text-slate-700">
      <slot>{{ value === null || value === undefined || value === '' ? '—' : value }}</slot>
    </dd>
  </div>
</template>
