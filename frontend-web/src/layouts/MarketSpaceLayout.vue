<script setup lang="ts">
import { computed } from 'vue'

import DashboardShell from '@/layouts/DashboardShell.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Dossier non approuvé ou profil incomplet : seul « Mon profil » est proposé
// (CLAUDE.md §5, ajouts v0.20 et v0.26) — `computed` recalcule le menu dès que
// le store change (ex. dossier approuvé, vu après « Actualiser »).
const navItems = computed(() =>
  auth.mustStayOnProfile
    ? [{ label: 'Mon profil', to: '/market-space/profile' }]
    : [
        { label: 'Tableau de bord', to: '/market-space' },
        { label: 'Produits', to: '/market-space/products' },
        { label: 'Commandes', to: '/market-space/orders' },
        { label: 'Messages', to: '/market-space/conversations' },
        { label: 'Avis', to: '/market-space/reviews' },
        { label: 'Réclamations', to: '/market-space/disputes' },
        { label: 'Notifications', to: '/market-space/notifications' },
        { label: 'Mon profil', to: '/market-space/profile' },
      ],
)
</script>

<template>
  <DashboardShell title="Espace Market Space" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
