<script setup lang="ts">
import DashboardShell from '@/layouts/DashboardShell.vue'

const navItems = [
  { label: 'Tableau de bord', to: '/admin' },
  { label: 'Inscriptions', to: '/admin/registrations' },
  { label: 'Services', to: '/admin/services' },
  { label: 'Produits', to: '/admin/products' },
  { label: 'Avis', to: '/admin/avis' },
]
</script>

<template>
  <DashboardShell title="Espace Administrateur" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
