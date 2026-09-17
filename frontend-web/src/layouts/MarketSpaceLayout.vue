<script setup lang="ts">
import DashboardShell from '@/layouts/DashboardShell.vue'

const navItems = [{ label: 'Tableau de bord', to: '/market-space' }]
</script>

<template>
  <DashboardShell title="Espace Market Space" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
