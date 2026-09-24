<script setup lang="ts">
import DashboardShell from '@/layouts/DashboardShell.vue'

const navItems = [
  { label: 'Tableau de bord', to: '/admin' },
  { label: 'Inscriptions', to: '/admin/registrations' },
  { label: 'Services', to: '/admin/services' },
  { label: 'Produits', to: '/admin/products' },
  { label: 'Avis', to: '/admin/avis' },
  { label: 'Litiges', to: '/admin/litiges' },
  // Supervision en lecture seule (CLAUDE.md §5, règle 8).
  { label: 'Garages', to: '/admin/garages', group: 'Supervision' },
  { label: 'Boutiques', to: '/admin/boutiques', group: 'Supervision' },
  { label: 'Rendez-vous', to: '/admin/appointments', group: 'Supervision' },
  { label: 'Devis', to: '/admin/quotes', group: 'Supervision' },
  { label: 'Commandes', to: '/admin/orders', group: 'Supervision' },
  { label: 'Conversations', to: '/admin/conversations', group: 'Supervision' },
]
</script>

<template>
  <DashboardShell title="Espace Administrateur" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
