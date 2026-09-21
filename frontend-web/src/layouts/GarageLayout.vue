<script setup lang="ts">
import { computed } from 'vue'

import DashboardShell from '@/layouts/DashboardShell.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

// Profil incomplet : seul « Mon profil » est proposé (CLAUDE.md §5, ajout
// v0.20) — `computed` recalcule le menu dès que le profil devient complet.
const navItems = computed(() =>
  auth.mustCompleteProfile
    ? [{ label: 'Mon profil', to: '/garage/profile' }]
    : [
        { label: 'Tableau de bord', to: '/garage' },
        { label: 'Services', to: '/garage/services' },
        { label: 'Produits', to: '/garage/products' },
        { label: 'Commandes', to: '/garage/orders' },
        { label: 'Rendez-vous', to: '/garage/appointments' },
        { label: 'Devis', to: '/garage/quotes' },
        { label: 'Messages', to: '/garage/conversations' },
        { label: 'Mon profil', to: '/garage/profile' },
      ],
)
</script>

<template>
  <DashboardShell title="Espace Garagiste" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
