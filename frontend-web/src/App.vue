<script setup lang="ts">
// Racine de l'app : ne fait rien d'autre qu'afficher la vue courante.
// Chaque espace (Admin/Garage/Market Space) gère son propre habillage via
// son layout (src/layouts/*.vue), rendu par le router lui-même.
</script>

<template>
  <RouterView />
</template>
