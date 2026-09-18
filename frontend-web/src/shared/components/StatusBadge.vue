<script setup lang="ts">
// Badge de statut générique : la page appelante fournit le libellé (déjà
// traduit côté backend, ex. `status_label`) et une "tonalité" abstraite —
// ce composant ne connaît rien des statuts métier eux-mêmes (devis, RDV,
// inscription...), il ne fait que les colorer de façon cohérente partout.
export type BadgeTone = 'success' | 'warning' | 'danger' | 'neutral' | 'info'

defineProps<{
  label: string
  tone: BadgeTone
}>()

const toneClasses: Record<BadgeTone, string> = {
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  warning: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  danger: 'bg-rose-50 text-rose-700 ring-rose-600/20',
  neutral: 'bg-slate-100 text-slate-600 ring-slate-500/10',
  info: 'bg-sky-50 text-sky-700 ring-sky-600/20',
}
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset',
      toneClasses[tone],
    ]"
  >
    {{ label }}
  </span>
</template>
