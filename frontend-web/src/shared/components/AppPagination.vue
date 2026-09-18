<script setup lang="ts">
import AppButton from '@/shared/components/AppButton.vue'

// Pagination générique "précédent/suivant", pilotée par le `meta` renvoyé
// par n'importe quelle liste paginée du backend (voir src/types/pagination.ts)
// — réutilisable par les futures listes admin (produits, services...).
defineProps<{
  currentPage: number
  lastPage: number
}>()

const emit = defineEmits<{ 'update:currentPage': [page: number] }>()
</script>

<template>
  <div class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3">
    <p class="text-sm text-slate-500">Page {{ currentPage }} / {{ lastPage }}</p>
    <div class="flex gap-2">
      <AppButton
        variant="secondary"
        :disabled="currentPage <= 1"
        @click="emit('update:currentPage', currentPage - 1)"
      >
        Précédent
      </AppButton>
      <AppButton
        variant="secondary"
        :disabled="currentPage >= lastPage"
        @click="emit('update:currentPage', currentPage + 1)"
      >
        Suivant
      </AppButton>
    </div>
  </div>
</template>
