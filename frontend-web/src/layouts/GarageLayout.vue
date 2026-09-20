<script setup lang="ts">
import DashboardShell from '@/layouts/DashboardShell.vue'

const navItems = [
  { label: 'Tableau de bord', to: '/garage' },
  { label: 'Localisation (test)', to: '/garage/location-test' },
]
</script>

<template>
  <DashboardShell title="Espace Garagiste" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
