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
    ? [{ label: 'Mon profil', to: '/garage/profile' }]
    : [
        { label: 'Tableau de bord', to: '/garage' },
        { label: 'Services', to: '/garage/services' },
        { label: 'Produits', to: '/garage/products' },
        { label: 'Rendez-vous', to: '/garage/appointments' },
        { label: 'Devis', to: '/garage/quotes' },
        { label: 'Commandes', to: '/garage/orders' },
        { label: 'Messages', to: '/garage/conversations' },
        { label: 'Avis', to: '/garage/reviews' },
        { label: 'Réclamations', to: '/garage/disputes' },
        { label: 'Notifications', to: '/garage/notifications' },
        { label: 'Mon profil', to: '/garage/profile' },
      ],
)
</script>

<template>
  <DashboardShell title="Espace Garagiste" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
