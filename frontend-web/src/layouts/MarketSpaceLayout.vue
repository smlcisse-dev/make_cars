<script setup lang="ts">
import { computed } from 'vue'

import DashboardShell from '@/layouts/DashboardShell.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Profil incomplet : seul « Mon profil » est proposé (CLAUDE.md §5, ajout
// v0.20) — `computed` recalcule le menu dès que le profil devient complet.
const navItems = computed(() =>
  auth.mustCompleteProfile
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
