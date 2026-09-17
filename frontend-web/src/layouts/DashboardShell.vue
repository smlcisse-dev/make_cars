<script setup lang="ts">
import { RouterLink } from 'vue-router'

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

defineProps<{
  title: string
  navItems: NavItem[]
}>()

const auth = useAuthStore()

async function handleLogout(): Promise<void> {
  await auth.logout()
  window.location.href = '/login'
}
</script>

<template>
  <div class="flex min-h-screen bg-slate-50">
    <aside class="flex w-64 flex-col border-r border-slate-200 bg-white">
      <div class="border-b border-slate-200 px-6 py-5">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Make Cars</p>
        <h1 class="text-lg font-semibold text-slate-900">{{ title }}</h1>
      </div>
      <nav class="flex-1 space-y-1 px-3 py-4">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="block rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100"
          active-class="bg-slate-900 text-white hover:bg-slate-900"
        >
          {{ item.label }}
        </RouterLink>
      </nav>
    </aside>

    <div class="flex flex-1 flex-col">
      <header class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
        <div>
          <p class="text-sm font-medium text-slate-900">{{ auth.user?.name }}</p>
          <p class="text-xs text-slate-500">{{ auth.user?.role_label }}</p>
        </div>
        <button
          type="button"
          class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100"
          @click="handleLogout"
        >
          Se déconnecter
        </button>
      </header>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
