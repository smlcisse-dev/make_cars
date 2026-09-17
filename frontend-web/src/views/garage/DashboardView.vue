<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold text-slate-900">Bienvenue, {{ auth.user?.name }}</h2>
    <p class="mt-1 text-sm text-slate-500">
      Le tableau de bord garagiste (profil, services, RDV, devis...) arrive dans un prochain
      module.
    </p>
  </div>
</template>
