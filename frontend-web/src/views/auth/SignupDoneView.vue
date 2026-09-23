<script setup lang="ts">
// Fin du parcours d'inscription (CLAUDE.md §5, ajout v0.26) : le compte
// existe, mais aucune connexion automatique — le professionnel se connecte
// lui-même, puis complète son profil et soumet son dossier.
</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-10 sm:items-center">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
      <h1 class="text-xl font-semibold text-slate-900">Votre compte a été créé</h1>
      <p class="mt-3 text-sm text-slate-600">
        Nous vous avons envoyé un email avec un lien vers votre profil. Connectez-vous pour le
        compléter, puis soumettez votre dossier pour validation.
      </p>
      <RouterLink
        :to="{ name: 'login' }"
        class="mt-6 block w-full rounded-md bg-slate-900 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-slate-800"
      >
        Se connecter
      </RouterLink>
    </div>
  </div>
</template>
