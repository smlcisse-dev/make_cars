<script setup lang="ts">
import ConfirmDialog from '@/shared/components/ConfirmDialog.vue'

// Racine de l'app : affiche la vue courante. Chaque espace
// (Admin/Garage/Market Space) gère son propre habillage via son layout
// (src/layouts/*.vue), rendu par le router lui-même. La fenêtre de
// confirmation, commune à toute l'application, est montée ici une seule fois.
</script>

<template>
  <RouterView />
  <ConfirmDialog />
</template>
