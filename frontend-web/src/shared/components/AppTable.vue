<script setup lang="ts" generic="T extends { id: string | number }">
// Tableau générique : la page appelante décrit ses colonnes et fournit ses
// lignes, ce composant ne connaît rien du métier (inscriptions, produits,
// services...). `generic="T extends ..."` (Vue 3.3+) rend ce composant
// typé : `items`/`row-click` restent liés au vrai type de ligne utilisé par
// chaque page (ex. `ProfessionalRegistration`), avec autocomplétion et
// vérification TypeScript, plutôt qu'un `any` générique.
//
// Le contenu de chaque cellule est personnalisable via un "scoped slot"
// nommé `cell-<clé>` : la page appelante récupère la ligne (`item`) pour
// l'afficher comme elle veut (badge de statut, date formatée...), avec un
// simple texte brut par défaut si elle ne fournit rien pour cette colonne.
export interface TableColumn {
  key: string
  label: string
  class?: string
}

defineProps<{
  items: T[]
  columns: TableColumn[]
}>()

const emit = defineEmits<{ 'row-click': [item: T] }>()
</script>

<template>
  <!-- `overflow-x-auto` : sur téléphone, le tableau défile horizontalement
       dans son cadre au lieu d'élargir la page. -->
  <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="min-w-full divide-y divide-slate-200">
      <thead class="bg-slate-50">
        <tr>
          <th
            v-for="column in columns"
            :key="column.key"
            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
          >
            {{ column.label }}
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <tr v-if="items.length === 0">
          <td :colspan="columns.length" class="px-4 py-8 text-center text-sm text-slate-500">
            <slot name="empty">Aucun résultat.</slot>
          </td>
        </tr>
        <tr
          v-for="item in items"
          :key="item.id"
          class="cursor-pointer hover:bg-slate-50"
          @click="emit('row-click', item)"
        >
          <td v-for="column in columns" :key="column.key" :class="['px-4 py-3 text-sm text-slate-700', column.class]">
            <slot :name="`cell-${column.key}`" :item="item">
              {{ item[column.key as keyof T] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
