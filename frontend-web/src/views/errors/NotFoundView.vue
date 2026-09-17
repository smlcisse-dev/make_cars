<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-50 px-4 text-center">
    <h1 class="text-2xl font-semibold text-slate-900">Page introuvable</h1>
    <p class="text-sm text-slate-500">Cette page n'existe pas.</p>
    <RouterLink
      to="/"
      class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
    >
      Retour à l'accueil
    </RouterLink>
  </div>
</template>
