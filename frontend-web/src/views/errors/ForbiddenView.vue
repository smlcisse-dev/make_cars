<script setup lang="ts">
import { RouterLink } from 'vue-router'

import { homePathForRole, useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-50 px-4 text-center">
    <h1 class="text-2xl font-semibold text-slate-900">Accès refusé</h1>
    <p class="text-sm text-slate-500">
      Votre compte ({{ auth.user?.role_label }}) n'a pas accès à cet espace.
    </p>
    <RouterLink
      v-if="auth.user"
      :to="homePathForRole(auth.user.role)"
      class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
    >
      Retour à mon espace
    </RouterLink>
  </div>
</template>
