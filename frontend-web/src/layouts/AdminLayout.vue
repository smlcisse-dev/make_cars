<script setup lang="ts">
import DashboardShell from '@/layouts/DashboardShell.vue'

const navItems = [{ label: 'Tableau de bord', to: '/admin' }]
</script>

<template>
  <DashboardShell title="Espace Administrateur" :nav-items="navItems">
    <RouterView />
  </DashboardShell>
</template>
