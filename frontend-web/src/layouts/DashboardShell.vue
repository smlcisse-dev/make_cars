<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

// Un seul composant de mise en page pour les trois espaces (CLAUDE.md §4 :
// "un seul projet gérant les trois espaces ... via son propre
// routing/permissions internes") : seuls le titre et les liens de navigation
// changent d'un espace à l'autre, passés en props par AdminLayout /
// GarageLayout / MarketSpaceLayout.
interface NavItem {
  label: string
  to: string
}

const props = defineProps<{
  title: string
  navItems: NavItem[]
}>()

const auth = useAuthStore()
const route = useRoute()

// Entrée d'accueil d'un espace (`/admin`, `/garage`, `/market-space`) : un
// seul segment de chemin.
function isHomeEntry(to: string): boolean {
  return to.split('/').filter(Boolean).length === 1
}

// Rubrique allumée, calculée ici plutôt que par l'`active-class` de
// RouterLink : celle-ci s'applique à tout lien dont le chemin est un préfixe
// de la page courante, donc « Tableau de bord » (`/admin`) restait allumé sur
// toutes les pages de l'espace. Règle : l'accueil n'est actif que sur son
// chemin exact ; toute autre entrée l'est sur son chemin ou sur une page de
// détail (`/admin/registrations/41` allume « Inscriptions »). Si plusieurs
// entrées conviennent, la plus longue (la plus précise) l'emporte : une seule
// rubrique allumée à la fois. `computed` se recalcule à chaque navigation,
// car `route.path` est réactif.
const activeTo = computed<string | null>(() => {
  const path = route.path.replace(/\/+$/, '') || '/'
  const matches = props.navItems
    .map((item) => item.to)
    .filter((to) =>
      isHomeEntry(to) ? path === to : path === to || path.startsWith(`${to}/`),
    )
  return matches.sort((a, b) => b.length - a.length)[0] ?? null
})

async function handleLogout(): Promise<void> {
  await auth.logout()
  window.location.href = '/login'
}
</script>

<template>
  <div class="flex min-h-screen bg-slate-50">
    <aside class="sticky top-0 flex h-screen w-64 flex-col border-r border-slate-200 bg-white">
      <div class="border-b border-slate-200 px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Make Cars</p>
        <h1 class="text-lg font-semibold text-slate-900">{{ title }}</h1>
      </div>
      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          :class="[
            'block rounded-md px-3 py-2 text-sm font-medium',
            item.to === activeTo
              ? 'bg-slate-900 text-white hover:bg-slate-900'
              : 'text-slate-700 hover:bg-slate-100',
          ]"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
      <div class="border-t border-slate-200 px-4 py-4">
        <p class="text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
        <p class="text-xs text-slate-500">{{ auth.user?.role_label }}</p>
        <button
          type="button"
          class="mt-3 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="handleLogout"
        >
          Se déconnecter
        </button>
      </div>
    </aside>

    <div class="flex flex-1 flex-col">
      <!-- Compte suspendu (CLAUDE.md §5, ajout v0.6) : simple information,
           l'accès au dashboard n'est pas bloqué. Placé ici plutôt que dans
           chaque layout : `isSuspended` ne vaut vrai que pour un
           professionnel, l'espace Admin n'est donc jamais concerné. -->
      <div
        v-if="auth.isSuspended"
        class="border-b border-red-300 bg-red-50 px-6 py-3 text-sm text-red-900"
      >
        <span class="font-semibold"
          >Votre compte est suspendu : il n'est plus visible par les automobilistes.</span
        >
        <span v-if="auth.suspensionReason"> Motif : {{ auth.suspensionReason }}</span>
      </div>
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
