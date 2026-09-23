<script setup lang="ts">
// Page de choix du type de compte professionnel (CLAUDE.md §5, ajout v0.26).
// Aucun état : les deux cartes sont de simples liens vers le formulaire du
// type choisi, qui fixe `account_type`.
</script>

<template>
  <div class="flex min-h-screen justify-center bg-slate-50 px-4 py-10 sm:items-center">
    <div class="w-full max-w-md">
      <h1 class="text-xl font-semibold text-slate-900">Créer un compte professionnel</h1>
      <p class="mt-1 text-sm text-slate-500">Choisissez votre activité.</p>

      <div class="mt-6 space-y-4">
        <!-- `RouterLink` navigue sans recharger la page ; `:to` avec un nom de
             route plutôt qu'un chemin écrit en dur. -->
        <RouterLink
          :to="{ name: 'signup.garagiste' }"
          class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400"
        >
          <span class="block text-base font-semibold text-slate-900">Je suis garagiste</span>
          <span class="mt-1 block text-sm text-slate-500">
            Réparation et entretien de véhicules, avec possibilité de vendre quelques produits
            courants.
          </span>
        </RouterLink>

        <RouterLink
          :to="{ name: 'signup.market-space' }"
          class="block rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400"
        >
          <span class="block text-base font-semibold text-slate-900">
            Je vends des pièces ou du matériel automobile
          </span>
          <span class="mt-1 block text-sm text-slate-500">
            Boutique de pièces détachées et de consommables.
          </span>
        </RouterLink>
      </div>

      <p class="mt-6 text-sm text-slate-500">
        Vous êtes automobiliste ? Make Cars s'utilise depuis l'application mobile.
      </p>
      <p class="mt-3 text-sm text-slate-500">
        Déjà inscrit ?
        <RouterLink :to="{ name: 'login' }" class="font-medium text-slate-900 underline">
          Se connecter
        </RouterLink>
      </p>
    </div>
  </div>
</template>
