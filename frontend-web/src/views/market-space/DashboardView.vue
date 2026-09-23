<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Bienvenue, {{ auth.user?.name }}</h2>
    <p class="mt-1 text-sm text-slate-500">
      Gérez votre boutique depuis le menu : produits et stock, commandes, messages, avis,
      réclamations et notifications.
    </p>
  </div>
</template>
